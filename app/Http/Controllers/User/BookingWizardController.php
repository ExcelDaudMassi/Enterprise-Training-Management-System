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
        if (!BookingWindow::active()->exists()) {
            return redirect()->route('user.dashboard')->with('error', 'Pemesanan baru tidak dapat diakses karena window booking sedang ditutup.');
        }
        return Inertia::render('User/BookingWizard');
    }

    public function downloadTemplate()
    {
        $filePath = base_path('Template_Peserta_Panitia.xlsx');

        if (!file_exists($filePath)) {
            abort(404, 'File template fisik tidak ditemukan di sistem.');
        }

        return response()->download($filePath, 'Template_Peserta_Panitia.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
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

        $sheetNames  = $spreadsheet->getSheetNames();
        $normalNames = array_map(fn($n) => mb_strtolower(trim($n)), $sheetNames);

        $hasPesertaSheet = in_array('peserta', $normalNames);
        $hasPanitiaSheet = in_array('panitia', $normalNames);
        $isDualSheet     = $hasPesertaSheet && $hasPanitiaSheet;

        $dataPeserta   = [];
        $dataPanitia   = [];
        $trackedNrps   = []; // Tracker duplikasi NRP di memori

        if ($isDualSheet) {
            $sheetMap = [
                'peserta' => 'peserta',
                'panitia' => 'panitia',
            ];

            foreach ($sheetMap as $sheetNameLower => $tipe) {
                $idx = array_search($sheetNameLower, $normalNames);
                if ($idx === false) continue;

                $sheet      = $spreadsheet->getSheet($idx);
                $highestRow = $sheet->getHighestRow();

                for ($row = 5; $row <= $highestRow; $row++) {
                    $nama         = trim((string) $sheet->getCell("A" . $row)->getValue());
                    $nrp          = trim((string) $sheet->getCell("B" . $row)->getValue());
                    $jabatan      = trim((string) $sheet->getCell("C" . $row)->getValue());
                    $site         = trim((string) $sheet->getCell("D" . $row)->getValue());
                    $noHp         = trim((string) $sheet->getCell("E" . $row)->getValue());
                    $jenisKelamin = trim((string) $sheet->getCell("F" . $row)->getValue());

                    // Abaikan jika barisnya benar-benar kosong
                    if (empty($nama) && empty($nrp) && empty($jabatan) && empty($site) && empty($noHp) && empty($jenisKelamin)) {
                        continue;
                    }

                    // 5. Validasi data Wajib
                    if (empty($nama)) {
                        return response()->json([
                            'error' => "Pemberitahuan: Sheet {$tipe} Baris ke-{$row} Gagal. Kolom Nama Lengkap (A) wajib diisi!",
                        ], 422);
                    }
                    if (empty($nrp)) {
                        return response()->json([
                            'error' => "Pemberitahuan: Sheet {$tipe} Baris ke-{$row} Gagal. Kolom NRP (B) wajib diisi, ketik 'N/A' jika tidak ada!",
                        ], 422);
                    }
                    if (empty($jabatan)) {
                        return response()->json([
                            'error' => "Pemberitahuan: Sheet {$tipe} Baris ke-{$row} Gagal. Kolom Jabatan (C) wajib diisi!",
                        ], 422);
                    }
                    if (empty($site)) {
                        return response()->json([
                            'error' => "Pemberitahuan: Sheet {$tipe} Baris ke-{$row} Gagal. Kolom Site (D) wajib diisi!",
                        ], 422);
                    }
                    
                    // Validasi Nomor HP
                    if (empty($noHp) || !preg_match('/^[0-9+]+$/', $noHp)) {
                        return response()->json([
                            'error' => "Pemberitahuan: Sheet {$tipe} Baris ke-{$row} Gagal. Kolom No. HP (E) wajib diisi dan minimal berisi angka!",
                        ], 422);
                    }

                    // Validasi Jenis Kelamin
                    $jk = strtoupper(trim($jenisKelamin));
                    if ($jk !== 'L' && $jk !== 'P') {
                        return response()->json([
                            'error' => "Pemberitahuan: Sheet {$tipe} Baris ke-{$row} Gagal. Kolom Jenis Kelamin (F) harus berisi L atau P!",
                        ], 422);
                    }

                    // Validasi duplikasi NRP
                    if (strtoupper($nrp) !== 'N/A') {
                        $nrpUpper = strtoupper($nrp);
                        if (in_array($nrpUpper, $trackedNrps)) {
                            return response()->json([
                                'error' => "Pemberitahuan: Sheet {$tipe} Baris ke-{$row} Gagal. NRP '{$nrp}' terdeteksi ganda dalam file Excel!",
                            ], 422);
                        }
                        $trackedNrps[] = $nrpUpper;
                    }

                    $rowArray = [
                        'nama'    => $nama,
                        'nrp'     => $nrp,
                        'jabatan' => $jabatan,
                        'site'    => $site,
                        'no_hp'   => $noHp,
                        'gender'  => $jk,
                    ];

                    if ($tipe === 'peserta') {
                        $dataPeserta[] = $rowArray;
                    } else {
                        $dataPanitia[] = $rowArray;
                    }
                }
            }
        } else {
            // Backward compatibility: format lama (satu sheet, semua dianggap peserta)
            $sheet      = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            for ($row = 5; $row <= $highestRow; $row++) {
                $nama         = trim((string) $sheet->getCell("A" . $row)->getValue());
                $nrp          = trim((string) $sheet->getCell("B" . $row)->getValue());
                $jabatan      = trim((string) $sheet->getCell("C" . $row)->getValue());
                $site         = trim((string) $sheet->getCell("D" . $row)->getValue());
                $noHp         = trim((string) $sheet->getCell("E" . $row)->getValue());
                $jenisKelamin = trim((string) $sheet->getCell("F" . $row)->getValue());

                // Abaikan jika barisnya benar-benar kosong
                if (empty($nama) && empty($nrp) && empty($jabatan) && empty($site) && empty($noHp) && empty($jenisKelamin)) {
                    continue;
                }

                // Validasi data Wajib
                if (empty($nama)) {
                    return response()->json([
                        'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom Nama Lengkap (A) wajib diisi!",
                    ], 422);
                }
                if (empty($nrp)) {
                    return response()->json([
                        'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom NRP (B) wajib diisi, ketik 'N/A' jika tidak ada!",
                    ], 422);
                }
                if (empty($jabatan)) {
                    return response()->json([
                        'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom Jabatan (C) wajib diisi!",
                    ], 422);
                }
                if (empty($site)) {
                    return response()->json([
                        'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom Site (D) wajib diisi!",
                    ], 422);
                }
                
                // Validasi Nomor HP
                if (empty($noHp) || !preg_match('/^[0-9+]+$/', $noHp)) {
                    return response()->json([
                        'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom No. HP (E) wajib diisi dan minimal berisi angka!",
                    ], 422);
                }

                // Validasi Jenis Kelamin
                $jk = strtoupper(trim($jenisKelamin));
                if ($jk !== 'L' && $jk !== 'P') {
                    return response()->json([
                        'error' => "Pemberitahuan: Baris ke-{$row} Gagal. Kolom Jenis Kelamin (F) harus berisi L atau P!",
                    ], 422);
                }

                // Validasi duplikasi NRP
                if (strtoupper($nrp) !== 'N/A') {
                    $nrpUpper = strtoupper($nrp);
                    if (in_array($nrpUpper, $trackedNrps)) {
                        return response()->json([
                            'error' => "Pemberitahuan: Baris ke-{$row} Gagal. NRP '{$nrp}' terdeteksi ganda dalam file Excel!",
                        ], 422);
                    }
                    $trackedNrps[] = $nrpUpper;
                }

                $dataPeserta[] = [
                    'nama'    => $nama,
                    'nrp'     => $nrp,
                    'jabatan' => $jabatan,
                    'site'    => $site,
                    'no_hp'   => $noHp,
                    'gender'  => $jk,
                ];
            }
        }

        if (count($dataPeserta) === 0 && count($dataPanitia) === 0) {
            return response()->json([
                'error' => 'Pemberitahuan: File Excel tidak berisi data peserta atau panitia yang valid pada baris 5 dst.',
            ], 422);
        }

        // 6. Kembalikan hasil kalkulasi bersih ke Frontend
        return response()->json([
            'success'       => true,
            'is_dual_sheet' => $isDualSheet,
            'total_peserta' => count($dataPeserta),
            'peserta'       => $dataPeserta,
            'total_panitia' => count($dataPanitia),
            'panitia'       => $dataPanitia,
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

        // Ambil booking langsung pada ruangan yang eligible
        $bookings = Booking::whereNotIn('status', ['cancelled', 'rejected'])
            ->whereIn('ruangan_id', $eligibleRoomIds)
            ->where('tgl_mulai', '<=', $yearEnd)
            ->where('tgl_selesai', '>=', $yearStart)
            ->with(['ruangan:id,nama_ruang'])
            ->get(['id', 'ruangan_id', 'nama_training', 'tgl_mulai', 'tgl_selesai', 'gabung_ruang']);

        // Juga ambil booking gabungan dari ruangan pasangan yang menempati eligible rooms
        // Contoh: Jika eligible rooms = [1,2,3,4,5,6,7], dan ada booking gabungan di Ruang 2,
        //         maka Ruang 3 juga harus ditandai sebagai terisi.
        $partnerRoomIds = Ruangan::whereIn('id', $eligibleRoomIds)
            ->whereNotNull('pasangan_ruang_id')
            ->pluck('pasangan_ruang_id')
            ->unique()
            ->diff($eligibleRoomIds) // Hindari duplikasi jika pasangan sudah ada di eligible
            ->values()
            ->toArray();

        $combinedBookingsFromPartners = collect();
        if (!empty($partnerRoomIds)) {
            $combinedBookingsFromPartners = Booking::whereNotIn('status', ['cancelled', 'rejected'])
                ->whereIn('ruangan_id', $partnerRoomIds)
                ->where('gabung_ruang', true)
                ->where('tgl_mulai', '<=', $yearEnd)
                ->where('tgl_selesai', '>=', $yearStart)
                ->with(['ruangan:id,nama_ruang,pasangan_ruang_id'])
                ->get(['id', 'ruangan_id', 'nama_training', 'tgl_mulai', 'tgl_selesai', 'gabung_ruang']);
        }

        $dateBookedRooms = [];

        // Proses booking langsung
        foreach ($bookings as $booking) {
            $start = max($booking->tgl_mulai, $yearStart);
            $end   = min($booking->tgl_selesai, $yearEnd);

            $current = $start->copy();
            while ($current->lte($end)) {
                $dateStr = $current->toDateString();
                if (!isset($dateBookedRooms[$dateStr])) {
                    $dateBookedRooms[$dateStr] = [];
                }
                $dateBookedRooms[$dateStr][$booking->ruangan_id] = [
                    'ruangan_id'    => $booking->ruangan_id,
                    'nama_ruang'    => $booking->displayRoomName(),
                    'nama_training' => $booking->nama_training,
                ];

                // Jika booking ini gabungan, tandai ruangan pasangan juga sebagai terpakai
                if ($booking->gabung_ruang && $booking->ruangan && $booking->ruangan->pasangan_ruang_id) {
                    $partnerId = $booking->ruangan->pasangan_ruang_id;
                    if (in_array($partnerId, $eligibleRoomIds)) {
                        $dateBookedRooms[$dateStr][$partnerId] = [
                            'ruangan_id'    => $partnerId,
                            'nama_ruang'    => $booking->displayRoomName(),
                            'nama_training' => $booking->nama_training,
                        ];
                    }
                }

                $current = $current->addDay();
            }
        }

        // Proses booking gabungan dari ruangan pasangan
        foreach ($combinedBookingsFromPartners as $booking) {
            $start = max($booking->tgl_mulai, $yearStart);
            $end   = min($booking->tgl_selesai, $yearEnd);

            // Cari eligible room yang merupakan pasangan dari ruangan booking ini
            $partnerInEligible = Ruangan::where('pasangan_ruang_id', $booking->ruangan_id)
                ->whereIn('id', $eligibleRoomIds)
                ->first();

            if (!$partnerInEligible) continue;

            $current = $start->copy();
            while ($current->lte($end)) {
                $dateStr = $current->toDateString();
                if (!isset($dateBookedRooms[$dateStr])) {
                    $dateBookedRooms[$dateStr] = [];
                }
                $dateBookedRooms[$dateStr][$partnerInEligible->id] = [
                    'ruangan_id'    => $partnerInEligible->id,
                    'nama_ruang'    => $booking->displayRoomName(),
                    'nama_training' => $booking->nama_training,
                ];
                $current = $current->addDay();
            }
        }

        $blockedDates = [];
        foreach ($dateBookedRooms as $dateStr => $bookedRoomMap) {
            $bookedCount = count($bookedRoomMap);
            if ($isCombined) {
                $status = 'full';
            } else {
                $status = $bookedCount >= $totalEligible ? 'full' : 'partial';
            }
            
            $blockedDates[$dateStr] = [
                'status'         => $status,
                'occupied_rooms' => array_values($bookedRoomMap),
            ];
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

            if (!$ruang2 || !$ruang3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konfigurasi ruang gabungan tidak ditemukan. Hubungi Admin untuk menyiapkan Ruang 2 dan Ruang 3.',
                ], 500);
            }

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
            'no_hp_pic'          => 'required|string|max:255',
            'layout_preferensi'  => 'required|string|in:classroom,u-shape,i-shape,o-shape,custom',
            'layout_custom_file' => 'nullable|file|mimes:jpeg,png,pdf|max:2048',
            'hybrid'             => 'nullable|boolean',
            'flipchart'          => 'nullable|boolean',
            'pena_mini_note'     => 'nullable|boolean',
            'catatan_user'       => 'nullable|string|max:500',
        ]);

        $path = session('booking_step4.layout_custom_path');

        if ($request->hasFile('layout_custom_file')) {
            $path = $request->file('layout_custom_file')->store('layouts', 'public');
        }

        session(['booking_step4' => [
            'nama_training'      => $validated['nama_training'],
            'nama_pic'           => $validated['nama_pic'],
            'no_hp_pic'          => $validated['no_hp_pic'],
            'layout_preferensi'  => $validated['layout_preferensi'],
            'layout_custom_path' => $path,
            'hybrid'             => $validated['hybrid'] ?? false,
            'flipchart'          => $validated['flipchart'] ?? false,
            'pena_mini_note'     => $validated['pena_mini_note'] ?? false,
            'catatan_user'       => $validated['catatan_user'] ?? null,
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
        if (!BookingWindow::active()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Pemesanan gagal diajukan. Window booking telah ditutup oleh Admin.'
            ], 403);
        }

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
                'status'             => Booking::STATUS_PENDING,
                'pic'                => $stage4Data['nama_pic'],
                'no_hp_pic'          => $stage4Data['no_hp_pic'] ?? null,
                'gabung_ruang'       => $isCombined,
                'layout_preferensi'  => $stage4Data['layout_preferensi'],
                'layout_custom_path' => $stage4Data['layout_custom_path'],
                'is_hybrid'          => $stage4Data['hybrid'],
                'is_flipchart'       => $stage4Data['flipchart'],
                'is_pena_mini_note'  => $stage4Data['pena_mini_note'],
                'catatan_user'       => $stage4Data['catatan_user'] ?? $stage4Data['catatan'] ?? null,
                'catatan_admin'      => null,
            ]);

            // Save Participants
            $participantsToInsert = [];
            foreach ($validated['peserta'] ?? [] as $p) {
                if (empty(trim($p['nama'] ?? ''))) continue;
                $participantsToInsert[] = [
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
                $participantsToInsert[] = [
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
            
            if (count($participantsToInsert) > 0) {
                BookingParticipant::insert($participantsToInsert);
            }

            DB::commit();
            session()->forget('booking_step4');

            // Broadcast di luar try-catch utama agar tidak mengganggu response
            try {
                broadcast(new \App\Events\NewBookingCreated($booking));
                \Log::info('[WebSocket] Broadcast NewBookingCreated berhasil untuk booking #' . $booking->id);
            } catch (\Exception $broadcastErr) {
                \Log::error('[WebSocket] Broadcast GAGAL: ' . $broadcastErr->getMessage());
            }

            return response()->json([
                'success'    => true,
                'message'    => 'Booking berhasil diajukan.',
                'booking_id' => $booking->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('[Booking] Submit GAGAL: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    /**
     * Cek apakah sebuah ruangan memiliki konflik jadwal pada rentang tanggal tertentu.
     *
     * Logika ini juga mendeteksi booking gabungan (Ruang 2 + 3):
     *  - Jika mengecek Ruang 3, akan ditemukan booking gabungan yang tercatat
     *    di Ruang 2 (karena gabung_ruang = true dan Ruang 2 adalah pasangan Ruang 3).
     *  - Demikian sebaliknya.
     */
    private function hasConflict(int $roomId, string $startDate, string $endDate): bool
    {
        // 1. Cek konflik langsung: booking yang ruangan_id-nya sama persis
        $directConflict = Booking::where('ruangan_id', $roomId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
            ->where('tgl_mulai', '<=', $endDate)
            ->where('tgl_selesai', '>=', $startDate)
            ->exists();

        if ($directConflict) {
            return true;
        }

        // 2. Cek konflik tidak langsung: booking gabungan yang menggunakan
        //    ruangan pasangan dari ruangan yang sedang dicek.
        //    Contoh: Ruang 3 (id=3) punya pasangan Ruang 2 (id=2).
        //            Jika ada booking gabungan di Ruang 2, maka Ruang 3 juga terpakai.
        $room = Ruangan::find($roomId);
        if ($room && $room->pasangan_ruang_id) {
            $partnerConflict = Booking::where('ruangan_id', $room->pasangan_ruang_id)
                ->where('gabung_ruang', true)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
                ->where('tgl_mulai', '<=', $endDate)
                ->where('tgl_selesai', '>=', $startDate)
                ->exists();

            if ($partnerConflict) {
                return true;
            }
        }

        return false;
    }
}

