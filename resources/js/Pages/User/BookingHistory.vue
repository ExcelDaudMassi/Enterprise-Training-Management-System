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
        label: 'ACC Final',
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

// ─── Filter Tabs ───────────────────────────────────────────────
const currentTab = ref('all') // all, siap, selesai, batal

const filteredBookings = computed(() => {
    if (!props.bookings) return []
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    return props.bookings.filter(b => {
        if (currentTab.value === 'all') return true
        
        const isAcc = b.status === 'confirmed' || b.status === 'final'
        const isPast = b.tgl_selesai ? new Date(b.tgl_selesai) < today : false

        if (currentTab.value === 'siap') {
            return isAcc && !isPast
        }
        if (currentTab.value === 'selesai') {
            return b.status === 'done' || (isAcc && isPast)
        }
        if (currentTab.value === 'batal') {
            return b.status === 'cancelled'
        }
        return true
    })
})

// ─── Modal Rekapan Detail ──────────────────────────────────────
const selectedBooking = ref(null)
const showRecapModal = ref(false)

function openDetail(b) {
    selectedBooking.value = b
    showRecapModal.value = true
}

function getInitials(name) {
    if (!name) return 'BK'
    return name.trim().split(/\s+/).map(word => word[0]).join('').substring(0, 2).toUpperCase()
}

// ─── Download Excel ──────────────────────────────────────
function downloadExcel(booking) {
    window.location.href = `/user/booking/${booking.id}/export`
}

</script>

