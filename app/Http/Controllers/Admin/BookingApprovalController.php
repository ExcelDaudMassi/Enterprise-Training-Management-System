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
    /**
     * Tampilkan daftar semua booking yang perlu diproses admin.
     * Menerima query param: ?filter=waiting_confirmation|confirmed|cancelled|urgent|overdue|all
     */
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $today  = Carbon::today();

        $query = Booking::with(['user', 'ruangan', 'participants']);

        // Terapkan filter dari query param (dikirim dari dashboard cards)
        match ($filter) {
            'waiting_confirmation' => $query->where('status', 'waiting_confirmation'),
            'confirmed'            => $query->where('status', 'confirmed'),
            'final_confirmed'      => $query->where('status', 'final_confirmed'),
            'cancelled'            => $query->where('status', 'cancelled'),
            'urgent'               => $query->where('status', 'waiting_confirmation')
                                            ->where('tgl_mulai', '<=', $today->copy()->addDays(14))
                                            ->where('tgl_mulai', '>=', $today),
            'overdue'              => $query->where('status', 'waiting_confirmation')
                                            ->where('tgl_mulai', '<', $today),
            default                => null, // 'all' — tanpa filter
        };

        // Pengurutan: jika melihat tab antrean (menunggu, urgent, overdue), urutkan dari yang TERLAMA (asc).
        // Jika melihat tab lain (semua, disetujui, ditolak), urutkan dari yang TERBARU (desc).
        if (in_array($filter, ['waiting_confirmation', 'urgent', 'overdue'])) {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $bookings = $query
            ->orderByRaw("CASE status 
                WHEN 'waiting_confirmation' THEN 1 
                WHEN 'confirmed' THEN 2 
                WHEN 'cancelled' THEN 3 
                WHEN 'plotting' THEN 4 
                ELSE 5 END")
            ->get()
            ->map(function (Booking $b) {
                return [
                    'id'              => $b->id,
                    'nama_training'   => $b->nama_training,
                    'pic'             => $b->pic,
                    'tgl_mulai'       => $b->tgl_mulai?->toDateString(),
                    'tgl_selesai'     => $b->tgl_selesai?->toDateString(),
                    'ruangan'         => $b->ruangan?->nama_ruang ?? 'Ruang Gabungan',
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
                    'jumlah_peserta'  => $b->participants->where('tipe', 'peserta')->count(),
                    'jumlah_panitia'  => $b->participants->where('tipe', 'panitia')->count(),
                    'created_at'      => $b->created_at->format('d M Y, H:i'),
                ];
            });

        return Inertia::render('Admin/BookingApproval', [
            'auth'         => ['user' => ['name' => Auth::user()->name, 'role' => Auth::user()->role]],
            'bookings'     => $bookings,
            'activeFilter' => $filter,
        ]);
    }

    public function recap(Request $request)
    {
        $query = Booking::with(['user', 'ruangan']);
        $today = Carbon::today();

        $filter = $request->query('filter', 'all');

        match ($filter) {
            'waiting_confirmation' => $query->where('status', 'waiting_confirmation'),
            'confirmed'            => $query->where('status', 'confirmed'),
            'final_confirmed'      => $query->where('status', 'final_confirmed'),
            'cancelled'            => $query->where('status', 'cancelled'),
            'urgent'               => $query->where('status', 'waiting_confirmation')
                                            ->where('tgl_mulai', '<=', $today->copy()->addDays(14))
                                            ->where('tgl_mulai', '>=', $today),
            'overdue'              => $query->where('status', 'waiting_confirmation')
                                            ->where('tgl_mulai', '<', $today),
            default                => null,
        };

        if (in_array($filter, ['waiting_confirmation', 'urgent', 'overdue'])) {
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
                'ruangan'         => $b->ruangan?->nama_ruang ?? 'Ruang Gabungan',
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
            'auth'     => ['user' => ['name' => Auth::user()->name, 'email' => Auth::user()->email, 'role' => Auth::user()->role]],
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
                'gender'  => $p->gender,
            ]);

        $panitia = $booking->participants
            ->where('tipe', 'panitia')
            ->values()
            ->map(fn($p) => [
                'nama'    => $p->nama,
                'jabatan' => $p->jabatan,
                'site'    => $p->site,
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
                'gabung_ruang'      => $booking->gabung_ruang,
                'layout_preferensi' => $booking->layout_preferensi,
                'layout_url'        => $layoutUrl,
                'is_hybrid'         => $booking->is_hybrid,
                'is_flipchart'      => $booking->is_flipchart,
                'catatan_admin'     => $booking->catatan_admin,
                'created_at'        => $booking->created_at->format('d M Y, H:i'),
                // Ruangan
                'ruangan'           => $booking->ruangan ? [
                    'nama_ruang'    => $booking->ruangan->nama_ruang,
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
     * Ekspor seluruh data booking ke file Excel (.xlsx).
     * Mendukung filter berdasarkan status dan bulan/tahun.
     */
    public function export(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $today  = Carbon::today();

        $query = Booking::with(['user', 'ruangan', 'participants']);

        match ($filter) {
            'waiting_confirmation' => $query->where('status', 'waiting_confirmation'),
            'confirmed'            => $query->where('status', 'confirmed'),
            'cancelled'            => $query->where('status', 'cancelled'),
            'urgent'               => $query->where('status', 'waiting_confirmation')
                                             ->where('tgl_mulai', '<=', $today->copy()->addDays(14))
                                             ->where('tgl_mulai', '>=', $today),
            'overdue'              => $query->where('status', 'waiting_confirmation')
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
        $sheet->setCellValue('A1', 'REKAP DATA BOOKING RUANGAN - ENTERPRISE TRAINING MANAGEMENT SYSTEM');
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
                $b->gabung_ruang ? 'Gabung Ruang' : null,
                $b->layout_preferensi ? ('Layout: ' . ucfirst($b->layout_preferensi)) : null,
            ])->filter()->implode(', ');

            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $b->nama_training);
            $sheet->setCellValue('C' . $row, $b->user?->name ?? '-');
            $sheet->setCellValue('D' . $row, $b->user?->divisi ?? '-');
            $sheet->setCellValue('E' . $row, $b->pic ?? '-');
            $sheet->setCellValue('F' . $row, $b->ruangan?->nama_ruang ?? 'Ruang Gabungan');
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

                // Cek konflik: apakah ada booking CONFIRMED lain yang bertabrakan tanggal dan ruangan
                $overlappingConfirmed = Booking::where('status', 'confirmed')
                    ->where('id', '!=', $booking->id)
                    ->where('tgl_mulai', '<=', $booking->tgl_selesai->toDateString())
                    ->where('tgl_selesai', '>=', $booking->tgl_mulai->toDateString())
                    ->with('ruangan')
                    ->get();

                $hasConflict = false;
                foreach ($overlappingConfirmed as $cb) {
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
            });

            return back()->with('success', "Booking #{$booking->id} berhasil disetujui.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Tolak booking.
     */
    public function reject(Request $request, Booking $booking)
    {
        $request->validate([
            'catatan_admin' => 'required|string|max:500',
        ], [
            'catatan_admin.required' => 'Alasan penolakan wajib diisi.',
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking, $request) {
                $booking->update([
                    'status'        => 'cancelled',
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
                // Ubah status menjadi final_confirmed
                $booking->update([
                    'status'  => 'final_confirmed',
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
            });

            return back()->with('success', "Booking #{$booking->id} berhasil di-ACC tahap 2 (Final Confirmation).");
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}
