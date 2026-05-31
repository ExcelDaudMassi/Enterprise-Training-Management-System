<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

import { router } from '@inertiajs/vue3'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps({
    auth:          Object,
    stats:         Object,
    bookingWindow: Object,
    notifications: Array,
    ruanganList:   Array,
    bookings:      Array,
    selectedYear:  Number,
    selectedRuangan: [Number, null],
})

// ============================================================
// Warna per ruangan (index-based, statis) — Palette baru yang tidak bentrok dengan warna status
// ============================================================
const ROOM_COLORS = [
    { bg: '#2563eb', light: '#dbeafe', text: '#1e3a8a' }, // Blue    - Ruang 1
    { bg: '#7c3aed', light: '#ede9fe', text: '#3b0764' }, // Violet  - Ruang 2
    { bg: '#e11d48', light: '#ffe4e6', text: '#881337' }, // Rose    - Ruang 3
    { bg: '#d97706', light: '#fef3c7', text: '#78350f' }, // Amber   - Ruang 4
    { bg: '#059669', light: '#d1fae5', text: '#064e3b' }, // Emerald - Ruang 5
    { bg: '#0284c7', light: '#e0f2fe', text: '#0c4a6e' }, // Sky     - Ruang 6
    { bg: '#ea580c', light: '#ffedd5', text: '#7c2d12' }, // Orange  - Ruang 7
]

function getRoomColor(ruanganId) {
    const idx = props.ruanganList.findIndex(r => r.id === ruanganId)
    return ROOM_COLORS[idx % ROOM_COLORS.length] ?? ROOM_COLORS[0]
}

// ============================================================
// Warna per status booking untuk Kalender & Gantt Chart
// ============================================================
const STATUS_COLORS = {
    plotting: {
        bg: '#ef4444',      // Red 500 (H - 14)
        light: '#fee2e2',   // Red 50
        text: '#b91c1c',    // Red 700
        border: '#fca5a5',  // Red 300
    },
    waiting_confirmation: {
        bg: '#f59e0b',      // Amber 500 (Pending)
        light: '#fffbeb',   // Amber 50
        text: '#b45309',    // Amber 700
        border: '#fcd34d',  // Amber 300
    },
    confirmed: {
        bg: '#10b981',      // Emerald 500 (Disetujui)
        light: '#ecfdf5',   // Emerald 50
        text: '#047857',    // Emerald 700
        border: '#6ee7b7',  // Emerald 300
    },
    cancelled: {
        bg: '#9ca3af',      // Gray 500 (Dibatalkan)
        light: '#f3f4f6',   // Gray 50
        text: '#4b5563',    // Gray 700
        border: '#e5e7eb',  // Gray 300
    }
}

function getStatusColor(status) {
    return STATUS_COLORS[status] ?? STATUS_COLORS.plotting
}

// ============================================================
// Filter state
// ============================================================
const filterYear = ref(props.selectedYear)
const filterRuangan = ref(props.selectedRuangan)
const isFilterOpen = ref(false)
const isYearOpen = ref(false)
const filterRef = ref(null)
const yearRef = ref(null)

function handleFilterClickOutside(e) {
    if (filterRef.value && !filterRef.value.contains(e.target)) {
        isFilterOpen.value = false
    }
    if (yearRef.value && !yearRef.value.contains(e.target)) {
        isYearOpen.value = false
    }
}

onMounted(() => document.addEventListener('click', handleFilterClickOutside))
onUnmounted(() => document.removeEventListener('click', handleFilterClickOutside))

const YEAR_OPTIONS = [2024, 2025, 2026, 2027, 2028]

watch(() => props.selectedYear, (newYear) => {
    filterYear.value = newYear
})

watch(() => props.selectedRuangan, (newRuangan) => {
    filterRuangan.value = newRuangan
})

const selectedRuanganLabel = computed(() => {
    if (!filterRuangan.value) return 'Semua Ruangan'
    const room = props.ruanganList?.find(r => r.id === filterRuangan.value)
    return room?.nama_ruang ?? 'Semua Ruangan'
})

const selectedRuanganColor = computed(() => {
    if (!filterRuangan.value) return null
    const idx = props.ruanganList?.findIndex(r => r.id === filterRuangan.value) ?? -1
    return idx >= 0 ? ROOM_COLORS[idx % ROOM_COLORS.length].bg : null
})

const hasActiveFilter = computed(() => {
    return filterYear.value !== new Date().getFullYear() || filterRuangan.value !== null
})

function applyFilter() {
    isFilterOpen.value = false
    router.get('/admin/dashboard', {
        year: filterYear.value,
        ruangan_id: filterRuangan.value || undefined,
    }, { 
        preserveState: true,
        preserveScroll: true,
    })
}

function resetFilter() {
    filterYear.value = new Date().getFullYear()
    filterRuangan.value = null
    applyFilter()
}

// ============================================================
// Kalender: 12 bulan dalam 1 halaman
// ============================================================
const MONTH_NAMES = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
const DAY_NAMES   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab']
const todayDate   = new Date()

// Fungsi: ambil semua hari dalam satu bulan sebagai array grid (7 kolom)
function getMonthGrid(year, monthIndex) {
    const firstDay = new Date(year, monthIndex, 1).getDay()
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate()
    const cells = []

    // Kosong sebelum hari pertama
    for (let i = 0; i < firstDay; i++) cells.push(null)

    for (let d = 1; d <= daysInMonth; d++) {
        cells.push(d)
    }
    return cells
}

// Fungsi: cari bookings yang aktif pada tanggal tertentu
function getBookingsOnDate(year, month, day) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    return props.bookings.filter(b => b.tgl_mulai <= dateStr && b.tgl_selesai >= dateStr)
}

function isToday(year, month, day) {
    return year === todayDate.getFullYear()
        && month === todayDate.getMonth()
        && day === todayDate.getDate()
}

function getDateHighlightClass(year, month, day) {
    const dayBookings = getBookingsOnDate(year, month, day)
    if (dayBookings.length === 0) return ''
    
    const bookedRoomIds = new Set()
    dayBookings.forEach(b => {
        if (b.gabung_ruang) {
            const r2 = props.ruanganList.find(r => r.nama_ruang === 'Ruang 2')
            const r3 = props.ruanganList.find(r => r.nama_ruang === 'Ruang 3')
            if (r2) bookedRoomIds.add(r2.id)
            if (r3) bookedRoomIds.add(r3.id)
        } else {
            bookedRoomIds.add(b.ruangan_id)
        }
    })
    
    const totalRooms = props.ruanganList?.length || 0
    
    if (totalRooms > 0 && bookedRoomIds.size >= totalRooms) {
        return 'bg-red-100 text-red-800 font-extrabold'
    } else {
        return 'bg-amber-100 text-amber-800 font-extrabold'
    }
}

