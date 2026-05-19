<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm, Link, usePage } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'

// ============================================================
// Props dari controller
// ============================================================
const props = defineProps({
    auth: Object,
    ruanganList: Array,
    bookings: Array,
    myBookings: Array,
    selectedYear: Number,
    selectedRuangan: [Number, null],
})

const page = usePage()
const isWindowActive = computed(() => page.props.bookingWindow?.is_active ?? true)

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
    router.get('/user/dashboard', {
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
const today       = new Date()

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
    return year === today.getFullYear()
        && month === today.getMonth()
        && day === today.getDate()
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
const selectedMonthIdx = ref(0)
const selectedMonthWeeks = ref([])

function getCellDate(year, monthIdx, cellIdx) {
    const firstDay = new Date(year, monthIdx, 1)
    const startDayOfWeek = firstDay.getDay() // 0 = Sunday, 6 = Saturday
    const offset = cellIdx - startDayOfWeek
    return new Date(year, monthIdx, 1 + offset)
}

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
    modalDate.value = `Jadwal Training — ${monthName} ${year}`
    selectedMonthIdx.value = monthIdx
    
    const grid = getMonthGrid(year, monthIdx)
    const weeks = []
    
    for (let i = 0; i < grid.length; i += 7) {
        // Generate all 7 days of the week row
        const weekDays = []
        for (let col = 0; col < 7; col++) {
            const cellIdx = i + col
            const date = getCellDate(year, monthIdx, cellIdx)
            weekDays.push({
                date,
                dayNum: date.getDate(),
                dayName: DAY_NAMES[col],
                ymd: dateToYmd(date),
                isToday: dateToYmd(date) === dateToYmd(new Date())
            })
        }
        
        const startDateStr = weekDays[0].ymd
        const endDateStr = weekDays[6].ymd
        
        // Find bookings overlapping with this week
        const weekBookings = []
        props.bookings.forEach(b => {
            if (b.tgl_mulai <= endDateStr && b.tgl_selesai >= startDateStr) {
                let startIndex = 0
                while (startIndex < 7 && weekDays[startIndex].ymd < b.tgl_mulai) { startIndex++ }
                
                let endIndex = 6
                while (endIndex >= 0 && weekDays[endIndex].ymd > b.tgl_selesai) { endIndex-- }
                
                if (endIndex >= startIndex) {
                    weekBookings.push({
                        ...b,
                        startIndex,
                        endIndex,
                        span: endIndex - startIndex + 1
                    })
                }
            }
        })
        
        // Sort: earlier start first, then longer span first
        weekBookings.sort((a, b) => {
            if (a.startIndex !== b.startIndex) return a.startIndex - b.startIndex
            return b.span - a.span
        })
        
        // Assign tracks
        const tracks = []
        weekBookings.forEach(booking => {
            let assignedTrack = -1
            for (let t = 0; t < tracks.length; t++) {
                let overlap = false
                for (let col = booking.startIndex; col <= booking.endIndex; col++) {
                    if (tracks[t][col]) {
                        overlap = true
                        break
                    }
                }
                if (!overlap) {
                    assignedTrack = t
                    break
                }
            }
            if (assignedTrack === -1) {
                assignedTrack = tracks.length
                tracks.push(new Array(7).fill(false))
            }
            for (let col = booking.startIndex; col <= booking.endIndex; col++) {
                tracks[assignedTrack][col] = true
            }
            booking.track = assignedTrack
        })
        
        weeks.push({
            weekIndex: weeks.length + 1,
            startDateStr,
            endDateStr,
            weekDays,
            bookings: weekBookings,
            trackCount: Math.max(tracks.length, 1)
        })
    }
    
    selectedMonthWeeks.value = weeks
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
}

// ============================================================
// Status badge helper
// ============================================================
const STATUS_STYLE = {
    plotting:             'bg-gray-100 text-gray-700',
    waiting_confirmation: 'bg-yellow-100 text-yellow-700',
    confirmed:            'bg-green-100 text-green-700',
    cancelled:            'bg-red-100 text-red-700',
}

function statusLabel(status) {
    const map = {
        plotting:             'Plotting',
        waiting_confirmation: 'Waiting',
        confirmed:            'Confirmed',
        cancelled:            'Cancelled',
    }
    return map[status] ?? status
}
</script>

<template>
    <UserLayout :auth="auth">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">User Dashboard</h2>
            <div v-if="!isWindowActive"
                 class="bg-gray-300 text-gray-500 text-sm font-medium px-4 py-2 rounded cursor-not-allowed border border-gray-400"
                 title="Window Booking sedang ditutup oleh Admin.">
                + Create New Booking (Tutup)
            </div>
            <Link v-else href="/user/booking/create" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded">
                + Create New Booking
            </Link>
        </div>

        <!-- ============================================================ -->
        <!-- Filter Area -->
        <!-- ============================================================ -->
        <div class="bg-white rounded shadow p-4 mb-4 flex flex-wrap gap-4 items-end">

            <!-- Year -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
                <select v-model="filterYear" @change="applyFilter" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
                    <option v-for="y in [2025,2026,2027,2028]" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <!-- Ruangan -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Ruangan</label>
                <select v-model="filterRuangan" @change="applyFilter" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
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
                        class="w-3 h-3 rounded-full inline-block"
                        :style="{ backgroundColor: ROOM_COLORS[idx % ROOM_COLORS.length].bg }"
                    ></span>
                    {{ r.nama_ruang }}
                </div>
            </div>

        </div>

        <!-- ============================================================ -->
        <!-- Full-Year Calendar: 4 kolom x 3 baris -->
        <!-- ============================================================ -->
        <div class="bg-white rounded shadow p-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Kalender {{ selectedYear }}</h3>

            <div class="grid grid-cols-4 gap-4">
                <div 
                    v-for="(monthName, monthIdx) in MONTH_NAMES" 
                    :key="monthIdx" 
                    class="group/month relative border border-gray-200 rounded p-2 bg-white transition-all duration-300 ease-in-out hover:scale-130 hover:shadow-2xl hover:z-[60] hover:border-blue-200"
                    :class="getMonthOriginClass(monthIdx)"
                >

                    <!-- Nama Bulan -->
                    <div @click="openModal(selectedYear, monthIdx)" class="text-xs font-bold text-center text-gray-700 mb-1 cursor-pointer hover:text-blue-600 transition">{{ monthName }}</div>

                    <!-- Header hari -->
                    <div class="grid grid-cols-7 text-center mb-1">
                        <span v-for="d in DAY_NAMES" :key="d" class="text-[9px] text-gray-400 font-medium">{{ d }}</span>
                    </div>

                    <!-- Grid tanggal -->
                    <div class="grid grid-cols-7 gap-0.5">
                        <template v-for="(day, cellIdx) in getMonthGrid(selectedYear, monthIdx)" :key="cellIdx">
                            <!-- Kosong -->
                            <div v-if="day === null" class="h-6"></div>

                            <!-- Tanggal berisi -->
                            <div
                                v-else
                                class="h-6 flex flex-col items-center justify-start cursor-pointer rounded relative group transition-all duration-200"
                                :class="[
                                    isToday(selectedYear, monthIdx, day) ? 'ring-1 ring-blue-500 font-bold bg-white' : '',
                                    getDateHighlightClass(selectedYear, monthIdx, day) || 'hover:bg-gray-50'
                                ]"
                                @click="openModal(selectedYear, monthIdx)"
                            >
                                <!-- Angka tanggal -->
                                <span 
                                    class="text-[10px] leading-none mt-0.5"
                                    :class="getBookingsOnDate(selectedYear, monthIdx, day).length > 0 ? '' : 'text-gray-600'"
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

        <!-- ============================================================ -->
        <!-- My Bookings Table -->
        <!-- ============================================================ -->
        <div class="bg-white rounded shadow p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">My Bookings – Divisi {{ auth.user.divisi }}</h3>

            <div v-if="myBookings.length === 0" class="text-sm text-gray-400 text-center py-6">
                Belum ada booking dari divisi ini.
            </div>

            <table v-else class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
                    <tr>
                        <th class="px-3 py-2">Training</th>
                        <th class="px-3 py-2">Ruangan</th>
                        <th class="px-3 py-2">Tanggal</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="b in myBookings" :key="b.id" class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-800">{{ b.nama_training }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ b.nama_ruang }}</td>
                        <td class="px-3 py-2 text-gray-600 text-xs">
                            {{ b.tgl_mulai }} <span class="text-gray-400">s/d</span> {{ b.tgl_selesai }}
                        </td>
                        <td class="px-3 py-2">
                            <span
                                class="px-2 py-0.5 rounded text-xs font-medium"
                                :class="STATUS_STYLE[b.status]"
                            >
                                {{ statusLabel(b.status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <button class="text-blue-600 hover:underline text-xs">Detail</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ============================================================ -->
        <!-- Modal: Detail Booking pada Tanggal -->
        <!-- ============================================================ -->
        <Teleport to="body">
            <div
                v-if="modalOpen"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl overflow-hidden flex flex-col border border-gray-150 max-h-[90vh]">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-150 flex items-center justify-between bg-gray-50/50 shrink-0">
                        <h4 class="font-bold text-gray-800 text-base flex items-center gap-2">
                            <span class="text-xl">📅</span> {{ modalDate }}
                        </h4>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-700 text-3xl leading-none cursor-pointer">&times;</button>
                    </div>

                    <!-- Body (Full Monthly Calendar Grid View) -->
                    <div class="p-6 overflow-y-auto bg-gray-50/30 flex-1 flex flex-col">
                        <!-- Calendar Container -->
                        <div class="bg-white rounded-xl border border-gray-150 shadow-sm overflow-hidden flex flex-col flex-1">
                            
                            <!-- Day names header row -->
                            <div class="grid grid-cols-7 border-b border-gray-150 bg-gray-50/75 text-center py-3 select-none animate-fade-in">
                                <div v-for="dayName in ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']" :key="dayName" class="text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    {{ dayName }}
                                </div>
                            </div>
                            
                            <!-- Weeks rows -->
                            <div class="divide-y divide-gray-150 flex-1 flex flex-col min-h-0">
                                <div 
                                    v-for="w in selectedMonthWeeks" 
                                    :key="w.weekIndex" 
                                    class="relative flex flex-col min-h-[110px]"
                                >
                                    <!-- Day Numbers (top of week row) -->
                                    <div class="grid grid-cols-7 text-right p-1.5 pointer-events-none select-none z-10">
                                        <div 
                                            v-for="d in w.weekDays" 
                                            :key="d.ymd" 
                                            class="text-[11px] font-bold pr-2 flex justify-end"
                                        >
                                            <span 
                                                class="w-5 h-5 rounded-full flex items-center justify-center transition-all"
                                                :class="[
                                                    d.isToday ? 'bg-blue-600 text-white shadow-xs font-black' : 'text-gray-700',
                                                    d.date.getMonth() !== selectedMonthIdx ? 'opacity-30' : ''
                                                ]"
                                            >
                                                {{ d.dayNum }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Bookings Area (spans across columns) -->
                                    <div 
                                        class="relative flex-1 pb-2"
                                        :style="{ minHeight: `${w.trackCount * 28 + 10}px` }"
                                    >
                                        <!-- Vertical separator lines in background -->
                                        <div class="absolute inset-0 grid grid-cols-7 pointer-events-none">
                                            <div v-for="i in 7" :key="i" class="border-r border-gray-100 last:border-r-0 h-full"></div>
                                        </div>
                                        
                                        <!-- Booking pills overlay -->
                                        <div
                                            v-for="b in w.bookings"
                                            :key="b.id"
                                            class="absolute h-6 rounded px-2.5 flex items-center justify-between text-[11px] font-semibold border shadow-2xs transition-all hover:scale-[1.01] hover:shadow-xs group cursor-pointer"
                                            :style="{
                                                left: `calc(${(b.startIndex / 7) * 100}% + 4px)`,
                                                width: `calc(${(b.span / 7) * 100}% - 8px)`,
                                                top: `${b.track * 28 + 4}px`,
                                                backgroundColor: getRoomColor(b.ruangan_id).light,
                                                color: getRoomColor(b.ruangan_id).text,
                                                borderColor: getRoomColor(b.ruangan_id).bg
                                            }"
                                            :title="`${b.nama_ruang} — ${b.nama_training} (${b.divisi}) — ${formatDateRange(b.tgl_mulai, b.tgl_selesai)}`"
                                        >
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: getRoomColor(b.ruangan_id).bg }"></span>
                                                <span class="font-bold shrink-0 text-[10px]">{{ b.nama_ruang }}:</span>
                                                <span class="truncate">{{ b.nama_training }}</span>
                                            </div>
                                            <span class="text-[9px] font-bold uppercase tracking-wider scale-90 opacity-80 shrink-0 hidden sm:inline-block pl-1">{{ statusLabel(b.status) }}</span>
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

    </UserLayout>
</template>
