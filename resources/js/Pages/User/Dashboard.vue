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
        return 'bg-red-100 text-red-800 font-semibold'
    } else {
        return 'bg-yellow-50 text-yellow-850 font-semibold'
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
const modalBookings  = ref([])

function openModal(year, month, day, bookingsOnDate) {
    if (!bookingsOnDate.length) return
    modalDate.value = `${day} ${MONTH_NAMES[month]} ${year}`
    modalBookings.value = bookingsOnDate
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
                    <div class="text-xs font-bold text-center text-gray-700 mb-1">{{ monthName }}</div>

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
                                @click="openModal(selectedYear, monthIdx, day, getBookingsOnDate(selectedYear, monthIdx, day))"
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
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40"
                @click.self="closeModal"
            >
                <div class="bg-white rounded shadow-lg w-full max-w-md p-5">

                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-800">Booking – {{ modalDate }}</h4>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-700 text-lg leading-none">&times;</button>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="b in modalBookings"
                            :key="b.id"
                            class="border rounded p-3 text-sm"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <span
                                    class="w-2.5 h-2.5 rounded-full inline-block flex-shrink-0"
                                    :style="{ backgroundColor: getRoomColor(b.ruangan_id).bg }"
                                ></span>
                                <span class="font-medium text-gray-800">{{ b.nama_ruang }}</span>
                            </div>
                            <div class="text-gray-700 pl-4">{{ b.nama_training }}</div>
                            <div class="text-gray-500 pl-4 text-xs">Divisi: {{ b.divisi }}</div>
                            <div class="text-gray-400 pl-4 text-xs">
                                {{ b.tgl_mulai }} s/d {{ b.tgl_selesai }}
                            </div>
                        </div>
                    </div>

                    <button @click="closeModal" class="mt-4 w-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm py-2 rounded">
                        Tutup
</button>
                </div>
            </div>
        </Teleport>

    </UserLayout>
</template>