function getDotsForDate(year, month, day) {
    const dayBookings = getBookingsOnDate(year, month, day)
    const dots = []
    dayBookings.forEach(b => {
        if (b.gabung_ruang) {
            const r2 = props.ruanganList.find(r => r.nama_ruang === 'Ruang 2')
            const r3 = props.ruanganList.find(r => r.nama_ruang === 'Ruang 3')
            
            const r2Id = r2 ? r2.id : 2
            const r3Id = r3 ? r3.id : 3
            
            dots.push({
                id: `${b.id}-r2`,
                bg: getRoomColor(r2Id).bg,
                title: `${b.nama_training} – ${b.divisi} (Ruang 2)`
            })
            dots.push({
                id: `${b.id}-r3`,
                bg: getRoomColor(r3Id).bg,
                title: `${b.nama_training} – ${b.divisi} (Ruang 3)`
            })
        } else {
            dots.push({
                id: b.id,
                bg: getRoomColor(b.ruangan_id).bg,
                title: `${b.nama_training} – ${b.divisi}`
            })
        }
    })
    return dots
}
function getMonthOriginClass(index) {
    let classes = ''
    const colXl = index % 4
    if (colXl === 0) classes += ' xl:origin-left'
    else if (colXl === 3) classes += ' xl:origin-right'
    else classes += ' xl:origin-center'
    
    const colMd = index % 2
    if (colMd === 0) classes += ' md:origin-left'
    else classes += ' md:origin-right'
    
    classes += ' origin-center'
    return classes
}

// ============================================================
// Tab State
// ============================================================
const activeTab = ref('calendar')

// ============================================================
// Gantt State
// ============================================================
const ganttMonthIdx  = ref(new Date().getMonth())
const ganttYear      = ref(new Date().getFullYear())
const selectedMonthDaysCount = ref(new Date(ganttYear.value, ganttMonthIdx.value + 1, 0).getDate())

watch([ganttYear, ganttMonthIdx], () => {
    selectedMonthDaysCount.value = new Date(ganttYear.value, ganttMonthIdx.value + 1, 0).getDate()
})

const selectedMonthDays = computed(() => {
    const year = ganttYear.value
    const monthIdx = ganttMonthIdx.value
    const daysInMonth = selectedMonthDaysCount.value
    const daysArray = []
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, monthIdx, d)
        const dayOfWeek = date.getDay()
        daysArray.push({
            dayNum: d,
            dayName: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][dayOfWeek],
            isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
            isToday: dateToYmd(date) === dateToYmd(new Date())
        })
    }
    return daysArray
})

function safeParseDate(dateStr) {
    if (!dateStr) return null
    const parts = dateStr.split('-')
    if (parts.length < 3) return null
    const y = parseInt(parts[0], 10)
    const m = parseInt(parts[1], 10) - 1
    const d = parseInt(parts[2], 10)
    if (isNaN(y) || isNaN(m) || isNaN(d)) return null
    return new Date(y, m, d)
}

const roomGanttData = computed(() => {
    const year = ganttYear.value
    const monthIdx = ganttMonthIdx.value
    const daysInMonth = selectedMonthDaysCount.value
    
    // Month boundaries
    const mStartStr = `${year}-${String(monthIdx + 1).padStart(2, '0')}-01`
    const mEndStr = `${year}-${String(monthIdx + 1).padStart(2, '0')}-${String(daysInMonth).padStart(2, '0')}`
    
    const mStart = new Date(year, monthIdx, 1)
    const mEnd = new Date(year, monthIdx, daysInMonth)
    
    return props.ruanganList.map(room => {
        // Find bookings for this room overlapping this month
        const roomBookings = props.bookings.filter(b => {
            if (b.ruangan_id !== room.id) return false
            return b.tgl_mulai <= mEndStr && b.tgl_selesai >= mStartStr
        }).map(b => {
            const bStart = safeParseDate(b.tgl_mulai)
            const bEnd = safeParseDate(b.tgl_selesai)
            if (!bStart || !bEnd) return null
            
            const startDay = bStart < mStart ? 1 : bStart.getDate()
            const endDay = bEnd > mEnd ? daysInMonth : bEnd.getDate()
            
            const startPct = ((startDay - 1) / daysInMonth) * 100
            const widthPct = ((endDay - startDay + 1) / daysInMonth) * 100
            
            return {
                ...b,
                startDay,
                endDay,
                startPct,
                widthPct,
                trackIndex: 0
            }
        }).filter(Boolean)
        
        // Pack bookings into non-overlapping tracks
        const sortedBookings = [...roomBookings].sort((a, b) => a.startDay - b.startDay)
        const tracks = []
        sortedBookings.forEach(b => {
            let placed = false
            for (let i = 0; i < tracks.length; i++) {
                const track = tracks[i]
                const lastB = track[track.length - 1]
                if (b.startDay > lastB.endDay) {
                    track.push(b)
                    b.trackIndex = i
                    placed = true
                    break
                }
            }
            if (!placed) {
                tracks.push([b])
                b.trackIndex = tracks.length - 1
            }
        })
        
        return {
            ...room,
            bookings: sortedBookings,
            tracksCount: Math.max(tracks.length, 1)
        }
    })
})

