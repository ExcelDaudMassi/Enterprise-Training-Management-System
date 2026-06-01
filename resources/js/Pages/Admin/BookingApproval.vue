<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AdminLayout })
import axios from 'axios'

const props = defineProps({
    auth:         Object,
    bookings:     Object,   // Laravel paginator object
    activeFilter: { type: String, default: 'all' },
    search:       { type: String, default: '' },
})

// ─── Tabs ─────────────────────────────────────────────────────
const tabs = [
    { key: 'waiting_confirmation', label: 'Menunggu ACC' },
    { key: 'h14',                  label: '🚨 H-14 (ACC Final)' },
    { key: 'overdue',              label: '⛔ Lewat Tenggat' },
    { key: 'date_changes',         label: '📅 Ubah Tanggal' },
    { key: 'confirmed',            label: 'Confirmed' },
    { key: 'final',                label: '✅ Final' },
    { key: 'cancelled',            label: 'Dibatalkan' },
    { key: 'all',                  label: 'Semua' },
]

const activeTab = ref(props.activeFilter ?? 'all')
const searchQuery = ref(props.search ?? '')

function switchTab(key) {
    activeTab.value = key
    searchQuery.value = ''
    router.get('/admin/bookings', { filter: key }, { preserveScroll: true, preserveState: true })
}

function doSearch() {
    router.get('/admin/bookings', { filter: activeTab.value, search: searchQuery.value }, { preserveScroll: true, preserveState: true })
}

function goToPage(url) {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true })
}

