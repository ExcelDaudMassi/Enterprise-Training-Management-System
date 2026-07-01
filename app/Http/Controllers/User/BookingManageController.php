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
            'catatan_user'     => 'Dibatalkan secara mandiri oleh pemohon.',
            'catatan_admin'    => $booking->catatan_admin, // pertahankan catatan admin yang ada
        ]);

        \App\Models\BookingLog::create([
            'booking_id' => $booking->id,
            'user_id'    => Auth::id(),
            'action'     => 'User Cancelled',
            'message'    => 'Booking dibatalkan secara mandiri oleh user.',
        ]);

        // Notifikasi ke Admin
        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\BookingNotification::create([
                'user_id'    => $admin->id,
                'booking_id' => $booking->id,
                'tipe'       => 'danger',
                'title'      => 'Booking Dibatalkan User',
                'message'    => 'User membatalkan booking: ' . $booking->nama_training,
                'is_read'    => false,
            ]);
        }

        broadcast(new \App\Events\BookingStatusUpdated($booking));

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

        broadcast(new \App\Events\BookingStatusUpdated($booking));

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
                $parsed = $this->parseExcelFile($request->file('file_peserta')->getRealPath());

                if (!$parsed['success']) {
                    return response()->json(['success' => false, 'message' => $parsed['message']], 422);
                }

                // Format baru: data sudah diberi 'tipe' oleh parseExcelFile
                // Format lama (single sheet): semua tanpa 'tipe', anggap peserta
                $validated['peserta'] = collect($parsed['data'])
                    ->filter(fn($r) => ($r['tipe'] ?? 'peserta') === 'peserta')
                    ->values()->all();
                $validated['panitia'] = collect($parsed['data'])
                    ->filter(fn($r) => ($r['tipe'] ?? 'peserta') === 'panitia')
                    ->values()->all();
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Gagal membaca file Excel: ' . $e->getMessage()], 422);
            }
        }

        $booking->load('ruangan');
        $maxCapacity = $booking->gabung_ruang ? 60 : ($booking->ruangan->kapasitas_max ?? 0);
        
        $pesertaCount = collect($validated['peserta'] ?? [])->filter(fn($p) => !empty(trim($p['nama'] ?? '')))->count();
        $panitiaCount = collect($validated['panitia'] ?? [])->filter(fn($p) => !empty(trim($p['nama'] ?? '')))->count();
        $totalInput = $pesertaCount + $panitiaCount;

        if ($maxCapacity > 0 && $totalInput > $maxCapacity) {
            return response()->json([
                'success' => false,
                'message' => 'Total peserta dan panitia (' . $totalInput . ' orang) melebihi kapasitas maksimal ruangan (' . $maxCapacity . ' orang).',
            ], 422);
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
                    'nrp'        => $p['nrp'] ?? 'N/A',
                    'jabatan'    => $p['jabatan'] ?? null,
                    'site'       => $p['site'] ?? null,
                    'no_hp'      => $p['no_hp'] ?? null,
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
                    'nrp'        => $p['nrp'] ?? 'N/A',
                    'jabatan'    => $p['jabatan'] ?? null,
                    'site'       => $p['site'] ?? null,
                    'no_hp'      => $p['no_hp'] ?? null,
                    'gender'     => $p['gender'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (count($toInsert) > 0) {
                BookingParticipant::insert($toInsert);
            }
        });

        $booking->load('participants');

        $pesertaFinalCount = BookingParticipant::where('booking_id', $booking->id)->where('tipe', 'peserta')->count();
        $panitiaFinalCount = BookingParticipant::where('booking_id', $booking->id)->where('tipe', 'panitia')->count();

        // Tambah log dan notifikasi ke admin bahwa peserta diupdate user
        \App\Models\BookingLog::create([
            'booking_id' => $booking->id,
            'user_id'    => Auth::id(),
            'action'     => 'User Updated Participants',
            'message'    => "User menyusulkan / merubah data secara mandiri: $pesertaFinalCount Peserta dan $panitiaFinalCount Panitia.",
        ]);

        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\BookingNotification::create([
                'user_id'    => $admin->id,
                'booking_id' => $booking->id,
                'tipe'       => 'info',
                'title'      => 'Perubahan Data Peserta',
                'message'    => 'User memperbarui data peserta untuk booking: ' . $booking->nama_training,
                'is_read'    => false,
            ]);
        }

        broadcast(new \App\Events\BookingStatusUpdated($booking));

        return response()->json([
            'success'         => true,
            'message'         => 'Data peserta berhasil diperbarui.',
            'jumlah_peserta'  => $booking->participants->where('tipe', 'peserta')->count(),
            'jumlah_panitia'  => $booking->participants->where('tipe', 'panitia')->count(),
        ]);
    }

    // ============================================================
    // PREVIEW EXCEL PARTICIPANTS — Parse Excel & deteksi duplikat
    // terhadap data existing, TANPA menyimpan apapun ke database.
    // ============================================================
    public function previewExcelParticipants(Request $request, Booking $booking)
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

        $request->validate([
            'file_peserta' => 'required|file|max:2048|mimes:xlsx',
        ]);

        try {
            $parsed = $this->parseExcelFile($request->file('file_peserta')->getRealPath());

            if (!$parsed['success']) {
                return response()->json(['success' => false, 'message' => $parsed['message']], 422);
            }

            $excelRows = $parsed['data']; // [{tipe, nama, nrp, jabatan, site, no_hp, gender}, ...]

            // Ambil peserta existing dari DB
            $existing = $booking->participants()->get(['nama', 'nrp', 'tipe']);

            // Buat index untuk pencocokan cepat
            $existingNrps  = $existing->pluck('nrp')
                ->map(fn($n) => strtoupper(trim($n)))
                ->filter(fn($n) => $n && $n !== 'N/A')
                ->values()->all();
            $existingNames = $existing->pluck('nama')
                ->map(fn($n) => mb_strtolower(trim($n)))
                ->values()->all();

            // Tandai baris Excel yang duplikat dengan existing
            $duplikat = [];
            foreach ($excelRows as $row) {
                $nrpUpper  = strtoupper(trim($row['nrp'] ?? ''));
                $namaLower = mb_strtolower(trim($row['nama'] ?? ''));

                $isDuplicate = false;
                $alasan      = '';

                if ($nrpUpper && $nrpUpper !== 'N/A' && in_array($nrpUpper, $existingNrps)) {
                    $isDuplicate = true;
                    $alasan      = "NRP {$row['nrp']} sudah terdaftar";
                } elseif ($namaLower && in_array($namaLower, $existingNames)) {
                    $isDuplicate = true;
                    $alasan      = "Nama '{$row['nama']}' sudah terdaftar";
                }

                if ($isDuplicate) {
                    $duplikat[] = [
                        'nama'   => $row['nama'],
                        'nrp'    => $row['nrp'],
                        'tipe'   => $row['tipe'] ?? 'peserta',
                        'alasan' => $alasan,
                    ];
                }
            }

            $totalPeserta = collect($excelRows)->where('tipe', 'peserta')->count();
            $totalPanitia = collect($excelRows)->where('tipe', 'panitia')->count();

            return response()->json([
                'success'          => true,
                'total_excel'      => count($excelRows),
                'total_peserta'    => $totalPeserta,
                'total_panitia'    => $totalPanitia,
                'total_existing'   => $existing->count(),
                'duplikat'         => $duplikat,
                'total_duplikat'   => count($duplikat),
                'is_dual_sheet'    => $parsed['is_dual_sheet'] ?? false,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membaca file Excel: ' . $e->getMessage()], 422);
        }
    }

    // ============================================================
    // ADD SINGLE PARTICIPANT — User menambah satu peserta/panitia
    // (APPEND — tidak menghapus peserta yang sudah ada)
    // ============================================================
    public function addParticipant(Request $request, Booking $booking)
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
            'tipe'    => 'required|in:peserta,panitia',
            'nama'    => 'required|string|max:255',
            'nrp'     => 'nullable|string|max:100',
            'jabatan' => 'nullable|string|max:255',
            'site'    => 'nullable|string|max:255',
            'no_hp'   => 'nullable|string|max:50',
            'gender'  => 'nullable|in:L,P',
        ], [
            'nama.required' => 'Nama peserta wajib diisi.',
            'tipe.required' => 'Tipe (peserta/panitia) wajib dipilih.',
            'tipe.in'       => 'Tipe harus salah satu dari: peserta, panitia.',
            'gender.in'     => 'Gender harus L atau P.',
        ]);

        $participant = BookingParticipant::create([
            'booking_id' => $booking->id,
            'tipe'       => $validated['tipe'],
            'nama'       => $validated['nama'],
            'nrp'        => $validated['nrp'] ?? 'N/A',
            'jabatan'    => $validated['jabatan'] ?? null,
            'site'       => $validated['site'] ?? null,
            'no_hp'      => $validated['no_hp'] ?? null,
            'gender'     => $validated['gender'] ?? null,
        ]);

        $booking->load('participants');

        \App\Models\BookingLog::create([
            'booking_id' => $booking->id,
            'user_id'    => Auth::id(),
            'action'     => 'User Added Participant',
            'message'    => "User menambahkan {$validated['tipe']}: {$validated['nama']}.",
        ]);

        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\BookingNotification::create([
                'user_id'    => $admin->id,
                'booking_id' => $booking->id,
                'tipe'       => 'info',
                'title'      => 'Tambah Peserta Baru',
                'message'    => "User menambah {$validated['tipe']} baru ({$validated['nama']}) untuk booking: {$booking->nama_training}",
                'is_read'    => false,
            ]);
        }

        broadcast(new \App\Events\BookingStatusUpdated($booking));

        return response()->json([
            'success'         => true,
            'message'         => ucfirst($validated['tipe']) . ' berhasil ditambahkan.',
            'participant'     => $participant,
            'jumlah_peserta'  => $booking->participants->where('tipe', 'peserta')->count(),
            'jumlah_panitia'  => $booking->participants->where('tipe', 'panitia')->count(),
        ]);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    /**
     * Cek konflik jadwal ruangan, termasuk booking gabungan di ruangan pasangan.
     */
    private function hasConflict(int $roomId, string $startDate, string $endDate, ?int $excludeBookingId = null): bool
    {
        // 1. Cek konflik langsung
        $directConflict = Booking::where('ruangan_id', $roomId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
            ->where('tgl_mulai', '<=', $endDate)
            ->where('tgl_selesai', '>=', $startDate)
            ->when($excludeBookingId, fn($q) => $q->where('id', '!=', $excludeBookingId))
            ->exists();

        if ($directConflict) {
            return true;
        }

        // 2. Cek konflik dari booking gabungan di ruangan pasangan
        $room = Ruangan::find($roomId);
        if ($room && $room->pasangan_ruang_id) {
            $partnerConflict = Booking::where('ruangan_id', $room->pasangan_ruang_id)
                ->where('gabung_ruang', true)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
                ->where('tgl_mulai', '<=', $endDate)
                ->where('tgl_selesai', '>=', $startDate)
                ->when($excludeBookingId, fn($q) => $q->where('id', '!=', $excludeBookingId))
                ->exists();

            if ($partnerConflict) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse file Excel peserta menjadi array data.
     *
     * Format baru (dual-sheet): Sheet bernama 'Peserta' dan 'Panitia'.
     *   → Setiap baris diberi field 'tipe' = 'peserta' atau 'panitia'.
     *
     * Format lama (single-sheet): Sheet aktif apapun, semua baris = peserta.
     *   → is_dual_sheet = false, setiap baris tanpa field 'tipe'.
     *
     * Kolom (mulai baris 5):
     *   A = Nama, B = NRP, C = Jabatan, D = Site, E = No HP, F = Gender (L/P)
     *
     * @param  string  $filePath  Path absolut ke file xlsx
     * @return array{success: bool, is_dual_sheet: bool, data?: array, message?: string}
     */
    private function parseExcelFile(string $filePath): array
    {
        $spreadsheet  = IOFactory::load($filePath);
        $sheetNames   = $spreadsheet->getSheetNames();
        $normalNames  = array_map(fn($n) => mb_strtolower(trim($n)), $sheetNames);

        // Deteksi format baru: ada sheet 'peserta' DAN 'panitia'
        $hasPesertaSheet = in_array('peserta', $normalNames);
        $hasPanitiaSheet = in_array('panitia', $normalNames);
        $isDualSheet     = $hasPesertaSheet && $hasPanitiaSheet;

        $result      = [];
        $trackedNrps = [];   // global NRP tracker (lintas sheet)

        if ($isDualSheet) {
            // ── Format baru: baca 2 sheet ──────────────────────────
            $sheetMap = [
                'peserta' => 'peserta',
                'panitia' => 'panitia',
            ];

            foreach ($sheetMap as $sheetNameLower => $tipe) {
                // Cari index sheet yang cocok (case-insensitive)
                $idx = array_search($sheetNameLower, $normalNames);
                if ($idx === false) continue;

                $sheet      = $spreadsheet->getSheet($idx);
                $highestRow = $sheet->getHighestRow();

                for ($row = 5; $row <= $highestRow; $row++) {
                    $nama    = trim((string) $sheet->getCell("A{$row}")->getValue());
                    $nrp     = trim((string) $sheet->getCell("B{$row}")->getValue());
                    $jabatan = trim((string) $sheet->getCell("C{$row}")->getValue());
                    $site    = trim((string) $sheet->getCell("D{$row}")->getValue());
                    $noHp    = trim((string) $sheet->getCell("E{$row}")->getValue());
                    $jk      = strtoupper(trim((string) $sheet->getCell("F{$row}")->getValue()));

                    if (empty($nama)) continue;

                    // Cek NRP ganda global
                    if (!empty($nrp) && strtoupper($nrp) !== 'N/A') {
                        $nrpUpper = strtoupper($nrp);
                        if (in_array($nrpUpper, $trackedNrps)) {
                            return [
                                'success'      => false,
                                'is_dual_sheet' => true,
                                'message'      => "Gagal: Sheet '{$tipe}' baris {$row}. NRP '{$nrp}' sudah digunakan di sheet lain atau baris sebelumnya!",
                            ];
                        }
                        $trackedNrps[] = $nrpUpper;
                    }

                    $result[] = [
                        'tipe'    => $tipe,
                        'nama'    => $nama,
                        'nrp'     => $nrp ?: 'N/A',
                        'jabatan' => $jabatan ?: null,
                        'site'    => $site ?: null,
                        'no_hp'   => $noHp ?: null,
                        'gender'  => in_array($jk, ['L', 'P']) ? $jk : null,
                    ];
                }
            }
        } else {
            // ── Format lama: single sheet (backward compat) ─────────
            $sheet      = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            for ($row = 5; $row <= $highestRow; $row++) {
                $nama    = trim((string) $sheet->getCell("A{$row}")->getValue());
                $nrp     = trim((string) $sheet->getCell("B{$row}")->getValue());
                $jabatan = trim((string) $sheet->getCell("C{$row}")->getValue());
                $site    = trim((string) $sheet->getCell("D{$row}")->getValue());
                $noHp    = trim((string) $sheet->getCell("E{$row}")->getValue());
                $jk      = strtoupper(trim((string) $sheet->getCell("F{$row}")->getValue()));

                if (empty($nama)) continue;

                if (!empty($nrp) && strtoupper($nrp) !== 'N/A') {
                    if (in_array($nrp, $trackedNrps)) {
                        return [
                            'success'       => false,
                            'is_dual_sheet' => false,
                            'message'       => "Gagal: Baris ke-{$row}. NRP '{$nrp}' terdeteksi ganda dalam file Excel!",
                        ];
                    }
                    $trackedNrps[] = $nrp;
                }

                $result[] = [
                    'tipe'    => 'peserta',   // default: semua peserta pada format lama
                    'nama'    => $nama,
                    'nrp'     => $nrp ?: 'N/A',
                    'jabatan' => $jabatan ?: null,
                    'site'    => $site ?: null,
                    'no_hp'   => $noHp ?: null,
                    'gender'  => in_array($jk, ['L', 'P']) ? $jk : null,
                ];
            }
        }

        return [
            'success'       => true,
            'is_dual_sheet' => $isDualSheet,
            'data'          => $result,
        ];
    }
}

