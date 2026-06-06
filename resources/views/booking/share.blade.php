<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Booking — {{ $booking->nama_training }}</title>
    <meta name="description" content="Detail peserta dan informasi booking training {{ $booking->nama_training }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
        }

        /* ── Header ─────────────────────────────── */
        .header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
            color: white;
            padding: 28px 20px 24px;
            text-align: center;
        }
        .header-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: clamp(18px, 4vw, 26px);
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 4px;
        }
        .header p {
            font-size: 13px;
            opacity: 0.8;
        }

        /* ── Wrapper ─────────────────────────────── */
        .wrapper {
            max-width: 860px;
            margin: 0 auto;
            padding: 20px 16px 48px;
        }

        /* ── Info Card ───────────────────────────── */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.04);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 20px;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header .icon { font-size: 16px; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        @media (max-width: 500px) {
            .info-grid { grid-template-columns: 1fr; }
        }
        .info-item {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-item:nth-child(even) { border-left: 1px solid #f1f5f9; }
        .info-label {
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        /* Status badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-final { background: #dcfce7; color: #16a34a; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* Chips */
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .chip-blue  { background: #dbeafe; color: #1d4ed8; }
        .chip-amber { background: #fef3c7; color: #d97706; }

        /* ── Table ───────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        thead th {
            background: #2563eb;
            color: white;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 11px 14px;
            text-align: left;
            white-space: nowrap;
        }
        thead th:first-child { border-radius: 0; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #374151;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #eff6ff; }

        .tipe-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }
        .tipe-peserta { background: #dbeafe; color: #1d4ed8; }
        .tipe-panitia { background: #fef3c7; color: #d97706; }

        .no-data {
            text-align: center;
            padding: 32px;
            color: #94a3b8;
            font-style: italic;
        }

        /* Summary row */
        .summary-row td {
            background: #f1f5f9 !important;
            font-weight: 700;
            color: #1e293b;
        }

        /* ── Footer ──────────────────────────────── */
        .footer {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            margin-top: 28px;
        }
        .footer strong { color: #64748b; }
        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 16px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .download-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(22, 163, 74, 0.4);
            color: white;
        }
        .download-btn:active { transform: translateY(0); }
        @media print { .download-btn { display: none !important; } }
    </style>
</head>
<body>

<div class="header">
    <div class="header-badge">📋 Detail Booking Training</div>
    <h1>{{ $booking->nama_training }}</h1>
    <p>Enterprise Training Management System</p>
</div>

<div class="wrapper">

    <div style="text-align:center; margin-bottom: 20px;">
        <a href="{{ $downloadUrl }}" class="download-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Unduh Excel Peserta & Panitia
        </a>
    </div>

    {{-- ── Info Utama ── --}}
    <div class="card">
        <div class="card-header">
            <span class="icon">📌</span> Informasi Booking
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Ruangan</div>
                <div class="info-value">{{ $booking->ruangan?->nama_ruang ?? 'Ruang Gabungan' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="badge badge-final"><span class="badge-dot"></span>Final (ACC)</span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Tanggal Mulai</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($booking->tgl_mulai)->translatedFormat('d F Y') }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Tanggal Selesai</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($booking->tgl_selesai)->translatedFormat('d F Y') }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">PIC</div>
                <div class="info-value">{{ $booking->pic ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">No. HP PIC</div>
                <div class="info-value">{{ $booking->no_hp_pic ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Pemohon</div>
                <div class="info-value">{{ $booking->user?->name ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Divisi</div>
                <div class="info-value">{{ $booking->user?->divisi ?? '-' }}</div>
            </div>
        </div>
    </div>

    {{-- ── Tata Letak & Kebutuhan ── --}}
    <div class="card">
        <div class="card-header">
            <span class="icon">🪑</span> Tata Letak & Kebutuhan Tambahan
        </div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Layout Ruangan</div>
                <div class="info-value">{{ ucfirst($booking->layout_preferensi) }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Kebutuhan Khusus</div>
                <div class="info-value">
                    <div class="chips">
                        @if($booking->is_hybrid)
                            <span class="chip chip-blue">🎥 Hybrid (Kamera & Mic)</span>
                        @endif
                        @if($booking->is_flipchart)
                            <span class="chip chip-amber">📋 Papan Flipchart</span>
                        @endif
                        @if($booking->is_pena_mini_note)
                            <span class="chip" style="background-color: #f0fdfa; color: #0f766e; border-color: #ccfbf1;">📝 Pena & Mini Note</span>
                        @endif
                        @if(!$booking->is_hybrid && !$booking->is_flipchart && !$booking->is_pena_mini_note)
                            <span class="text-muted" style="font-size: 13px;">—</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Peserta</div>
                <div class="info-value">{{ $peserta->count() }} Orang</div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Panitia</div>
                <div class="info-value">{{ $panitia->count() }} Orang</div>
            </div>
        </div>
    </div>

    {{-- ── Daftar Peserta ── --}}
    <div class="card">
        <div class="card-header">
            <span class="icon">👥</span> Daftar Peserta & Panitia
            <span style="margin-left:auto; font-size:12px; font-weight:500; color:#94a3b8;">
                {{ $peserta->count() + $panitia->count() }} Total
            </span>
        </div>
        <div class="table-wrap">
            @php
                $all = $peserta->map(fn($p) => ['tipe' => 'peserta', 'data' => $p])
                    ->concat($panitia->map(fn($p) => ['tipe' => 'panitia', 'data' => $p]));
            @endphp
            @if($all->isEmpty())
                <div class="no-data">Belum ada data peserta yang ditambahkan.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px">No</th>
                            <th>Tipe</th>
                            <th>Nama Lengkap</th>
                            <th>NRP</th>
                            <th>Jabatan</th>
                            <th>Site</th>
                            <th>No. HP</th>
                            <th style="width:60px">Gender</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($all as $i => $item)
                            @php $p = $item['data']; @endphp
                            <tr>
                                <td style="text-align:center; color:#94a3b8; font-weight:600;">{{ $i + 1 }}</td>
                                <td>
                                    <span class="tipe-badge {{ $item['tipe'] === 'peserta' ? 'tipe-peserta' : 'tipe-panitia' }}">
                                        {{ ucfirst($item['tipe']) }}
                                    </span>
                                </td>
                                <td style="font-weight:600;">{{ $p->nama ?: 'N/A' }}</td>
                                <td style="font-family:monospace; font-size:12px;">{{ $p->nrp ?: 'N/A' }}</td>
                                <td>{{ $p->jabatan ?: '-' }}</td>
                                <td>{{ $p->site ?: '-' }}</td>
                                <td>{{ $p->no_hp ?: '-' }}</td>
                                <td style="text-align:center; font-weight:600;">{{ $p->gender ?: '-' }}</td>
                            </tr>
                        @endforeach
                        <tr class="summary-row">
                            <td colspan="2" style="text-align:center;">TOTAL</td>
                            <td colspan="6">{{ $all->count() }} Orang ({{ $peserta->count() }} Peserta + {{ $panitia->count() }} Panitia)</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="footer">
        <p>Halaman ini dibuat otomatis oleh sistem saat booking di-ACC Final.</p>
        <p style="margin-top:4px;">Digenerate pada: <strong>{{ now()->translatedFormat('d F Y, H:i') }} WIB</strong></p>
    </div>
</div>

</body>
</html>
