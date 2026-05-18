<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\BookingWindow;
use App\Models\Ruangan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BookingWizardController extends Controller
{
    /**
     * Menampilkan halaman Booking Wizard.
     */
    public function create()
    {
        return Inertia::render('User/BookingWizard');
    }

    /**
     * Membuat dan mengunduh berkas template Excel asli (.xlsx) secara dinamis.
     */
    public function downloadTemplate()
    {
        $filePath = base_path('template_peserta_baru_kosong.xlsx');

        if (!file_exists($filePath)) {
            abort(404, 'File template fisik tidak ditemukan di sistem.');
        }

        return response()->download($filePath, 'template_peserta_baru_kosong.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Endpoint validasi instan menggunakan koordinat sel PhpSpreadsheet.
     * Mengikuti panduan presisi:
     * - Wajib berformat .xlsx
     * - Mengakses koordinat A, B, C per baris
     * - Gender wajib L atau P
     * - Return error berformat khusus ['error' => '...']
     */
    public function validateParticipants(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:2048|mimes:xlsx', // Hanya terima Excel asli (.xlsx)
        ]);

        $file = $request->file('file');

        try {
            // 1. Load file secara utuh sebagai objek Spreadsheet
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();

            // 2. Ambil total baris
            $highestRow = $sheet->getHighestRow();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Pemberitahuan: Gagal memuat file Excel (.xlsx). Pastikan berkas tidak terenkripsi.',
            ], 422);
        }

        $dataPeserta   = [];
        $jumlahPeserta = 0;

        // 3. Perulangan per baris dimulai dari baris ke-5 (karena baris 1-4 adalah header & info)
        for ($row = 5; $row <= $highestRow; $row++) {
            // 4. Baca per kolom menggunakan koordinat huruf (A, B, C, D) dan pastikan di-cast menjadi string
            $nama         = trim((string) $sheet->getCell("A" . $row)->getValue());
            $jabatan      = trim((string) $sheet->getCell("B" . $row)->getValue());
            $site         = trim((string) $sheet->getCell("C" . $row)->getValue());
            $jenisKelamin = trim((string) $sheet->getCell("D" . $row)->getValue());

            // Abaikan jika barisnya benar-benar kosong
            if (empty($nama) && empty($jabatan) && empty($site) && empty($jenisKelamin)) {
                continue;
            }

            // 5. Validasi data Wajib (Kolom A, B, C)
            if (empty($nama)) {
                return response()->json([
                    'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom Nama Lengkap (A) wajib diisi!",
                ], 422);
            }
            if (empty($jabatan)) {
                return response()->json([
                    'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom Jabatan (B) wajib diisi!",
                ], 422);
            }
            if (empty($site)) {
                return response()->json([
                    'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom Site (C) wajib diisi!",
                ], 422);
            }

            // Validasi Jenis Kelamin (Kolom D)
            $jk = strtoupper(trim($jenisKelamin));
            if ($jk !== 'L' && $jk !== 'P') {
                return response()->json([
                    'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom Jenis Kelamin (D) harus berisi L atau P!",
                ], 422);
            }

            $dataPeserta[] = [
                'nama'    => $nama,
                'jabatan' => $jabatan,
                'site'    => $site,
                'gender'  => $jk,
            ];

            $jumlahPeserta++;
        }

        if ($jumlahPeserta === 0) {
            return response()->json([
                'error' => 'Pemberitahuan: File Excel tidak berisi data peserta yang valid pada baris 5 dst.',
            ], 422);
        }

        // 6. Kembalikan hasil kalkulasi bersih ke Frontend
        return response()->json([
            'success'       => true,
            'total_peserta' => $jumlahPeserta,
            'peserta'       => $dataPeserta,
        ]);
    }

    // ============================================================
    // TAHAP 1 — Cek Booking Window & Filter Kapasitas
    // ============================================================
    public function checkEligibility(Request $request)
    {
        $activeWindow = BookingWindow::active()->first();

        if (!$activeWindow) {
            return response()->json([
                'success' => false,
                'message' => 'Periode pemesanan belum dibuka oleh admin.',
            ], 403);
        }

        $validated = $request->validate([
            'jumlah_peserta' => 'required|integer|min:1',
            'jumlah_panitia' => 'required|integer|min:0',
        ]);

        $totalOrang = $validated['jumlah_peserta'] + $validated['jumlah_panitia'];

        $eligibleRooms = collect();

        if ($totalOrang <= 25) {
            $eligibleRooms = Ruangan::all();
        } elseif ($totalOrang <= 30) {
            $eligibleRooms = Ruangan::whereNotIn('nama_ruang', ['Ruang 5', 'Ruang 6'])->get();
        } else {
            $ruang2 = Ruangan::where('nama_ruang', 'Ruang 2')->first();
            $ruang3 = Ruangan::where('nama_ruang', 'Ruang 3')->first();

            if ($ruang2 && $ruang3) {
                $eligibleRooms = collect([(object) [
                    'id'            => 'combined_2_3',
                    'nama_ruang'    => 'Ruang Gabungan 2 + 3',
                    'lokasi_gedung' => $ruang2->lokasi_gedung,
                    'kapasitas_max' => 60,
                    'is_combined'   => true,
                    'room_ids'      => [$ruang2->id, $ruang3->id],
                ]]);
            }
        }

        if ($eligibleRooms->isEmpty() || $totalOrang > 60) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada ruangan yang tersedia untuk kapasitas tersebut.',
            ], 422);
        }

        return response()->json([
            'success'        => true,
            'total_orang'    => $totalOrang,
            'eligible_rooms' => $eligibleRooms,
            'booking_window' => ['tahun' => $activeWindow->tahun],
        ]);
    }

    // ============================================================
    // TAHAP 2 — Kalkulasi Status Hari
    // ============================================================
    public function getAvailability(Request $request)
    {
        $validated = $request->validate([
            'year'                => 'required|integer|min:2020|max:2100',
            'eligible_room_ids'   => 'required|array|min:1',
            'eligible_room_ids.*' => 'integer',
            'is_combined'         => 'boolean',
        ]);

        $year            = $validated['year'];
        $eligibleRoomIds = $validated['eligible_room_ids'];
        $isCombined      = $validated['is_combined'] ?? false;
        $totalEligible   = count($eligibleRoomIds);

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        $bookings = Booking::whereNotIn('status', ['cancelled'])
            ->whereIn('ruangan_id', $eligibleRoomIds)
            ->where('tgl_mulai', '<=', $yearEnd)
            ->where('tgl_selesai', '>=', $yearStart)
            ->get(['ruangan_id', 'tgl_mulai', 'tgl_selesai']);

        $dateBookedRooms = [];

        foreach ($bookings as $booking) {
            $start = max($booking->tgl_mulai, $yearStart);
            $end   = min($booking->tgl_selesai, $yearEnd);

            $current = $start->copy();
            while ($current->lte($end)) {
                $dateStr = $current->toDateString();
                if (!isset($dateBookedRooms[$dateStr])) {
                    $dateBookedRooms[$dateStr] = [];
                }
                $dateBookedRooms[$dateStr][$booking->ruangan_id] = true;
                $current->addDay();
            }
        }

        $blockedDates = [];
        foreach ($dateBookedRooms as $dateStr => $bookedRoomMap) {
            $bookedCount = count($bookedRoomMap);
            if ($isCombined) {
                $blockedDates[$dateStr] = 'full';
            } else {
                $blockedDates[$dateStr] = $bookedCount >= $totalEligible ? 'full' : 'partial';
            }
        }

        return response()->json([
            'success'       => true,
            'year'          => $year,
            'blocked_dates' => $blockedDates,
        ]);
    }

    // ============================================================
    // TAHAP 3 — Cek Ketersediaan Ruangan
    // ============================================================
    public function getAvailableRooms(Request $request)
    {
        $validated = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'total_orang' => 'required|integer|min:1',
        ]);

        $startDate  = $validated['start_date'];
        $endDate    = $validated['end_date'];
        $totalOrang = $validated['total_orang'];

        if ($totalOrang > 30) {
            $ruang2 = Ruangan::where('nama_ruang', 'Ruang 2')->first();
            $ruang3 = Ruangan::where('nama_ruang', 'Ruang 3')->first();

            $r2Conflict = $this->hasConflict($ruang2->id, $startDate, $endDate);
            $r3Conflict = $this->hasConflict($ruang3->id, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'rooms'   => [[
                    'id'           => 'combined_2_3',
                    'nama_ruang'   => 'Ruang Gabungan 2 + 3',
                    'lokasi_gedung'=> $ruang2->lokasi_gedung,
                    'kapasitas_max'=> 60,
                    'is_combined'  => true,
                    'room_ids'     => [$ruang2->id, $ruang3->id],
                    'is_available' => !$r2Conflict && !$r3Conflict,
                    'deskripsi'    => 'Dua ruangan yang dapat digabungkan (Ruang 2 + Ruang 3)',
                ]],
            ]);
        }

        $eligibleRooms = $totalOrang <= 25
            ? Ruangan::all()
            : Ruangan::whereNotIn('nama_ruang', ['Ruang 5', 'Ruang 6'])->get();

        $rooms = $eligibleRooms->map(function (Ruangan $room) use ($startDate, $endDate) {
            return [
                'id'           => $room->id,
                'nama_ruang'   => $room->nama_ruang,
                'lokasi_gedung'=> $room->lokasi_gedung,
                'kapasitas_max'=> $room->kapasitas_max,
                'is_combined'  => false,
                'room_ids'     => [$room->id],
                'is_available' => !$this->hasConflict($room->id, $startDate, $endDate),
                'deskripsi'    => null,
            ];
        });

        return response()->json([
            'success' => true,
            'rooms'   => $rooms,
        ]);
    }

    // ============================================================
    // TAHAP 4 — Simpan Detail Booking ke Session
    // ============================================================
    public function saveStage4(Request $request)
    {
        $validated = $request->validate([
            'nama_training'      => 'required|string|max:255',
            'nama_pic'           => 'required|string|max:255',
            'layout_preferensi'  => 'required|string|in:classroom,u-shape,i-shape,o-shape,custom',
            'layout_custom_file' => 'nullable|file|mimes:jpeg,png,pdf|max:2048',
            'hybrid'             => 'nullable|boolean',
            'flipchart'          => 'nullable|boolean',
            'catatan'            => 'nullable|string',
        ]);

        $path = session('booking_step4.layout_custom_path');

        if ($request->hasFile('layout_custom_file')) {
            $path = $request->file('layout_custom_file')->store('layouts', 'public');
        }

        session(['booking_step4' => [
            'nama_training'      => $validated['nama_training'],
            'nama_pic'           => $validated['nama_pic'],
            'layout_preferensi'  => $validated['layout_preferensi'],
            'layout_custom_path' => $path,
            'hybrid'             => $validated['hybrid'] ?? false,
            'flipchart'          => $validated['flipchart'] ?? false,
            'catatan'            => $validated['catatan'] ?? '',
        ]]);

        return response()->json([
            'success' => true,
            'message' => 'Detail booking berhasil diamankan.',
        ]);
    }

    // ============================================================
    // TAHAP 5 — Submit Final
    // ============================================================
    public function submitBooking(Request $request)
    {
        $validated = $request->validate([
            'ruangan_id' => 'required',
            'tgl_mulai'  => 'required|date',
            'tgl_selesai'=> 'required|date|after_or_equal:tgl_mulai',
            'peserta'    => 'nullable|array',
            'panitia'    => 'nullable|array',
        ]);

        $stage4Data = session('booking_step4');
        if (!$stage4Data) {
            return response()->json(['success' => false, 'message' => 'Data detail (Tahap 4) tidak ditemukan. Silakan ulangi.'], 400);
        }

        try {
            DB::beginTransaction();

            // Pessimistic Locking
            $roomId = $validated['ruangan_id'];
            $isCombined = $roomId === 'combined_2_3';

            if ($isCombined) {
                $ruang2 = Ruangan::where('nama_ruang', 'Ruang 2')->lockForUpdate()->first();
                $ruang3 = Ruangan::where('nama_ruang', 'Ruang 3')->lockForUpdate()->first();
                
                if ($this->hasConflict($ruang2->id, $validated['tgl_mulai'], $validated['tgl_selesai']) || 
                    $this->hasConflict($ruang3->id, $validated['tgl_mulai'], $validated['tgl_selesai'])) {
                    throw new \Exception('Maaf, ruangan baru saja dipesan oleh divisi lain beberapa detik yang lalu.');
                }
                $primaryRoomId = $ruang2->id; 
            } else {
                $ruang = Ruangan::where('id', $roomId)->lockForUpdate()->first();
                if (!$ruang || $this->hasConflict($ruang->id, $validated['tgl_mulai'], $validated['tgl_selesai'])) {
                    throw new \Exception('Maaf, ruangan baru saja dipesan oleh divisi lain beberapa detik yang lalu.');
                }
                $primaryRoomId = $ruang->id;
            }

            // Create Booking
            $booking = Booking::create([
                'user_id'            => auth()->id(),
                'ruangan_id'         => $primaryRoomId,
                'nama_training'      => $stage4Data['nama_training'],
                'tgl_mulai'          => $validated['tgl_mulai'],
                'tgl_selesai'        => $validated['tgl_selesai'],
                'fase'               => 'reguler',
                'status'             => 'waiting_confirmation',
                'pic'                => $stage4Data['nama_pic'],
                'gabung_ruang'       => $isCombined,
                'layout_preferensi'  => $stage4Data['layout_preferensi'],
                'layout_custom_path' => $stage4Data['layout_custom_path'],
                'is_hybrid'          => $stage4Data['hybrid'],
                'is_flipchart'       => $stage4Data['flipchart'],
                'catatan_admin'      => $stage4Data['catatan'],
            ]);

            // Save Participants
            $participantsToInsert = [];
            foreach ($validated['peserta'] ?? [] as $p) {
                if (empty(trim($p['nama'] ?? ''))) continue;
                $participantsToInsert[] = [
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
                $participantsToInsert[] = [
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
            
            if (count($participantsToInsert) > 0) {
                BookingParticipant::insert($participantsToInsert);
            }

            DB::commit();
            session()->forget('booking_step4');

            return response()->json([
                'success'    => true,
                'message'    => 'Booking berhasil diajukan.',
                'booking_id' => $booking->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================
    private function hasConflict(int $roomId, string $startDate, string $endDate): bool
    {
        return Booking::where('ruangan_id', $roomId)
            ->whereNotIn('status', ['cancelled'])
            ->where('tgl_mulai', '<=', $endDate)
            ->where('tgl_selesai', '>=', $startDate)
            ->exists();
    }
}
