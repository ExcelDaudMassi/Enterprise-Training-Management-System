<script setup>
import { ref, computed, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { router } from '@inertiajs/vue3'

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
// Warna per ruangan (index-based, statis)
// ============================================================
const ROOM_COLORS = [
    { bg: '#3b82f6', light: '#dbeafe', text: '#1e40af' }, // Blue  - Ruang 1
    { bg: '#f97316', light: '#ffedd5', text: '#9a3412' }, // Orange - Ruang 2
    { bg: '#22c55e', light: '#dcfce7', text: '#166534' }, // Green  - Ruang 3
    { bg: '#ef4444', light: '#fee2e2', text: '#991b1b' }, // Red    - Ruang 4
    { bg: '#a855f7', light: '#f3e8ff', text: '#6b21a8' }, // Purple - Ruang 5
    { bg: '#eab308', light: '#fef9c3', text: '#854d0e' }, // Yellow - Ruang 6
    { bg: '#14b8a6', light: '#ccfbf1', text: '#134e4a' }, // Teal   - Ruang 7
]

function getRoomColor(ruanganId) {
    const idx = props.ruanganList.findIndex(r => r.id === ruanganId)
    return ROOM_COLORS[idx % ROOM_COLORS.length] ?? ROOM_COLORS[0]
}

// ============================================================
// Filter state
// ============================================================
const filterYear = ref(props.selectedYear)
const filterRuangan = ref(props.selectedRuangan)

watch(() => props.selectedYear, (newYear) => {
    filterYear.value = newYear
})

watch(() => props.selectedRuangan, (newRuangan) => {
    filterRuangan.value = newRuangan
})

function applyFilter() {
    router.get('/admin/dashboard', {
        year: filterYear.value,
        ruangan_id: filterRuangan.value || undefined,
    }, { 
        preserveState: true,
        preserveScroll: true,
    })
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
    
    const bookedRoomIds = new Set(dayBookings.map(b => b.ruangan_id))
    const totalRooms = props.ruanganList?.length || 0
    
    if (totalRooms > 0 && bookedRoomIds.size >= totalRooms) {
        return 'bg-red-50 text-red-800 font-semibold border border-red-200'
    } else {
        return 'bg-amber-50 text-amber-800 font-semibold border border-amber-200'
    }
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
// Modal state
// ============================================================
const modalOpen      = ref(false)
const modalDate      = ref('')
const modalMonthIdx  = ref(0)
const modalYear      = ref(2026)
const selectedMonthDaysCount = ref(30)
const selectedMonthDays = ref([])

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
    const year = modalYear.value
    const monthIdx = modalMonthIdx.value
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
                widthPct
            }
        }).filter(Boolean)
        
        return {
            ...room,
            bookings: roomBookings
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

function openModal(year, monthIdx) {
    const monthName = MONTH_NAMES[monthIdx]
    modalDate.value = `Diagram Jadwal Training — ${monthName} ${year}`
    modalMonthIdx.value = monthIdx
    modalYear.value = year
    
    const daysInMonth = new Date(year, monthIdx + 1, 0).getDate()
    selectedMonthDaysCount.value = daysInMonth
    
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
    selectedMonthDays.value = daysArray
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
}

// ============================================================
// Status badge helper
// ============================================================
const STATUS_STYLE = {
    plotting:             'bg-gray-150 text-gray-700',
    waiting_confirmation: 'bg-yellow-105 text-yellow-800 border border-yellow-250',
    confirmed:            'bg-green-105 text-green-800 border border-green-200',
    cancelled:            'bg-red-100 text-red-800 border border-red-200',
}

function statusLabel(status) {
    const map = {
        plotting:             'Plotting',
        waiting_confirmation: 'Menunggu',
        confirmed:            'Disetujui',
        cancelled:            'Ditolak',
    }
    return map[status] ?? status
}

// ── Stats Card Definitions ─────────────────────────────────────────
const cards = [
    {
        key:     'pending_approval',
        label:   'Menunggu Approval',
        sub:     'Perlu tindakan segera',
        icon:    '⏳',
        filter:  'waiting_confirmation',
        theme: {
            bg:     'bg-amber-50',
            border: 'border-amber-200',
            icon:   'bg-amber-100 text-amber-600',
            num:    'text-amber-700',
            label:  'text-amber-600',
            badge:  'bg-amber-100 text-amber-700',
            hover:  'hover:border-amber-400 hover:shadow-amber-100',
        },
    },
    {
        key:     'confirmed_this_month',
        label:   'Confirmed Bulan Ini',
        sub:     'Booking yang sudah disetujui',
        icon:    '✅',
        filter:  'confirmed',
        theme: {
            bg:     'bg-emerald-50',
            border: 'border-emerald-200',
            icon:   'bg-emerald-100 text-emerald-600',
            num:    'text-emerald-700',
            label:  'text-emerald-600',
            badge:  'bg-emerald-100 text-emerald-700',
            hover:  'hover:border-emerald-400 hover:shadow-emerald-100',
        },
    },
    {
        key:     'urgent_h14',
        label:   'Urgent — H-14',
        sub:     'Mulai dalam 14 hari, belum di-ACC',
        icon:    '🚨',
        filter:  'urgent',
        theme: {
            bg:     'bg-red-50',
            border: 'border-red-200',
            icon:   'bg-red-100 text-red-600',
            num:    'text-red-700',
            label:  'text-red-600',
            badge:  'bg-red-100 text-red-700',
            hover:  'hover:border-red-400 hover:shadow-red-100',
        },
    },
    {
        key:     'rooms_today',
        label:   'Ruangan Terpakai',
        sub:     'Hari ini',
        icon:    '🏢',
        filter:  'confirmed',
        theme: {
            bg:     'bg-blue-50',
            border: 'border-blue-200',
            icon:   'bg-blue-100 text-blue-600',
            num:    'text-blue-700',
            label:  'text-blue-600',
            badge:  'bg-blue-100 text-blue-700',
            hover:  'hover:border-blue-400 hover:shadow-blue-100',
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

const today = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>
    <AdminLayout :auth="auth" :bookingWindow="bookingWindow" :notifications="notifications">

        <!-- ── Page Header ──────────────────────────────────────────── -->
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-400 mt-0.5">{{ today }}</p>
        </div>

        <!-- ── 4 Stats Cards ────────────────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <button
                v-for="card in cards"
                :key="card.key"
                @click="goToFilter(card.filter)"
                class="text-left border rounded-lg p-5 transition-all duration-200 shadow-sm hover:shadow-md group cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-400"
                :class="[card.theme.bg, card.theme.border, card.theme.hover]"
            >
                <!-- Icon + number row -->
                <div class="flex items-start justify-between mb-3">
                    <span class="w-10 h-10 flex items-center justify-center rounded-lg text-lg"
                          :class="card.theme.icon">{{ card.icon }}</span>
                    <span class="text-[10px] font-semibold uppercase px-2 py-1 rounded-full tracking-wider"
                          :class="card.theme.badge">Lihat →</span>
                </div>

                <!-- Number -->
                <p class="text-4xl font-extrabold leading-none mb-2 transition-transform group-hover:scale-105"
                   :class="card.theme.num">
                    {{ props.stats[card.key] ?? 0 }}
                </p>

                <!-- Label + sub -->
                <p class="text-sm font-semibold" :class="card.theme.label">{{ card.label }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5">{{ card.sub }}</p>
            </button>
        </div>

        <!-- ── Quick Info: Booking Window status ─────────────────────── -->
        <div v-if="bookingWindow" class="mb-6">
            <div
                class="flex items-center gap-3 rounded-lg border px-5 py-3.5"
                :class="bookingWindow.is_active
                    ? 'bg-emerald-50 border-emerald-200'
                    : 'bg-gray-50 border-gray-200'"
            >
                <span class="w-3 h-3 rounded-full flex-shrink-0 transition-all"
                      :class="bookingWindow.is_active ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400'"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold"
                       :class="bookingWindow.is_active ? 'text-emerald-800' : 'text-gray-600'">
                        {{ bookingWindow.is_active ? 'Window Booking Sedang Aktif' : 'Window Booking Tutup' }}
                    </p>
                    <p class="text-xs mt-0.5"
                       :class="bookingWindow.is_active ? 'text-emerald-600' : 'text-gray-400'">
                        <template v-if="bookingWindow.is_active">
                            Periode: {{ bookingWindow.nama }} · Ditutup: {{ formatDate(bookingWindow.end_date) }}
                        </template>
                        <template v-else>
                            User tidak bisa mengajukan booking baru. Klik tombol "Buka" di navbar untuk membuka window.
                        </template>
                    </p>
                </div>
                <span class="text-2xl flex-shrink-0">{{ bookingWindow.is_active ? '🟢' : '⛔' }}</span>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Filter Area & Kalender (Sama seperti User Dashboard) -->
        <!-- ============================================================ -->
        <div class="mb-6 flex flex-wrap gap-4 items-end bg-white rounded-lg border border-gray-150 shadow-sm p-4">
            <!-- Year -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Tahun Kalender</label>
                <select v-model="filterYear" @change="applyFilter" class="border border-gray-250 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option v-for="y in [2025,2026,2027,2028]" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <!-- Ruangan -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Filter Ruangan</label>
                <select v-model="filterRuangan" @change="applyFilter" class="border border-gray-250 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option :value="null">Semua Ruangan</option>
                    <option v-for="r in ruanganList" :key="r.id" :value="r.id">{{ r.nama_ruang }}</option>
                </select>
            </div>

            <!-- Legend Warna Ruangan -->
            <div class="flex flex-wrap gap-2 ml-auto">
                <div
                    v-for="(r, idx) in ruanganList"
                    :key="r.id"
                    class="flex items-center gap-1 text-xs"
                >
                    <span
                        class="w-2.5 h-2.5 rounded-full inline-block"
                        :style="{ backgroundColor: ROOM_COLORS[idx % ROOM_COLORS.length].bg }"
                    ></span>
                    {{ r.nama_ruang }}
                </div>
            </div>
        </div>

        <!-- Kalender Grid -->
        <div class="bg-white rounded-lg border border-gray-150 shadow-sm p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center gap-1.5">
                <span>📅</span> Kalender Pemesanan Ruangan — {{ selectedYear }}
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div 
                    v-for="(monthName, monthIdx) in MONTH_NAMES" 
                    :key="monthIdx" 
                    class="group/month relative border border-gray-100 rounded p-3 bg-gray-50/30 transition-all duration-300 ease-in-out hover:scale-130 hover:shadow-2xl hover:bg-white hover:z-[60] hover:border-blue-200"
                    :class="getMonthOriginClass(monthIdx)"
                >

                    <!-- Nama Bulan -->
                    <div @click="openModal(selectedYear, monthIdx)" class="text-xs font-bold text-center text-gray-800 mb-2 border-b border-gray-100 pb-1 cursor-pointer hover:text-blue-600 transition">{{ monthName }}</div>

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
                                class="h-8 flex flex-col items-center justify-start cursor-pointer rounded relative group transition-all duration-200"
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
                                        v-for="b in getBookingsOnDate(selectedYear, monthIdx, day)"
                                        :key="b.id"
                                        class="w-1 h-1 rounded-full"
                                        :style="{ backgroundColor: getRoomColor(b.ruangan_id).bg }"
                                        :title="`${b.nama_training} – ${b.divisi}`"
                                    ></span>
                                </div>
                            </div>
                        </template>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Notification Summary ──────────────────────────────────── -->
        <div v-if="notifications && notifications.length > 0"
             class="bg-white rounded-lg border border-gray-150 shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-800">Notifikasi Terbaru</h2>
                <span class="text-xs bg-red-100 text-red-700 font-bold px-2 py-0.5 rounded-full">
                    {{ notifications.length }} item
                </span>
            </div>

            <div class="divide-y divide-gray-50">
                <button
                    v-for="n in notifications.slice(0, 6)"
                    :key="n.booking_id + n.type"
                    @click="goToFilter(n.filter)"
                    class="w-full text-left px-5 py-3 flex items-start gap-3 transition-colors cursor-pointer"
                    :class="{
                        'hover:bg-red-50':   n.type === 'overdue',
                        'hover:bg-amber-50': n.type === 'urgent',
                        'hover:bg-blue-50':  n.type === 'new',
                    }"
                >
                    <span class="mt-1.5 w-2 h-2 rounded-full flex-shrink-0"
                          :class="{
                              'bg-red-500':   n.type === 'overdue',
                              'bg-amber-500': n.type === 'urgent',
                              'bg-blue-500':  n.type === 'new',
                          }"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ n.label }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ n.sub }}</p>
                    </div>
                    <span class="text-[10px] text-gray-400 flex-shrink-0 mt-0.5">{{ n.created_at }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-300 flex-shrink-0 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <div v-if="notifications.length > 6"
                 class="px-5 py-2.5 bg-gray-50 border-t border-gray-100 text-center">
                <button @click="goToFilter('waiting_confirmation')"
                        class="text-xs text-blue-600 hover:text-blue-800 font-medium cursor-pointer">
                    Lihat {{ notifications.length - 6 }} notifikasi lainnya →
                </button>
            </div>
        </div>

        <!-- Empty state kalau tidak ada notifikasi -->
        <div v-else class="bg-white rounded-lg border border-gray-150 shadow-sm px-5 py-8 text-center text-gray-400 text-sm mb-6">
            <p class="text-2xl mb-2">🎉</p>
            <p class="font-medium text-gray-600">Semua aman!</p>
            <p class="text-xs mt-1">Tidak ada booking yang menunggu perhatian saat ini.</p>
        </div>

        <!-- ============================================================ -->
        <!-- Modal: Detail Booking pada Tanggal -->
        <!-- ============================================================ -->
        <Teleport to="body">
            <div
                v-if="modalOpen"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 animate-fade-in"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl overflow-hidden flex flex-col border border-gray-150 h-[80vh] min-h-[500px] transition-all">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-150 flex items-center justify-between bg-gray-50/50 shrink-0">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">📊</span>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm sm:text-base leading-none">{{ modalDate }}</h4>
                                <p class="text-[10.5px] text-gray-500 font-semibold mt-1">Diagram Gantt Jadwal Penggunaan Ruangan</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-700 text-3xl leading-none cursor-pointer">&times;</button>
                    </div>

                    <!-- Gantt Body -->
                    <div class="flex-1 overflow-hidden bg-gray-50/30 p-6 flex flex-col min-h-0">
                        
                        <!-- Diagram Legend -->
                        <div class="mb-4 flex flex-wrap gap-4 items-center bg-white p-3.5 rounded-xl border border-gray-150 shadow-3xs select-none shrink-0">
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status Booking:</div>
                            <div class="flex items-center gap-1.5 text-xs font-bold">
                                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                                <span class="text-gray-700">Plotting</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs font-bold">
                                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                <span class="text-gray-700">Menunggu Persetujuan</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs font-bold">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <span class="text-gray-700">Disetujui</span>
                            </div>
                            <div class="ml-auto text-[10px] text-gray-400 font-bold hidden sm:block">
                                💡 Arahkan kursor (hover) pada bar diagram untuk melihat detail lengkap.
                            </div>
                        </div>
                        
                        <!-- Gantt Board Scroll Container -->
                        <div class="bg-white rounded-2xl border border-gray-150 shadow-sm overflow-hidden flex flex-col flex-1 min-h-0">
                            <div class="overflow-x-auto flex-1 min-w-0">
                                <div class="min-w-[1000px] flex flex-col h-full">
                                    
                                    <!-- Timeline Header Row -->
                                    <div class="flex border-b border-gray-150 bg-gray-50 select-none shrink-0">
                                        <!-- Room Name Column Header -->
                                        <div class="w-48 p-4 shrink-0 font-extrabold text-xs text-gray-500 uppercase tracking-wider border-r border-gray-150 flex items-center">
                                            Nama Ruangan
                                        </div>
                                        
                                        <!-- Days Column Headers -->
                                        <div class="flex-1 grid" :style="{ gridTemplateColumns: `repeat(${selectedMonthDaysCount}, minmax(0, 1fr))` }">
                                            <div 
                                                v-for="d in selectedMonthDays" 
                                                :key="d.dayNum"
                                                class="text-center py-2.5 flex flex-col items-center justify-center border-r border-gray-100 last:border-r-0"
                                                :class="[
                                                    d.isWeekend ? 'bg-red-50/50 text-red-650' : 'text-gray-700',
                                                    d.isToday ? 'bg-blue-50 text-blue-600 font-bold' : ''
                                                ]"
                                            >
                                                <span class="text-[9px] uppercase font-extrabold tracking-tight opacity-75">{{ d.dayName }}</span>
                                                <span class="text-xs font-extrabold mt-0.5" :class="d.isToday ? 'w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center font-black shadow-2xs' : ''">
                                                    {{ d.dayNum }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Rows (One per Room) -->
                                    <div class="divide-y divide-gray-150 flex-1 overflow-y-auto min-h-0">
                                        <div 
                                            v-for="room in roomGanttData" 
                                            :key="room.id"
                                            class="flex min-h-[68px] hover:bg-gray-50/40 transition relative group"
                                        >
                                            <!-- Room Info Label -->
                                            <div class="w-48 p-4 shrink-0 border-r border-gray-150 flex flex-col justify-center bg-gray-50/20 select-none">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2.5 h-2.5 rounded-full shrink-0 animate-pulse" :style="{ backgroundColor: getRoomColor(room.id).bg }"></span>
                                                    <span class="font-extrabold text-gray-800 text-xs truncate leading-snug">{{ room.nama_ruang }}</span>
                                                </div>
                                                <span class="text-[9.5px] text-gray-400 font-semibold mt-1">Kapasitas: {{ room.kapasitas }} pax</span>
                                            </div>
                                            
                                            <!-- Gantt Track Area (Absolute Bars Overlay) -->
                                            <div class="flex-1 relative min-h-[68px]">
                                                <!-- Vertical background grid lines for days -->
                                                <div class="absolute inset-0 grid pointer-events-none" :style="{ gridTemplateColumns: `repeat(${selectedMonthDaysCount}, minmax(0, 1fr))` }">
                                                    <div 
                                                        v-for="d in selectedMonthDays" 
                                                        :key="d.dayNum" 
                                                        class="border-r border-gray-100 last:border-r-0 h-full"
                                                        :class="d.isWeekend ? 'bg-red-50/10' : ''"
                                                    ></div>
                                                </div>
                                                
                                                <!-- Bookings in this room -->
                                                <div
                                                    v-for="b in room.bookings"
                                                    :key="b.id"
                                                    class="absolute h-9 rounded-xl px-3 flex items-center border shadow-3xs hover:shadow-2xs hover:scale-[1.01] transition-all cursor-pointer group"
                                                    :style="{
                                                        left: `calc(${b.startPct}% + 4px)`,
                                                        width: `calc(${b.widthPct}% - 8px)`,
                                                        top: '16px',
                                                        backgroundColor: getRoomColor(b.ruangan_id).light,
                                                        color: getRoomColor(b.ruangan_id).text,
                                                        borderColor: getRoomColor(b.ruangan_id).bg
                                                    }"
                                                    :title="`${b.nama_ruang} — ${b.nama_training} (${b.divisi}) — ${formatDateRange(b.tgl_mulai, b.tgl_selesai)}`"
                                                >
                                                    <div class="flex items-center gap-1.5 min-w-0 w-full">
                                                        <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getRoomColor(b.ruangan_id).bg }"></span>
                                                        <span class="font-extrabold text-[11px] truncate">{{ b.nama_training }}</span>
                                                        
                                                        <!-- Status indicator dot at the end -->
                                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 ml-auto" :class="[
                                                            b.status === 'plotting' ? 'bg-amber-500 animate-pulse' : '',
                                                            b.status === 'waiting_confirmation' ? 'bg-blue-500 animate-pulse' : '',
                                                            b.status === 'approved' ? 'bg-emerald-500' : ''
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

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-gray-150 bg-gray-50 flex justify-end shrink-0">
                        <button 
                            @click="closeModal" 
                            class="bg-white border border-gray-200 text-gray-700 font-semibold text-xs px-4 py-2.5 rounded-lg hover:bg-gray-100 transition shadow-sm cursor-pointer"
                        >
                            Tutup
                        </button>
                    </div>

                </div>
            </div>
        </Teleport>

    </AdminLayout>
</template>
