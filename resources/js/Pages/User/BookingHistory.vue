<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: UserLayout })

const props = defineProps({
    auth:     Object,
    bookings: Array
})

const page = usePage()
const isWindowActive = computed(() => page.props.bookingWindow?.is_active ?? true)

const STATUS_META = {
    plotting: {
        label: 'Pending',
        class: 'bg-amber-100 text-amber-800 border border-amber-200',
    },
    waiting_confirmation: {
        label: 'Menunggu Persetujuan',
        class: 'bg-yellow-100 text-yellow-800 border border-yellow-200',
    },
    confirmed: {
        label: 'Disetujui',
        class: 'bg-indigo-100 text-indigo-800 border border-indigo-200',
    },
    final: {
        label: 'Final ACC / Persiapan Lapangan',
        class: 'bg-green-100 text-green-800 border border-green-200',
    },
    cancelled: {
        label: 'Dibatalkan',
        class: 'bg-gray-100 text-gray-600 border border-gray-200',
    },
    done: {
        label: 'Selesai',
        class: 'bg-gray-100 text-gray-600 border border-gray-200',
    },
}

function getStatusMeta(b) {
    if (!b) return STATUS_META.waiting_confirmation
    if (b.status === 'cancelled') return STATUS_META.cancelled
    if (b.status === 'waiting_confirmation' || b.status === 'plotting') {
        return {
            label: 'Pending',
            class: 'bg-amber-100 text-amber-800 border border-amber-200',
        }
    }
    if (b.status === 'confirmed') {
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        const h14Cutoff = new Date()
        h14Cutoff.setDate(today.getDate() + 14)
        h14Cutoff.setHours(23, 59, 59, 999)
        
        const start = new Date(b.tgl_mulai)
        if (start >= today && start <= h14Cutoff) {
            return {
                label: 'H - 14 (Perlu ACC Final)',
                class: 'bg-red-100 text-red-800 border border-red-200',
            }
        }
        return STATUS_META.confirmed
    }
    return STATUS_META[b.status] ?? STATUS_META.waiting_confirmation
}

