<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AdminLayout })
import axios from 'axios'

const props = defineProps({
    auth:         Object,
    bookings:     Array,
    activeFilter: { type: String, default: 'all' },
})

// Map filter dari dashboard ke tab yang relevan
const filterToTab = {
    'pending':              'pending',
    'waiting_confirmation': 'pending',
    'confirmed':            'confirmed',
    'finalized':            'finalized',
    'final':                'finalized',
    'cancelled':            'cancelled',
    'rejected':             'rejected',
    'completed':            'completed',
    'urgent':               'urgent',
    'overdue':              'overdue',
    'all':                  'all',
}
const activeTab = ref(filterToTab[props.activeFilter] ?? 'all')

const tabs = [
    { key: 'pending',              label: 'Pending' },
    { key: 'confirmed',            label: 'Confirmed' },
    { key: 'finalized',            label: 'Finalized' },
    { key: 'rejected',             label: 'Rejected' },
    { key: 'cancelled',            label: 'Cancelled' },
    { key: 'completed',            label: 'Completed' },
    { key: 'urgent',               label: '🚨 H-14' },
    { key: 'overdue',              label: '⛔ Lewat Tenggat' },
    { key: 'all',                  label: 'Semua' },
]

const statusMeta = {
    pending:              { label: 'Pending',   class: 'bg-yellow-50 text-yellow-850 border border-yellow-200' },
    confirmed:            { label: 'Confirmed', class: 'bg-indigo-50 text-indigo-850 border border-indigo-200' },
    finalized:            { label: 'Finalized', class: 'bg-green-50 text-green-850 border border-green-200' },
    rejected:             { label: 'Rejected',  class: 'bg-rose-50 text-rose-850 border border-rose-200' },
    cancelled:            { label: 'Cancelled', class: 'bg-slate-50 text-slate-800 border border-slate-200' },
    completed:            { label: 'Completed', class: 'bg-emerald-50 text-emerald-850 border border-emerald-250' },
    
    // Legacy support
    waiting_confirmation: { label: 'Pending',   class: 'bg-yellow-50 text-yellow-850 border border-yellow-200' },
    final:                { label: 'Finalized', class: 'bg-green-50 text-green-850 border border-green-200' },
    plotting:             { label: 'Plotting',  class: 'bg-amber-50 text-amber-850 border border-amber-200' },
}

function tabCount(key) {
    if (key === 'all') return props.bookings.length
    if (key === activeTab.value && ['urgent', 'overdue'].includes(key)) return props.bookings.length
    if (['urgent', 'overdue'].includes(key)) return '-'
    return props.bookings.filter(b => {
        if (key === 'pending') return b.status === 'pending' || b.status === 'waiting_confirmation'
        if (key === 'finalized') return b.status === 'finalized' || b.status === 'final'
        return b.status === key
    }).length
}