function dateToYmd(date) {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

function formatIndoDateShort(dateStr) {
    if (!dateStr) return ''
    const parts = dateStr.split('-')
    if (parts.length < 3) return dateStr
    const day = parseInt(parts[2], 10)
    const monthIdx = parseInt(parts[1], 10) - 1
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    return `${day} ${months[monthIdx]}`
}

function formatDateRange(startStr, endStr) {
    const start = formatIndoDateShort(startStr)
    const end = formatIndoDateShort(endStr)
    const startYear = startStr.split('-')[0]
    const endYear = endStr.split('-')[0]
    
    if (startYear === endYear) {
        return `${start} s/d ${end} ${startYear}`
    }
    return `${start} ${startYear} s/d ${end} ${endYear}`
}

function selectGanttMonth(year, monthIdx) {
    ganttYear.value = year
    ganttMonthIdx.value = monthIdx
    activeTab.value = 'timeline'
}

function openModal(year, monthIdx) {
    selectGanttMonth(year, monthIdx)
}

function closeModal() {
    activeTab.value = 'calendar'
}

// ============================================================
// Detail Modal state untuk Popup Informasi Acara/Training
// ============================================================
const detailModalOpen = ref(false)
const selectedDetailBooking = ref(null)

function openDetailModal(booking) {
    selectedDetailBooking.value = booking
    detailModalOpen.value = true
}

function closeDetailModal() {
    detailModalOpen.value = false
    selectedDetailBooking.value = null
}

function formatIndoDateTime(dateTimeStr) {
    if (!dateTimeStr) return '-'
    const d = new Date(dateTimeStr)
    const dateStr = d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
    const timeStr = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
    return `${dateStr} pukul ${timeStr} WIB`
}

const filteredPeserta = computed(() => {
    return selectedDetailBooking.value?.participants?.filter(p => p.tipe === 'peserta') || []
})

const filteredPanitia = computed(() => {
    return selectedDetailBooking.value?.participants?.filter(p => p.tipe === 'panitia') || []
})

const sortedParticipants = computed(() => {
    const list = selectedDetailBooking.value?.participants || []
    return [...list].sort((a, b) => {
        if (a.tipe === b.tipe) return 0
        return a.tipe === 'peserta' ? -1 : 1
    })
})

// ============================================================
// Status badge helper
// ============================================================
const STATUS_STYLE = {
    plotting:             'bg-red-50 text-red-850 border border-red-200',
    waiting_confirmation: 'bg-amber-50 text-amber-800 border border-amber-200',
    confirmed:            'bg-green-105 text-green-800 border border-green-200',
    cancelled:            'bg-gray-100 text-gray-800 border border-gray-200',
}

function statusLabel(status) {
    const map = {
        plotting:             'H - 14',
        waiting_confirmation: 'Pending',
        confirmed:            'Disetujui',
        cancelled:            'Dibatalkan',
        final:                'ACC Final',
        done:                 'Selesai',
    }
    return map[status] ?? status
}

function getVisualStatus(b) {
    if (!b) return 'waiting_confirmation'
    if (b.status === 'cancelled') return 'cancelled'
    if (b.status === 'waiting_confirmation' || b.status === 'plotting') return 'waiting_confirmation'
    
    if (b.status === 'confirmed') {
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        const h14Cutoff = new Date()
        h14Cutoff.setDate(today.getDate() + 14)
        h14Cutoff.setHours(23, 59, 59, 999)
        
        const start = new Date(b.tgl_mulai)
        if (start >= today && start <= h14Cutoff) {
            return 'plotting'
        }
    }
    return 'confirmed'
}

// ── Stats Card Definitions ─────────────────────────────────────────
const cards = [
    {
        key:     'pending_approval',
        label:   'Pending',
        sub:     'Perlu tindakan segera',
        icon:    '⏳',
        filter:  'waiting_confirmation',
        theme: {
            bg:     'bg-amber-50',
            border: 'border-transparent',
            icon:   'bg-amber-100 text-amber-600',
            num:    'text-amber-700',
            label:  'text-amber-600',
            badge:  'bg-amber-100 text-amber-700',
            hover:  'hover:shadow-amber-100',
        },
    },
    {
        key:     'urgent_h14',
        label:   'H - 14',
        sub:     'Mulai dalam 14 hari, belum di-ACC',
        icon:    '🚨',
        filter:  'urgent',
        theme: {
            bg:     'bg-red-50',
            border: 'border-transparent',
            icon:   'bg-red-100 text-red-600',
            num:    'text-red-700',
            label:  'text-red-600',
            badge:  'bg-red-100 text-red-700',
            hover:  'hover:shadow-red-100',
        },
    },
    {
        key:     'confirmed_this_month',
        label:   'Terkonfirmasi',
        sub:     'Booking yang sudah disetujui',
        icon:    '✅',
        filter:  'confirmed',
        theme: {
            bg:     'bg-emerald-50',
            border: 'border-transparent',
            icon:   'bg-emerald-100 text-emerald-600',
            num:    'text-emerald-700',
            label:  'text-emerald-600',
            badge:  'bg-emerald-100 text-emerald-700',
            hover:  'hover:shadow-emerald-100',
        },
    },
    {
        key:     'rooms_today',
        label:   'Ruangan terpakai',
        sub:     'Hari ini',
        icon:    '🏢',
        filter:  'confirmed',
        theme: {
            bg:     'bg-blue-50',
            border: 'border-transparent',
            icon:   'bg-blue-100 text-blue-600',
            num:    'text-blue-700',
            label:  'text-blue-600',
            badge:  'bg-blue-100 text-blue-700',
            hover:  'hover:shadow-blue-100',
        },
    },
]

function goToFilter(filter) {
    router.get('/admin/bookings', { filter })
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

// ============================================================
// Chart State
// ============================================================
const chartSeries = computed(() => {
    let pending = 0, confirmed = 0, h14 = 0

    // Gunakan tahun yang dipilih user
    const selectedYear = props.selectedYear
    const currentYear = new Date().getFullYear()
    const currentDate = new Date()
    currentDate.setHours(0, 0, 0, 0)

    // Tentukan jangka H-14 berdasarkan konteks tahun
    let h14Start, h14End
    if (selectedYear === currentYear) {
        // Tahun ini: H-14 dihitung dari hari ini sampai 14 hari ke depan
        h14Start = new Date(currentDate)
        h14End = new Date(currentDate)
        h14End.setDate(h14End.getDate() + 14)
        h14End.setHours(23, 59, 59, 999)
    } else {
        // Tahun lain (masa lalu / depan): seluruh confirmed dalam tahun itu dianggap H-14
        h14Start = new Date(selectedYear, 0, 1)
        h14End   = new Date(selectedYear, 11, 31, 23, 59, 59, 999)
    }

    // Hitung dari props.bookings (sudah exclude cancelled — cancelled ada di stats sendiri)
    props.bookings.forEach(b => {
        if (b.status === 'waiting_confirmation' || b.status === 'plotting') {
            pending++
        } else if (b.status === 'confirmed') {
            const startDate = new Date(b.tgl_mulai)
            startDate.setHours(0, 0, 0, 0)

            if (selectedYear === currentYear) {
                // Untuk tahun berjalan: H-14 = confirmed & mulai dalam 14 hari dari sekarang
                if (startDate >= h14Start && startDate <= h14End) {
                    h14++
                } else {
                    confirmed++
                }
            } else {
                // Untuk tahun lain: seluruh confirmed masuk ke "H-14"
                h14++
            }
        } else if (b.status === 'final' || b.status === 'final_confirmed') {
            confirmed++
        }
    })

    // Cancelled diambil dari stats backend (terpisah dari bookings kalender)
    const cancelled = props.stats?.cancelled_count ?? 0

    return [pending, confirmed, h14, cancelled]
})

const chartOptions = computed(() => {
    return {
        chart: { type: 'donut', fontFamily: 'inherit' },
        labels: ['Pending', 'Disetujui', 'H - 14', 'Dibatalkan'],
        colors: ['#f59e0b', '#10b981', '#ef4444', '#9ca3af'], // Amber, Emerald, Red, Gray
        plotOptions: {
            pie: {
                donut: {
                    size: '58%',
                    labels: {
                        show: true,
                        name: { show: true, offsetY: -10, color: '#6b7280' },
                        value: { show: true, fontSize: '34px', fontWeight: '800', color: '#1f2937', offsetY: 5 },
                        total: {
                            show: true,
                            label: 'Total',
                            color: '#9ca3af',
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                            }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', fontSize: '12px', markers: { radius: 12 } },
        stroke: { width: 0 }
    }
})

const today = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>

        <!-- ── Page Header ──────────────────────────────────────────── -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Dashboard Admin</h1>
                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mt-1">{{ today }}</p>
            </div>
        </div>

        <!-- ── Top Row Layout: 2x2 Stats Grid + Donut Chart ─────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            
            <!-- Left Side: 2x2 Stats Grid (50% width) -->
            <div class="lg:col-span-1 grid grid-cols-2 gap-4">
                <button
                    v-for="card in cards"
                    :key="card.key"
                    @click="goToFilter(card.filter)"
                    class="text-left border-0 bg-white rounded-lg p-4 transition-all duration-200 shadow-xs hover:shadow-md hover:-translate-y-0.5 group cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-400 flex flex-col justify-between"
                >
                    <!-- Icon + number row -->
                    <div class="flex items-start justify-between mb-2 w-full">
                        <span class="w-8.5 h-8.5 flex items-center justify-center rounded-xl text-sm shrink-0"
                              :class="card.theme.icon">
                            <!-- SVGs based on card key -->
                            <svg v-if="card.key === 'pending_approval'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
                            <svg v-else-if="card.key === 'confirmed_this_month'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                            <svg v-else-if="card.key === 'urgent_h14'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <svg v-else-if="card.key === 'rooms_today'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </span>
                        <span class="text-[9px] font-bold uppercase px-2.5 py-1 rounded-full tracking-wider border transition-colors bg-white/70"
                              :class="card.theme.badge">Tinjau →</span>
                    </div>
 
                    <!-- Number + Label Section -->
                    <div class="mt-auto">
                        <p class="text-3xl font-black leading-none mb-1.5 transition-transform group-hover:scale-103"
                           :class="card.theme.num">
                            {{ props.stats[card.key] ?? 0 }}
                        </p>
                        <p class="text-xs font-extrabold uppercase tracking-wide" :class="card.theme.label">{{ card.label }}</p>
                        <p class="text-[10px] text-gray-400 font-medium mt-1 leading-snug">{{ card.sub }}</p>
                    </div>
                </button>
            </div>
            
            <!-- Right Side: Donut Chart (50% width) -->
            <div class="lg:col-span-1 bg-white rounded-lg shadow-xs p-5 flex flex-col items-stretch justify-between h-full">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"/>
                        </svg>
                    </span>
                    <h3 class="text-xs font-black text-gray-600 uppercase tracking-widest">Statistik Proporsi Booking</h3>
                </div>
                <div class="w-full flex-1 flex items-center justify-center min-h-[290px]" v-if="props.bookings.length > 0">
                    <VueApexCharts type="donut" width="100%" height="290" :options="chartOptions" :series="chartSeries" />
                </div>
                <div v-else class="text-gray-400 text-xs font-semibold italic flex-1 flex items-center justify-center">
                    Belum ada data booking tahun ini
                </div>
            </div>

        </div>



        <!-- ============================================================ -->
        <!-- Filter Area — Premium Panel -->
        <!-- ============================================================ -->
        <div class="bg-white rounded-lg shadow-xs mb-6 relative">

            <!-- Filter Header -->
            <div class="px-5 py-3.5 flex items-center justify-between bg-gray-50/20 rounded-t-lg">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 bg-blue-50 rounded-lg text-blue-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-black text-gray-600 uppercase tracking-widest">Filter Tampilan Kalender</span>
                    <span v-if="hasActiveFilter" class="ml-1 px-2 py-0.5 text-[9px] font-black bg-blue-50 text-blue-700 border border-blue-100 rounded-full uppercase tracking-wider">Aktif</span>
                </div>
                <button
                    v-if="hasActiveFilter"
                    @click="resetFilter"
                    class="text-[10.5px] text-gray-400 hover:text-red-600 font-bold flex items-center gap-1 transition-colors cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Reset Filter
                </button>
            </div>

            <!-- Filter Controls -->
            <div class="px-5 py-4 flex flex-wrap items-end gap-4">

                <!-- Tahun Select -->
                <div ref="yearRef" class="flex flex-col gap-1.5 min-w-[130px]">
                    <label class="flex items-center gap-1.5 text-[9.5px] font-black text-gray-400 uppercase tracking-widest">
                        <svg class="w-3.5 h-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Tahun
                    </label>
                    <div class="relative">
                        <!-- Trigger Display -->
                        <div
                            @click="isYearOpen = !isYearOpen"
                            class="w-full h-9 pl-3 pr-8 bg-gray-50 border border-gray-200 rounded-lg text-xs font-extrabold flex items-center gap-2 cursor-pointer select-none transition"
                            :class="isYearOpen ? 'ring-2 ring-blue-500 border-blue-400 bg-white' : 'hover:border-gray-300'"
                        >
                            <span class="w-5 h-5 rounded-md bg-blue-50 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </span>
                            <span class="text-gray-800">{{ filterYear }}</span>
                            <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none transition-transform" :class="isYearOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <!-- Dropdown -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 translate-y-1 scale-98"
                            enter-to-class="opacity-100 translate-y-0 scale-100"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0 scale-100"
                            leave-to-class="opacity-0 translate-y-1 scale-98"
                        >
                            <div
                                v-if="isYearOpen"
                                class="absolute top-full left-0 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1 overflow-hidden"
                            >
                                <button
                                    v-for="y in YEAR_OPTIONS"
                                    :key="y"
                                    @click="filterYear = y; isYearOpen = false"
                                    class="w-full flex items-center gap-3 px-3 py-2 text-xs transition text-left hover:bg-gray-50 cursor-pointer"
                                    :class="filterYear === y ? 'bg-blue-50 font-extrabold text-blue-700' : 'text-gray-700 font-semibold'"
                                >
                                    <span class="w-5 h-5 rounded-md shrink-0 flex items-center justify-center"
                                        :style="filterYear === y ? 'background:#dbeafe' : 'background:#f3f4f6'"
                                    >
                                        <svg class="w-3 h-3" :class="filterYear === y ? 'text-blue-600' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </span>
                                    <span>{{ y }}</span>
                                    <svg v-if="filterYear === y" class="w-3.5 h-3.5 ml-auto text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Ruangan Select -->
                <div ref="filterRef" class="flex flex-col gap-1.5 min-w-[220px]">
                    <label class="flex items-center gap-1.5 text-[9.5px] font-black text-gray-400 uppercase tracking-widest">
                        <svg class="w-3.5 h-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        Ruangan
                    </label>
                    <div class="relative">
                        <!-- Trigger Display -->
                        <div
                            @click="isFilterOpen = !isFilterOpen"
                            class="w-full h-9 pl-3 pr-8 bg-gray-50 border border-gray-200 rounded-lg text-xs font-extrabold flex items-center gap-2 cursor-pointer select-none transition"
                            :class="isFilterOpen ? 'ring-2 ring-blue-500 border-blue-400 bg-white' : 'hover:border-gray-300'"
                        >
                            <!-- Icon box — dot warna jika ruangan dipilih, grid icon jika semua -->
                            <span
                                v-if="selectedRuanganColor"
                                class="w-5 h-5 rounded-md shrink-0 flex items-center justify-center"
                                :style="{ backgroundColor: selectedRuanganColor + '22' }"
                            >
                                <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: selectedRuanganColor }"></span>
                            </span>
                            <span v-else class="w-5 h-5 rounded-md bg-gray-100 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                            </span>
                            <span class="text-gray-805 truncate">{{ selectedRuanganLabel }}</span>
                            <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none transition-transform" :class="isFilterOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <!-- Dropdown Options -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 translate-y-1 scale-98"
                            enter-to-class="opacity-100 translate-y-0 scale-100"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0 scale-100"
                            leave-to-class="opacity-0 translate-y-1 scale-98"
                        >
                            <div
                                v-if="isFilterOpen"
                                class="absolute top-full left-0 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1 overflow-hidden"
                            >
                                <!-- Semua Ruangan -->
                                <button
                                    @click="filterRuangan = null; isFilterOpen = false"
                                    class="w-full flex items-center gap-3 px-3 py-2 text-xs hover:bg-gray-50 transition text-left cursor-pointer font-semibold"
                                    :class="filterRuangan === null ? 'bg-blue-50 font-extrabold text-blue-700' : 'text-gray-700'"
                                >
                                    <span class="w-5 h-5 rounded-md bg-gray-100 flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                        </svg>
                                    </span>
                                    <span>Semua Ruangan</span>
                                    <svg v-if="filterRuangan === null" class="w-3.5 h-3.5 ml-auto text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </button>
                                <div class="my-1 border-t border-gray-100"></div>
                                <!-- Per Ruangan -->
                                <button
                                    v-for="(r, idx) in ruanganList"
                                    :key="r.id"
                                    @click="filterRuangan = r.id; isFilterOpen = false"
                                    class="w-full flex items-center gap-3 px-3 py-2 text-xs hover:bg-gray-50 transition text-left cursor-pointer font-semibold"
                                    :class="filterRuangan === r.id ? 'bg-blue-50 font-extrabold text-blue-700' : 'text-gray-700'"
                                >
                                    <span
                                        class="w-5 h-5 rounded-md shrink-0 flex items-center justify-center"
                                        :style="{ backgroundColor: ROOM_COLORS[idx % ROOM_COLORS.length].light }"
                                    >
                                        <span
                                            class="w-2.5 h-2.5 rounded-full"
                                            :style="{ backgroundColor: ROOM_COLORS[idx % ROOM_COLORS.length].bg }"
                                        ></span>
                                    </span>
                                    <span>{{ r.nama_ruang }}</span>
                                    <svg v-if="filterRuangan === r.id" class="w-3.5 h-3.5 ml-auto text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Tombol Terapkan -->
                <button
                    @click="applyFilter"
                    class="h-9 px-5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-xs font-bold text-white rounded-lg shadow-xs transition flex items-center gap-2 select-none shrink-0 cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                    Terapkan
                </button>

            </div>

            <!-- Legend Ruangan -->
            <div class="px-5 py-3 bg-gray-50/40 flex flex-wrap gap-2 rounded-b-lg">
                <span class="text-[9.5px] font-black text-gray-400 uppercase tracking-widest self-center mr-1">Warna Ruangan:</span>
                <button
                    v-for="(r, idx) in ruanganList"
                    :key="r.id"
                    @click="filterRuangan = r.id; applyFilter()"
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold border transition-all cursor-pointer shadow-3xs"
                    :style="{
                        backgroundColor: filterRuangan === r.id ? ROOM_COLORS[idx % ROOM_COLORS.length].light : '#f9fafb',
                        borderColor: filterRuangan === r.id ? ROOM_COLORS[idx % ROOM_COLORS.length].bg : '#e5e7eb',
                        color: filterRuangan === r.id ? ROOM_COLORS[idx % ROOM_COLORS.length].text : '#4b5563'
                    }"
                    :title="`Filter cepat: ${r.nama_ruang}`"
                >
                    <span
                        class="w-2 h-2 rounded-full shrink-0 animate-pulse"
                        :style="{ backgroundColor: ROOM_COLORS[idx % ROOM_COLORS.length].bg }"
                    ></span>
                    {{ r.nama_ruang }}
                </button>
            </div>

        </div>

        <!-- ============================================================ -->
        <!-- Tabs Navigation -->
        <!-- ============================================================ -->
        <div class="flex items-center gap-2 mb-5">
            <button @click="activeTab = 'calendar'" class="px-5 py-2.5 rounded-lg text-sm font-bold transition-all focus:outline-none" :class="activeTab === 'calendar' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">Kalender Bulanan</button>
            <button @click="activeTab = 'timeline'" class="px-5 py-2.5 rounded-lg text-sm font-bold transition-all focus:outline-none" :class="activeTab === 'timeline' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">Timeline Gantt Chart</button>
        </div>

        <!-- ============================================================ -->
        <!-- Kalender Grid (Tab) -->
        <!-- ============================================================ -->
        <div v-if="activeTab === 'calendar'" class="bg-white rounded-xl shadow-xs p-5 mb-6 animate-fade-in">
            
            <!-- Premium Calendar Header with SVG Icon -->
            <div class="flex items-center gap-2.5 mb-5 border-b border-gray-100 pb-4">
                <span class="w-8.5 h-8.5 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                </span>
                <div>
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none">Jadwal Kalender Pemesanan</h3>
                    <h2 class="text-sm font-black text-gray-800 mt-1.5 font-sans">Kalender Pemesanan Ruangan — {{ selectedYear }}</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div 
                    v-for="(monthName, monthIdx) in MONTH_NAMES" 
                    :key="monthIdx" 
                    class="group/month relative rounded-lg p-3 bg-gray-50/20 border border-gray-100/50 transition-all duration-300 ease-in-out hover:scale-140 hover:shadow-2xl hover:bg-white hover:z-[60] hover:border-blue-200"
                    :class="getMonthOriginClass(monthIdx)"
                >
                    <!-- Nama Bulan -->
                    <div @click="openModal(selectedYear, monthIdx)" class="text-xs font-black text-center text-gray-800 mb-2 border-b border-gray-100 pb-1.5 cursor-pointer hover:text-blue-600 transition">{{ monthName }}</div>

                    <!-- Header hari -->
                    <div class="grid grid-cols-7 text-center mb-1">
                        <span v-for="d in DAY_NAMES" :key="d" class="text-[10px] text-gray-400 font-medium">{{ d }}</span>
                    </div>

                    <!-- Grid tanggal -->
                    <div class="grid grid-cols-7 gap-1">
                        <template v-for="(day, cellIdx) in getMonthGrid(selectedYear, monthIdx)" :key="cellIdx">
                            <!-- Kosong -->
                            <div v-if="day === null" class="h-8"></div>

                            <!-- Tanggal berisi -->
                            <div
                                v-else
                                class="h-8 flex flex-col items-center justify-start cursor-pointer rounded-lg relative group transition-all duration-200"
                                :class="[
                                    isToday(selectedYear, monthIdx, day) ? 'ring-1 ring-blue-500 font-bold bg-white shadow-sm' : '',
                                    getDateHighlightClass(selectedYear, monthIdx, day) || 'bg-white/40 hover:bg-white hover:shadow-sm'
                                ]"
                                @click="openModal(selectedYear, monthIdx)"
                            >
                                <!-- Angka tanggal -->
                                <span 
                                    class="text-[10px] leading-none mt-1 font-medium"
                                    :class="getBookingsOnDate(selectedYear, monthIdx, day).length > 0 ? '' : 'text-gray-700'"
                                >{{ day }}</span>

                                <!-- Titik warna booking -->
                                <div class="flex gap-0.5 px-0.5 mt-0.5 justify-center transition-all duration-300 opacity-0 scale-75 max-h-0 overflow-hidden group-hover/month:opacity-100 group-hover/month:scale-100 group-hover/month:max-h-8">
                                    <span
                                        v-for="dot in getDotsForDate(selectedYear, monthIdx, day)"
                                        :key="dot.id"
                                        class="w-1 h-1 rounded-full"
                                        :style="{ backgroundColor: dot.bg }"
                                        :title="dot.title"
                                    ></span>
                                </div>
                            </div>
                        </template>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Timeline Gantt Chart (Tab) -->
        <!-- ============================================================ -->
        <div v-if="activeTab === 'timeline'" class="bg-white rounded-xl shadow-xs p-5 mb-5 flex flex-col h-[85vh] min-h-[600px] animate-fade-in">
            <!-- Header -->
            <div class="flex items-center justify-between mb-5 border-b border-gray-100 pb-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="w-8.5 h-8.5 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none">Diagram Jadwal Training</h3>
                        <h2 class="text-sm font-black text-gray-800 mt-1.5 font-sans">{{ MONTH_NAMES[ganttMonthIdx] }} {{ ganttYear }}</h2>
                    </div>
                </div>

                <!-- Month Selector -->
                <div class="flex items-center gap-2">
                    <button @click="ganttMonthIdx = (ganttMonthIdx - 1 + 12) % 12; if(ganttMonthIdx === 11) ganttYear--" class="p-2 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-500 cursor-pointer transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <select v-model="ganttMonthIdx" class="h-9 pl-3 pr-8 border border-gray-200 rounded-lg text-sm font-bold text-gray-700 bg-gray-50 hover:border-gray-300 cursor-pointer focus:ring-0">
                        <option v-for="(m, i) in MONTH_NAMES" :key="i" :value="i">{{ m }}</option>
                    </select>
                    <select v-model="ganttYear" class="h-9 pl-3 pr-8 border border-gray-200 rounded-lg text-sm font-bold text-gray-700 bg-gray-50 hover:border-gray-300 cursor-pointer focus:ring-0">
                        <option v-for="y in YEAR_OPTIONS" :key="y" :value="y">{{ y }}</option>
                    </select>
                    <button @click="ganttMonthIdx = (ganttMonthIdx + 1) % 12; if(ganttMonthIdx === 0) ganttYear++" class="p-2 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-500 cursor-pointer transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            <!-- Gantt Body -->
            <div class="flex-1 overflow-hidden bg-white flex flex-col min-h-0 border border-gray-200 rounded-xl shadow-xs">
                <!-- Diagram Legend (Modern Compact Ribbon) -->
                <div class="flex flex-wrap gap-4 items-center bg-gray-50 border-b border-gray-200 p-2.5 select-none shrink-0 px-5">
                    <div class="text-[9.5px] font-black text-gray-400 uppercase tracking-widest">Status Booking:</div>
                    <div class="flex items-center gap-1.5 text-[10.5px] font-bold bg-amber-50/80 text-amber-800 px-3 py-1 rounded-full border border-amber-200/70">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <span>Pending</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[10.5px] font-bold bg-red-50/80 text-red-800 px-3 py-1 rounded-full border border-red-200/70">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        <span>H - 14</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[10.5px] font-bold bg-emerald-50/80 text-emerald-800 px-3 py-1 rounded-full border border-emerald-200/70">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span>Disetujui</span>
                    </div>
                    <div class="ml-auto text-[10px] text-gray-400 font-bold hidden md:block flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-amber-500 shrink-0 inline-block mr-0.5 align-text-bottom" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        <span>Arahkan kursor pada bar diagram untuk detail</span>
                    </div>
                </div>
                
                <!-- Gantt Board Scroll Container -->
                <div class="overflow-hidden flex flex-col flex-1 min-h-0 relative">
                    <div class="overflow-x-auto flex-1 min-w-0 custom-scrollbar">
                        <div class="min-w-[1000px] flex flex-col h-full relative">
                            
                            <!-- Timeline Header Row -->
                            <div class="flex border-b border-gray-200 bg-white select-none shrink-0 sticky top-0 z-30 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                                <!-- Room Name Column Header -->
                                <div class="w-48 p-4 shrink-0 font-extrabold text-[11px] text-gray-500 uppercase tracking-wider border-r border-gray-200 flex items-center bg-white sticky left-0 z-40 shadow-[2px_0_5px_rgba(0,0,0,0.015)]">
                                    Nama Ruangan
                                </div>
                                <!-- Days Column Headers -->
                                <div class="flex-1 grid" :style="{ gridTemplateColumns: `repeat(${selectedMonthDaysCount}, minmax(0, 1fr))` }">
                                    <div 
                                        v-for="d in selectedMonthDays" 
                                        :key="d.dayNum"
                                        class="text-center py-2 flex flex-col items-center justify-center border-r border-gray-200/80 last:border-r-0"
                                        :class="[
                                            d.isWeekend ? 'bg-red-50/20 text-red-500' : 'text-gray-600',
                                            d.isToday ? 'bg-blue-50/40 text-blue-600 font-bold' : ''
                                        ]"
                                    >
                                        <span class="text-[8.5px] uppercase font-black tracking-tighter opacity-70">{{ d.dayName }}</span>
                                        <span class="text-[11px] font-extrabold mt-0.5" :class="d.isToday ? 'w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center font-black shadow-xs' : ''">
                                            {{ d.dayNum }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rows (One per Room) -->
                            <div class="divide-y divide-gray-100 flex-1 overflow-y-auto min-h-0 bg-gray-50/20">
                                <div 
                                    v-for="room in roomGanttData" 
                                    :key="room.id"
                                    class="flex hover:bg-gray-50/50 transition-colors relative group border-b border-gray-100 last:border-0"
                                    :style="{ minHeight: (24 + room.tracksCount * 36) + 'px' }"
                                >
                                    <!-- Room Info Label -->
                                    <div class="w-48 p-4 shrink-0 border-r border-gray-200 flex flex-col justify-center bg-white transition-colors sticky left-0 z-10 select-none shadow-[2px_0_5px_rgba(0,0,0,0.015)]">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-xs" :style="{ backgroundColor: getRoomColor(room.id).bg }"></span>
                                            <span class="font-extrabold text-gray-800 text-[11.5px] truncate leading-snug">{{ room.nama_ruang }}</span>
                                        </div>
                                        <span class="text-[9px] text-gray-400 font-semibold mt-1">Kapasitas: {{ room.kapasitas || '-' }} pax</span>
                                    </div>
                                    
                                    <!-- Gantt Track Area (Absolute Bars Overlay) -->
                                    <div class="flex-1 relative">
                                        <!-- Vertical background grid lines for days (Clearly Visible) -->
                                        <div class="absolute inset-0 grid pointer-events-none" :style="{ gridTemplateColumns: `repeat(${selectedMonthDaysCount}, minmax(0, 1fr))` }">
                                            <div 
                                                v-for="d in selectedMonthDays" 
                                                :key="d.dayNum" 
                                                class="border-r border-gray-200/60 last:border-r-0 h-full"
                                                :class="d.isWeekend ? 'bg-red-50/10' : ''"
                                            ></div>
                                        </div>
                                        
                                        <!-- Bookings in this room -->
                                        <div
                                            v-for="b in room.bookings"
                                            :key="b.id"
                                            @click="openDetailModal(b)"
                                            class="absolute h-7 rounded-lg px-2 flex items-center border shadow-3xs hover:shadow-2xs hover:scale-[1.01] hover:-translate-y-[0.5px] transition-all cursor-pointer group select-none"
                                            :style="{
                                                left: `calc(${b.startPct}% + 3px)`,
                                                width: `calc(${b.widthPct}% - 6px)`,
                                                top: (12 + (b.trackIndex * 36)) + 'px',
                                                backgroundColor: getStatusColor(getVisualStatus(b)).light,
                                                color: getStatusColor(getVisualStatus(b)).text,
                                                borderColor: getStatusColor(getVisualStatus(b)).bg
                                            }"
                                            :title="`${b.nama_ruang} — ${b.nama_training} (${b.divisi}) — ${formatDateRange(b.tgl_mulai, b.tgl_selesai)}`"
                                        >
                                            <div class="flex items-center gap-1.5 min-w-0 w-full">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: getRoomColor(b.ruangan_id).bg }" :title="`Ruangan: ${b.nama_ruang}`"></span>
                                                <span class="font-extrabold text-[10px] truncate leading-none mt-0.5">{{ b.nama_training }}</span>
                                                
                                                <!-- Status indicator dot at the end -->
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0 ml-auto shadow-xs" :class="[
                                                    getVisualStatus(b) === 'plotting' ? 'bg-red-500 animate-pulse' : '',
                                                    getVisualStatus(b) === 'waiting_confirmation' ? 'bg-amber-500 animate-pulse' : '',
                                                    getVisualStatus(b) === 'confirmed' ? 'bg-emerald-500' : ''
                                                ]"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Empty state if there are no rooms -->
                                <div v-if="roomGanttData.length === 0" class="py-16 text-center text-gray-400">
                                    Tidak ada ruangan terdaftar.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Notification Summary (Full Width) ───────────────────────── -->
        <div v-if="notifications && notifications.length > 0" class="mb-6">
            <div class="bg-white rounded-lg shadow-xs overflow-hidden flex flex-col w-full">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/20 shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <h2 class="text-xs font-black text-gray-600 uppercase tracking-widest">Notifikasi Terbaru</h2>
                    </div>
                    <span class="text-[10px] bg-red-50 text-red-755 border border-red-100 font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider shrink-0">
                        {{ notifications.length }} item
                    </span>
                </div>

                <div class="divide-y divide-gray-100 flex-1 overflow-y-auto max-h-[300px]">
                    <button
                        v-for="n in notifications.slice(0, 6)"
                        :key="n.booking_id + n.type"
                        @click="goToFilter(n.filter)"
                        class="w-full text-left px-5 py-3.5 flex items-center gap-3.5 transition-all duration-200 cursor-pointer"
                        :class="{
                            'hover:bg-red-50 bg-red-50/5':   n.type === 'overdue',
                            'hover:bg-amber-50 bg-amber-50/5': n.type === 'urgent',
                            'hover:bg-blue-50 bg-blue-50/5':  n.type === 'new',
                        }"
                    >
                        <span class="w-2.5 h-2.5 rounded-full shrink-0"
                              :class="{
                                  'bg-red-500 animate-pulse':   n.type === 'overdue',
                                  'bg-amber-500 animate-pulse': n.type === 'urgent',
                                  'bg-blue-500':  n.type === 'new',
                              }"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-extrabold text-gray-800 truncate">{{ n.label }}</p>
                            <p class="text-[11px] text-gray-500 font-semibold truncate mt-0.5">{{ n.sub }}</p>
                        </div>
                        <span class="text-[10px] text-gray-400 font-bold shrink-0 mt-0.5">{{ n.created_at }}</span>
                        <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                <div v-if="notifications.length > 6"
                     class="px-5 py-3 bg-gray-50/50 border-t border-gray-100 text-center shrink-0">
                    <button @click="goToFilter('waiting_confirmation')"
                            class="text-xs text-blue-600 hover:text-blue-700 active:text-blue-800 font-black transition-colors cursor-pointer inline-flex items-center gap-1 select-none">
                        Lihat {{ notifications.length - 6 }} notifikasi lainnya 
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <div v-else class="bg-white rounded-lg shadow-xs px-5 py-10 text-center flex flex-col items-center justify-center min-h-[180px] h-full mb-6">
            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="font-extrabold text-gray-800 text-sm">Semua Aman & Terkendali!</p>
            <p class="text-xs text-gray-400 font-semibold mt-1">Tidak ada pengajuan booking yang perlu tindakan segera saat ini.</p>
        </div>

        <!-- Modal: Detail Booking Training -->
        <Teleport to="body">
            <div
                v-if="detailModalOpen"
                class="fixed inset-0 z-[110] flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-fade-in"
                @click.self="closeDetailModal"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl md:w-[94vw] overflow-hidden flex flex-col border border-gray-100 h-[85vh] min-h-[550px]">

                    <!-- ── Header (sama gaya dengan Gantt modal) ── -->
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-white shrink-0">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </span>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-extrabold text-gray-800 text-sm sm:text-base leading-none">
                                        {{ selectedDetailBooking?.nama_training || 'Detail Pemesanan' }}
                                    </h4>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide border"
                                        :class="STATUS_STYLE[getVisualStatus(selectedDetailBooking)]"
                                    >
                                        {{ statusLabel(getVisualStatus(selectedDetailBooking)) }}
                                    </span>
                                    <span v-if="selectedDetailBooking?.gabung_ruang" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">🔗 Gabung Ruang</span>
                                </div>
                                <p class="text-[10px] text-gray-400 font-semibold mt-1.5 uppercase tracking-wider">
                                    {{ formatDateRange(selectedDetailBooking?.tgl_mulai, selectedDetailBooking?.tgl_selesai) }}
                                    &nbsp;·&nbsp; {{ selectedDetailBooking?.gabung_ruang ? 'Ruang Gabungan (2+3)' : (selectedDetailBooking?.nama_ruang || '-') }}
                                </p>
                            </div>
                        </div>
                        <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1.5 rounded-lg transition-colors cursor-pointer focus:outline-none shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- ── Info Bar (ringkas seperti legend di Gantt) ── -->
                    <div class="shrink-0 px-6 py-3 border-b border-gray-100 bg-gray-50/40 flex flex-wrap items-center gap-x-6 gap-y-1.5 text-[11px]">
                        <div class="flex items-center gap-1.5 text-gray-600">
                            <span class="font-semibold text-gray-400 uppercase tracking-wider text-[9.5px]">Pemohon:</span>
                            <span class="font-bold text-gray-800">{{ selectedDetailBooking?.pemohon || '-' }}</span>
                            <span class="text-gray-300">·</span>
                            <span class="text-blue-600 font-semibold">{{ selectedDetailBooking?.divisi || '-' }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-gray-600">
                            <span class="font-semibold text-gray-400 uppercase tracking-wider text-[9.5px]">PIC:</span>
                            <span class="font-bold text-gray-800">{{ selectedDetailBooking?.pic || '-' }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-gray-600">
                            <span class="font-semibold text-gray-400 uppercase tracking-wider text-[9.5px]">No. HP PIC:</span>
                            <span class="font-bold text-gray-800">{{ selectedDetailBooking?.no_hp_pic || '-' }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-gray-600">
                            <span class="font-semibold text-gray-400 uppercase tracking-wider text-[9.5px]">Layout:</span>
                            <span class="font-bold text-gray-800 capitalize">{{ selectedDetailBooking?.layout_preferensi || '-' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" :class="selectedDetailBooking?.is_hybrid ? 'bg-purple-500' : 'bg-gray-300'"></span>
                            <span class="text-gray-600">Hybrid</span>
                            <span class="w-2 h-2 rounded-full ml-1" :class="selectedDetailBooking?.is_flipchart ? 'bg-orange-500' : 'bg-gray-300'"></span>
                            <span class="text-gray-600">Flipchart</span>
                        </div>
                        <div class="ml-auto flex items-center gap-3 text-[10px] font-bold">
                            <span class="text-emerald-700">Peserta: {{ filteredPeserta.length }}</span>
                            <span class="text-gray-300">|</span>
                            <span class="text-indigo-700">Panitia: {{ filteredPanitia.length }}</span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-600">Total: {{ filteredPeserta.length + filteredPanitia.length }}</span>
                        </div>
                    </div>

                    <!-- ── Body: Tabel Roster ── -->
                    <div class="flex-1 overflow-hidden flex flex-col min-h-0 bg-gray-50/20 p-5">

                        <!-- Judul tabel + link denah -->
                        <div class="flex items-center justify-between mb-3 shrink-0">
                            <div class="text-[9.5px] font-black text-gray-400 uppercase tracking-widest">👥 Roster Acara</div>
                            <a v-if="selectedDetailBooking?.layout_url"
                               :href="selectedDetailBooking.layout_url" target="_blank"
                               class="inline-flex items-center gap-1 text-[11px] text-purple-700 hover:text-purple-900 font-semibold hover:underline">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Lihat File Denah Kustom
                            </a>
                        </div>

                        <!-- Tabel Roster (full width, scrollable) -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-xs overflow-hidden flex flex-col flex-1 min-h-0">
                            <div class="overflow-y-auto flex-1">
                                <table class="min-w-full divide-y divide-gray-100 text-xs">
                                    <thead class="bg-white sticky top-0 z-10 border-b border-gray-100">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[9px] font-bold text-gray-400 uppercase tracking-wider w-8">#</th>
                                            <th class="px-4 py-3 text-left text-[9px] font-bold text-gray-400 uppercase tracking-wider">Nama</th>
                                            <th class="px-4 py-3 text-center text-[9px] font-bold text-gray-400 uppercase tracking-wider w-20">Peran</th>
                                            <th class="px-4 py-3 text-center text-[9px] font-bold text-gray-400 uppercase tracking-wider w-24">NRP</th>
                                            <th class="px-4 py-3 text-left text-[9px] font-bold text-gray-400 uppercase tracking-wider">Jabatan / Site</th>
                                            <th class="px-4 py-3 text-center text-[9px] font-bold text-gray-400 uppercase tracking-wider w-10">JK</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-50">
                                        <tr v-if="!selectedDetailBooking?.participants || selectedDetailBooking.participants.length === 0">
                                            <td colspan="6" class="px-4 py-12 text-center text-gray-400 text-[11px] italic">Tidak ada data anggota roster.</td>
                                        </tr>
                                        <tr v-for="(p, idx) in sortedParticipants" :key="idx" class="hover:bg-gray-50/60 transition-colors">
                                            <td class="px-4 py-2.5 text-[10px] text-gray-400 font-medium">{{ idx + 1 }}</td>
                                            <td class="px-4 py-2.5">
                                                <div class="font-bold text-gray-800 text-xs">{{ p.nama }}</div>
                                            </td>
                                            <td class="px-4 py-2.5 text-center select-none">
                                                <span v-if="p.tipe === 'panitia'" class="inline-flex items-center bg-indigo-50 text-indigo-700 text-[9px] font-extrabold px-2 py-0.5 rounded-full border border-indigo-100">
                                                    Panitia
                                                </span>
                                                <span v-else class="inline-flex items-center bg-emerald-50 text-emerald-700 text-[9px] font-extrabold px-2 py-0.5 rounded-full border border-emerald-100">
                                                    Peserta
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <span v-if="!p.nrp || p.nrp.toUpperCase() === 'N/A'" class="inline-flex items-center gap-0.5 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded text-[9px] text-gray-400 font-mono">
                                                    N/A
                                                    <span class="bg-gray-200/60 text-gray-500 font-normal px-0.5 rounded text-[7px] uppercase tracking-wider font-sans select-none">Eks</span>
                                                </span>
                                                <span v-else class="inline-flex bg-blue-50 text-blue-700 text-[10px] font-extrabold font-mono px-1.5 py-0.5 rounded border border-blue-100">
                                                    {{ p.nrp }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <div class="font-semibold text-gray-600 text-[11px]">{{ p.jabatan || '-' }}</div>
                                                <div class="text-[9px] text-gray-400 mt-0.5 flex flex-wrap items-center gap-1">
                                                    <span>📍 {{ p.site || '-' }}</span>
                                                    <span v-if="p.no_hp" class="text-gray-300 font-light">|</span>
                                                    <span v-if="p.no_hp" class="font-mono text-[8.5px] text-gray-500">📞 {{ p.no_hp }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <span class="text-xs font-black" :class="p.gender === 'L' ? 'text-blue-600' : 'text-pink-500'">
                                                    {{ p.gender || '-' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Catatan Admin (jika ada) -->
                        <div v-if="selectedDetailBooking?.catatan_admin" class="mt-3 bg-red-50 rounded-xl p-3.5 border border-red-100 shrink-0">
                            <h6 class="text-[9.5px] font-black text-red-600 uppercase tracking-widest mb-1.5">📝 Catatan Admin</h6>
                            <p class="text-xs text-gray-700 leading-relaxed">{{ selectedDetailBooking.catatan_admin }}</p>
                        </div>

                    </div>

                    <!-- ── Footer ── -->
                    <div class="shrink-0 px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
                        <p class="text-[10px] text-gray-400">
                            📅 Diajukan: {{ formatIndoDateTime(selectedDetailBooking?.created_at) }}
                        </p>
                        <div class="flex items-center gap-2">
                            <a v-if="selectedDetailBooking"
                               :href="`/admin/bookings/${selectedDetailBooking.id}/export-detail`"
                               target="_blank"
                               class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold px-3 py-2 rounded-lg transition shadow-sm cursor-pointer"
                               title="Unduh Excel daftar peserta & panitia"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Ekspor Excel
                            </a>
                            <button
                                @click="closeDetailModal"
                                class="bg-white border border-gray-200 text-gray-700 font-semibold text-xs px-4 py-2 rounded-lg hover:bg-gray-100 transition shadow-sm cursor-pointer"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </Teleport>

</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f8fafc;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
