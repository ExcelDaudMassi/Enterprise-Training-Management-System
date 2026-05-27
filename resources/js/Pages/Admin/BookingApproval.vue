<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
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

// ─── Status display helpers ───────────────────────────────────
const STATUS_META = {
    waiting_confirmation: { label: 'Menunggu',  class: 'bg-yellow-100 text-yellow-800 border border-yellow-200' },
    confirmed:            { label: 'Disetujui', class: 'bg-blue-100 text-blue-800 border border-blue-200' },
    final:                { label: 'Final ACC / Persiapan Lapangan', class: 'bg-indigo-100 text-indigo-800 border border-indigo-200' },
    cancelled:            { label: 'Ditolak',   class: 'bg-red-100 text-red-800 border border-red-200' },
    plotting:             { label: 'Plotting',  class: 'bg-purple-100 text-purple-800 border border-purple-200' },
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
    <AdminLayout :auth="auth">
        <!-- ── Page Header ─────────────────────────────────────── -->
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">⚙️ Manajemen Booking</h1>
                <p class="text-sm text-gray-500 mt-1">Kelola dan proses pengajuan peminjaman ruangan</p>
            </div>
        </div>


        <!-- Tabs -->
        <div class="flex flex-wrap gap-1 mb-4 bg-gray-100 p-1 rounded w-fit">
            <button
                v-for="tab in tabs" :key="tab.key"
                @click="switchTab(tab.key)"
                class="px-3 py-1.5 rounded text-xs font-medium transition"
                :class="activeTab === tab.key
                    ? 'bg-white text-gray-800 shadow-sm'
                    : 'text-gray-500 hover:text-gray-700'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Search Bar -->
        <div class="flex items-center gap-2 mb-4">
            <div class="relative flex-1 max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
                <input
                    v-model="searchQuery"
                    @keyup.enter="doSearch"
                    type="text"
                    placeholder="Cari nama acara, pemohon, divisi..."
                    class="w-full pl-9 pr-4 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 bg-white"
                />
            </div>
            <button @click="doSearch"
                class="px-4 py-2 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                Cari
            </button>
            <button v-if="searchQuery" @click="searchQuery = ''; doSearch()"
                class="px-3 py-2 text-xs text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg transition">
                Reset
            </button>
            <span class="text-xs text-gray-400 ml-auto">
                {{ bookings.total }} hasil ditemukan
            </span>
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
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <tr v-if="bookings.data.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                Tidak ada data booking untuk tab ini.
                            </div>
                        </td>
                    </tr>
                    <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-gray-50 transition-colors align-top">
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
                            <!-- Countdown -->
                            <div v-if="b.days_to_start !== null && b.status === 'confirmed' && b.days_to_start >= 0"
                                 class="mt-1 font-semibold text-[11px]"
                                 :class="b.days_to_start <= 14 ? 'text-red-600 bg-red-50 border border-red-100 px-1 py-0.5 rounded inline-block' : 'text-gray-500 bg-gray-50 border border-gray-100 px-1 py-0.5 rounded inline-block'">
                                H-{{ b.days_to_start }}
                            </div>
                            <div v-if="b.is_overdue_acc2" class="text-red-600 font-bold mt-1.5 flex items-center gap-1 text-[11px]">
                                ⛔ Tanggal sudah lewat
                            </div>
                            <!-- Usulan Ubah Tanggal -->
                            <div v-if="b.has_pending_date_change" class="mt-1.5 text-orange-600 text-[11px] font-bold bg-orange-50 border border-orange-100 p-1 rounded">
                                📅 Usulan: {{ formatDate(b.proposed_tgl_mulai) }} – {{ formatDate(b.proposed_tgl_selesai) }}
                            </div>
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
                                  :class="STATUS_META[b.status]?.class ?? 'bg-gray-100 text-gray-600 border border-gray-200'">
                                {{ STATUS_META[b.status]?.label ?? b.status }}
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
                                <!-- Approve/Reject (only for waiting) -->
                                <div v-if="b.status === 'waiting_confirmation'" class="flex gap-1.5">
                                    <button @click="confirmApprove(b)"
                                        class="flex-1 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold py-1.5 px-2 rounded-lg transition">
                                        ✓ Setujui
                                    </button>
                                    <button @click="confirmReject(b)"
                                        class="flex-1 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold py-1.5 px-2 rounded-lg transition border border-red-200">
                                        ✕ Tolak
                                    </button>
                                </div>
                                <!-- Tahap 3: Perubahan Tanggal -->
                                <template v-if="b.has_pending_date_change">
                                    <button @click="openApproveDate(b)"
                                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs py-1.5 px-2 rounded-lg transition font-semibold">
                                        ✓ ACC Ubah Tanggal
                                    </button>
                                    <button @click="openRejectDate(b)"
                                            class="bg-orange-100 hover:bg-orange-200 text-orange-700 text-xs py-1.5 px-2 rounded-lg transition font-semibold border border-orange-200">
                                        ✕ Tolak Ubah Tanggal
                                    </button>
                                </template>
                                <!-- Tahap 4: ACC Final / Final Confirm -->
                                <template v-if="b.can_be_finalized && !b.is_overdue_acc2">
                                    <button @click="openFinal(b)"
                                            class="w-full flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold py-1.5 px-2 rounded-lg transition shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        🏁 ACC Final
                                    </button>
                                </template>
                                <!-- Tahap 5: ACC Terlambat -->
                                <template v-if="b.can_be_finalized && b.is_overdue_acc2">
                                    <button @click="openFinalLate(b)"
                                            class="w-full flex items-center justify-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold py-1.5 px-2 rounded-lg transition shadow-sm">
                                        ⚠️ ACC Terlambat
                                    </button>
                                </template>
                                <!-- Catatan penolakan jika ditolak -->
                                <div v-if="b.status === 'cancelled' && b.catatan_admin" class="text-xs text-red-600 italic max-w-[160px] truncate" :title="b.catatan_admin">
                                    ⚠ {{ b.catatan_admin }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
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
                                                  :class="STATUS_META[detailData.booking.status]?.class ?? 'bg-gray-100 text-gray-600'">
                                                {{ STATUS_META[detailData.booking.status]?.label ?? detailData.booking.status }}
                                            </span>
                                        </div>
                                        <h2 class="text-base font-bold text-white mt-1 truncate">
                                            {{ detailData?.booking?.nama_training ?? '—' }}
                                        </h2>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <!-- Tombol Download Excel Detail -->
                                        <a v-if="detailData"
                                           :href="`/admin/bookings/${detailData.booking.id}/export-detail`"
                                           target="_blank"
                                           class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition shadow-sm"
                                           title="Download Excel daftar peserta & panitia"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Excel Peserta
                                        </a>
                                        <button @click="closeDetail" class="text-slate-400 hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Loading State (Skeleton) ── -->
                            <div v-if="detailLoading" class="flex-1 flex flex-col p-6 animate-pulse">
                                <!-- Tabs Skeleton -->
                                <div class="flex gap-4 mb-6 border-b border-gray-100 pb-2">
                                    <div class="h-8 w-24 bg-gray-200 rounded"></div>
                                    <div class="h-8 w-24 bg-gray-200 rounded"></div>
                                    <div class="h-8 w-24 bg-gray-200 rounded"></div>
                                </div>
                                
                                <!-- Content Box 1 Skeleton -->
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 mb-5">
                                    <div class="h-4 w-48 bg-gray-200 rounded mb-4"></div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div v-for="i in 4" :key="i">
                                            <div class="h-3 w-16 bg-gray-200 rounded mb-2"></div>
                                            <div class="h-4 w-32 bg-gray-200 rounded"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Content Box 2 Skeleton -->
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                    <div class="h-4 w-40 bg-gray-200 rounded mb-4"></div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div v-for="i in 6" :key="'sec'+i">
                                            <div class="h-3 w-16 bg-gray-200 rounded mb-2"></div>
                                            <div class="h-4 w-28 bg-gray-200 rounded"></div>
                                        </div>
                                    </div>
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
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showApproveModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-green-600 to-emerald-600">
                            <h2 class="text-base font-bold text-white">Konfirmasi Persetujuan</h2>
                            <p class="text-xs text-green-100 mt-1">Ruangan akan dikunci setelah booking disetujui.</p>
                        </div>
                        <div class="px-6 py-4 space-y-3 bg-gray-50">
                            <div><span class="text-xs text-gray-500 block">Nama Training</span><span class="text-sm font-semibold text-gray-800">{{ selectedBooking?.nama_training }}</span></div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><span class="text-xs text-gray-500 block">Pemohon</span><span class="text-sm text-gray-800">{{ selectedBooking?.pemohon }} ({{ selectedBooking?.divisi }})</span></div>
                                <div><span class="text-xs text-gray-500 block">Ruangan</span><span class="text-sm text-gray-800">{{ selectedBooking?.ruangan }}</span></div>
                            </div>
                            <div><span class="text-xs text-gray-500 block">Jadwal</span><span class="text-sm text-gray-800">{{ selectedBooking?.tgl_mulai }} – {{ selectedBooking?.tgl_selesai }}</span></div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3 px-6 pb-6">
                            <button @click="showApproveModal = false" :disabled="approveForm.processing" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition disabled:opacity-50">Batal</button>
                            <button @click="submitApprove" :disabled="approveForm.processing" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition shadow-sm disabled:opacity-50">
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
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="showAcc2Modal" class="fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 relative overflow-hidden" @click.stop>
                        <div class="absolute top-0 left-0 w-full h-1.5 bg-emerald-500"></div>
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 text-emerald-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Final Confirmation (ACC-2)</h3>
                                <p class="text-sm text-gray-500 mt-1">Anda akan memberikan Final Confirmation untuk acara <strong class="text-gray-800">"{{ selectedBooking?.nama_training }}"</strong>.</p>
                                <p class="text-xs text-gray-500 mt-2 bg-emerald-50 p-2 rounded border border-emerald-100">Pastikan persiapan ruangan dan logistik sudah siap sesuai kebutuhan.</p>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button @click="showAcc2Modal = false" :disabled="acc2Form.processing" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition disabled:opacity-50">Batal</button>
                            <button @click="submitAcc2" :disabled="acc2Form.processing" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition shadow-sm disabled:opacity-50">
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
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-red-600 to-rose-600">
                            <h2 class="text-base font-bold text-white">Penolakan Booking</h2>
                            <p class="text-xs text-red-100 mt-1">Booking <strong>{{ selectedBooking?.nama_training }}</strong> akan dibatalkan.</p>
                        </div>
                        <form @submit.prevent="submitReject" class="px-6 py-5">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Alasan Penolakan <span class="text-red-500">*</span></label>
                            <textarea
                                v-model="rejectForm.catatan_admin"
                                required
                                rows="3"
                                placeholder="Contoh: Jadwal bentrok, ruangan sedang direnovasi..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            ></textarea>
                            <p v-if="rejectForm.errors.catatan_admin" class="text-xs text-red-500 mt-1">{{ rejectForm.errors.catatan_admin }}</p>

                            <div class="mt-5 flex gap-3">
                                <button type="button" @click="showRejectModal = false" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                                <button type="submit" :disabled="rejectForm.processing || !rejectForm.catatan_admin"
                                    class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white rounded-lg text-sm font-semibold">
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
            <div v-if="showFinalModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-3">🏁 Konfirmasi ACC Final</h2>
                    <div class="text-sm text-gray-700 space-y-1 mb-4">
                        <div><strong>Training:</strong> {{ selectedBooking?.nama_training }}</div>
                        <div><strong>Jadwal:</strong> {{ formatDate(selectedBooking?.tgl_mulai) }} – {{ formatDate(selectedBooking?.tgl_selesai) }}</div>
                        <div><strong>Peserta:</strong> {{ selectedBooking?.jumlah_peserta }} orang, {{ selectedBooking?.jumlah_panitia }} panitia</div>
                    </div>
                    <p class="text-xs text-orange-600 mb-4">
                        ⚠️ Setelah ACC Final, user tidak dapat mengubah booking ini. Pastikan semua data sudah benar.
                    </p>
                    <div class="flex gap-3">
                        <button @click="showFinalModal = false"
                                class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                        <button @click="submitFinal" :disabled="finalForm.processing"
                                class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm disabled:opacity-50">
                            {{ finalForm.processing ? 'Memproses...' : '🏁 ACC & Final' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: ACC Terlambat ───────────────────────────── -->
        <Teleport to="body">
            <div v-if="showFinalLateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-1">⚠️ ACC Terlambat</h2>
                    <p class="text-xs text-red-600 mb-4">Tanggal training sudah lewat. Mohon isi alasan ACC terlambat.</p>
                    <form @submit.prevent="submitFinalLate">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan ACC Terlambat <span class="text-red-500">*</span></label>
                        <textarea v-model="finalLateForm.catatan_acc_terlambat" required rows="3"
                                  placeholder="Contoh: Training tetap berjalan, laporan menyusul..."
                                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        <p v-if="finalLateForm.errors.catatan_acc_terlambat" class="text-xs text-red-500 mt-1">
                            {{ finalLateForm.errors.catatan_acc_terlambat }}
                        </p>
                        <div class="flex gap-3 mt-4">
                            <button type="button" @click="showFinalLateModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="finalLateForm.processing || !finalLateForm.catatan_acc_terlambat"
                                    class="flex-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded text-sm disabled:opacity-50">
                                {{ finalLateForm.processing ? 'Menyimpan...' : '⚠️ Konfirmasi ACC Terlambat' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Setujui Ubah Tanggal ────────────────────── -->
        <Teleport to="body">
            <div v-if="showApproveDateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-3">Setujui Perubahan Tanggal</h2>
                    <div class="text-sm text-gray-700 space-y-1 mb-4">
                        <div><strong>Training:</strong> {{ selectedBooking?.nama_training }}</div>
                        <div class="text-gray-400 line-through text-xs">
                            Lama: {{ formatDate(selectedBooking?.tgl_mulai) }} – {{ formatDate(selectedBooking?.tgl_selesai) }}
                        </div>
                        <div class="text-green-700 font-medium">
                            Baru: {{ formatDate(selectedBooking?.proposed_tgl_mulai) }} – {{ formatDate(selectedBooking?.proposed_tgl_selesai) }}
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showApproveDateModal = false"
                                class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                        <button @click="submitApproveDate" :disabled="approveDateForm.processing"
                                class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm disabled:opacity-50">
                            {{ approveDateForm.processing ? 'Memproses...' : '✓ Setujui' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Tolak Ubah Tanggal ──────────────────────── -->
        <Teleport to="body">
            <div v-if="showRejectDateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-1">Tolak Perubahan Tanggal</h2>
                    <p class="text-xs text-gray-500 mb-4">
                        Tanggal lama akan tetap berlaku. Booking tetap berstatus Confirmed.
                    </p>
                    <form @submit.prevent="submitRejectDate">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan <span class="text-red-500">*</span></label>
                        <textarea v-model="rejectDateForm.catatan_admin" required rows="3"
                                  placeholder="Contoh: Tanggal baru bentrok dengan event lain..."
                                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        <div class="flex gap-3 mt-4">
                            <button type="button" @click="showRejectDateModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="rejectDateForm.processing || !rejectDateForm.catatan_admin"
                                    class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded text-sm disabled:opacity-50">
                                {{ rejectDateForm.processing ? 'Menyimpan...' : '✕ Tolak' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
                <div v-if="showEditParticipantModal" class="fixed inset-0 bg-black/60 z-[70] flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 relative overflow-hidden" @click.stop>
                        <div class="absolute top-0 left-0 w-full h-1.5 bg-blue-500"></div>
                        <div class="flex items-center justify-between mb-4 border-b border-gray-100 pb-2">
                            <h3 class="text-base font-bold text-gray-900">
                                ✏️ Edit Data {{ editParticipantForm.tipe === 'panitia' ? 'Panitia' : 'Peserta' }}
                            </h3>
                            <button @click="showEditParticipantModal = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

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
                                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
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
                                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
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
                                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
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
            </Transition>
        </Teleport>
    </AdminLayout>
</template>