const filteredBookings = computed(() => {
    if (activeTab.value === 'all') return props.bookings
    if (activeTab.value === 'urgent' || activeTab.value === 'overdue') return props.bookings
    return props.bookings.filter(b => {
        if (activeTab.value === 'pending') return b.status === 'pending' || b.status === 'waiting_confirmation'
        if (activeTab.value === 'finalized') return b.status === 'finalized' || b.status === 'final'
        return b.status === activeTab.value
    })
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

// ── Edit Participant ──────────────────────────────────────────────
const showEditParticipantModal = ref(false)
const editParticipantProcessing = ref(false)
const editParticipantErrors = ref({})
const editParticipantForm = ref({
    id: null,
    nama: '',
    nrp: '',
    jabatan: '',
    site: '',
    no_hp: '',
    gender: 'L',
    tipe: 'peserta'
})

function openEditParticipantModal(p) {
    editParticipantErrors.value = {}
    editParticipantForm.value = {
        id: p.id,
        nama: p.nama || '',
        nrp: p.nrp || '',
        jabatan: p.jabatan || '',
        site: p.site || '',
        no_hp: p.no_hp || '',
        gender: p.gender || 'L',
        tipe: p.tipe || 'peserta'
    }
    showEditParticipantModal.value = true
}

async function submitEditParticipant() {
    editParticipantProcessing.value = true
    editParticipantErrors.value = {}

    try {
        const res = await axios.put(`/admin/participants/${editParticipantForm.value.id}`, editParticipantForm.value)
        if (res.data.success) {
            // Update detail data dynamically
            detailData.value = res.data
            showEditParticipantModal.value = false
        }
    } catch (e) {
        if (e.response && e.response.status === 422) {
            editParticipantErrors.value = e.response.data.errors || { general: e.response.data.message }
        } else if (e.response && e.response.data && e.response.data.message) {
            editParticipantErrors.value = { general: e.response.data.message }
        } else {
            editParticipantErrors.value = { general: 'Gagal memperbarui data peserta. Silakan coba lagi.' }
        }
    } finally {
        editParticipantProcessing.value = false
    }
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

// ── Real-time WebSockets ──────────────────────────────────────────
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('bookings')
            .listen('NewBookingCreated', (e) => {
                router.reload({ only: ['bookings'], preserveState: true, preserveScroll: true })
            })
            .listen('BookingStatusUpdated', (e) => {
                router.reload({ only: ['bookings'], preserveState: true, preserveScroll: true })
            })
    }
})

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('bookings')
    }
})
</script>

