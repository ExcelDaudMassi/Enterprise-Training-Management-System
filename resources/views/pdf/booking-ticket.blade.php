<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tiket Booking {{ $booking->nama_training }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0 0 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .info-table th { background-color: transparent; border: none; padding: 4px; width: 30%; }
        .info-table td { border: none; padding: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TIKET / BUKTI BOOKING RUANGAN</h1>
        <p>Enterprise Training Management System</p>
    </div>

    <h3>A. Informasi Acara</h3>
    <table class="info-table">
        <tr>
            <th>ID Booking</th>
            <td>: #{{ str_pad($booking->id, 5, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <th>Nama Acara</th>
            <td>: {{ $booking->nama_training }}</td>
        </tr>
        <tr>
            <th>Penanggung Jawab (PIC)</th>
            <td>: {{ $booking->pic }}</td>
        </tr>
        <tr>
            <th>Ruangan</th>
            <td>: {{ $booking->ruangan ? $booking->ruangan->nama_ruang : 'Ruang Gabungan' }}</td>
        </tr>
        <tr>
            <th>Tanggal Pelaksanaan</th>
            <td>: {{ \Carbon\Carbon::parse($booking->tgl_mulai)->format('d F Y') }} s/d {{ \Carbon\Carbon::parse($booking->tgl_selesai)->format('d F Y') }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>: {{ strtoupper($booking->status) }}</td>
        </tr>
    </table>

    <h3>B. Daftar Peserta & Panitia</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Lengkap</th>
                <th>Jabatan</th>
                <th>Site</th>
                <th>No HP</th>
                <th>Gender</th>
            </tr>
        </thead>
        <tbody>
            @forelse($booking->participants as $index => $p)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $p->nama }}</td>
                <td>{{ $p->jabatan ?? '-' }}</td>
                <td>{{ $p->site ?? '-' }}</td>
                <td>{{ $p->no_hp ?? '-' }}</td>
                <td>{{ $p->gender ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center;">Tidak ada data peserta</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
