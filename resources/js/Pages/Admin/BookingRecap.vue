<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    auth:         Object,
    bookings:     Array,
    activeFilter: { type: String, default: 'all' },
})

// Map filter dari dashboard ke tab yang relevan
const filterToTab = {
    'waiting_confirmation': 'waiting_confirmation',
    'confirmed':            'confirmed',
    'final_confirmed':      'final_confirmed',
    'cancelled':            'cancelled',
    'urgent':               'urgent',
    'overdue':              'overdue',
    'all':                  'all',
}
const activeTab = ref(filterToTab[props.activeFilter] ?? 'all')

const tabs = [
    { key: 'waiting_confirmation', label: 'Menunggu' },
    { key: 'confirmed',            label: 'Disetujui' },
    { key: 'final_confirmed',      label: 'Final Confirmed' },
    { key: 'cancelled',            label: 'Ditolak' },
    { key: 'urgent',               label: '🚨 H-14' },
    { key: 'overdue',              label: '⛔ Lewat Tenggat' },
    { key: 'all',                  label: 'Semua' },
]

const statusMeta = {
    waiting_confirmation: { label: 'Menunggu',  class: 'bg-yellow-100 text-yellow-800 border border-yellow-200' },
    confirmed:            { label: 'Disetujui', class: 'bg-blue-100 text-blue-800 border border-blue-200' },
    final_confirmed:      { label: 'Final',     class: 'bg-green-100 text-green-800 border border-green-200' },
    cancelled:            { label: 'Ditolak',   class: 'bg-red-100 text-red-800 border border-red-200' },
    plotting:             { label: 'Plotting',  class: 'bg-purple-100 text-purple-800 border border-purple-200' },
}

function tabCount(key) {
    if (key === 'all') return props.bookings.length
    if (key === activeTab.value && ['urgent', 'overdue'].includes(key)) return props.bookings.length
    if (['urgent', 'overdue'].includes(key)) return '-'
    return props.bookings.filter(b => b.status === (key === 'urgent' ? 'waiting_confirmation' : key)).length
}

const filteredBookings = computed(() => {
    if (activeTab.value === 'all') return props.bookings
    return props.bookings.filter(b => b.status === activeTab.value)
})

