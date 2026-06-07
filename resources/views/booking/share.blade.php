<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Booking — {{ $booking->nama_training }}</title>
    <meta name="description" content="Detail peserta dan informasi booking training {{ $booking->nama_training }}">
    
    <!-- Google Fonts Preconnect & Font family -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (via Vite) -->
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 font-sans text-gray-800 antialiased min-h-screen flex flex-col selection:bg-blue-100 selection:text-blue-900">

<!-- Header -->
<div class="bg-white border-b border-gray-200 py-10 shadow-sm relative overflow-hidden">
    <!-- Subtle background gradient -->
    <div class="absolute inset-0 bg-gradient-to-b from-blue-50/50 to-transparent pointer-events-none"></div>
    
    <div class="max-w-6xl mx-auto px-6 text-center relative z-10">
        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white border border-blue-100 text-blue-700 text-[10px] font-extrabold uppercase tracking-widest mb-4 shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Detail Booking Training
        </div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight leading-snug">{{ $booking->nama_training }}</h1>
        <p class="text-sm font-semibold text-gray-500 mt-2">Enterprise Training Management System</p>
    </div>
</div>

<div class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 mt-8 mb-12">

    <div class="flex flex-col lg:flex-row gap-6 items-start">
        
        <!-- Main Content (Left) -->
        <div class="w-full lg:w-2/3 space-y-6">

            <!-- Informasi Booking -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-slate-50/80 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wide">Informasi Booking</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <div class="p-5 border-b md:border-b-0 md:border-r border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Ruangan</p>
                        <p class="text-sm font-bold text-gray-900">{{ $booking->ruangan?->nama_ruang ?? 'Ruang Gabungan' }}</p>
                    </div>
                    <div class="p-5 border-b border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Status</p>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-bold ring-1 ring-emerald-600/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Final (ACC)
                        </div>
                    </div>
                    <div class="p-5 border-b md:border-b-0 md:border-r border-gray-100 bg-gray-50/50">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Tanggal Mulai</p>
                        <p class="text-sm font-bold text-gray-900">{{ \Carbon\Carbon::parse($booking->tgl_mulai)->translatedFormat('d M Y') }}</p>
                    </div>
                    <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Tanggal Selesai</p>
                        <p class="text-sm font-bold text-gray-900">{{ \Carbon\Carbon::parse($booking->tgl_selesai)->translatedFormat('d M Y') }}</p>
                    </div>
                    <div class="p-5 border-b md:border-b-0 md:border-r border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">PIC (Penanggung Jawab)</p>
                        <p class="text-sm font-bold text-gray-900">{{ $booking->pic ?? '-' }}</p>
                    </div>
                    <div class="p-5 border-b border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">No. HP PIC</p>
                        <p class="text-sm font-bold text-blue-600 font-mono bg-blue-50 px-2 py-0.5 rounded border border-blue-100 inline-block">{{ $booking->no_hp_pic ?? '-' }}</p>
                    </div>
                    <div class="p-5 border-b md:border-b-0 md:border-r border-gray-100 bg-gray-50/50">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Pemohon</p>
                        <p class="text-sm font-bold text-gray-900">{{ $booking->user?->name ?? '-' }}</p>
                    </div>
                    <div class="p-5 bg-gray-50/50">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Divisi</p>
                        <p class="text-sm font-bold text-gray-900">{{ $booking->user?->divisi ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <!-- Tata Letak & Kebutuhan Tambahan -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-slate-50/80 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    </span>
                    <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wide">Tata Letak & Kebutuhan</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <div class="p-5 border-b md:border-b-0 md:border-r border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Layout Ruangan</p>
                        <p class="text-sm font-bold text-gray-900">{{ ucfirst($booking->layout_preferensi) }}</p>
                    </div>
                    <div class="p-5 border-b border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Kebutuhan Khusus</p>
                        <div class="flex flex-wrap gap-2">
                            @if($booking->is_hybrid)
                                <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-[10px] font-bold border border-blue-100 uppercase tracking-wide">🎥 Hybrid</span>
                            @endif
                            @if($booking->is_flipchart)
                                <span class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-[10px] font-bold border border-amber-100 uppercase tracking-wide">📋 Flipchart</span>
                            @endif
                            @if($booking->is_pena_mini_note)
                                <span class="px-2.5 py-1 rounded-lg bg-teal-50 text-teal-700 text-[10px] font-bold border border-teal-100 uppercase tracking-wide">📝 Pena & Note</span>
                            @endif
                            @if(!$booking->is_hybrid && !$booking->is_flipchart && !$booking->is_pena_mini_note)
                                <span class="text-sm text-gray-400 font-semibold">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="p-5 border-b md:border-b-0 md:border-r border-gray-100 bg-gray-50/50">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Peserta</p>
                        <p class="text-sm font-bold text-gray-900">{{ $peserta->count() }} <span class="text-xs text-gray-500 font-medium">Orang</span></p>
                    </div>
                    <div class="p-5 bg-gray-50/50">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Panitia</p>
                        <p class="text-sm font-bold text-gray-900">{{ $panitia->count() }} <span class="text-xs text-gray-500 font-medium">Orang</span></p>
                    </div>
                </div>
            </div>

            <!-- Daftar Peserta -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-slate-50/80 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full bg-pink-100 flex items-center justify-center text-pink-600 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </span>
                        <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wide">Daftar Personil</h3>
                    </div>
                    <span class="text-[10px] font-bold text-gray-500 bg-white shadow-sm border border-gray-200 px-3 py-1 rounded-lg">
                        {{ $peserta->count() + $panitia->count() }} TOTAL
                    </span>
                </div>

                <div class="overflow-x-auto">
                    @php
                        $all = $peserta->map(fn($p) => ['tipe' => 'peserta', 'data' => $p])
                            ->concat($panitia->map(fn($p) => ['tipe' => 'panitia', 'data' => $p]));
                    @endphp
                    @if($all->isEmpty())
                        <div class="text-center py-12 bg-gray-50/30">
                            <p class="text-sm font-semibold text-gray-400 italic">Belum ada data peserta yang ditambahkan.</p>
                        </div>
                    @else
                        <table class="w-full text-left border-collapse min-w-[700px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200">
                                    <th class="px-5 py-3.5 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider w-12">No</th>
                                    <th class="px-5 py-3.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-24">Tipe</th>
                                    <th class="px-5 py-3.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-5 py-3.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">NRP / NIK</th>
                                    <th class="px-5 py-3.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Jabatan</th>
                                    <th class="px-5 py-3.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Site</th>
                                    <th class="px-5 py-3.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">No. HP</th>
                                    <th class="px-5 py-3.5 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider w-16">JK</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($all as $i => $item)
                                    @php $p = $item['data']; @endphp
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-5 py-3 text-center text-[11px] font-bold text-gray-400">{{ $i + 1 }}</td>
                                        <td class="px-5 py-3">
                                            @if($item['tipe'] === 'peserta')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100">Peserta</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">Panitia</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-xs font-bold text-gray-800">{{ $p->nama ?: '-' }}</td>
                                        <td class="px-5 py-3 text-[11px] font-mono font-medium text-gray-600">{{ $p->nrp ?: '-' }}</td>
                                        <td class="px-5 py-3 text-xs font-medium text-gray-600">{{ $p->jabatan ?: '-' }}</td>
                                        <td class="px-5 py-3 text-xs font-medium text-gray-600">{{ $p->site ?: '-' }}</td>
                                        <td class="px-5 py-3 text-[11px] font-mono font-medium text-gray-600">{{ $p->no_hp ?: '-' }}</td>
                                        <td class="px-5 py-3 text-center">
                                            <span class="text-[11px] font-extrabold {{ $p->gender === 'L' ? 'text-blue-600' : 'text-pink-600' }}">
                                                {{ $p->gender ?: '-' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50/80 border-t-2 border-gray-200">
                                    <td colspan="2" class="px-5 py-4 text-center text-xs font-extrabold text-gray-700">TOTAL</td>
                                    <td colspan="6" class="px-5 py-4 text-[13px] font-bold text-gray-800">
                                        {{ $all->count() }} Orang 
                                        <span class="text-[11px] text-gray-500 font-medium ml-1">({{ $peserta->count() }} Peserta + {{ $panitia->count() }} Panitia)</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

        </div>

        <!-- Sidebar (Right) -->
        <div class="w-full lg:w-1/3 space-y-6 lg:sticky lg:top-6">
            
            <!-- Download Button -->
            <a href="{{ $downloadUrl }}" class="group w-full relative flex items-center justify-center gap-2 px-6 py-4 font-bold text-white bg-emerald-600 rounded-2xl shadow-[0_8px_30px_rgba(5,150,105,0.2)] hover:bg-emerald-700 hover:shadow-[0_8px_30px_rgba(5,150,105,0.35)] hover:-translate-y-0.5 transition-all duration-300 overflow-hidden ring-1 ring-emerald-700/50">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-out"></div>
                <svg class="w-5 h-5 relative z-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span class="relative z-10 text-[15px]">Download Excel</span>
            </a>

            <!-- Calendar Card -->
            @php
                $start = \Carbon\Carbon::parse($booking->tgl_mulai);
                $end = \Carbon\Carbon::parse($booking->tgl_selesai);
                
                $month = $start->month;
                $year = $start->year;
                
                $firstDayOfMonth = \Carbon\Carbon::create($year, $month, 1);
                $daysInMonth = $firstDayOfMonth->daysInMonth;
                $startDayOfWeek = $firstDayOfMonth->dayOfWeek; // 0 for Sunday
                $monthName = $firstDayOfMonth->translatedFormat('F Y');
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-slate-50/80 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </span>
                    <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wide">Jadwal Training</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-center mb-5">
                        <span class="text-[15px] font-extrabold text-gray-800 tracking-tight">{{ $monthName }}</span>
                    </div>
                    <div class="grid grid-cols-7 gap-y-3 gap-x-1 mb-2">
                        <!-- Header hari -->
                        @foreach(['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $hari)
                            <div class="text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ $hari }}</div>
                        @endforeach
                        
                        <!-- Grid sel -->
                        @for($i = 0; $i < $startDayOfWeek; $i++)
                            <div></div>
                        @endfor
                        
                        @for($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $currentDate = \Carbon\Carbon::create($year, $month, $day)->startOfDay();
                                $isHighlighted = $currentDate->betweenIncluded($start->copy()->startOfDay(), $end->copy()->startOfDay());
                            @endphp
                            <div class="flex justify-center items-center">
                                <div class="w-8 h-8 flex items-center justify-center rounded-full text-xs font-bold transition-colors {{ $isHighlighted ? 'bg-blue-600 text-white shadow-[0_4px_12px_rgba(37,99,235,0.35)]' : 'text-gray-700 hover:bg-gray-100' }}">
                                    {{ $day }}
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-3 h-3 rounded bg-blue-600 shadow-sm shadow-blue-600/30"></div>
                        <span class="text-xs font-bold text-gray-600 uppercase tracking-wide">Tanggal Pelaksanaan</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Footer -->
<footer class="mt-auto border-t border-gray-200 bg-white py-4 px-4 md:px-6 w-full shrink-0">
    <div class="flex flex-col md:flex-row items-center justify-between gap-3 text-center md:text-left">
        <div class="text-[11px] text-gray-500 font-medium">
            &copy; {{ date('Y') }} &nbsp; <span class="font-bold text-gray-800">PAMA BANJARBARU SUPPORT OFFICE (BBSO)</span>. All rights reserved.
        </div>
        <div class="flex flex-wrap justify-center items-center gap-x-4 gap-y-2 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
            <span class="hover:text-blue-600 cursor-pointer transition-colors">Bantuan</span>
            <span class="text-gray-300">•</span>
            <span class="hover:text-blue-600 cursor-pointer transition-colors">Kebijakan Privasi</span>
            <span class="text-gray-300">•</span>
            <span class="text-gray-500">v1.0.0</span>
        </div>
    </div>
</footer>

</body>
</html>