<template>
    <div class="w-full space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-200 pb-4">
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

        <!-- Filter Tabs -->
        <div class="flex flex-wrap items-center gap-2">
            <button @click="currentTab = 'all'" 
                    :class="currentTab === 'all' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                    class="px-4 py-1.5 rounded-full text-xs font-bold border transition-colors shadow-2xs cursor-pointer select-none">
                Semua Riwayat
            </button>
            <button @click="currentTab = 'siap'" 
                    :class="currentTab === 'siap' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100'"
                    class="px-4 py-1.5 rounded-full text-xs font-bold border transition-colors shadow-2xs cursor-pointer select-none">
                Siap Dilaksanakan
            </button>
            <button @click="currentTab = 'selesai'" 
                    :class="currentTab === 'selesai' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'"
                    class="px-4 py-1.5 rounded-full text-xs font-bold border transition-colors shadow-2xs cursor-pointer select-none">
                Selesai Dilaksanakan
            </button>
            <button @click="currentTab = 'batal'" 
                    :class="currentTab === 'batal' ? 'bg-red-600 text-white border-red-600' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100'"
                    class="px-4 py-1.5 rounded-full text-xs font-bold border transition-colors shadow-2xs cursor-pointer select-none">
                Dibatalkan
            </button>
        </div>

        <!-- Empty State -->
        <div v-if="filteredBookings.length === 0"
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
                        <tr v-for="b in filteredBookings" :key="b.id" class="hover:bg-gray-50/40 transition-colors align-middle">

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
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openDetail(b)"
                                            title="Lihat Detail Rekapan"
                                       class="inline-flex items-center justify-center bg-blue-50 text-blue-700 hover:bg-blue-100 p-1.5 border border-blue-200 rounded-md transition shadow-xs active:scale-[0.98] cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                    <button @click="downloadExcel(b)"
                                            title="Download Excel / CSV"
                                       class="inline-flex items-center justify-center bg-emerald-50 text-emerald-700 hover:bg-emerald-100 p-1.5 border border-emerald-200 rounded-md transition shadow-xs active:scale-[0.98] cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
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

    <!-- ── MODAL: Detail Rekapan ── -->
    <Teleport to="body">
        <Transition 
            enter-active-class="transition-all ease-out duration-300" 
            enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0" 
            enter-to-class="opacity-100 scale-100 translate-y-0" 
            leave-active-class="transition-all ease-in duration-200" 
            leave-from-class="opacity-100 scale-100 translate-y-0" 
            leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0">
            <div v-if="showRecapModal && selectedBooking" class="fixed inset-0 backdrop-blur-sm bg-black/40 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                    
                    <!-- Modal Header -->
                    <div class="px-6 py-4 flex justify-between items-center bg-blue-800 text-white border-b-4 border-yellow-400">
                        <div>
                            <h2 class="text-lg font-bold leading-tight flex items-center gap-2">
                                <svg class="w-5 h-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                Detail Rekapan Booking
                            </h2>
                            <p class="text-[11px] text-blue-200 mt-0.5">ID: #{{ String(selectedBooking.id).padStart(5, '0') }}</p>
                        </div>
                        <button @click="showRecapModal = false" class="text-blue-200 hover:text-white transition bg-blue-700/50 hover:bg-blue-600 rounded-full p-1.5 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Modal Body -->
                <div class="p-6 overflow-y-auto">
                    <div class="space-y-6">
                        
                        <!-- Kegiatan Info -->
                        <div>
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Nama Kegiatan</div>
                            <h3 class="text-base font-bold text-gray-900">{{ selectedBooking.nama_training }}</h3>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-md" :class="getStatusMeta(selectedBooking).class">
                                    {{ getStatusMeta(selectedBooking).label }}
                                </span>
                            </div>
                        </div>

                        <!-- Info Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" /></svg>
                                    Ruangan
                                </div>
                                <p class="text-sm font-bold text-gray-800">{{ selectedBooking.ruangan?.nama_ruang ?? selectedBooking.nama_ruang ?? 'Ruang Gabungan' }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ selectedBooking.gabung_ruang ? 'Gabungan Ruang 2 & 3' : (selectedBooking.ruangan?.lokasi_gedung ?? '-') }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                    Waktu Pelaksanaan
                                </div>
                                <p class="text-sm font-bold text-gray-800">{{ formatDate(selectedBooking.tgl_mulai) }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">s/d {{ formatDate(selectedBooking.tgl_selesai) }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                    PIC (Penanggung Jawab)
                                </div>
                                <p class="text-sm font-bold text-gray-800">{{ selectedBooking.pic }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                                    Kapasitas
                                </div>
                                <p class="text-sm font-bold text-gray-800">{{ selectedBooking.jumlah_peserta }} <span class="text-xs font-normal">Peserta</span> &bull; {{ selectedBooking.jumlah_panitia }} <span class="text-xs font-normal">Panitia</span></p>
                            </div>
                        </div>

                        <!-- Fasilitas & Catatan -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2 border-b border-gray-100 pb-1">Fasilitas Preferensi</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span v-if="selectedBooking.layout_preferensi" class="px-2 py-1 bg-gray-100 rounded text-[10px] font-bold text-gray-600 uppercase">Layout: {{ selectedBooking.layout_preferensi }}</span>
                                    <span v-if="selectedBooking.is_hybrid" class="px-2 py-1 bg-violet-100 rounded text-[10px] font-bold text-violet-700 uppercase">Hybrid Mode</span>
                                    <span v-if="selectedBooking.is_flipchart" class="px-2 py-1 bg-orange-100 rounded text-[10px] font-bold text-orange-700 uppercase">Flipchart</span>
                                    <span v-if="!selectedBooking.layout_preferensi && !selectedBooking.is_hybrid && !selectedBooking.is_flipchart" class="text-xs text-gray-400 italic">Tidak ada</span>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2 border-b border-gray-100 pb-1">Catatan Tambahan</h4>
                                <p v-if="selectedBooking.catatan_user" class="text-xs text-gray-700 italic">"{{ selectedBooking.catatan_user }}"</p>
                                <p v-else class="text-xs text-gray-400 italic">Tidak ada catatan dari pengaju.</p>
                                
                                <template v-if="selectedBooking.catatan_admin">
                                    <h4 class="text-[10px] font-bold text-red-400 uppercase tracking-widest mb-1 mt-3 border-b border-red-100 pb-1">Catatan Admin</h4>
                                    <p class="text-xs text-red-700 font-semibold italic">"{{ selectedBooking.catatan_admin }}"</p>
                                </template>
                            </div>
                        </div>

                        <!-- Daftar Peserta -->
                        <div v-if="selectedBooking.participants && selectedBooking.participants.length > 0" class="border-t border-gray-100 pt-4">
                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Daftar Peserta &amp; Panitia</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="p in selectedBooking.participants" :key="p.id" 
                                     class="flex items-start gap-3 p-2.5 border border-gray-100 bg-gray-50/50 rounded-lg">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5" 
                                         :class="p.tipe === 'panitia' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'">
                                        {{ getInitials(p.nama) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-gray-800 leading-snug truncate">{{ p.nama }}</p>
                                        <div class="flex flex-wrap gap-x-2 gap-y-0.5 mt-1 text-[9px] text-gray-500 font-medium">
                                            <span v-if="p.nrp">NRP: <strong class="text-gray-700">{{ p.nrp }}</strong></span>
                                            <span v-if="p.jabatan" class="truncate max-w-[80px]" :title="p.jabatan">{{ p.jabatan }}</span>
                                            <span class="uppercase font-bold" :class="p.tipe === 'panitia' ? 'text-violet-600' : 'text-blue-600'">{{ p.tipe }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-between items-center">
                    <button @click="downloadExcel(selectedBooking)" class="px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-blue-900 text-xs font-bold rounded-lg transition shadow-sm inline-flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download Excel
                    </button>
                    <button @click="showRecapModal = false" class="px-5 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-50 transition shadow-sm cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
            </div>
        </Transition>
    </Teleport>

</template>