function formatTanggal(tgl) {
    if (!tgl) return '-'
    return new Date(tgl).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function isPastH14(tglMulai) {
    if (!tglMulai) return false
    const start = new Date(tglMulai)
    // reset waktu start ke 00:00:00
    start.setHours(0, 0, 0, 0)
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const diffDays = (start - today) / (1000 * 60 * 60 * 24)
    return diffDays <= 14 && diffDays >= 0
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}



// ── Detail Modal ─────────────────────────────────────────────────
const showDetailModal = ref(false)
const detailLoading   = ref(false)
const detailError     = ref(null)
const detailData      = ref(null)
const detailTab       = ref('info') // 'info' | 'peserta' | 'panitia' | 'log'

async function openDetail(booking) {
    showDetailModal.value = true
    detailLoading.value   = true
    detailError.value     = null
    detailData.value      = null
    detailTab.value       = 'info'

    try {
        const res = await axios.get(`/admin/bookings/${booking.id}/details`)
        detailData.value = res.data
    } catch (e) {
        detailError.value = 'Gagal memuat detail booking. Silakan coba lagi.'
    } finally {
        detailLoading.value = false
    }
}

function closeDetail() {
    showDetailModal.value = false
    detailData.value = null
}

const layoutLabels = {
    classroom: 'Classroom',
    'u-shape': 'U-Shape',
    'i-shape': 'I-Shape',
    'o-shape': 'O-Shape',
    custom:    'Custom (Lihat file)',
}

// ── Export Excel ─────────────────────────────────────────────────
function exportExcel() {
    window.open(`/admin/bookings/export?filter=${activeTab.value}`, '_blank')
}
</script>

<template>
    <AdminLayout :auth="auth">
        <!-- ── Page Header ─────────────────────────────────────── -->
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">📋 Rekap Booking</h1>
                <p class="text-sm text-gray-500 mt-1">Melihat riwayat dan merekap semua pengajuan peminjaman ruangan</p>
            </div>
            <!-- Export Button -->
            <button
                @click="exportExcel"
                class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors"
                title="Unduh rekap sesuai tab aktif sebagai file Excel"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Unduh Rekap ({{ tabs.find(t => t.key === activeTab)?.label }})
            </button>
        </div>

        <!-- Active filter banner -->
        <div v-if="activeFilter !== 'all'" class="mb-3 flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700 font-medium">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
            Filter aktif dari Dashboard: <strong class="ml-1">{{ tabs.find(t => t.key === activeFilter)?.label ?? activeFilter }}</strong>
        </div>

        <!-- Tabs -->
        <div class="flex flex-wrap gap-1 mb-4 bg-gray-100 p-1 rounded-lg w-fit">
            <button
                v-for="tab in tabs" :key="tab.key"
                @click="activeTab = tab.key"
                class="px-4 py-1.5 rounded text-sm font-medium transition"
                :class="activeTab === tab.key ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
                {{ tab.label }}
                <span class="ml-1 text-xs text-gray-400">({{ tabCount(tab.key) }})</span>
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Acara / Pemohon</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Ruangan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Jadwal</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Peserta</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Fasilitas</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <tr v-if="filteredBookings.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                Tidak ada data booking untuk tab ini.
                            </div>
                        </td>
                    </tr>
                    <tr v-for="b in filteredBookings" :key="b.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-gray-900">{{ b.nama_training }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ b.pemohon }} · <span class="font-medium text-gray-600">{{ b.divisi }}</span></div>
                            <div class="text-xs text-gray-400">PIC: {{ b.pic }}</div>
                            <div class="text-xs text-gray-300 mt-0.5 mb-1">Diajukan: {{ b.created_at }}</div>
                            <!-- Alert Merah jika confirmed & sudah lewat batas H-14 -->
                            <div v-if="b.status === 'confirmed' && isPastH14(b.tgl_mulai)" class="inline-flex items-center gap-1 text-[10px] font-bold text-red-600 bg-red-50 border border-red-200 px-1.5 py-0.5 rounded shadow-sm">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Lewat Batas ACC-2
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-800 font-medium">{{ b.ruangan }}</div>
                            <div class="text-xs text-gray-400 capitalize mt-0.5">Layout: {{ b.layout || '-' }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-700">
                            <div class="font-medium">{{ formatDate(b.tgl_mulai) }}</div>
                            <div class="text-gray-400">s/d</div>
                            <div class="font-medium">{{ formatDate(b.tgl_selesai) }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="text-sm font-bold text-gray-800">{{ b.jumlah_peserta }}</div>
                            <div class="text-xs text-gray-400">peserta</div>
                            <div class="text-xs text-gray-500 mt-1">+{{ b.jumlah_panitia }} panitia</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 space-y-1">
                            <div v-if="b.is_hybrid" class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-purple-400 flex-shrink-0"></span>Hybrid
                            </div>
                            <div v-if="b.is_flipchart" class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-400 flex-shrink-0"></span>Flipchart
                            </div>
                            <div v-if="!b.is_hybrid && !b.is_flipchart" class="text-gray-300">—</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold"
                                  :class="statusMeta[b.status]?.class ?? 'bg-gray-100 text-gray-600 border border-gray-200'">
                                {{ statusMeta[b.status]?.label ?? b.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-1.5">
                                <!-- Detail Button (always visible) -->
                                <button @click="openDetail(b)"
                                    class="flex items-center gap-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-medium py-1.5 px-3 rounded-lg transition border border-blue-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Lihat Detail
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>


        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- MODAL: DETAIL LENGKAP BOOKING                            -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showDetailModal"
                     class="fixed inset-0 bg-black/60 z-50 flex items-start justify-end p-4 pt-16 sm:pt-4"
                     @click.self="closeDetail">

                    <!-- Slide-over Panel (lebar penuh layar kanan) -->
                    <Transition
                        enter-active-class="transition-transform duration-300 ease-out"
                        enter-from-class="translate-x-full"
                        enter-to-class="translate-x-0"
                        leave-active-class="transition-transform duration-200 ease-in"
                        leave-from-class="translate-x-0"
                        leave-to-class="translate-x-full"
                        appear
                    >
                        <div v-if="showDetailModal"
                             class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl h-full max-h-[calc(100vh-2rem)] flex flex-col overflow-hidden">

                            <!-- ── Panel Header ── -->
                            <div class="flex-shrink-0 px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-slate-800 to-slate-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0 pr-4">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Detail Booking</span>
                                            <span v-if="detailData"
                                                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                                  :class="statusMeta[detailData.booking.status]?.class ?? 'bg-gray-100 text-gray-600'">
                                                {{ statusMeta[detailData.booking.status]?.label ?? detailData.booking.status }}
                                            </span>
                                        </div>
                                        <h2 class="text-base font-bold text-white mt-1 truncate">
                                            {{ detailData?.booking?.nama_training ?? '—' }}
                                        </h2>
                                    </div>
                                    <button @click="closeDetail" class="text-slate-400 hover:text-white transition-colors flex-shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- ── Loading State ── -->
                            <div v-if="detailLoading" class="flex-1 flex items-center justify-center">
                                <div class="text-center">
                                    <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                                    <p class="text-sm text-gray-500">Memuat detail booking...</p>
                                </div>
                            </div>

                            <!-- ── Error State ── -->
                            <div v-else-if="detailError" class="flex-1 flex items-center justify-center p-6">
                                <div class="text-center text-red-500">
                                    <svg class="w-10 h-10 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                    <p class="text-sm font-medium">{{ detailError }}</p>
                                </div>
                            </div>

                            <!-- ── Content ── -->
                            <div v-else-if="detailData" class="flex-1 overflow-hidden flex flex-col">
                                <!-- Sub-tabs -->
                                <div class="flex-shrink-0 flex border-b border-gray-100 px-6 pt-3 gap-0">
                                    <button v-for="tab in [
                                        { key: 'info',    label: '📋 Informasi', count: null },
                                        { key: 'peserta', label: '👥 Peserta',   count: detailData.total_peserta },
                                        { key: 'panitia', label: '🎯 Panitia',   count: detailData.total_panitia },
                                        { key: 'log',     label: '🕒 Log Aktivitas', count: detailData.logs.length },
                                    ]" :key="tab.key"
                                        @click="detailTab = tab.key"
                                        class="px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors mr-1"
                                        :class="detailTab === tab.key
                                            ? 'border-blue-600 text-blue-700 bg-blue-50/50'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    >
                                        {{ tab.label }}
                                        <span v-if="tab.count !== null" class="ml-1 text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full font-bold">{{ tab.count }}</span>
                                    </button>
                                </div>

                                <!-- Tab Content Area -->
                                <div class="flex-1 overflow-y-auto p-6">

                                    <!-- ── TAB: INFORMASI UMUM ── -->
                                    <div v-if="detailTab === 'info'" class="space-y-5">

                                        <!-- Pemohon & PIC -->
                                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">👤 Pemohon & Penanggung Jawab</h3>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Nama Pemohon</p>
                                                    <p class="text-sm font-semibold text-gray-800">{{ detailData.booking.pemohon?.name ?? '-' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Email</p>
                                                    <p class="text-sm text-gray-700">{{ detailData.booking.pemohon?.email ?? '-' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Divisi</p>
                                                    <p class="text-sm font-medium text-blue-700">{{ detailData.booking.pemohon?.divisi ?? '-' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">PIC Kegiatan</p>
                                                    <p class="text-sm font-medium text-gray-800">{{ detailData.booking.pic ?? '-' }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Ruangan & Jadwal -->
                                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                                            <h3 class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-3">🏢 Ruangan & Jadwal</h3>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Nama Ruangan</p>
                                                    <p class="text-sm font-bold text-gray-800">
                                                        {{ detailData.booking.gabung_ruang ? 'Ruang Gabungan (2+3)' : (detailData.booking.ruangan?.nama_ruang ?? '-') }}
                                                    </p>
                                                    <p v-if="detailData.booking.gabung_ruang" class="text-xs text-emerald-600 font-medium mt-0.5">🔗 Mode Gabung Aktif</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Lokasi Gedung</p>
                                                    <p class="text-sm text-gray-700">{{ detailData.booking.ruangan?.lokasi_gedung ?? 'Gabungan Gedung' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Kapasitas Ruang</p>
                                                    <p class="text-sm font-medium text-gray-700">
                                                        {{ detailData.booking.gabung_ruang ? '60 orang (gabungan)' : (detailData.booking.ruangan?.kapasitas_max ? detailData.booking.ruangan.kapasitas_max + ' orang' : '-') }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Total Orang</p>
                                                    <p class="text-sm font-bold text-gray-800">{{ detailData.total_peserta + detailData.total_panitia }} orang</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Tanggal Mulai</p>
                                                    <p class="text-sm font-semibold text-gray-800">{{ detailData.booking.tgl_mulai }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Tanggal Selesai</p>
                                                    <p class="text-sm font-semibold text-gray-800">{{ detailData.booking.tgl_selesai }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Fasilitas & Layout -->
                                        <div class="bg-purple-50 rounded-xl p-4 border border-purple-100">
                                            <h3 class="text-xs font-bold text-purple-600 uppercase tracking-wider mb-3">⚙️ Konfigurasi & Fasilitas</h3>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Preferensi Layout</p>
                                                    <p class="text-sm font-semibold text-gray-800 capitalize">{{ layoutLabels[detailData.booking.layout_preferensi] ?? detailData.booking.layout_preferensi ?? '-' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Fase Pemesanan</p>
                                                    <p class="text-sm font-medium text-gray-700 capitalize">{{ detailData.booking.fase === 'reguler' ? '📋 Reguler' : '📊 Plotting' }}</p>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <div class="flex items-center gap-2">
                                                        <span :class="detailData.booking.is_hybrid ? 'bg-purple-500' : 'bg-gray-300'" class="w-3 h-3 rounded-full flex-shrink-0"></span>
                                                        <span class="text-sm text-gray-700">Hybrid</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span :class="detailData.booking.is_flipchart ? 'bg-orange-500' : 'bg-gray-300'" class="w-3 h-3 rounded-full flex-shrink-0"></span>
                                                        <span class="text-sm text-gray-700">Flipchart</span>
                                                    </div>
                                                </div>
                                                <div v-if="detailData.booking.layout_url">
                                                    <a :href="detailData.booking.layout_url" target="_blank"
                                                       class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-800 font-medium underline">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                        Lihat File Denah Kustom
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Statistik Cepat -->
                                        <div class="grid grid-cols-3 gap-3">
                                            <div class="bg-blue-50 rounded-xl p-3 text-center border border-blue-100">
                                                <p class="text-2xl font-black text-blue-700">{{ detailData.total_peserta }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">Peserta</p>
                                            </div>
                                            <div class="bg-amber-50 rounded-xl p-3 text-center border border-amber-100">
                                                <p class="text-2xl font-black text-amber-700">{{ detailData.total_panitia }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">Panitia</p>
                                            </div>
                                            <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-100">
                                                <p class="text-2xl font-black text-gray-700">{{ detailData.total_peserta + detailData.total_panitia }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">Total Orang</p>
                                            </div>
                                        </div>

                                        <!-- Gender & Site Stats -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="bg-pink-50 rounded-xl p-4 border border-pink-100">
                                                <h4 class="text-xs font-bold text-pink-600 uppercase tracking-wider mb-2">Distribusi Gender</h4>
                                                <div class="flex items-center gap-3">
                                                    <div class="text-center">
                                                        <p class="text-xl font-black text-blue-600">{{ detailData.gender_stats.L }}</p>
                                                        <p class="text-xs text-gray-500">Laki-laki</p>
                                                    </div>
                                                    <div class="flex-1 h-px bg-gray-200"></div>
                                                    <div class="text-center">
                                                        <p class="text-xl font-black text-pink-600">{{ detailData.gender_stats.P }}</p>
                                                        <p class="text-xs text-gray-500">Perempuan</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bg-teal-50 rounded-xl p-4 border border-teal-100">
                                                <h4 class="text-xs font-bold text-teal-600 uppercase tracking-wider mb-2">Distribusi Site</h4>
                                                <div class="space-y-1.5">
                                                    <div v-for="(count, site) in detailData.site_stats" :key="site"
                                                         class="flex items-center justify-between text-xs">
                                                        <span class="text-gray-700 font-medium truncate">{{ site }}</span>
                                                        <span class="ml-2 px-2 py-0.5 bg-teal-100 text-teal-700 rounded-full font-bold flex-shrink-0">{{ count }}</span>
                                                    </div>
                                                    <p v-if="!Object.keys(detailData.site_stats).length" class="text-gray-400 text-xs">—</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Catatan Admin (jika ada) -->
                                        <div v-if="detailData.booking.catatan_admin" class="bg-red-50 rounded-xl p-4 border border-red-100">
                                            <h4 class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">📝 Catatan Admin</h4>
                                            <p class="text-sm text-gray-700">{{ detailData.booking.catatan_admin }}</p>
                                        </div>

                                        <!-- Timestamp -->
                                        <p class="text-xs text-gray-400 text-right">📅 Diajukan: {{ detailData.booking.created_at }}</p>
                                    </div>

                                    <!-- ── TAB: DAFTAR PESERTA ── -->
                                    <div v-if="detailTab === 'peserta'">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-sm font-bold text-gray-800">Daftar Peserta <span class="text-gray-400 font-normal">({{ detailData.total_peserta }} orang)</span></h3>
                                            <div class="flex items-center gap-3 text-xs text-gray-500">
                                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-400"></span>L: {{ detailData.gender_stats.L }}</span>
                                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-pink-400"></span>P: {{ detailData.gender_stats.P }}</span>
                                            </div>
                                        </div>
                                        <div v-if="detailData.peserta.length === 0" class="py-8 text-center text-gray-400 text-sm">Tidak ada data peserta.</div>
                                        <div v-else class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                                            <table class="min-w-full divide-y divide-gray-100">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase w-8">No</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Nama Lengkap</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">NRP</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Jabatan</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Site</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">No HP</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-gray-500 uppercase">JK</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-50">
                                                    <tr v-for="(p, i) in detailData.peserta" :key="i" class="hover:bg-gray-50">
                                                        <td class="px-3 py-2 text-xs text-gray-400">{{ i + 1 }}</td>
                                                        <td class="px-3 py-2 text-xs font-semibold text-gray-800">{{ p.nama }}</td>
                                                        <td class="px-3 py-2 text-xs font-mono text-gray-700 bg-gray-50/50 px-1 rounded">{{ p.nrp || 'N/A' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600">{{ p.jabatan || '-' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600">{{ p.site || '-' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600 font-mono">{{ p.no_hp || '-' }}</td>
                                                        <td class="px-3 py-2 text-center">
                                                            <span class="text-xs font-bold"
                                                                  :class="p.gender === 'L' ? 'text-blue-600' : 'text-pink-600'">
                                                                {{ p.gender || '-' }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- ── TAB: DAFTAR PANITIA ── -->
                                    <div v-if="detailTab === 'panitia'">
                                        <div class="mb-4">
                                            <h3 class="text-sm font-bold text-gray-800">Daftar Panitia <span class="text-gray-400 font-normal">({{ detailData.total_panitia }} orang)</span></h3>
                                        </div>
                                        <div v-if="detailData.panitia.length === 0" class="py-8 text-center text-gray-400 text-sm">Tidak ada data panitia.</div>
                                        <div v-else class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                                            <table class="min-w-full divide-y divide-gray-100">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase w-8">No</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Nama Lengkap</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">NRP</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Jabatan</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Site</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">No HP</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-gray-500 uppercase">JK</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-50">
                                                    <tr v-for="(p, i) in detailData.panitia" :key="i" class="hover:bg-gray-50">
                                                        <td class="px-3 py-2 text-xs text-gray-400">{{ i + 1 }}</td>
                                                        <td class="px-3 py-2 text-xs font-semibold text-gray-800">{{ p.nama }}</td>
                                                        <td class="px-3 py-2 text-xs font-mono text-gray-700 bg-gray-50/50 px-1 rounded">{{ p.nrp || 'N/A' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600">{{ p.jabatan || '-' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600">{{ p.site || '-' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600 font-mono">{{ p.no_hp || '-' }}</td>
                                                        <td class="px-3 py-2 text-center">
                                                            <span class="text-xs font-bold"
                                                                  :class="p.gender === 'L' ? 'text-blue-600' : 'text-pink-600'">
                                                                {{ p.gender || '-' }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- ── TAB: LOG AKTIVITAS ── -->
                                    <div v-if="detailTab === 'log'">
                                        <h3 class="text-sm font-bold text-gray-800 mb-4">Log Aktivitas</h3>
                                        <div v-if="detailData.logs.length === 0" class="py-8 text-center text-gray-400 text-sm">Belum ada log aktivitas untuk booking ini.</div>
                                        <div v-else class="space-y-3">
                                            <div v-for="(log, i) in detailData.logs" :key="i"
                                                 class="flex gap-3 bg-gray-50 rounded-xl p-4 border border-gray-100">
                                                <div class="flex-shrink-0 mt-0.5">
                                                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                                                          :class="{
                                                              'bg-green-100 text-green-700': log.action === 'approve',
                                                              'bg-red-100 text-red-700':    log.action === 'reject',
                                                              'bg-blue-100 text-blue-700':  !['approve','reject'].includes(log.action),
                                                          }">
                                                        {{ log.action === 'approve' ? '✓' : log.action === 'reject' ? '✕' : '•' }}
                                                    </span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-xs font-semibold text-gray-800">{{ log.message }}</p>
                                                    <p class="text-[11px] text-gray-400 mt-1">oleh <span class="font-medium text-gray-600">{{ log.actor }}</span> · {{ log.created_at }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div><!-- /Tab Content -->

                                <!-- Panel Footer: Read-only -->
                                <div class="flex-shrink-0 border-t border-gray-100 px-6 py-4 bg-gray-50">
                                    <p class="text-xs text-gray-500 text-center">Menampilkan detail rekap booking (Mode Read-Only)</p>
                                </div>

                            </div><!-- /if detailData -->
                        </div><!-- /panel -->
                    </Transition>
                </div>
            </Transition>
        </Teleport>




    </AdminLayout>
</template>
