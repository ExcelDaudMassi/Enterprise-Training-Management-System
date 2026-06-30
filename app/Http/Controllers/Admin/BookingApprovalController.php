<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\BookingParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BookingApprovalController extends Controller
{
    // ============================================================
    // INDEX — Tampilkan daftar booking dengan filter tab
    // ============================================================

    /**
     * Filter yang tersedia via query param ?filter=...:
     *   waiting_confirmation  — Tahap 1: menunggu ACC awal
     *   confirmed             — sudah di-ACC Tahap 1
     *   cancelled             — ditolak/dibatalkan
     *   final                 — sudah ACC Tahap 2
     *   preparation_alert     — Tahap 4: confirmed & tgl_mulai dalam X hari
     *   overdue               — Tahap 5: confirmed & tgl_mulai sudah lewat
     *   date_changes          — Memiliki usulan perubahan tanggal (pending)
     *   all                   — semua
     */
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $today  = Carbon::today();

        $query = Booking::with(['user', 'ruangan', 'participants']);

        match ($filter) {
            'waiting_confirmation', 'pending' => $query->where('status', Booking::STATUS_PENDING),
            'confirmed'            => $query->where('status', Booking::STATUS_CONFIRMED),
            'cancelled'            => $query->where('status', Booking::STATUS_CANCELLED),
            'rejected'             => $query->where('status', Booking::STATUS_REJECTED),
            'final', 'finalized'   => $query->where('status', Booking::STATUS_FINALIZED),
            'completed'            => $query->where('status', Booking::STATUS_COMPLETED),

            // Preparation Alert: confirmed, mulai dalam 0-X hari ke depan
            'preparation_alert', 'urgent' => $query->urgentPreparation(),

            // Overdue ACC Tahap 2: confirmed tapi tanggal mulai sudah lewat
            'overdue' => $query->where('status', Booking::STATUS_CONFIRMED)
                               ->where('tgl_mulai', '<', $today),

            // Usulan perubahan tanggal yang belum diproses
            'date_changes' => $query->where('status_perubahan', Booking::CHANGE_PENDING),

            default => null, // 'all' — tanpa filter tambahan
        };

        // Fitur Pencarian: cari berdasarkan nama training atau nama/divisi pemohon
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_training', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")
                                                    ->orWhere('divisi', 'like', "%{$search}%"));
            });
        }

        // Pengurutan: antrian tunggu → diurutkan dari TERLAMA; yang lain → TERBARU
        if (in_array($filter, ['waiting_confirmation', 'pending', 'preparation_alert', 'overdue', 'date_changes'])) {
            $query->orderBy('tgl_mulai', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Paginasi: 20 data per halaman, URL params dipertahankan
        $paginated = $query->paginate(20)->withQueryString();
        $bookings  = $paginated->getCollection()->map(fn(Booking $b) => $this->formatBooking($b));
        $paginated->setCollection($bookings);

        return Inertia::render('Admin/BookingApproval', [
            'bookings'     => $paginated,
            'activeFilter' => $filter,
            'search'       => $request->query('search', ''),
        ]);
    }

    // ============================================================
    // TAHAP 1 — Setujui booking (waiting_confirmation → confirmed)
    // ============================================================
    public function recap(Request $request)
    {
        $query = Booking::with(['user', 'ruangan']);
        $today = Carbon::today();

        $filter = $request->query('filter', 'all');

        match ($filter) {
            'waiting_confirmation', 'pending' => $query->where('status', Booking::STATUS_PENDING),
            'confirmed'            => $query->where('status', Booking::STATUS_CONFIRMED),
            'final', 'finalized'   => $query->where('status', Booking::STATUS_FINALIZED),
            'cancelled'            => $query->where('status', Booking::STATUS_CANCELLED),
            'rejected'             => $query->where('status', Booking::STATUS_REJECTED),
            'completed'            => $query->where('status', Booking::STATUS_COMPLETED),
            'urgent'               => $query->urgentPreparation()->where('status', Booking::STATUS_PENDING),
            'overdue'              => $query->where('status', Booking::STATUS_PENDING)
                                            ->where('tgl_mulai', '<', $today),
            default                => null,
        };

        if (in_array($filter, ['waiting_confirmation', 'pending', 'urgent', 'overdue'])) {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $bookings = $query->get()->map(function ($b) {
            return [
                'id'              => $b->id,
                'nama_training'   => $b->nama_training,
                'tgl_mulai'       => $b->tgl_mulai?->toDateString(),
                'tgl_selesai'     => $b->tgl_selesai?->toDateString(),
                'ruangan'         => $b->displayRoomName(),
                'layout'          => $b->layout_preferensi,
                'fase'            => $b->fase,
                'status'          => $b->status,
                'catatan_admin'   => $b->catatan_admin,
                'acc1_at'         => $b->acc1_at,
                'acc1_by'         => $b->acc1_by,
                'acc2_at'         => $b->acc2_at,
                'acc2_by'         => $b->acc2_by,
                'is_hybrid'       => (bool) $b->is_hybrid,
                'gabung_ruang'    => $b->gabung_ruang,
                'pemohon'         => $b->user?->name ?? '-',
                'divisi'          => $b->user?->divisi ?? '-',
                'pic'             => $b->pic,
                'jumlah_peserta'  => $b->jumlah_peserta,
                'jumlah_panitia'  => $b->jumlah_panitia,
                'created_at'      => $b->created_at?->format('d M Y, H:i'),
            ];
        });

        return Inertia::render('Admin/BookingRecap', [
            'bookings' => $bookings,
            'filter'   => $filter,
            'today'    => $today->toDateString(),
        ]);
    }

    /**
     * Tampilkan detail lengkap sebuah booking (AJAX).
     * Mengembalikan semua data yang dibutuhkan admin sebelum memproses.
     */
    public function showDetails(Booking $booking)
    {
        $booking->loadMissing([
            'ruangan',
            'user',
            'participants',
            'logs.user',
        ]);

        $peserta = $booking->participants
            ->where('tipe', 'peserta')
            ->values()
            ->map(fn($p) => [
                'nama'    => $p->nama,
                'jabatan' => $p->jabatan,
                'site'    => $p->site,
                'no_hp'   => $p->no_hp,
                'gender'  => $p->gender,
            ]);

        $panitia = $booking->participants
            ->where('tipe', 'panitia')
            ->values()
            ->map(fn($p) => [
                'nama'    => $p->nama,
                'jabatan' => $p->jabatan,
                'site'    => $p->site,
                'no_hp'   => $p->no_hp,
                'gender'  => $p->gender,
            ]);

        $logs = $booking->logs
            ->sortByDesc('created_at')
            ->values()
            ->map(fn($l) => [
                'action'     => $l->action,
                'message'    => $l->message,
                'actor'      => $l->user?->name ?? 'Sistem',
                'created_at' => $l->created_at->format('d M Y, H:i'),
            ]);

        // Hitung statistik gender
        $genderStats = [
            'L' => $peserta->where('gender', 'L')->count(),
            'P' => $peserta->where('gender', 'P')->count(),
        ];

        // Kelompokkan peserta berdasarkan site
        $siteStats = $peserta->groupBy('site')->map->count()->sortDesc();

        // Layout custom URL jika ada
        $layoutUrl = $booking->layout_custom_path
            ? asset('storage/' . $booking->layout_custom_path)
            : null;

        return response()->json([
            'booking' => [
                'id'                => $booking->id,
                'nama_training'     => $booking->nama_training,
                'tgl_mulai'         => $booking->tgl_mulai->format('d M Y'),
                'tgl_selesai'       => $booking->tgl_selesai->format('d M Y'),
                'status'            => $booking->status,
                'fase'              => $booking->fase,
                'pic'               => $booking->pic,
                'no_hp_pic'         => $booking->no_hp_pic,
                'gabung_ruang'      => $booking->gabung_ruang,
                'layout_preferensi' => $booking->layout_preferensi,
                'layout_url'        => $layoutUrl,
                'is_hybrid'         => $booking->is_hybrid,
                'is_flipchart'      => $booking->is_flipchart,
                'is_pena_mini_note' => $booking->is_pena_mini_note,
                'catatan_admin'     => $booking->catatan_admin,
                'created_at'        => $booking->created_at->format('d M Y, H:i'),
                // Ruangan
                'ruangan'           => $booking->ruangan ? [
                    'nama_ruang'    => $booking->displayRoomName(),
                    'lokasi_gedung' => $booking->ruangan->lokasi_gedung,
                    'kapasitas_max' => $booking->ruangan->kapasitas_max,
                ] : null,
                // Pemohon
                'pemohon' => $booking->user ? [
                    'name'   => $booking->user->name,
                    'email'  => $booking->user->email,
                    'divisi' => $booking->user->divisi,
                ] : null,
            ],
            'peserta'       => $peserta,
            'panitia'       => $panitia,
            'logs'          => $logs,
            'gender_stats'  => $genderStats,
            'site_stats'    => $siteStats,
            'total_peserta' => $peserta->count(),
            'total_panitia' => $panitia->count(),
        ]);
    }

    /**
     * Ekspor detail 1 booking ke Excel menggunakan BookingExcelExportService.
     */
    public function generateExcelFileForBooking(Booking $booking): string
    {
        return (new \App\Services\BookingExcelExportService())->generate($booking);
    }

    public function exportDetail(Booking $booking)
    {
        $filePath  = $this->generateExcelFileForBooking($booking);
        $cleanName = preg_replace('/^temp_excel_[a-z0-9]+_/', '', basename($filePath));
        return response()->download($filePath, $cleanName)->deleteFileAfterSend(true);
    }

    /**
     * Ekspor seluruh data booking ke file Excel (.xlsx).
     * Mendukung filter berdasarkan status dan bulan/tahun.
     */
    public function export(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $today  = Carbon::today();

        $query = Booking::with(['user', 'ruangan', 'participants']);

        match ($filter) {
            'waiting_confirmation', 'pending' => $query->where('status', Booking::STATUS_PENDING),
            'confirmed'            => $query->where('status', Booking::STATUS_CONFIRMED),
            'final', 'finalized'   => $query->where('status', Booking::STATUS_FINALIZED),
            'cancelled'            => $query->where('status', Booking::STATUS_CANCELLED),
            'rejected'             => $query->where('status', Booking::STATUS_REJECTED),
            'completed'            => $query->where('status', Booking::STATUS_COMPLETED),
            'urgent'               => $query->urgentPreparation()->where('status', Booking::STATUS_PENDING),
            'overdue'              => $query->where('status', Booking::STATUS_PENDING)
                                             ->where('tgl_mulai', '<', $today),
            default                => null,
        };

        $bookings = $query->orderBy('tgl_mulai', 'asc')->get();

        // ── Buat Spreadsheet ──────────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Booking');

        // Header style
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];

        // ── Header Utama ──
        $sheet->setCellValue('A1', 'REKAP DATA BOOKING RUANGAN - APLIKASI BOOKING RUANGAN');
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->setCellValue('A2', 'Diekspor pada: ' . now()->format('d M Y, H:i') . ' | Filter: ' . strtoupper($filter));
        $sheet->mergeCells('A2:L2');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['argb' => 'FF555555']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // ── Header Kolom ──
        $headers = [
            'A' => 'No.',
            'B' => 'Nama Training',
            'C' => 'Pemohon',
            'D' => 'Divisi',
            'E' => 'PIC',
            'F' => 'Ruangan',
            'G' => 'Tgl Mulai',
            'H' => 'Tgl Selesai',
            'I' => 'Status',
            'J' => 'Jml Peserta',
            'K' => 'Jml Panitia',
            'L' => 'Fasilitas',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '3', $label);
        }
        $sheet->getStyle('A3:L3')->applyFromArray($headerStyle);
        $sheet->getRowDimension(3)->setRowHeight(22);

        // ── Lebar Kolom ──
        $colWidths = ['A' => 5, 'B' => 35, 'C' => 22, 'D' => 20, 'E' => 20,
                      'F' => 22, 'G' => 14, 'H' => 14, 'I' => 18, 'J' => 12, 'K' => 12, 'L' => 28];
        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ── Baris Data ──
        $statusLabels = [
            'waiting_confirmation' => 'Menunggu',
            'confirmed'            => 'Disetujui',
            'cancelled'            => 'Ditolak',
            'plotting'             => 'Plotting',
        ];

        $row = 4;
        foreach ($bookings as $i => $b) {
            $fasilitas = collect([
                $b->is_hybrid ? 'Hybrid' : null,
                $b->is_flipchart ? 'Flipchart' : null,
                $b->is_pena_mini_note ? 'Pena & Mini Note' : null,
                $b->gabung_ruang ? 'Gabung Ruang' : null,
                $b->layout_preferensi ? ('Layout: ' . ucfirst($b->layout_preferensi)) : null,
            ])->filter()->implode(', ');

            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $b->nama_training);
            $sheet->setCellValue('C' . $row, $b->user?->name ?? '-');
            $sheet->setCellValue('D' . $row, $b->user?->divisi ?? '-');
            $sheet->setCellValue('E' . $row, $b->pic ?? '-');
            $sheet->setCellValue('F' . $row, $b->displayRoomName());
            $sheet->setCellValue('G' . $row, $b->tgl_mulai?->format('d/m/Y') ?? '-');
            $sheet->setCellValue('H' . $row, $b->tgl_selesai?->format('d/m/Y') ?? '-');
            $sheet->setCellValue('I' . $row, $statusLabels[$b->status] ?? $b->status);
            $sheet->setCellValue('J' . $row, $b->participants->where('tipe', 'peserta')->count());
            $sheet->setCellValue('K' . $row, $b->participants->where('tipe', 'panitia')->count());
            $sheet->setCellValue('L' . $row, $fasilitas ?: '-');

            // Zebra striping
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                ]);
            }

            // Warna status
            $statusColors = [
                'waiting_confirmation' => 'FFFFF3CD',
                'confirmed'            => 'FFD1FAE5',
                'cancelled'            => 'FFFEE2E2',
            ];
            if (isset($statusColors[$b->status])) {
                $sheet->getStyle('I' . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $statusColors[$b->status]]],
                    'font' => ['bold' => true],
                ]);
            }

            // Center alignment untuk kolom numerik dan status
            $sheet->getStyle('A' . $row . ':A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('I' . $row . ':K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        // ── Border tabel ──
        if ($row > 4) {
            $sheet->getStyle('A3:L' . ($row - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color'       => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ]);
        }

        // ── Row total ──
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->setCellValue('J' . $row, '=SUM(J4:J' . ($row - 1) . ')');
        $sheet->setCellValue('K' . $row, '=SUM(K4:K' . ($row - 1) . ')');
        $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
        ]);

        // ── Output ──
        $filename = 'Rekap_Booking_' . strtoupper($filter) . '_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Setujui booking.
     */
    public function approve(Booking $booking)
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking) {
                // Tentukan daftar ID ruangan yang dipesan
                $roomIds = [$booking->ruangan_id];
                $booking->loadMissing('ruangan');
                if ($booking->gabung_ruang && $booking->ruangan && $booking->ruangan->pasangan_ruang_id) {
                    $roomIds[] = $booking->ruangan->pasangan_ruang_id;
                }

                // Pessimistic locking pada baris ruangan di database
                \App\Models\Ruangan::whereIn('id', $roomIds)->lockForUpdate()->get();

                // Cek konflik: apakah ada booking aktif lain yang bertabrakan tanggal dan ruangan
                // Status aktif = semua kecuali cancelled (waiting_confirmation, confirmed, final)
                $overlappingActive = Booking::whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
                    ->where('id', '!=', $booking->id)
                    ->where('tgl_mulai', '<=', $booking->tgl_selesai->toDateString())
                    ->where('tgl_selesai', '>=', $booking->tgl_mulai->toDateString())
                    ->with('ruangan')
                    ->get();

                $hasConflict = false;
                foreach ($overlappingActive as $cb) {
                    $cbRoomIds = [$cb->ruangan_id];
                    if ($cb->gabung_ruang && $cb->ruangan && $cb->ruangan->pasangan_ruang_id) {
                        $cbRoomIds[] = $cb->ruangan->pasangan_ruang_id;
                    }
                    if (!empty(array_intersect($roomIds, $cbRoomIds))) {
                        $hasConflict = true;
                        break;
                    }
                }

                if ($hasConflict) {
                    throw new \Exception("Gagal menyetujui: Ruangan telah terisi (disetujui oleh admin lain) pada rentang tanggal tersebut.");
                }

                // Ubah status menjadi confirmed (ruangan resmi terkunci)
                $booking->update([
                    'status'  => 'confirmed',
                    'acc1_at' => now(),
                    'acc1_by' => auth()->id(),
                ]);

                // Catat log administratif
                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => auth()->id(),
                    'action'     => 'approve',
                    'message'    => "Admin " . auth()->user()->name . " menyetujui booking ini.",
                ]);

                // Kirim/Catat notifikasi untuk user pemohon
                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'approval',
                    'title'      => 'Booking Disetujui',
                    'message'      => "Booking Anda untuk '{$booking->nama_training}' pada tanggal " . $booking->tgl_mulai->format('d M Y') . " telah disetujui.",
                ]);

                \Illuminate\Support\Facades\Log::info("Booking #{$booking->id} disetujui oleh admin ID #" . auth()->id());

                broadcast(new \App\Events\BookingStatusUpdated($booking));
            });

            return back()->with('success', "Booking #{$booking->id} berhasil disetujui.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ============================================================
    // TAHAP 1 — Tolak booking (waiting_confirmation → cancelled)
    // ============================================================
    public function reject(Request $request, Booking $booking)
    {
        if (!$booking->isWaitingConfirmation()) {
            return back()->with('error', 'Hanya booking berstatus "Menunggu" yang dapat ditolak.');
        }

        $request->validate([
            'catatan_admin' => 'required|string|max:500',
        ], [
            'catatan_admin.required' => 'Alasan penolakan wajib diisi.',
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $request) {
                $booking->update([
                    'status'        => Booking::STATUS_REJECTED,
                    'catatan_admin' => $request->catatan_admin,
                ]);

                // Catat log administratif penolakan
                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => auth()->id(),
                    'action'     => 'reject',
                    'message'    => 'Admin ' . auth()->user()->name . ' menolak booking ini. Alasan: ' . $request->catatan_admin,
                ]);

                // Kirim notifikasi ke user pemohon
                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'rejection',
                    'title'      => 'Booking Ditolak',
                    'message'    => "Booking Anda untuk '{$booking->nama_training}' pada tanggal " . $booking->tgl_mulai->format('d M Y') . " telah ditolak. Alasan: {$request->catatan_admin}",
                ]);

                \Illuminate\Support\Facades\Log::info("Booking #{$booking->id} ditolak oleh admin ID #" . auth()->id());

                broadcast(new \App\Events\BookingStatusUpdated($booking));
            });

            return back()->with('success', "Booking #{$booking->id} telah ditolak.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function acc2(Request $request, Booking $booking)
    {
        if (!$booking->canBeAcc2()) {
            return back()->with('error', 'Booking ini tidak dalam status yang dapat di-ACC tahap 2.');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking) {
                // Ubah status menjadi final
                $booking->update([
                    'status'  => Booking::STATUS_FINALIZED,
                    'acc2_at' => now(),
                    'acc2_by' => auth()->id(),
                ]);

                // Catat log administratif
                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => auth()->id(),
                    'action'     => 'acc2',
                    'message'    => 'Admin ' . auth()->user()->name . ' melakukan Final Confirmation (ACC-2).',
                ]);

                // Kirim notifikasi ke user pemohon
                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'approval',
                    'title'      => 'Booking Final Confirmed',
                    'message'    => "Booking Anda untuk '{$booking->nama_training}' telah mendapatkan Final Confirmation (ACC-2). Kegiatan siap dilaksanakan.",
                ]);

                \Illuminate\Support\Facades\Log::info("Booking #{$booking->id} di-ACC tahap 2 oleh admin ID #" . auth()->id());

                $this->notifyFrontdesk($booking);
                broadcast(new \App\Events\BookingStatusUpdated($booking));
            });

            return back()->with('success', "Booking #{$booking->id} berhasil di-ACC tahap 2 (Final Confirmation).");
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // ============================================================
    // TAHAP 4 — ACC Final (confirmed → final)
    // ============================================================
    public function approveFinal(Booking $booking)
    {
        if (!$booking->canBeFinalized()) {
            return back()->with('error', 'Booking tidak dapat di-finalisasi. Status saat ini: ' . $booking->status);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking) {
                $booking->update([
                    'status'  => Booking::STATUS_FINALIZED,
                    'acc2_at' => now(),
                    'acc2_by' => Auth::id(),
                ]);

                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => Auth::id(),
                    'action'     => 'approve_final',
                    'message'    => 'Admin ' . Auth::user()->name . ' melakukan ACC Final.',
                ]);

                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'approval',
                    'title'      => 'Booking Final ACC',
                    'message'    => "Booking Anda untuk '{$booking->nama_training}' telah mendapatkan ACC Final. Persiapan lapangan segera dilakukan.",
                ]);

                \Illuminate\Support\Facades\Log::info("Booking #{$booking->id} di-ACC Final oleh admin ID #" . Auth::id());

                $this->notifyFrontdesk($booking);
            });

            broadcast(new \App\Events\BookingStatusUpdated($booking));

            return back()->with('success', "Booking #{$booking->id} berhasil di-ACC Final. Status: FINAL.");
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // ============================================================
    // TAHAP 5 — ACC Terlambat (confirmed → final, dengan catatan alasan)
    // ============================================================
    public function approveFinalLate(Request $request, Booking $booking)
    {
        if (!$booking->canBeFinalized()) {
            return back()->with('error', 'Booking tidak dapat di-finalisasi. Status saat ini: ' . $booking->status);
        }

        $request->validate([
            'catatan_acc_terlambat' => 'required|string|max:1000',
        ], [
            'catatan_acc_terlambat.required' => 'Alasan ACC terlambat wajib diisi.',
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $request) {
                $booking->update([
                    'status'                => Booking::STATUS_FINALIZED,
                    'acc2_at'               => now(),
                    'acc2_by'               => Auth::id(),
                    'catatan_acc_terlambat' => $request->catatan_acc_terlambat,
                ]);

                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => Auth::id(),
                    'action'     => 'approve_final_late',
                    'message'    => 'Admin ' . Auth::user()->name . ' melakukan ACC Final (Terlambat). Alasan: ' . $request->catatan_acc_terlambat,
                ]);

                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'approval',
                    'title'      => 'Booking Final ACC (Terlambat)',
                    'message'    => "Booking Anda untuk '{$booking->nama_training}' telah mendapatkan ACC Final. Persiapan lapangan segera dilakukan.",
                ]);

                \Illuminate\Support\Facades\Log::info("Booking #{$booking->id} di-ACC Final Terlambat oleh admin ID #" . Auth::id());

                $this->notifyFrontdesk($booking);
            });

            broadcast(new \App\Events\BookingStatusUpdated($booking));

            return back()->with('success', "Booking #{$booking->id} di-ACC Terlambat dan kini berstatus FINAL.");
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // ============================================================
    // TAHAP 3 — Setujui Perubahan Tanggal dari User
    // ============================================================
    public function approveDateChange(Booking $booking)
    {
        if (!$booking->hasPendingDateChange()) {
            return back()->with('error', 'Tidak ada usulan perubahan tanggal yang aktif untuk booking ini.');
        }

        // Cek konflik ruangan pada tanggal baru sebelum menyetujui
        $booking->loadMissing('ruangan');
        $roomIds = [$booking->ruangan_id];
        if ($booking->gabung_ruang && $booking->ruangan && $booking->ruangan->pasangan_ruang_id) {
            $roomIds[] = $booking->ruangan->pasangan_ruang_id;
        }

        $newStart = $booking->proposed_tgl_mulai->toDateString();
        $newEnd   = $booking->proposed_tgl_selesai->toDateString();

        $hasConflict = Booking::whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
            ->where('id', '!=', $booking->id)
            ->where('tgl_mulai', '<=', $newEnd)
            ->where('tgl_selesai', '>=', $newStart)
            ->where(function ($q) use ($roomIds) {
                // Cek langsung pada ruangan booking ini
                $q->whereIn('ruangan_id', $roomIds)
                  // ATAU booking gabungan yang pasangannya overlap
                  ->orWhere(function ($q2) use ($roomIds) {
                      $q2->where('gabung_ruang', true)
                         ->whereHas('ruangan', function ($rq) use ($roomIds) {
                             $rq->whereIn('pasangan_ruang_id', $roomIds);
                         });
                  });
            })
            ->exists();

        if ($hasConflict) {
            return back()->with('error', 'Gagal menyetujui perubahan tanggal: ruangan bentrok dengan booking lain pada tanggal baru.');
        }

        $booking->update([
            'tgl_mulai'            => $booking->proposed_tgl_mulai,
            'tgl_selesai'          => $booking->proposed_tgl_selesai,
            'proposed_tgl_mulai'   => null,
            'proposed_tgl_selesai' => null,
            'status_perubahan'     => Booking::CHANGE_APPROVED,
        ]);

        broadcast(new \App\Events\BookingStatusUpdated($booking));

        return back()->with('success', "Perubahan tanggal untuk Booking #{$booking->id} telah disetujui.");
    }

    // ============================================================
    // TAHAP 3 — Tolak Perubahan Tanggal dari User
    // ============================================================
    public function rejectDateChange(Request $request, Booking $booking)
    {
        if (!$booking->hasPendingDateChange()) {
            return back()->with('error', 'Tidak ada usulan perubahan tanggal yang aktif untuk booking ini.');
        }

        $request->validate([
            'catatan_admin' => 'required|string|max:500',
        ], [
            'catatan_admin.required' => 'Alasan penolakan perubahan tanggal wajib diisi.',
        ]);

        // Tanggal lama TETAP berlaku, hanya hapus proposal dan ubah status_perubahan
        $booking->update([
            'proposed_tgl_mulai'   => null,
            'proposed_tgl_selesai' => null,
            'status_perubahan'     => Booking::CHANGE_REJECTED,
            'catatan_admin'        => $request->catatan_admin,
        ]);

        broadcast(new \App\Events\BookingStatusUpdated($booking));

        return back()->with('success', "Usulan perubahan tanggal Booking #{$booking->id} ditolak. Tanggal lama tetap berlaku.");
    }

    /**
     * Memperbarui data satu peserta/panitia oleh Admin.
     */
    public function updateParticipant(Request $request, BookingParticipant $participant)
    {
        $validated = $request->validate([
            'nama'    => 'required|string|max:100',
            'nrp'     => 'required|string|max:50',
            'jabatan' => 'nullable|string|max:100',
            'site'    => 'nullable|string|max:100',
            'no_hp'   => 'nullable|string|max:20',
            'gender'  => 'required|in:L,P',
        ], [
            'nama.required'   => 'Nama lengkap wajib diisi.',
            'nrp.required'    => 'NRP wajib diisi (ketik N/A jika tidak ada).',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in'       => 'Jenis kelamin harus L atau P.',
        ]);

        $nrpUpper = strtoupper(trim($validated['nrp']));

        // Validasi duplikasi NRP: Jika NRP yang baru bukan "N/A" dan berubah,
        // pastikan tidak ada peserta lain di booking yang sama dengan NRP tersebut.
        if ($nrpUpper !== 'N/A') {
            $duplicateExists = BookingParticipant::where('booking_id', $participant->booking_id)
                ->where('id', '!=', $participant->id)
                ->whereRaw('UPPER(nrp) = ?', [$nrpUpper])
                ->exists();

            if ($duplicateExists) {
                return response()->json([
                    'success' => false,
                    'message' => "NRP '{$validated['nrp']}' sudah terdaftar pada peserta/panitia lain di acara ini.",
                ], 422);
            }
        }

        $participant->update([
            'nama'    => trim($validated['nama']),
            'nrp'     => $nrpUpper,
            'jabatan' => trim($validated['jabatan'] ?? ''),
            'site'    => trim($validated['site'] ?? ''),
            'no_hp'   => trim($validated['no_hp'] ?? ''),
            'gender'  => $validated['gender'],
        ]);

        // Catat log aktivitas admin mengedit peserta
        \App\Models\BookingLog::create([
            'booking_id' => $participant->booking_id,
            'user_id'    => auth()->id(),
            'action'     => 'Admin Edit Participant',
            'message'    => "Admin mengubah data {$participant->tipe} bernama '{$participant->nama}'.",
        ]);

        broadcast(new \App\Events\BookingStatusUpdated($participant->booking));

        $res = $this->showDetails($participant->booking);
        $data = $res->getData(true);
        $data['success'] = true;
        $data['message'] = 'Data peserta berhasil diperbarui oleh Admin.';
        return response()->json($data);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    /**
     * Format satu objek Booking menjadi array yang siap dikonsumsi frontend.
     */
    private function formatBooking(Booking $b): array
    {
        $today       = Carbon::today();
        $daysToStart = $b->tgl_mulai ? $today->diffInDays($b->tgl_mulai, false) : null;
        $prepDays    = \App\Models\Setting::where('key', 'preparation_alert_days')->value('value') ?? 14;

        return [
            'id'                      => $b->id,
            'nama_training'           => $b->nama_training,
            'pic'                     => $b->pic,
            'tgl_mulai'               => $b->tgl_mulai?->toDateString(),
            'tgl_selesai'             => $b->tgl_selesai?->toDateString(),
            'proposed_tgl_mulai'      => $b->proposed_tgl_mulai?->toDateString(),
            'proposed_tgl_selesai'    => $b->proposed_tgl_selesai?->toDateString(),
            'status_perubahan'        => $b->status_perubahan,
            'ruangan'                 => $b->displayRoomName(),
            'gabung_ruang'            => $b->gabung_ruang,
            'layout'                  => $b->layout_preferensi,
            'is_hybrid'               => $b->is_hybrid,
            'is_flipchart'            => $b->is_flipchart,
            'is_pena_mini_note'       => $b->is_pena_mini_note,
            'catatan_admin'           => $b->catatan_admin,
            'catatan_user'            => $b->catatan_user,
            'catatan_acc_terlambat'   => $b->catatan_acc_terlambat,
            'status'                  => $b->status,
            'pemohon'                 => $b->user?->name ?? '-',
            'divisi'                  => $b->user?->divisi ?? '-',
            'jumlah_peserta'          => BookingParticipant::where('booking_id', $b->id)->where('tipe', 'peserta')->count(),
            'jumlah_panitia'          => BookingParticipant::where('booking_id', $b->id)->where('tipe', 'panitia')->count(),
            'created_at'              => $b->created_at->format('d M Y, H:i'),
            'acc1_at'                 => $b->acc1_at?->format('d M Y, H:i'),
            'acc1_by'                 => $b->acc1_by,
            'acc2_at'                 => $b->acc2_at?->format('d M Y, H:i'),
            'acc2_by'                 => $b->acc2_by,
            'fase'                    => $b->fase,
            // Helpers UI
            'days_to_start'           => $daysToStart,
            'preparation_alert_days'  => (int) $prepDays,
            'is_overdue_acc2'         => $b->isConfirmed() && $b->tgl_mulai?->isPast(),
            'can_be_finalized'        => $b->canBeFinalized(),
            'has_pending_date_change' => $b->hasPendingDateChange(),
        ];
    }

    /**
     * Kirim notifikasi WA detail ke Frontdesk saat booking mencapai status Final.
     */
    private function notifyFrontdesk(Booking $booking)
    {
        $frontdeskPhone = config('services.fonnte.frontdesk_target');
        if (empty($frontdeskPhone)) return;

        $booking->loadMissing(['ruangan', 'participants']);
        $ruangName = $booking->displayRoomName();
        $tglMulai = \Carbon\Carbon::parse($booking->tgl_mulai)->translatedFormat('d M Y');
        $tglSelesai = \Carbon\Carbon::parse($booking->tgl_selesai)->translatedFormat('d M Y');
        $tglStr = $tglMulai === $tglSelesai ? $tglMulai : "{$tglMulai} s/d {$tglSelesai}";
        $jmlPeserta = $booking->participants->where('tipe', 'peserta')->count();
        $layout = ucfirst($booking->layout_preferensi);
        
        $kebutuhan = collect([
            $booking->is_hybrid ? 'Hybrid (Kamera & Mic)' : null,
            $booking->is_flipchart ? 'Papan Flipchart' : null,
            $booking->is_pena_mini_note ? 'Pena & Mini Note' : null
        ])->filter()->implode(', ');
        if (empty($kebutuhan)) {
            $kebutuhan = '-';
        }

        // Buat signed URL yang berlaku 7 hari (tanpa perlu login)
        $shareUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'booking.share',
            now()->addDays(7),
            ['booking' => $booking->id]
        );

        // Persingkat URL dengan TinyURL agar bisa langsung diklik di WhatsApp
        $shortUrl = $this->shortenUrl($shareUrl);

        $msg = "*INFORMASI TRAINING BARU (FINAL)*\n\nHalo Frontdesk,\nTerdapat jadwal training baru yang telah di-ACC Final dan perlu disiapkan:\n\n*Nama Training:* {$booking->nama_training}\n*Ruangan:* {$ruangName}\n*Tanggal:* {$tglStr}\n*PIC:* {$booking->pic}\n*Peserta:* {$jmlPeserta} Orang\n*Tata Letak:* {$layout}\n*Tambahan:* {$kebutuhan}\n\n📋 *Lihat Detail Peserta & Panitia:*\n{$shortUrl}\n\n_(Link berlaku 7 hari)_\n\nMohon segera dipersiapkan sesuai kebutuhan. Terima kasih!";

        \App\Jobs\SendWhatsAppNotification::dispatchSync($frontdeskPhone, $msg);
    }

    /**
     * Persingkat URL panjang menggunakan TinyURL API (gratis, tanpa API key).
     * Fallback ke URL asli jika TinyURL tidak bisa dihubungi.
     */
    private function shortenUrl(string $longUrl): string
    {
        try {
            // Menggunakan is.gd karena jauh lebih cepat dari TinyURL (hanya ~0.5 detik)
            // dan domainnya (.gd) otomatis berwarna biru / bisa diklik di WhatsApp.
            $response = \Illuminate\Support\Facades\Http::timeout(2)->get('https://is.gd/create.php?format=simple&url=' . urlencode($longUrl));
            if ($response->successful()) {
                return trim($response->body());
            }
        } catch (\Exception $e) {
            // Fallback jika API is.gd bermasalah
        }

        // Fallback ke Cache lokal jika semua gagal
        $key = \Illuminate\Support\Str::random(6);
        \Illuminate\Support\Facades\Cache::put('short_url_' . $key, $longUrl, now()->addDays(7));
        return url('/s/' . $key);
    }
}