<template>
        <!-- ── Page Header ─────────────────────────────────────── -->
        <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent">Rekap Booking</h1>
                <p class="text-sm text-slate-500 mt-1 font-medium">Melihat riwayat dan merekap semua pengajuan peminjaman ruangan</p>
            </div>
            <!-- Export Button -->
            <button
                @click="exportExcel"
                class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm transition-all active:scale-95"
                title="Unduh rekap sesuai tab aktif sebagai file Excel"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Unduh Rekap ({{ tabs.find(t => t.key === activeTab)?.label }})
            </button>
        </div>

        <!-- Active filter banner -->
        <div v-if="activeFilter !== 'all'" class="mb-5 flex items-center gap-2 px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-700 font-bold shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
            Filter aktif dari Dashboard: <strong class="ml-1">{{ tabs.find(t => t.key === activeFilter)?.label ?? activeFilter }}</strong>
        </div>

        <!-- Tabs -->
        <div class="flex flex-wrap items-center gap-1.5 mb-5 bg-slate-100/80 backdrop-blur p-1.5 rounded-xl w-fit shadow-inner">
            <button
                v-for="tab in tabs" :key="tab.key"
                @click="activeTab = tab.key"
                class="px-4 py-2 rounded-lg text-xs font-bold transition-all duration-300 flex items-center gap-1.5"
                :class="activeTab === tab.key
                    ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-200/50 scale-100'
                    : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/50 hover:scale-95'"
            >
                {{ tab.label }}
                <span class="text-[10px] px-1.5 py-0.5 rounded-md"
                      :class="activeTab === tab.key ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-500'">
                    {{ tabCount(tab.key) }}
                </span>
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative z-10">
            <div class="overflow-x-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                <table class="min-w-full table-fixed divide-y divide-slate-100">
                    <thead class="bg-slate-50/80 backdrop-blur-sm border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[22%]">Acara / Pemohon</th>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[12%]">Ruangan</th>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[16%]">Jadwal</th>
                            <th class="px-5 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[10%]">Peserta</th>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[12%]">Fasilitas</th>
                            <th class="px-5 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[12%]">Status</th>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[16%]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-100">
                        <tr v-if="filteredBookings.length === 0">
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center border border-slate-100 mb-2">
                                        <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    </div>
                                    <span class="text-sm font-semibold text-slate-500">Tidak ada data booking untuk tab ini.</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="b in filteredBookings" :key="b.id" class="hover:bg-slate-50/50 transition-all duration-300 align-top group">
                            <td class="px-5 py-4">
                                <div class="text-sm font-bold text-slate-900 group-hover:text-blue-700 transition-colors line-clamp-2">{{ b.nama_training }}</div>
                                <div class="text-xs text-slate-500 mt-1 flex items-center gap-1"><span class="font-semibold text-slate-700">{{ b.pemohon }}</span> &bull; {{ b.divisi }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">PIC: <span class="font-medium">{{ b.pic }}</span></div>
                                <div class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>{{ b.created_at }}</div>
                                <!-- Alert Merah jika confirmed & sudah lewat batas H-14 -->
                                <div v-if="b.status === 'confirmed' && isPastH14(b.tgl_mulai)" class="inline-flex items-center gap-1.5 text-[10px] font-bold text-red-700 bg-red-50 border border-red-200 px-2 py-1 rounded-md mt-2 shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Lewat Batas ACC-2
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 font-bold text-xs px-2.5 py-1 rounded-lg border border-slate-200">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    {{ b.ruangan }}
                                </div>
                                <div class="text-[11px] text-slate-400 capitalize mt-2 flex items-center gap-1">Layout: <span class="font-medium text-slate-600">{{ b.layout || '-' }}</span></div>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-700">
                                <div class="font-bold text-slate-800 bg-slate-50 border border-slate-100 px-2 py-1 rounded-md inline-block mb-1">{{ formatDate(b.tgl_mulai) }}</div>
                                <div class="text-slate-400 text-[10px] ml-2 mb-1">s/d</div>
                                <div class="font-bold text-slate-800 bg-slate-50 border border-slate-100 px-2 py-1 rounded-md inline-block">{{ formatDate(b.tgl_selesai) }}</div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <div class="inline-flex flex-col items-center bg-blue-50/50 border border-blue-100 px-3 py-1.5 rounded-xl">
                                    <div class="text-lg font-black text-blue-700 leading-none">{{ b.jumlah_peserta }}</div>
                                    <div class="text-[10px] font-bold text-blue-500 uppercase mt-0.5">Peserta</div>
                                </div>
                                <div class="text-[11px] font-semibold text-amber-600 mt-1.5 bg-amber-50 rounded-md py-0.5 border border-amber-100">+{{ b.jumlah_panitia }} Panitia</div>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600 space-y-1.5">
                                <div v-if="b.is_hybrid" class="flex items-center gap-2 bg-purple-50 text-purple-700 font-semibold px-2 py-1 rounded-md border border-purple-100 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-purple-500 flex-shrink-0 animate-pulse"></span>Hybrid
                                </div>
                                <div v-if="b.is_flipchart" class="flex items-center gap-2 bg-orange-50 text-orange-700 font-semibold px-2 py-1 rounded-md border border-orange-100 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500 flex-shrink-0"></span>Flipchart
                                </div>
                                <div v-if="b.is_pena_mini_note" class="flex items-center gap-2 bg-teal-50 text-teal-700 font-semibold px-2 py-1 rounded-md border border-teal-100 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500 flex-shrink-0"></span>Pena & Mini Note
                                </div>
                                <div v-if="!b.is_hybrid && !b.is_flipchart && !b.is_pena_mini_note" class="text-slate-300 font-medium">—</div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="px-3 py-1.5 rounded-full text-[11px] font-bold shadow-sm"
                                      :class="statusMeta[b.status]?.class ?? 'bg-slate-100 text-slate-600 border border-slate-200'">
                                    {{ statusMeta[b.status]?.label ?? b.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-col gap-2">
                                    <!-- Detail Button (always visible) -->
                                    <button @click="openDetail(b)"
                                        class="group/btn flex items-center justify-center gap-1.5 bg-white hover:bg-slate-50 text-slate-700 hover:text-blue-600 text-[11px] font-bold py-2 px-3 rounded-xl transition-all border border-slate-200 hover:border-blue-300 shadow-sm hover:shadow">
                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover/btn:text-blue-500 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Lihat Detail
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
                     class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-start justify-end p-4 pt-16 sm:pt-4"
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
                             class="relative bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-slate-100 w-full max-w-4xl h-full max-h-[calc(100vh-2rem)] flex flex-col overflow-hidden ring-1 ring-slate-900/5">

                            <!-- ── Panel Header ── -->
                            <div class="flex-shrink-0 px-6 py-5 border-b border-gray-200 bg-white relative overflow-hidden">
                                <!-- Decorative subtle circle or gradient -->
                                <div class="absolute -top-12 -right-12 w-32 h-32 bg-blue-500/5 rounded-full blur-2xl"></div>
                                <div class="relative flex items-center justify-between">
                                    <div class="flex-1 min-w-0 pr-4">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-blue-600 uppercase tracking-wider">Detail Booking</span>
                                            <span v-if="detailData"
                                                  class="px-2 py-0.5 rounded-full text-[10px] font-bold shadow-sm"
                                                  :class="statusMeta[detailData.booking.status]?.class ?? 'bg-slate-100 text-slate-600'">
                                                {{ statusMeta[detailData.booking.status]?.label ?? detailData.booking.status }}
                                            </span>
                                        </div>
                                        <h2 class="text-lg font-black text-gray-800 mt-1 truncate">
                                            {{ detailData?.booking?.nama_training ?? '—' }}
                                        </h2>
                                    </div>
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <!-- Tombol Download Excel Detail -->
                                        <a v-if="detailData"
                                           :href="`/admin/bookings/${detailData.booking.id}/export-detail`"
                                           target="_blank"
                                           class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white hover:text-white text-xs font-bold px-3 py-1.5 rounded-xl transition-all shadow-sm"
                                           title="Download Excel daftar peserta & panitia"
                                        >
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Export
                                        </a>
                                        <button @click="closeDetail" class="text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 p-1.5 rounded-full transition-all cursor-pointer">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Loading State (Skeleton) ── -->
                            <div v-if="detailLoading" class="flex-1 flex flex-col p-6 animate-pulse">
                                <!-- Tabs Skeleton -->
                                <div class="flex gap-4 mb-6 border-b border-slate-100 pb-2">
                                    <div class="h-8 w-24 bg-slate-200 rounded-lg"></div>
                                    <div class="h-8 w-24 bg-slate-200 rounded-lg"></div>
                                    <div class="h-8 w-24 bg-slate-200 rounded-lg"></div>
                                </div>
                                
                                <!-- Content Box 1 Skeleton -->
                                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-5">
                                    <div class="h-4 w-48 bg-slate-200 rounded mb-4"></div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div v-for="i in 4" :key="i">
                                            <div class="h-3 w-16 bg-slate-200 rounded mb-2"></div>
                                            <div class="h-4 w-32 bg-slate-200 rounded"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Content Box 2 Skeleton -->
                                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                    <div class="h-4 w-40 bg-slate-200 rounded mb-4"></div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div v-for="i in 6" :key="'sec'+i">
                                            <div class="h-3 w-16 bg-slate-200 rounded mb-2"></div>
                                            <div class="h-4 w-28 bg-slate-200 rounded"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Error State ── -->
                            <div v-else-if="detailError" class="flex-1 flex items-center justify-center p-6 bg-slate-50">
                                <div class="text-center text-rose-500 bg-rose-50 border border-rose-100 p-6 rounded-2xl shadow-sm">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-rose-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                    <p class="text-sm font-bold">{{ detailError }}</p>
                                </div>
                            </div>

                            <!-- ── Content ── -->
                            <div v-else-if="detailData" class="flex-1 overflow-hidden flex flex-col bg-slate-50/30">
                                <!-- Sub-tabs -->
                                <div class="flex-shrink-0 flex border-b border-slate-200 px-6 pt-4 gap-2 bg-white">
                                    <button v-for="tab in [
                                        { key: 'info',    label: 'Informasi', count: null },
                                        { key: 'peserta', label: 'Peserta',   count: detailData.total_peserta },
                                        { key: 'panitia', label: 'Panitia',   count: detailData.total_panitia },
                                        { key: 'log',     label: 'Log',       count: detailData.logs.length },
                                    ]" :key="tab.key"
                                        @click="detailTab = tab.key"
                                        class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all mr-1 rounded-t-lg flex items-center gap-1.5 cursor-pointer select-none"
                                        :class="detailTab === tab.key
                                            ? 'border-blue-600 text-blue-700 bg-blue-50/50'
                                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                                    >
                                        <!-- Real SVG Icons instead of Emojis -->
                                        <svg v-if="tab.key === 'info'" class="w-[15px] h-[15px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9Z" /></svg>
                                        <svg v-else-if="tab.key === 'peserta'" class="w-[15px] h-[15px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.978 11.978 0 0112 20.25a11.98 11.98 0 01-3-1.013v-.109m6 0c0-.529-.029-1.049-.086-1.56M9 19.128v-.003c0-1.113.285-2.16.786-3.07M9 19.128v.109A12.042 12.042 0 015 18.25c-1.391-.772-2.5-1.954-3.125-3.376a4.125 4.125 0 017.533-2.493m0 0A3.125 3.125 0 1112 9.75a3.125 3.125 0 01-2.593 3.031m4.5 4.52c0-.529-.029-1.049-.086-1.56m-4.5 1.56a12.188 12.188 0 01-3.97-1.56" /></svg>
                                        <svg v-else-if="tab.key === 'panitia'" class="w-[15px] h-[15px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0Zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0Zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0Z" /></svg>
                                        <svg v-else-if="tab.key === 'log'" class="w-[15px] h-[15px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>

                                        {{ tab.label }}
                                        <span v-if="tab.count !== null" class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full font-bold transition-colors"
                                              :class="detailTab === tab.key ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'">
                                            {{ tab.count }}
                                        </span>
                                    </button>
                                </div>

                                <!-- Tab Content Area -->
                                <div class="flex-1 overflow-y-auto p-6">

                                    <!-- ── TAB: INFORMASI UMUM ── -->
                                    <div v-if="detailTab === 'info'" class="space-y-5">

                                        <!-- Pemohon & PIC -->
                                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                                Pemohon &amp; Penanggung Jawab
                                            </h3>
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
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">No. HP PIC</p>
                                                    <p class="text-sm font-semibold text-slate-800">{{ detailData.booking.no_hp_pic ?? '-' }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Ruangan & Jadwal -->
                                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                                            <h3 class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12v-.008Z" /></svg>
                                                Ruangan &amp; Jadwal
                                            </h3>
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
                                            <h3 class="text-xs font-bold text-purple-600 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-purple-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                                Konfigurasi &amp; Fasilitas
                                            </h3>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Preferensi Layout</p>
                                                    <p class="text-sm font-semibold text-gray-800 capitalize">{{ layoutLabels[detailData.booking.layout_preferensi] ?? detailData.booking.layout_preferensi ?? '-' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Fase Pemesanan</p>
                                                    <p class="text-sm font-medium text-gray-700 capitalize flex items-center gap-1">
                                                        <svg v-if="detailData.booking.fase === 'reguler'" class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                        <svg v-else class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v5.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 013 18.375v-5.25zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125v-9.75zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v14.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                                                        {{ detailData.booking.fase }}
                                                    </p>
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
                                                    <div class="flex items-center gap-2">
                                                        <span :class="detailData.booking.is_pena_mini_note ? 'bg-teal-500' : 'bg-gray-300'" class="w-3 h-3 rounded-full flex-shrink-0"></span>
                                                        <span class="text-sm text-gray-700">Pena & Mini Note</span>
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
                                            <h4 class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                Catatan Admin
                                            </h4>
                                            <p class="text-sm text-gray-700">{{ detailData.booking.catatan_admin }}</p>
                                        </div>

                                        <!-- Timestamp -->
                                        <p class="text-xs text-gray-400 text-right flex items-center justify-end gap-1">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                                            Diajukan: {{ detailData.booking.created_at }}
                                        </p>
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
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-gray-500 uppercase">NRP</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Jabatan</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Site</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">No HP</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-gray-500 uppercase">JK</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-gray-500 uppercase w-12">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-50">
                                                    <tr v-for="(p, i) in detailData.peserta" :key="i" class="hover:bg-gray-50">
                                                        <td class="px-3 py-2 text-xs text-gray-400">{{ i + 1 }}</td>
                                                        <td class="px-3 py-2 text-xs font-semibold text-gray-800">{{ p.nama }}</td>
                                                        <td class="px-3 py-2 text-xs text-center">
                                                            <span v-if="!p.nrp || p.nrp.toUpperCase() === 'N/A'" class="inline-flex items-center gap-1 bg-gray-50 border border-gray-250/30 px-2 py-0.5 rounded text-[11px] text-gray-400 font-mono">
                                                                N/A
                                                                <span class="bg-gray-200/60 text-gray-500 font-normal px-1 py-0.2 rounded-[4px] text-[8px] uppercase tracking-wider font-sans select-none scale-90">Eksternal</span>
                                                            </span>
                                                            <span v-else class="inline-flex bg-blue-50 text-blue-750 text-[11px] font-extrabold font-mono px-2 py-0.5 rounded border border-blue-100">
                                                                {{ p.nrp }}
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-2 text-xs text-gray-600">{{ p.jabatan || '-' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600">{{ p.site || '-' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600 font-mono">{{ p.no_hp || '-' }}</td>
                                                        <td class="px-3 py-2 text-center">
                                                            <span class="text-xs font-bold"
                                                                  :class="p.gender === 'L' ? 'text-blue-600' : 'text-pink-600'">
                                                                {{ p.gender || '-' }}
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-2 text-center">
                                                            <button @click="openEditParticipantModal(p)" class="p-1 text-gray-450 hover:text-blue-600 hover:bg-blue-50 rounded transition" title="Edit Data">
                                                                <svg class="w-3.5 h-3.5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                                </svg>
                                                            </button>
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
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-gray-500 uppercase">NRP</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Jabatan</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">Site</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase">No HP</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-gray-500 uppercase">JK</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-gray-500 uppercase w-12">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-50">
                                                    <tr v-for="(p, i) in detailData.panitia" :key="i" class="hover:bg-gray-50">
                                                        <td class="px-3 py-2 text-xs text-gray-400">{{ i + 1 }}</td>
                                                        <td class="px-3 py-2 text-xs font-semibold text-gray-800">{{ p.nama }}</td>
                                                        <td class="px-3 py-2 text-xs text-center">
                                                            <span v-if="!p.nrp || p.nrp.toUpperCase() === 'N/A'" class="inline-flex items-center gap-1 bg-gray-50 border border-gray-250/30 px-2 py-0.5 rounded text-[11px] text-gray-400 font-mono">
                                                                N/A
                                                                <span class="bg-gray-200/60 text-gray-500 font-normal px-1 py-0.2 rounded-[4px] text-[8px] uppercase tracking-wider font-sans select-none scale-90">Eksternal</span>
                                                            </span>
                                                            <span v-else class="inline-flex bg-blue-50 text-blue-750 text-[11px] font-extrabold font-mono px-2 py-0.5 rounded border border-blue-100">
                                                                {{ p.nrp }}
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-2 text-xs text-gray-600">{{ p.jabatan || '-' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600">{{ p.site || '-' }}</td>
                                                        <td class="px-3 py-2 text-xs text-gray-600 font-mono">{{ p.no_hp || '-' }}</td>
                                                        <td class="px-3 py-2 text-center">
                                                            <span class="text-xs font-bold"
                                                                  :class="p.gender === 'L' ? 'text-blue-600' : 'text-pink-600'">
                                                                {{ p.gender || '-' }}
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-2 text-center">
                                                            <button @click="openEditParticipantModal(p)" class="p-1 text-gray-450 hover:text-blue-600 hover:bg-blue-50 rounded transition" title="Edit Data">
                                                                <svg class="w-3.5 h-3.5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                                </svg>
                                                            </button>
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
                                    <p class="text-xs text-gray-500 text-center">Menampilkan detail rekap booking</p>
                                </div>

                            </div><!-- /if detailData -->
                        </div><!-- /panel -->
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal: Edit Peserta/Panitia oleh Admin ──────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="showEditParticipantModal" class="fixed inset-0 bg-white/60 backdrop-blur-sm z-[70] flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200" @click.stop>
                        <div class="px-6 py-4 border-b border-gray-200 bg-white flex justify-between items-center">
                            <div>
                                <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                    Edit Data {{ editParticipantForm.tipe === 'panitia' ? 'Panitia' : 'Peserta' }}
                                </h2>
                                <p class="text-[11px] text-gray-500 mt-1">Ubah manifest data personil secara langsung.</p>
                            </div>
                            <button @click="showEditParticipantModal = false" class="text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 p-1.5 rounded-full transition-all cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="px-6 py-5">
                            <!-- Error Banner (General) -->
                            <div v-if="editParticipantErrors.general" class="mb-4 bg-red-50 border border-red-200 text-red-600 text-xs rounded-lg p-2.5 flex items-start gap-2">
                                <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>{{ editParticipantErrors.general }}</span>
                            </div>

                            <form @submit.prevent="submitEditParticipant" class="space-y-4">
                                <!-- Nama Lengkap -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="editParticipantForm.nama"
                                        type="text"
                                        required
                                        placeholder="Contoh: Budi Santoso"
                                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                                    />
                                    <p v-if="editParticipantErrors.nama" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.nama[0] }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- NRP -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">NRP / NIK</label>
                                        <input
                                            v-model="editParticipantForm.nrp"
                                            type="text"
                                            placeholder="Contoh: 12345678"
                                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                                        />
                                        <p v-if="editParticipantErrors.nrp" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.nrp[0] }}</p>
                                    </div>

                                    <!-- Jabatan -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Jabatan</label>
                                        <input
                                            v-model="editParticipantForm.jabatan"
                                            type="text"
                                            placeholder="Contoh: Operator"
                                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                                        />
                                        <p v-if="editParticipantErrors.jabatan" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.jabatan[0] }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Departemen / Divisi -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Departemen / Perusahaan</label>
                                        <input
                                            v-model="editParticipantForm.departemen"
                                            type="text"
                                            placeholder="Contoh: HC"
                                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                                        />
                                        <p v-if="editParticipantErrors.departemen" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.departemen[0] }}</p>
                                    </div>

                                    <!-- Site / Lokasi Kerja -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Site / Lokasi</label>
                                        <input
                                            v-model="editParticipantForm.site"
                                            type="text"
                                            placeholder="Contoh: ADRO"
                                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                                        />
                                        <p v-if="editParticipantErrors.site" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.site[0] }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- No HP -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">No HP</label>
                                        <input
                                            v-model="editParticipantForm.no_hp"
                                            type="text"
                                            placeholder="081234567..."
                                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                                        />
                                        <p v-if="editParticipantErrors.no_hp" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.no_hp[0] }}</p>
                                    </div>

                                    <!-- Jenis Kelamin -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Jenis Kelamin <span class="text-red-500">*</span></label>
                                        <select
                                            v-model="editParticipantForm.gender"
                                            required
                                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none bg-white font-sans"
                                        >
                                            <option value="L">Laki-laki (L)</option>
                                            <option value="P">Perempuan (P)</option>
                                        </select>
                                        <p v-if="editParticipantErrors.gender" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.gender[0] }}</p>
                                    </div>
                                </div>

                                <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                                    <button type="button" @click="showEditParticipantModal = false" :disabled="editParticipantProcessing" class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition disabled:opacity-50">
                                        Batal
                                    </button>
                                    <button type="submit" :disabled="editParticipantProcessing" class="flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition shadow-sm disabled:opacity-50">
                                        <span v-if="editParticipantProcessing" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>


</template>