const CHANGE_META = {
    pending:  { label: 'Menunggu Persetujuan Ubah Tanggal', class: 'text-orange-700 bg-orange-50 border border-orange-100' },
    approved: { label: 'Perubahan Tanggal Disetujui',       class: 'text-green-700 bg-green-50 border border-green-100' },
    rejected: { label: 'Perubahan Tanggal Ditolak',         class: 'text-red-700 bg-red-50 border border-red-100' },
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

// ─── Dropdown per baris ───────────────────────────────────────
const openDropdownId = ref(null)
const dropdownPos    = ref({ top: 0, right: 0 })

function toggleDropdown(id, event) {
    if (openDropdownId.value === id) {
        openDropdownId.value = null
        return
    }
    const rect = event.currentTarget.getBoundingClientRect()
    dropdownPos.value = {
        top:   rect.bottom + window.scrollY + 4,
        right: window.innerWidth - rect.right,
    }
    openDropdownId.value = id
}

function closeDropdown() {
    openDropdownId.value = null
}

onMounted(() => document.addEventListener('click', closeDropdown))
onUnmounted(() => document.removeEventListener('click', closeDropdown))

// ─── State modals ──────────────────────────────────────────────
const selectedBooking      = ref(null)
const showCancelModal      = ref(false)
const showDateModal        = ref(false)
const showParticipantModal = ref(false)

// Form: Batalkan
const cancelLoading = ref(false)
const cancelError   = ref('')

// Form: Ubah Tanggal
const dateForm    = ref({ proposed_tgl_mulai: '', proposed_tgl_selesai: '', alasan: '' })
const dateLoading = ref(false)
const dateError   = ref('')

// Form: Update Peserta
const participantLoading = ref(false)
const participantError   = ref('')

// ─── Buka Modal ────────────────────────────────────────────────
function openCancel(b) {
    selectedBooking.value = b
    cancelError.value = ''
    showCancelModal.value = true
}

function openDateChange(b) {
    selectedBooking.value = b
    dateError.value = ''
    dateForm.value = {
        proposed_tgl_mulai:   b.tgl_mulai ?? '',
        proposed_tgl_selesai: b.tgl_selesai ?? '',
        alasan: ''
    }
    showDateModal.value = true
}

function openParticipantUpdate(b) {
    selectedBooking.value = b
    participantError.value = ''
    showParticipantModal.value = true
}

// ─── Aksi: Batalkan ────────────────────────────────────────────
async function submitCancel() {
    if (!selectedBooking.value) return
    cancelLoading.value = true
    cancelError.value   = ''
    try {
        await window.axios.post(`/api/booking/${selectedBooking.value.id}/cancel`)
        showCancelModal.value = false
        router.reload({ only: ['bookings'] })
    } catch (e) {
        cancelError.value = e.response?.data?.message ?? 'Terjadi kesalahan.'
    } finally {
        cancelLoading.value = false
    }
}

// ─── Aksi: Ubah Tanggal ────────────────────────────────────────
async function submitDateChange() {
    if (!selectedBooking.value) return
    dateLoading.value = true
    dateError.value   = ''
    try {
        await window.axios.post(`/api/booking/${selectedBooking.value.id}/request-date-change`, dateForm.value)
        showDateModal.value = false
        router.reload({ only: ['bookings'] })
    } catch (e) {
        dateError.value = e.response?.data?.message
            ?? e.response?.data?.errors?.proposed_tgl_mulai?.[0]
            ?? 'Terjadi kesalahan.'
    } finally {
        dateLoading.value = false
    }
}
</script>

<template>
    <div class="w-full space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Riwayat Booking</h1>
                    <p class="text-sm text-gray-500 mt-1">Daftar seluruh riwayat pemesanan ruangan dan status pengajuannya.</p>
                </div>
            </div>

            <!-- Buat Booking Baru -->
            <div v-if="!isWindowActive"
                 class="bg-gray-100 text-gray-400 text-sm font-semibold px-4 py-2 rounded-lg cursor-not-allowed border border-gray-200 flex items-center gap-1.5 shrink-0"
                 title="Window Booking sedang ditutup oleh Admin.">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Buat Booking Baru (Tutup)
            </div>
            <a v-else href="/user/booking/create"
               class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Buat Booking Baru
            </a>
        </div>

        <!-- Empty State -->
        <div v-if="bookings.length === 0"
             class="bg-white rounded-xl border border-dashed border-gray-200 py-20 text-center flex flex-col items-center justify-center p-6">
            <div class="p-4 bg-gray-50 text-gray-400 rounded-full mb-4">
                <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2" />
                </svg>
            </div>
            <p class="text-gray-500 font-medium">Belum ada riwayat booking.</p>
            <p class="text-sm text-gray-400 mt-1">Booking yang sudah selesai atau dibatalkan akan muncul di sini.</p>
            <a href="/user/booking/create"
               class="inline-flex items-center gap-1.5 mt-6 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg text-sm transition shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Buat Booking Sekarang
            </a>
        </div>

        <!-- Booking Table -->
        <div v-else class="bg-white rounded-2xl border border-gray-200 shadow-xs w-full">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/50 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                            <th class="py-4 px-6 w-[120px] whitespace-nowrap">No. Booking</th>
                            <th class="py-4 px-6 min-w-[260px]">Kegiatan &amp; PIC</th>
                            <th class="py-4 px-6">Ruangan</th>
                            <th class="py-4 px-6">Status</th>
                            <th class="py-4 px-6">Tanggal Mulai</th>
                            <th class="py-4 px-6">Tanggal Selesai</th>
                            <th class="py-4 px-6">Kapasitas</th>
                            <th class="py-4 px-6 text-right w-[180px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <tr v-for="b in bookings" :key="b.id" class="hover:bg-gray-50/40 transition-colors align-middle">

                            <!-- No. Booking -->
                            <td class="py-4 px-6 whitespace-nowrap">
                                <span class="font-bold text-blue-600 text-xs">#{{ b.id }}</span>
                            </td>

                            <!-- Kegiatan & PIC -->
                            <td class="py-4 px-6">
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 text-sm truncate leading-snug">{{ b.nama_training }}</div>
                                    <div class="text-[10px] text-gray-500 mt-0.5">
                                        PIC: <span class="font-semibold text-gray-700">{{ b.pic }}</span>
                                        · Diajukan: {{ b.created_at }}
                                    </div>
                                </div>
                            </td>

                            <!-- Ruangan -->
                            <td class="py-4 px-6">
                                <div class="font-medium text-gray-800">{{ b.ruangan?.nama_ruang ?? b.nama_ruang ?? 'Ruang Gabungan' }}</div>
                                <div v-if="b.gabung_ruang || b.ruangan?.lokasi_gedung" class="text-[10px] text-gray-400 mt-0.5">
                                    {{ b.gabung_ruang ? 'Gabungan Ruang 2+3' : b.ruangan?.lokasi_gedung }}
                                </div>
                                <div v-if="b.layout_preferensi" class="text-[10px] text-gray-400 capitalize mt-0.5">{{ b.layout_preferensi }}</div>
                            </td>

                             <!-- Status -->
                             <td class="py-4 px-6">
                                 <div class="flex flex-col gap-1.5">
                                     <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold shrink-0 inline-flex items-center gap-1 w-fit"
                                           :class="getStatusMeta(b).class">
                                         <!-- Pending / waiting_confirmation -->
                                         <svg v-if="b.status === 'waiting_confirmation' || b.status === 'plotting'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z" />
                                         </svg>
                                         <!-- H-14 or Confirmed -->
                                         <template v-else-if="b.status === 'confirmed'">
                                             <!-- H-14 -->
                                             <svg v-if="getStatusMeta(b).label.startsWith('H - 14')" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                 <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                             </svg>
                                             <!-- Disetujui -->
                                             <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                 <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z" />
                                             </svg>
                                         </template>
                                         <!-- final -->
                                         <svg v-else-if="b.status === 'final'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766l.002-.001a1.56 1.56 0 0 1 1.883 1.883l-.001.002c-.14.468-.382.89-.766 1.208l-3.03 2.496ZM11.42 15.17l-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243 3 3 0 0 0 4.243 4.243Zm0 0L7.88 9.75a3 3 0 0 0-3 3v.375c0 .621.504 1.125 1.125 1.125h.375M16.5 7.5a1.5 1.5 0 1 1-3 0a1.5 1.5 0 0 1 3 0Z" />
                                         </svg>
                                         <!-- cancelled -->
                                         <svg v-else-if="b.status === 'cancelled'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z" />
                                         </svg>
                                         <!-- done -->
                                         <svg v-else-if="b.status === 'done'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                         </svg>
                                         {{ getStatusMeta(b).label }}
                                     </span>

                                    <!-- Catatan admin jika dibatalkan -->
                                    <div v-if="b.status === 'cancelled' && b.catatan_admin"
                                         class="p-1.5 bg-red-50 border border-red-100 rounded text-[10px] text-red-600 italic max-w-[200px]">
                                        "{{ b.catatan_admin }}"
                                    </div>

                                    <!-- Usulan Perubahan Tanggal -->
                                    <div v-if="b.status_perubahan && b.status_perubahan !== 'none'"
                                         class="px-2 py-1 rounded-md text-[10px] font-medium inline-flex items-center gap-1 w-fit"
                                         :class="CHANGE_META[b.status_perubahan]?.class">
                                        <svg v-if="b.status_perubahan === 'pending'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        <svg v-else-if="b.status_perubahan === 'approved'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <svg v-else-if="b.status_perubahan === 'rejected'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                        <span>
                                            {{ CHANGE_META[b.status_perubahan]?.label }}
                                            <span v-if="b.status_perubahan === 'pending'" class="font-normal text-gray-500">
                                                ({{ formatDate(b.proposed_tgl_mulai) }} – {{ formatDate(b.proposed_tgl_selesai) }})
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Tanggal Mulai -->
                            <td class="py-4 px-6 font-medium text-gray-800">
                                {{ formatDate(b.tgl_mulai) }}
                            </td>

                            <!-- Tanggal Selesai -->
                            <td class="py-4 px-6 font-medium text-gray-800">
                                {{ formatDate(b.tgl_selesai) }}
                            </td>

                            <!-- Kapasitas -->
                            <td class="py-4 px-6">
                                <div class="font-bold text-gray-800">{{ b.jumlah_peserta }} <span class="text-[10px] font-normal text-gray-500">Peserta</span></div>
                                <div class="text-[10px] text-gray-400 font-medium mt-0.5">{{ b.jumlah_panitia }} <span class="font-normal">Panitia</span></div>
                            </td>

                            <!-- Aksi -->
                            <td class="py-4 px-6 text-right">
                                <!-- Tidak ada aksi -->
                                <span v-if="!b.can_cancel && !b.can_request_date_change && !b.can_update_participants"
                                      class="text-xs text-gray-300">—</span>

                                <!-- Dropdown Aksi -->
                                <div v-else class="inline-block text-left">
                                    <button @click.stop="toggleDropdown(b.id, $event)"
                                            class="inline-flex items-center gap-1.5 border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 py-1.5 px-3 rounded-md text-xs font-semibold transition active:scale-[0.98] shadow-xs">
                                        Aksi
                                        <svg class="w-3.5 h-3.5 text-gray-400 transition-transform"
                                             :class="openDropdownId === b.id ? 'rotate-180' : ''"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </div>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ── Dropdown Aksi (Teleport ke body agar tidak terpotong overflow) ── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
        >
            <div v-if="openDropdownId !== null"
                 class="fixed z-[999] w-48 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden origin-top-right"
                 :style="{ top: dropdownPos.top + 'px', right: dropdownPos.right + 'px' }"
                 @click.stop>
                <!-- Isi dropdown untuk booking yang sedang terbuka -->
                <template v-for="b in bookings" :key="b.id">
                    <template v-if="b.id === openDropdownId">
                        <!-- Edit Pesanan -->
                        <div v-if="b.can_request_date_change || b.can_update_participants"
                             class="border-b border-gray-100">
                            <button v-if="b.can_request_date_change"
                                    @click="closeDropdown(); openDateChange(b)"
                                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition text-left">
                                <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                                Ubah Tanggal
                            </button>
                            <button v-if="b.can_update_participants"
                                    @click="closeDropdown(); openParticipantUpdate(b)"
                                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition text-left">
                                <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                                Update Peserta
                            </button>
                        </div>
                        <!-- Batalkan Pesanan -->
                        <button v-if="b.can_cancel"
                                @click="closeDropdown(); openCancel(b)"
                                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs text-red-600 hover:bg-red-50 transition text-left">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            Batalkan Pesanan
                        </button>
                    </template>
                </template>
            </div>
        </Transition>
    </Teleport>


    <!-- ── Modal: Batalkan Booking ──────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showCancelModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-center text-gray-900 mb-2">Batalkan Booking?</h2>
                <p class="text-sm text-center text-gray-600 mb-1">
                    Anda yakin ingin membatalkan:
                    <strong class="text-gray-800">{{ selectedBooking?.nama_training }}</strong>?
                </p>
                <p class="text-xs text-center text-red-500 mb-4">Tindakan ini permanen dan tidak dapat diurungkan.</p>
                <div v-if="cancelError" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded p-3">
                    {{ cancelError }}
                </div>
                <div class="flex gap-3">
                    <button @click="showCancelModal = false"
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Kembali
                    </button>
                    <button @click="submitCancel" :disabled="cancelLoading"
                            class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition">
                        {{ cancelLoading ? 'Membatalkan...' : 'Ya, Batalkan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ── Modal: Ubah Tanggal ──────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showDateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full mb-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <h2 class="text-base font-bold text-gray-900 mb-1">Ajukan Perubahan Tanggal</h2>
                <p class="text-xs text-gray-500 mb-4">
                    Tanggal baru perlu disetujui admin. Tanggal lama tetap berlaku sampai disetujui.
                </p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Mulai Baru</label>
                        <input type="date" v-model="dateForm.proposed_tgl_mulai"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Selesai Baru</label>
                        <input type="date" v-model="dateForm.proposed_tgl_selesai"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Alasan (Opsional)</label>
                        <textarea v-model="dateForm.alasan" rows="2"
                                  placeholder="Misalnya: Narasumber tidak bisa hadir..."
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <div v-if="dateError" class="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-2">
                    {{ dateError }}
                </div>
                <div class="flex gap-3 mt-4">
                    <button @click="showDateModal = false"
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button @click="submitDateChange" :disabled="dateLoading"
                            class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition">
                        {{ dateLoading ? 'Mengirim...' : 'Ajukan Perubahan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ── Modal: Update Peserta ────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showParticipantModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-indigo-100 rounded-full mb-4">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <h2 class="text-base font-bold text-gray-900 mb-2">Update Data Peserta</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Upload ulang file Excel peserta untuk booking:
                    <strong>{{ selectedBooking?.nama_training }}</strong>
                </p>
                <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 text-center mb-4 hover:border-blue-300 transition">
                    <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <input type="file" accept=".xlsx"
                           @change="async (e) => {
                               const file = e.target.files[0]
                               if (!file) return
                               participantLoading.value = true
                               participantError.value = ''
                               try {
                                   const fd = new FormData()
                                   fd.append('file_peserta', file)
                                   await window.axios.post(`/api/booking/${selectedBooking.value.id}/update-participants`, fd)
                                   showParticipantModal.value = false
                                   router.reload({ only: ['bookings'] })
                               } catch (err) {
                                   participantError.value = err.response?.data?.message ?? 'Gagal upload.'
                               } finally {
                                   participantLoading.value = false
                               }
                           }"
                           class="text-sm text-gray-600" />
                    <p class="text-xs text-gray-400 mt-2">Format: .xlsx | Maks 2MB</p>
                </div>
                <div v-if="participantError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-2 mb-3">
                    {{ participantError }}
                </div>
                <button @click="showParticipantModal = false"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Tutup
                </button>
            </div>
        </div>
    </Teleport>

</template>
