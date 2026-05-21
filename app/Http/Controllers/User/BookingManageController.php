<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\Ruangan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * BookingManageController
 *
 * Mengelola tindakan user terhadap booking yang sudah ada:
 *   - Pembatalan (cancel)
 *   - Pengajuan perubahan tanggal (request date change)
 *   - Pembaruan data peserta tanpa approval (update participants)
 */
class BookingManageController extends Controller
{
    // ============================================================
    // CANCEL — User membatalkan booking miliknya sendiri
    // ============================================================
    public function cancel(Request $request, Booking $booking)
    {
        // Pastikan booking milik user yang sedang login
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        if (!$booking->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak dapat dibatalkan. Status saat ini: ' . $booking->status,
            ], 422);
        }

        $booking->update([
            'status'           => Booking::STATUS_CANCELLED,
            'catatan_admin'    => $booking->catatan_admin, // pertahankan catatan admin yang ada
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dibatalkan.',
        ]);
    }

    // ============================================================
    // REQUEST DATE CHANGE — User mengajukan perubahan tanggal
    // ============================================================
    public function requestDateChange(Request $request, Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        if (!$booking->canRequestDateChange()) {
            return response()->json([
                'success' => false,
                'message' => 'Perubahan tanggal tidak dapat diajukan. Status saat ini: ' . $booking->status,
            ], 422);
        }

        $validated = $request->validate([
            'proposed_tgl_mulai'   => 'required|date|after_or_equal:today',
            'proposed_tgl_selesai' => 'required|date|after_or_equal:proposed_tgl_mulai',
            'alasan'               => 'nullable|string|max:500',
        ], [
            'proposed_tgl_mulai.after_or_equal'   => 'Tanggal mulai baru tidak boleh di masa lalu.',
            'proposed_tgl_selesai.after_or_equal'  => 'Tanggal selesai harus sama atau sesudah tanggal mulai.',
        ]);

        // Cek konflik ruangan untuk tanggal baru (kecualikan booking ini sendiri)
        $hasConflict = $this->hasConflict(
            $booking->ruangan_id,
            $validated['proposed_tgl_mulai'],
            $validated['proposed_tgl_selesai'],
            excludeBookingId: $booking->id
        );

        // Jika booking gabungan, cek ruangan pasangan juga
        if (!$hasConflict && $booking->gabung_ruang) {
            $ruang3 = Ruangan::where('nama_ruang', 'Ruang 3')->first();
            if ($ruang3) {
                $hasConflict = $this->hasConflict(
                    $ruang3->id,
                    $validated['proposed_tgl_mulai'],
                    $validated['proposed_tgl_selesai'],
                    excludeBookingId: $booking->id
                );
            }
        }

        if ($hasConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal yang diajukan bentrok dengan booking lain yang sudah ada.',
            ], 422);
        }

        $booking->update([
            'proposed_tgl_mulai'   => $validated['proposed_tgl_mulai'],
            'proposed_tgl_selesai' => $validated['proposed_tgl_selesai'],
            'status_perubahan'     => Booking::CHANGE_PENDING,
            // Simpan alasan di catatan_user
            'catatan_user'         => $validated['alasan'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usulan perubahan tanggal berhasil diajukan. Menunggu persetujuan admin.',
        ]);
    }

    // ============================================================
    // UPDATE PARTICIPANTS — User memperbarui data peserta
    // (Tidak perlu approval admin karena tidak mengubah ruangan/tanggal)
    // ============================================================
    public function updateParticipants(Request $request, Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        if (!$booking->canUpdateParticipants()) {
            return response()->json([
                'success' => false,
                'message' => 'Data peserta tidak dapat diperbarui. Booking sudah berstatus: ' . $booking->status,
            ], 422);
        }

        $validated = $request->validate([
            'peserta'   => 'nullable|array',
            'panitia'   => 'nullable|array',
        ]);

        // Opsi alternatif: upload Excel baru untuk peserta
        if ($request->hasFile('file_peserta')) {
            $request->validate([
                'file_peserta' => 'file|max:2048|mimes:xlsx',
            ]);

            try {
                $spreadsheet = IOFactory::load($request->file('file_peserta')->getRealPath());
                $sheet       = $spreadsheet->getActiveSheet();
                $highestRow  = $sheet->getHighestRow();

                $pesertaBaru = [];
                for ($row = 5; $row <= $highestRow; $row++) {
                    $nama    = trim((string) $sheet->getCell("A{$row}")->getValue());
                    $jabatan = trim((string) $sheet->getCell("B{$row}")->getValue());
                    $site    = trim((string) $sheet->getCell("C{$row}")->getValue());
                    $jk      = strtoupper(trim((string) $sheet->getCell("D{$row}")->getValue()));

                    if (empty($nama)) continue;

                    $pesertaBaru[] = [
                        'nama'    => $nama,
                        'jabatan' => $jabatan,
                        'site'    => $site,
                        'gender'  => in_array($jk, ['L', 'P']) ? $jk : null,
                    ];
                }
                $validated['peserta'] = $pesertaBaru;
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Gagal membaca file Excel.'], 422);
            }
        }

        DB::transaction(function () use ($booking, $validated) {
            // Hapus peserta & panitia lama, ganti dengan yang baru
            BookingParticipant::where('booking_id', $booking->id)->delete();

            $toInsert = [];
            foreach ($validated['peserta'] ?? [] as $p) {
                if (empty(trim($p['nama'] ?? ''))) continue;
                $toInsert[] = [
                    'booking_id' => $booking->id,
                    'tipe'       => 'peserta',
                    'nama'       => $p['nama'],
                    'jabatan'    => $p['jabatan'] ?? null,
                    'site'       => $p['site'] ?? null,
                    'gender'     => $p['gender'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach ($validated['panitia'] ?? [] as $p) {
                if (empty(trim($p['nama'] ?? ''))) continue;
                $toInsert[] = [
                    'booking_id' => $booking->id,
                    'tipe'       => 'panitia',
                    'nama'       => $p['nama'],
                    'jabatan'    => $p['jabatan'] ?? null,
                    'site'       => $p['site'] ?? null,
                    'gender'     => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (count($toInsert) > 0) {
                BookingParticipant::insert($toInsert);
            }
        });

        $booking->load('participants');

        return response()->json([
            'success'         => true,
            'message'         => 'Data peserta berhasil diperbarui.',
            'jumlah_peserta'  => $booking->participants->where('tipe', 'peserta')->count(),
            'jumlah_panitia'  => $booking->participants->where('tipe', 'panitia')->count(),
        ]);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function hasConflict(int $roomId, string $startDate, string $endDate, ?int $excludeBookingId = null): bool
    {
        return Booking::where('ruangan_id', $roomId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED])
            ->where('tgl_mulai', '<=', $endDate)
            ->where('tgl_selesai', '>=', $startDate)
            ->when($excludeBookingId, fn($q) => $q->where('id', '!=', $excludeBookingId))
            ->exists();
    }
}