const STATUS_META = {
    waiting_confirmation: { label: 'Menunggu',  class: 'bg-yellow-100 text-yellow-800 border border-yellow-200' },
    confirmed:            { label: 'Disetujui', class: 'bg-blue-100 text-blue-800 border border-blue-200' },
    final:                { label: 'ACC Final', class: 'bg-indigo-100 text-indigo-800 border border-indigo-200' },
    cancelled:            { label: 'Ditolak',   class: 'bg-red-100 text-red-800 border border-red-200' },
    plotting:             { label: 'Pending',  class: 'bg-amber-100 text-amber-800 border border-amber-200' },
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

// ── Approve / Reject / ACC-2 ─────────────────────────────────────
const showApproveModal = ref(false)
const showRejectModal  = ref(false)
const showAcc2Modal    = ref(false)
const selectedBooking  = ref(null)

const rejectForm  = useForm({ catatan_admin: '' })
const approveForm = useForm({})
const acc2Form    = useForm({})

function openApprove(b) {
    selectedBooking.value = b
    showApproveModal.value = true
}
function confirmApprove(b) {
    selectedBooking.value = b
    showApproveModal.value = true
}

function openReject(b) {
    selectedBooking.value = b
    rejectForm.reset()
    showRejectModal.value = true
}
function confirmReject(b) {
    selectedBooking.value = b
    rejectForm.reset()
    showRejectModal.value = true
}

function confirmAcc2(b) {
    selectedBooking.value = b
    showAcc2Modal.value = true
}

function submitApprove() {
    approveForm.post(`/admin/bookings/${selectedBooking.value.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => { showApproveModal.value = false; selectedBooking.value = null }
    })
}

// ─── Modal: ACC Tahap 1 Reject ────────────────────────────────
function submitReject() {
    rejectForm.post(`/admin/bookings/${selectedBooking.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => { showRejectModal.value = false; selectedBooking.value = null; rejectForm.reset() }
    })
}

function submitAcc2() {
    if (!selectedBooking.value) return
    acc2Form.post(`/admin/bookings/${selectedBooking.value.id}/acc2`, {
        preserveScroll: true,
        onSuccess: () => { showAcc2Modal.value = false; selectedBooking.value = null }
    })
}

// ─── Modal: ACC Final (Tahap 4) ───────────────────────────────
const showFinalModal  = ref(false)
const finalForm       = useForm({})

function openFinal(b) {
    selectedBooking.value = b
    showFinalModal.value = true
}
function submitFinal() {
    finalForm.post(`/admin/bookings/${selectedBooking.value.id}/final`, {
        preserveScroll: true,
        onSuccess: () => { showFinalModal.value = false }
    })
}

// ─── Modal: ACC Terlambat (Tahap 5) ──────────────────────────
const showFinalLateModal  = ref(false)
const finalLateForm       = useForm({ catatan_acc_terlambat: '' })

function openFinalLate(b) {
    selectedBooking.value = b
    finalLateForm.reset()
    showFinalLateModal.value = true
}
function submitFinalLate() {
    finalLateForm.post(`/admin/bookings/${selectedBooking.value.id}/final-late`, {
        preserveScroll: true,
        onSuccess: () => { showFinalLateModal.value = false; finalLateForm.reset() }
    })
}

// ─── Modal: Setujui Perubahan Tanggal ────────────────────────
const showApproveDateModal = ref(false)
const approveDateForm      = useForm({})

function openApproveDate(b) {
    selectedBooking.value = b
    showApproveDateModal.value = true
}
function submitApproveDate() {
    approveDateForm.post(`/admin/bookings/${selectedBooking.value.id}/approve-date-change`, {
        preserveScroll: true,
        onSuccess: () => { showApproveDateModal.value = false }
    })
}

// ─── Modal: Tolak Perubahan Tanggal ──────────────────────────
const showRejectDateModal = ref(false)
const rejectDateForm      = useForm({ catatan_admin: '' })

function openRejectDate(b) {
    selectedBooking.value = b
    rejectDateForm.reset()
    showRejectDateModal.value = true
}
function submitRejectDate() {
    rejectDateForm.post(`/admin/bookings/${selectedBooking.value.id}/reject-date-change`, {
        preserveScroll: true,
        onSuccess: () => { showRejectDateModal.value = false; rejectDateForm.reset() }
    })
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
</script>

<template>
        <!-- ── Page Header ─────────────────────────────────────── -->
        <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent">Manajemen Booking</h1>
                <p class="text-sm text-slate-500 mt-1 font-medium">Kelola dan proses pengajuan peminjaman ruangan dengan mudah</p>
            </div>
        </div>


        <!-- Tabs -->
        <div class="flex flex-wrap items-center gap-1.5 mb-5 bg-slate-100/80 backdrop-blur p-1.5 rounded-xl w-fit shadow-inner">
            <button
                v-for="tab in tabs" :key="tab.key"
                @click="switchTab(tab.key)"
                class="px-4 py-2 rounded-lg text-xs font-bold transition-all duration-300"
                :class="activeTab === tab.key
                    ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-200/50 scale-100'
                    : 'text-slate-500 hover:text-slate-700 hover:bg-slate-200/50 hover:scale-95'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Search Bar -->
        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div class="relative flex-1 max-w-md group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-blue-500 text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                </div>
                <input
                    v-model="searchQuery"
                    @keyup.enter="doSearch"
                    type="text"
                    placeholder="Cari nama acara, pemohon, divisi..."
                    class="w-full pl-11 pr-4 py-2.5 text-sm border-0 ring-1 ring-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 bg-white shadow-sm transition-all placeholder:text-slate-400"
                />
            </div>
            <button @click="doSearch"
                class="px-5 py-2.5 text-sm font-bold bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl shadow-sm hover:shadow transition-all active:scale-95">
                Cari
            </button>
            <button v-if="searchQuery" @click="searchQuery = ''; doSearch()"
                class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-white ring-1 ring-slate-200 hover:bg-slate-50 rounded-xl transition-all active:scale-95 shadow-sm">
                Reset
            </button>
            <div class="ml-auto flex items-center">
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                    {{ bookings.total }} hasil ditemukan
                </span>
            </div>
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
                        <tr v-if="bookings.data.length === 0">
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center border border-slate-100 mb-2">
                                        <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    </div>
                                    <span class="text-sm font-semibold text-slate-500">Tidak ada data booking</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-slate-50/50 transition-all duration-300 align-top group">
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
                                <!-- Countdown -->
                                <div v-if="b.days_to_start !== null && b.status === 'confirmed' && b.days_to_start >= 0"
                                     class="mt-2 font-bold text-[11px] w-fit shadow-sm"
                                     :class="b.days_to_start <= 14 ? 'text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-md' : 'text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md'">
                                    H-{{ b.days_to_start }}
                                </div>
                                <div v-if="b.is_overdue_acc2" class="text-red-600 font-bold mt-2 flex items-center gap-1.5 text-[11px] bg-red-50 px-2 py-1 rounded border border-red-100">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    Tanggal lewat
                                </div>
                                <!-- Usulan Ubah Tanggal -->
                                <div v-if="b.has_pending_date_change" class="mt-2 text-orange-700 text-[11px] font-bold bg-orange-50 border border-orange-200 p-1.5 rounded-md shadow-sm">
                                    📅 Usulan: {{ formatDate(b.proposed_tgl_mulai) }} – {{ formatDate(b.proposed_tgl_selesai) }}
                                </div>
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
                                <div v-if="!b.is_hybrid && !b.is_flipchart" class="text-slate-300 font-medium">—</div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="px-3 py-1.5 rounded-full text-[11px] font-bold shadow-sm"
                                      :class="STATUS_META[b.status]?.class ?? 'bg-slate-100 text-slate-600 border border-slate-200'">
                                    {{ STATUS_META[b.status]?.label ?? b.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-col gap-2">
                                    <!-- Detail Button -->
                                    <button @click="openDetail(b)"
                                        class="group/btn flex items-center justify-center gap-1.5 bg-white hover:bg-slate-50 text-slate-700 hover:text-blue-600 text-[11px] font-bold py-2 px-3 rounded-xl transition-all border border-slate-200 hover:border-blue-300 shadow-sm hover:shadow">
                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover/btn:text-blue-500 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Lihat Detail
                                    </button>
                                    <!-- Approve/Reject (only for waiting) -->
                                    <div v-if="b.status === 'waiting_confirmation'" class="grid grid-cols-2 gap-2">
                                        <button @click="confirmApprove(b)"
                                            class="flex items-center justify-center gap-1 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white text-[11px] font-bold py-2 px-2 rounded-xl transition-all shadow-sm hover:shadow active:scale-95">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            Setuju
                                        </button>
                                        <button @click="confirmReject(b)"
                                            class="flex items-center justify-center gap-1 bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 text-[11px] font-bold py-2 px-2 rounded-xl transition-all shadow-sm hover:shadow active:scale-95">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Tolak
                                        </button>
                                    </div>
                                    <!-- Tahap 3: Perubahan Tanggal -->
                                    <template v-if="b.has_pending_date_change">
                                        <button @click="openApproveDate(b)"
                                                class="bg-blue-600 hover:bg-blue-700 text-white text-[11px] py-2 px-2 rounded-xl transition-all font-bold shadow-sm active:scale-95">
                                            ✓ ACC Ubah Tanggal
                                        </button>
                                        <button @click="openRejectDate(b)"
                                                class="bg-white hover:bg-orange-50 border border-orange-200 text-orange-600 text-[11px] py-2 px-2 rounded-xl transition-all font-bold shadow-sm active:scale-95">
                                            ✕ Tolak Ubah
                                        </button>
                                    </template>
                                    <!-- Tahap 4: ACC Final / Final Confirm -->
                                    <template v-if="b.can_be_finalized && !b.is_overdue_acc2">
                                        <button @click="openFinal(b)"
                                                class="w-full flex items-center justify-center gap-1.5 bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white text-[11px] font-bold py-2 px-2 rounded-xl transition-all shadow-sm hover:shadow active:scale-95">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            🏁 ACC Final
                                        </button>
                                    </template>
                                    <!-- Tahap 5: ACC Terlambat -->
                                    <template v-if="b.can_be_finalized && b.is_overdue_acc2">
                                        <button @click="openFinalLate(b)"
                                                class="w-full flex items-center justify-center gap-1.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white text-[11px] font-bold py-2 px-2 rounded-xl transition-all shadow-sm hover:shadow active:scale-95">
                                            ⚠️ ACC Terlambat
                                        </button>
                                    </template>
                                    <!-- Catatan penolakan -->
                                    <div v-if="b.status === 'cancelled' && b.catatan_admin" class="text-[10px] text-rose-600 font-medium bg-rose-50 border border-rose-100 p-1.5 rounded-lg line-clamp-2 mt-1" :title="b.catatan_admin">
                                        ⚠ {{ b.catatan_admin }}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div v-if="bookings.last_page > 1" class="flex items-center justify-between mt-4">
            <p class="text-xs text-gray-500">
                Menampilkan <span class="font-semibold">{{ bookings.from }}</span>–<span class="font-semibold">{{ bookings.to }}</span>
                dari <span class="font-semibold">{{ bookings.total }}</span> booking
            </p>
            <div class="flex items-center gap-1">
                <!-- Prev -->
                <button @click="goToPage(bookings.prev_page_url)"
                    :disabled="!bookings.prev_page_url"
                    class="px-3 py-1.5 text-xs rounded border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    ← Prev
                </button>

                <!-- Page numbers -->
                <template v-for="link in bookings.links" :key="link.label">
                    <button v-if="link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;'"
                        @click="goToPage(link.url)"
                        :class="[
                            'px-3 py-1.5 text-xs rounded border transition',
                            link.active
                                ? 'bg-blue-600 text-white border-blue-600 font-bold'
                                : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'
                        ]"
                        :disabled="!link.url"
                        v-html="link.label"
                    />
                </template>

                <!-- Next -->
                <button @click="goToPage(bookings.next_page_url)"
                    :disabled="!bookings.next_page_url"
                    class="px-3 py-1.5 text-xs rounded border border-gray-200 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    Next →
                </button>
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
                                                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold shadow-sm"
                                                  :class="STATUS_META[detailData.booking.status]?.class ?? 'bg-slate-100 text-slate-600'">
                                                {{ STATUS_META[detailData.booking.status]?.label ?? detailData.booking.status }}
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
                                        <svg v-else-if="tab.key === 'log'" class="w-[15px] h-[15px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0Z" /></svg>

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
                                            <h3 class="text-xs font-bold text-blue-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
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
                                            <h3 class="text-xs font-bold text-emerald-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" /></svg>
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
                                            <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                                                Konfigurasi &amp; Fasilitas
                                            </h3>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Preferensi Layout</p>
                                                    <p class="text-sm font-semibold text-gray-800 capitalize">{{ layoutLabels[detailData.booking.layout_preferensi] ?? detailData.booking.layout_preferensi ?? '-' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[11px] text-gray-400 mb-0.5">Fase Pemesanan</p>
                                                    <p class="text-sm font-bold text-gray-800 capitalize">{{ detailData.booking.fase === 'reguler' ? 'Reguler' : 'Plotting' }}</p>
                                                </div>
                                                <div class="flex items-center gap-3 col-span-2">
                                                    <div class="flex items-center gap-2">
                                                        <span :class="detailData.booking.is_hybrid ? 'bg-purple-500' : 'bg-gray-300'" class="w-3 h-3 rounded-full flex-shrink-0"></span>
                                                        <span class="text-sm text-gray-700">Hybrid</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span :class="detailData.booking.is_flipchart ? 'bg-orange-500' : 'bg-gray-300'" class="w-3 h-3 rounded-full flex-shrink-0"></span>
                                                        <span class="text-sm text-gray-700">Flipchart</span>
                                                    </div>
                                                </div>
                                                <div v-if="detailData.booking.layout_url" class="col-span-2">
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
                                                <p class="text-2xl font-black text-gray-700">{{ detailData.booking.status === 'confirmed' ? '1/2' : (detailData.booking.status === 'final' ? '2/2' : '0/2') }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">Tahap ACC</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ── TAB: DAFTAR PESERTA ── -->
                                    <div v-if="detailTab === 'peserta'">
                                        <div class="mb-4 flex items-center justify-between">
                                            <h3 class="text-sm font-bold text-gray-800">Daftar Peserta <span class="text-gray-400 font-normal">({{ detailData.total_peserta }} orang)</span></h3>
                                            <!-- Gender Stats -->
                                            <div class="flex gap-3 text-xs">
                                                <span class="text-blue-600 font-semibold">♂ L: {{ detailData.gender_stats?.L ?? 0 }}</span>
                                                <span class="text-pink-600 font-semibold">♀ P: {{ detailData.gender_stats?.P ?? 0 }}</span>
                                            </div>
                                        </div>
                                        <div v-if="detailData.peserta.length === 0" class="py-8 text-center text-gray-400 text-sm">Tidak ada data peserta.</div>
                                        <div v-else class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                                            <table class="min-w-full divide-y divide-gray-100">
                                                <thead class="bg-blue-50/60 border-b border-blue-100/80">
                                                    <tr>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase w-8">No</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase">Nama Lengkap</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-bold text-blue-900 uppercase">NRP</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase">Jabatan</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase">Site</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase">No HP</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-bold text-blue-900 uppercase">JK</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-bold text-blue-900 uppercase w-12">Aksi</th>
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
                                                <thead class="bg-blue-50/60 border-b border-blue-100/80">
                                                    <tr>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase w-8">No</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase">Nama Lengkap</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-bold text-blue-900 uppercase">NRP</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase">Jabatan</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase">Site</th>
                                                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-blue-900 uppercase">No HP</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-bold text-blue-900 uppercase">JK</th>
                                                        <th class="px-3 py-2.5 text-center text-[10px] font-bold text-blue-900 uppercase w-12">Aksi</th>
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

                                <!-- Panel Footer: Approve / Reject / ACC-2 Actions -->
                                <div v-if="detailData.booking.status === 'waiting_confirmation'"
                                     class="flex-shrink-0 border-t border-gray-100 px-6 py-4 bg-gray-50 flex gap-3">
                                    <button @click="closeDetail(); confirmReject({ id: detailData.booking.id, nama_training: detailData.booking.nama_training })"
                                        class="flex-1 px-4 py-2.5 bg-red-100 hover:bg-red-200 border border-red-200 text-red-700 rounded-lg text-sm font-semibold transition">
                                        ✕ Tolak Booking
                                    </button>
                                    <button @click="closeDetail(); confirmApprove({ id: detailData.booking.id, nama_training: detailData.booking.nama_training, pemohon: detailData.booking.pemohon?.name, divisi: detailData.booking.pemohon?.divisi, ruangan: detailData.booking.ruangan?.nama_ruang ?? 'Ruang Gabungan', tgl_mulai: detailData.booking.tgl_mulai, tgl_selesai: detailData.booking.tgl_selesai })"
                                        class="flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold transition shadow-sm">
                                        ✓ Setujui Booking
                                    </button>
                                </div>
                                <div v-if="detailData.booking.status === 'confirmed'"
                                     class="flex-shrink-0 border-t px-6 py-4 flex gap-3 flex-col"
                                     :class="isPastH14(detailData.booking.tgl_mulai) ? 'border-red-100 bg-red-50' : 'border-emerald-100 bg-emerald-50'">
                                    
                                    <div v-if="isPastH14(detailData.booking.tgl_mulai)" class="text-xs text-red-700 font-semibold flex items-center gap-1.5 mb-1 bg-white px-3 py-2 rounded-md border border-red-200">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        Booking ini sudah melewati batas waktu ACC-2 (H-14). Silakan segera proses manual.
                                    </div>

                                    <button @click="closeDetail(); confirmAcc2({ id: detailData.booking.id, nama_training: detailData.booking.nama_training })"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-white rounded-lg text-sm font-semibold transition shadow-sm"
                                        :class="isPastH14(detailData.booking.tgl_mulai) ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Beri Final Confirmation (ACC-2)
                                    </button>
                                </div>

                            </div><!-- /if detailData -->
                        </div><!-- /panel -->
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal: Konfirmasi Approve ──────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showApproveModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-white">
                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                Konfirmasi Persetujuan
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-1">Ruangan akan dikunci setelah booking disetujui.</p>
                        </div>
                        <div class="px-6 py-4 space-y-3 bg-gray-50">
                            <div><span class="text-xs text-gray-500 block">Nama Training</span><span class="text-sm font-bold text-gray-800">{{ selectedBooking?.nama_training }}</span></div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><span class="text-xs text-gray-500 block">Pemohon</span><span class="text-sm font-bold text-gray-800">{{ selectedBooking?.pemohon }} ({{ selectedBooking?.divisi }})</span></div>
                                <div><span class="text-xs text-gray-500 block">Ruangan</span><span class="text-sm font-bold text-gray-800">{{ selectedBooking?.ruangan }}</span></div>
                            </div>
                            <div><span class="text-xs text-gray-500 block">Jadwal</span><span class="text-sm font-bold text-gray-800">{{ selectedBooking?.tgl_mulai }} – {{ selectedBooking?.tgl_selesai }}</span></div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3 px-6 pb-6">
                            <button @click="showApproveModal = false" :disabled="approveForm.processing" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none disabled:opacity-50">Batal</button>
                            <button @click="submitApprove" :disabled="approveForm.processing" class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-md transition shadow-sm cursor-pointer select-none disabled:opacity-50">
                                <span v-if="approveForm.processing" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                Setujui Booking
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal: Konfirmasi ACC-2 (Final Confirm) ──────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showAcc2Modal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200" @click.stop>
                        <div class="px-6 py-4 border-b border-gray-200 bg-white">
                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Final Confirmation (ACC-2)
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-1">Konfirmasi akhir sebelum persiapan ruangan dimulai.</p>
                        </div>
                        <div class="px-6 py-5 bg-gray-50 space-y-3">
                            <p class="text-xs text-gray-600 leading-relaxed">
                                Anda akan memberikan Final Confirmation untuk acara:
                                <strong class="text-gray-800 block mt-1 text-sm font-bold">"{{ selectedBooking?.nama_training }}"</strong>
                            </p>
                            <p class="text-xs text-blue-900 bg-blue-50 p-2.5 rounded border border-blue-100 font-medium">
                                Pastikan persiapan ruangan dan logistik sudah siap sesuai kebutuhan.
                            </p>
                        </div>
                        <div class="px-6 pb-6 pt-4 flex justify-end gap-3 bg-white">
                            <button @click="showAcc2Modal = false" :disabled="acc2Form.processing" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none disabled:opacity-50">Batal</button>
                            <button @click="submitAcc2" :disabled="acc2Form.processing" class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-md transition shadow-sm cursor-pointer select-none disabled:opacity-50">
                                <span v-if="acc2Form.processing" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                Final Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal: Konfirmasi Tolak ──────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showRejectModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-white">
                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                Penolakan Booking
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-1">Booking <strong>{{ selectedBooking?.nama_training }}</strong> akan dibatalkan.</p>
                        </div>
                        <form @submit.prevent="submitReject" class="px-6 py-5">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1.5">Alasan Penolakan <span class="text-red-500">*</span></label>
                            <textarea
                                v-model="rejectForm.catatan_admin"
                                required
                                rows="3"
                                placeholder="Contoh: Jadwal bentrok, ruangan sedang direnovasi..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                            ></textarea>
                            <p v-if="rejectForm.errors.catatan_admin" class="text-xs text-red-500 mt-1">{{ rejectForm.errors.catatan_admin }}</p>

                            <div class="mt-5 flex gap-3">
                                <button type="button" @click="showRejectModal = false" class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 cursor-pointer select-none transition">Batal</button>
                                <button type="submit" :disabled="rejectForm.processing || !rejectForm.catatan_admin"
                                    class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white rounded-md text-sm font-bold cursor-pointer select-none transition shadow-sm">
                                    {{ rejectForm.processing ? 'Menyimpan...' : '✕ Tolak Booking' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal: ACC Final ───────────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showFinalModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-white">
                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z" /></svg>
                                Konfirmasi ACC Final
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-1">User tidak dapat mengubah booking setelah tahap ini.</p>
                        </div>
                        <div class="px-6 py-5 bg-gray-50 space-y-3">
                            <div class="text-sm text-gray-700 space-y-2 bg-white p-4 rounded-lg border border-gray-200">
                                <div><span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block">Training</span> <strong class="text-gray-800 text-sm">{{ selectedBooking?.nama_training }}</strong></div>
                                <div><span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block">Jadwal</span> <strong class="text-gray-800">{{ formatDate(selectedBooking?.tgl_mulai) }} – {{ formatDate(selectedBooking?.tgl_selesai) }}</strong></div>
                                <div><span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block">Kapasitas</span> <strong class="text-gray-800">{{ selectedBooking?.jumlah_peserta }}</strong> peserta, <strong class="text-gray-800">{{ selectedBooking?.jumlah_panitia }}</strong> panitia</div>
                            </div>
                            <p class="text-[11px] text-blue-900 font-semibold bg-blue-50 p-2.5 rounded border border-blue-100 leading-relaxed">
                                Setelah ACC Final, WhatsApp Notifikasi otomatis akan dikirim ke Frontdesk untuk persiapan fisik ruangan.
                            </p>
                        </div>
                        <div class="px-6 pb-6 pt-4 flex gap-3 bg-white justify-end">
                            <button @click="showFinalModal = false" :disabled="finalForm.processing" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none">Batal</button>
                            <button @click="submitFinal" :disabled="finalForm.processing" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-bold transition shadow-sm cursor-pointer select-none disabled:opacity-50">
                                <span v-if="finalForm.processing" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                ACC &amp; Finalize
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal: ACC Terlambat ───────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showFinalLateModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-white">
                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                ACC Terlambat (Backdate)
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-1">Tanggal training sudah lewat. Mohon isi alasan.</p>
                        </div>
                        <form @submit.prevent="submitFinalLate" class="px-6 py-5">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1.5">Alasan ACC Terlambat <span class="text-red-500">*</span></label>
                            <textarea v-model="finalLateForm.catatan_acc_terlambat" required rows="3"
                                      placeholder="Contoh: Training tetap berjalan, laporan menyusul..."
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition"></textarea>
                            <p v-if="finalLateForm.errors.catatan_acc_terlambat" class="text-xs text-red-500 mt-1">
                                {{ finalLateForm.errors.catatan_acc_terlambat }}
                            </p>
                            <div class="flex gap-3 mt-5">
                                <button type="button" @click="showFinalLateModal = false"
                                        class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 cursor-pointer transition select-none">Batal</button>
                                <button type="submit" :disabled="finalLateForm.processing || !finalLateForm.catatan_acc_terlambat"
                                        class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-bold transition shadow-sm cursor-pointer select-none disabled:opacity-50">
                                    <span v-if="finalLateForm.processing" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin inline-block mr-1"></span>
                                    {{ finalLateForm.processing ? 'Menyimpan...' : 'Konfirmasi ACC' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal: Setujui Ubah Tanggal ────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showApproveDateModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-white">
                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                Setujui Perubahan Tanggal
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-1">Persetujuan untuk proposal tanggal baru dari user.</p>
                        </div>
                        <div class="px-6 py-5 bg-gray-50 space-y-3">
                            <div class="text-sm text-gray-700 space-y-3 bg-white p-4 rounded-lg border border-gray-200">
                                <div><span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block mb-0.5">Training</span> <strong class="text-gray-800 text-sm">{{ selectedBooking?.nama_training }}</strong></div>
                                <div class="mt-2 border-t border-gray-150 pt-2">
                                    <span class="text-[10px] text-gray-400 uppercase tracking-widest block mb-0.5">Tanggal Lama</span>
                                    <div class="text-gray-400 line-through text-xs font-semibold">
                                        {{ formatDate(selectedBooking?.tgl_mulai) }} – {{ formatDate(selectedBooking?.tgl_selesai) }}
                                    </div>
                                </div>
                                <div class="mt-2 border-t border-gray-150 pt-2">
                                    <span class="text-[10px] text-gray-400 uppercase tracking-widest block mb-0.5">Tanggal Baru</span>
                                    <div class="text-blue-700 font-bold text-sm">
                                        {{ formatDate(selectedBooking?.proposed_tgl_mulai) }} – {{ formatDate(selectedBooking?.proposed_tgl_selesai) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 pb-6 pt-4 flex gap-3 bg-white justify-end">
                            <button @click="showApproveDateModal = false" :disabled="approveDateForm.processing" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none">Batal</button>
                            <button @click="submitApproveDate" :disabled="approveDateForm.processing" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-bold transition shadow-sm cursor-pointer select-none disabled:opacity-50">
                                <span v-if="approveDateForm.processing" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                Setujui Perubahan
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal: Tolak Ubah Tanggal ──────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showRejectDateModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-white">
                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                Tolak Perubahan Tanggal
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-1">Tanggal lama akan dipertahankan. Status tetap Confirmed.</p>
                        </div>
                        <form @submit.prevent="submitRejectDate" class="px-6 py-5">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1.5">Alasan Penolakan <span class="text-red-500">*</span></label>
                            <textarea v-model="rejectDateForm.catatan_admin" required rows="3"
                                      placeholder="Contoh: Tanggal baru bentrok dengan jadwal VIP..."
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition"></textarea>
                            <div class="flex gap-3 mt-5">
                                <button type="button" @click="showRejectDateModal = false"
                                        class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition select-none">Batal</button>
                                <button type="submit" :disabled="rejectDateForm.processing || !rejectDateForm.catatan_admin"
                                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white rounded-md text-sm font-bold transition shadow-sm cursor-pointer select-none">
                                    {{ rejectDateForm.processing ? 'Memproses...' : 'Tolak Perubahan' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
        <!-- ── Modal: Edit Peserta/Panitia oleh Admin ──────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showEditParticipantModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[70] flex items-center justify-center p-4">
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
                        <div class="px-6 py-5 bg-gray-50">

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
                                    placeholder="Masukkan nama lengkap"
                                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                />
                                <p v-if="editParticipantErrors.nama" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.nama[0] }}</p>
                            </div>

                            <!-- NRP -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">NRP <span class="text-red-500">*</span></label>
                                <input
                                    v-model="editParticipantForm.nrp"
                                    type="text"
                                    required
                                    placeholder="NRP (contoh: 10425, atau ketik 'N/A' jika eksternal)"
                                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                />
                                <p v-if="editParticipantErrors.nrp" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.nrp[0] }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Jabatan -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Jabatan</label>
                                    <input
                                        v-model="editParticipantForm.jabatan"
                                        type="text"
                                        placeholder="Staff, Manager, dll."
                                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                    />
                                    <p v-if="editParticipantErrors.jabatan" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.jabatan[0] }}</p>
                                </div>

                                <!-- Site -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Site</label>
                                    <input
                                        v-model="editParticipantForm.site"
                                        type="text"
                                        placeholder="Site kerja"
                                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
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
                                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                    />
                                    <p v-if="editParticipantErrors.no_hp" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.no_hp[0] }}</p>
                                </div>

                                <!-- Jenis Kelamin -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Jenis Kelamin <span class="text-red-500">*</span></label>
                                    <select
                                        v-model="editParticipantForm.gender"
                                        required
                                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none bg-white font-sans transition"
                                    >
                                        <option value="L">Laki-laki (L)</option>
                                        <option value="P">Perempuan (P)</option>
                                    </select>
                                    <p v-if="editParticipantErrors.gender" class="text-[11px] text-red-500 mt-1">{{ editParticipantErrors.gender[0] }}</p>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button" @click="showEditParticipantModal = false" :disabled="editParticipantProcessing" class="px-4 py-2 text-xs font-bold text-gray-700 border border-gray-300 hover:bg-gray-50 rounded-md transition cursor-pointer select-none disabled:opacity-50">
                                    Batal
                                </button>
                                <button type="submit" :disabled="editParticipantProcessing" class="flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-md transition shadow-sm cursor-pointer select-none disabled:opacity-50">
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
