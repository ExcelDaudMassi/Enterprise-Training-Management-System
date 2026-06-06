<?php

namespace App\Services;

use App\Models\Booking;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BookingExcelExportService
{
    /**
     * Generate file Excel detail booking dan simpan ke storage/app/public sementara.
     * Mengembalikan path file yang dibuat.
     */
    public function generate(Booking $booking): string
    {
        $booking->load(['ruangan', 'user', 'participants']);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Detail Peserta');

        // ── Judul ──
        $sheet->setCellValue('A1', 'DETAIL PESERTA & PANITIA - ' . strtoupper($booking->nama_training));
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ── Info Booking ──
        $infos = [
            ['Ruangan',     $booking->ruangan?->nama_ruang ?? 'Ruang Gabungan'],
            ['Tanggal',     ($booking->tgl_mulai?->format('d M Y') ?? '-') . ' s/d ' . ($booking->tgl_selesai?->format('d M Y') ?? '-')],
            ['PIC',         $booking->pic ?? '-'],
            ['No. HP PIC',  $booking->no_hp_pic ?? '-'],
            ['Pemohon',     $booking->user?->name . ' (' . ($booking->user?->divisi ?? '-') . ')'],
            ['Diekspor',    now()->format('d M Y, H:i')],
        ];
        $infoRow = 2;
        foreach ($infos as [$label, $value]) {
            $sheet->setCellValue('A' . $infoRow, $label . ':');
            $sheet->mergeCells('A' . $infoRow . ':B' . $infoRow);
            $sheet->setCellValue('C' . $infoRow, $value);
            $sheet->mergeCells('C' . $infoRow . ':H' . $infoRow);
            $sheet->getStyle('A' . $infoRow)->getFont()->setBold(true);
            $sheet->getStyle('A' . $infoRow . ':H' . $infoRow)->getFont()->setSize(9);
            $sheet->getStyle('A' . $infoRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('C' . $infoRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $infoRow++;
        }

        // ── Header Kolom ──
        $headerRow = $infoRow + 1;
        $headers = [
            'A' => 'No.', 'B' => 'Tipe', 'C' => 'Nama Lengkap',
            'D' => 'NRP', 'E' => 'Jabatan', 'F' => 'Site', 'G' => 'No HP', 'H' => 'Gender',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . $headerRow, $label);
        }
        $sheet->getStyle('A' . $headerRow . ':H' . $headerRow)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        // ── Lebar Kolom ──
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(10);

        // ── Baris Data ──
        $all = collect();
        foreach ($booking->participants->where('tipe', 'peserta') as $p) {
            $all->push(['tipe' => 'Peserta', 'data' => $p]);
        }
        foreach ($booking->participants->where('tipe', 'panitia') as $p) {
            $all->push(['tipe' => 'Panitia', 'data' => $p]);
        }

        $dataRow = $headerRow + 1;
        foreach ($all as $i => $item) {
            $p    = $item['data'];
            $tipe = $item['tipe'];

            $sheet->setCellValue('A' . $dataRow, $i + 1);
            $sheet->setCellValue('B' . $dataRow, $tipe);
            $sheet->setCellValue('C' . $dataRow, $p->nama ?: 'N/A');
            $sheet->setCellValue('D' . $dataRow, $p->nrp ?: 'N/A');
            $sheet->setCellValue('E' . $dataRow, $p->jabatan ?: 'N/A');
            $sheet->setCellValue('F' . $dataRow, $p->site ?: 'N/A');
            $sheet->setCellValue('G' . $dataRow, $p->no_hp ?: 'N/A');
            $sheet->setCellValue('H' . $dataRow, $p->gender ?: 'N/A');

            if ($dataRow % 2 === 0) {
                $sheet->getStyle('A' . $dataRow . ':H' . $dataRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F9FF']],
                ]);
            }

            if ($tipe === 'Panitia') {
                $sheet->getStyle('B' . $dataRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF3C7']],
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFD97706']],
                ]);
            } else {
                $sheet->getStyle('B' . $dataRow)->applyFromArray([
                    'font' => ['color' => ['argb' => 'FF1D4ED8']],
                ]);
            }

            $sheet->getStyle('A' . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $dataRow++;
        }

        // ── Row Total ──
        $sheet->setCellValue('A' . $dataRow, 'TOTAL');
        $sheet->setCellValue('C' . $dataRow, $all->count() . ' orang');
        $sheet->getStyle('A' . $dataRow . ':H' . $dataRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE2E8F0']],
        ]);

        // ── Border ──
        if ($dataRow > $headerRow + 1) {
            $sheet->getStyle('A' . $headerRow . ':H' . ($dataRow))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color'       => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ]);
        }

        // ── Seksi Tata Letak & Kebutuhan Tambahan ──
        $layoutStartRow = $dataRow + 3;
        $sheet->setCellValue('A' . $layoutStartRow, 'INFORMASI TATA LETAK & KEBUTUHAN TAMBAHAN');
        $sheet->mergeCells('A' . $layoutStartRow . ':H' . $layoutStartRow);
        $sheet->getStyle('A' . $layoutStartRow)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($layoutStartRow)->setRowHeight(24);

        $layoutInfos = [
            ['Tata Letak (Layout)',  ucfirst($booking->layout_preferensi)],
            ['Kebutuhan Hybrid',     $booking->is_hybrid ? 'AKTIF (Kamera & Mic)' : 'TIDAK AKTIF'],
            ['Papan Tulis Flipchart', $booking->is_flipchart ? 'AKTIF' : 'TIDAK AKTIF'],
            ['Pena & Mini Note', $booking->is_pena_mini_note ? 'AKTIF' : 'TIDAK AKTIF'],
            ['Catatan Tambahan',     $booking->catatan_admin ?: '-'],
        ];

        $subInfoRow = $layoutStartRow + 2;
        foreach ($layoutInfos as [$label, $value]) {
            $sheet->setCellValue('A' . $subInfoRow, $label . ':');
            $sheet->mergeCells('A' . $subInfoRow . ':B' . $subInfoRow);
            $sheet->setCellValue('C' . $subInfoRow, $value);
            $sheet->mergeCells('C' . $subInfoRow . ':H' . $subInfoRow);
            $sheet->getStyle('A' . $subInfoRow)->getFont()->setBold(true);
            $sheet->getStyle('A' . $subInfoRow . ':H' . $subInfoRow)->getFont()->setSize(9);
            $sheet->getStyle('A' . $subInfoRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('C' . $subInfoRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $subInfoRow++;
        }

        // ── Gambar Denah ──
        $imgRow = $subInfoRow + 1;
        $sheet->setCellValue('A' . ($imgRow - 1), 'Visual Denah / Tata Letak Ruangan:');
        $sheet->mergeCells('A' . ($imgRow - 1) . ':H' . ($imgRow - 1));
        $sheet->getStyle('A' . ($imgRow - 1))->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle('A' . ($imgRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $imagePath = null;
        $isPdf     = false;
        if ($booking->layout_preferensi === 'custom') {
            if ($booking->layout_custom_path) {
                $fullPath = storage_path('app/public/' . $booking->layout_custom_path);
                if (file_exists($fullPath)) {
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    $imagePath = in_array($ext, ['png', 'jpg', 'jpeg']) ? $fullPath : null;
                    $isPdf     = !$imagePath;
                }
            }
        } else {
            $stdPath = public_path('layouts/' . $booking->layout_preferensi . '.png');
            if (file_exists($stdPath)) {
                $imagePath = $stdPath;
            }
        }

        if ($imagePath) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Denah Ruangan')->setDescription('Denah Ruangan')
                    ->setPath($imagePath)->setHeight(160)
                    ->setCoordinates('C' . $imgRow)->setOffsetX(10)->setOffsetY(10)
                    ->setWorksheet($sheet);
            $sheet->getRowDimension($imgRow)->setRowHeight(180);
        } elseif ($isPdf) {
            $sheet->setCellValue('C' . $imgRow, 'Denah kustom diunggah dalam format PDF.');
            $sheet->mergeCells('C' . $imgRow . ':H' . $imgRow);
            $sheet->getStyle('C' . $imgRow)->getFont()->setItalic(true)->setSize(9);
        } else {
            $sheet->setCellValue('C' . $imgRow, 'Tidak ada denah visual tersedia.');
            $sheet->mergeCells('C' . $imgRow . ':H' . $imgRow);
            $sheet->getStyle('C' . $imgRow)->getFont()->setItalic(true)->setSize(9);
        }

        // ── Buat nama file & simpan ──
        $namaClean   = trim(preg_replace('/-+/', '-', preg_replace('/[^A-Za-z0-9\-]/', '-', $booking->nama_training)), '-');
        $pemohonClean = trim(preg_replace('/-+/', '-', preg_replace('/[^A-Za-z0-9\-]/', '-', $booking->user?->name ?? 'Pemohon')), '-');
        $tglFormat   = $booking->tgl_mulai->format('Ymd') . '-' . $booking->tgl_selesai->format('Ymd');
        $filename    = $namaClean . '-' . $pemohonClean . '-' . $tglFormat . '.xlsx';

        $path = storage_path('app/public/temp_excel_' . uniqid() . '_' . $filename);
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
