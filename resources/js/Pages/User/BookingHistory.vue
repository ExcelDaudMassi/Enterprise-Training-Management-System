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
        label: 'Plotting',
        class: 'bg-amber-50 text-amber-850 border border-amber-200',
    },
    pending: {
        label: 'Pending',
        class: 'bg-yellow-50 text-yellow-850 border border-yellow-200',
    },
    confirmed: {
        label: 'Confirmed',
        class: 'bg-indigo-50 text-indigo-850 border border-indigo-200',
    },
    finalized: {
        label: 'Finalized',
        class: 'bg-green-50 text-green-850 border border-green-200',
    },
    rejected: {
        label: 'Rejected',
        class: 'bg-rose-50 text-rose-850 border border-rose-200',
    },
    cancelled: {
        label: 'Cancelled',
        class: 'bg-slate-50 text-slate-800 border border-slate-200',
    },
    completed: {
        label: 'Completed',
        class: 'bg-emerald-50 text-emerald-850 border border-emerald-250',
    },
}

function getStatusMeta(b) {
    if (!b) return STATUS_META.pending
    if (b.status === 'cancelled') return STATUS_META.cancelled
    if (b.status === 'rejected') return STATUS_META.rejected
    if (b.status === 'completed' || b.status === 'done') return STATUS_META.completed
    if (b.status === 'waiting_confirmation' || b.status === 'pending' || b.status === 'plotting') {
        return STATUS_META.pending
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
                class: 'bg-red-50 text-red-850 border border-red-200',
            }
        }
        return STATUS_META.confirmed
    }
    if (b.status === 'final' || b.status === 'finalized') {
        return STATUS_META.finalized
    }
    return STATUS_META[b.status] ?? STATUS_META.pending
}

const CHANGE_META = {
    pending:  { label: 'Menunggu Persetujuan Ubah Tanggal', class: 'text-orange-750 bg-orange-50/70 border border-orange-200' },
    approved: { label: 'Perubahan Tanggal Disetujui',       class: 'text-green-750 bg-green-50/70 border border-green-200' },
    rejected: { label: 'Perubahan Tanggal Ditolak',         class: 'text-red-750 bg-red-50/70 border border-red-200' },
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
        
        const isAcc = b.status === 'confirmed' || b.status === 'final' || b.status === 'finalized'
        const isPast = b.tgl_selesai ? new Date(b.tgl_selesai) < today : false

        if (currentTab.value === 'siap') {
            return isAcc && !isPast
        }
        if (currentTab.value === 'selesai') {
            return b.status === 'completed' || b.status === 'done' || (isAcc && isPast)
        }
        if (currentTab.value === 'batal') {
            return b.status === 'cancelled' || b.status === 'rejected'
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

// ─── Real-time WebSockets ──────────────────────────────────────
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('bookings')
            .listen('NewBookingCreated', (e) => {
                if (e.booking.user_id === props.auth.user.id) {
                    router.reload({ only: ['bookings'], preserveState: true, preserveScroll: true })
                }
            })
            .listen('BookingStatusUpdated', (e) => {
                if (e.booking.user_id === props.auth.user.id) {
                    router.reload({ only: ['bookings'], preserveState: true, preserveScroll: true })
                }
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

        <!-- Booking Table - Admin Style -->
        <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-sm w-full overflow-hidden">
            <div class="overflow-x-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                <table class="min-w-full table-fixed divide-y divide-slate-100">
                    <thead class="bg-slate-50/80 backdrop-blur-sm border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[29%]">Acara / PIC</th>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[18%]">Ruangan</th>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[20%]">Jadwal</th>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[20%]">Status</th>
                            <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[13%]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-100">
                        <tr v-for="b in filteredBookings" :key="b.id" class="hover:bg-slate-50/50 transition-all duration-200 align-top group">

                            <!-- Acara / PIC -->
                            <td class="px-5 py-4">
                                <div class="text-sm font-bold text-slate-900 group-hover:text-blue-700 transition-colors line-clamp-2">{{ b.nama_training }}</div>
                                <div class="text-xs text-slate-500 mt-1">
                                    PIC: <span class="font-semibold text-slate-700">{{ b.pic }}</span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ b.created_at }}
                                </div>
                            </td>

                            <!-- Ruangan -->
                            <td class="px-5 py-4">
                                <div class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 font-bold text-xs px-2.5 py-1 rounded-lg border border-slate-200">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    {{ b.ruangan?.nama_ruang ?? b.nama_ruang ?? 'Ruang Gabungan' }}
                                </div>
                                <div v-if="b.gabung_ruang" class="text-[10px] text-emerald-600 font-medium mt-1.5">Gabungan Ruang 2+3</div>
                                <div v-else-if="b.ruangan?.lokasi_gedung" class="text-[10px] text-slate-400 mt-1.5">{{ b.ruangan.lokasi_gedung }}</div>
                                <div v-if="b.layout_preferensi" class="text-[11px] text-slate-400 capitalize mt-1.5 flex items-center gap-1">
                                    Layout: <span class="font-medium text-slate-600">{{ b.layout_preferensi }}</span>
                                </div>
                            </td>

                            <!-- Jadwal -->
                            <td class="px-5 py-4 text-xs text-slate-700">
                                <div class="font-bold text-slate-800 bg-slate-50 border border-slate-100 px-2 py-1 rounded-md inline-block mb-1">{{ formatDate(b.tgl_mulai) }}</div>
                                <div class="text-slate-400 text-[10px] ml-2 mb-1">s/d</div>
                                <div class="font-bold text-slate-800 bg-slate-50 border border-slate-100 px-2 py-1 rounded-md inline-block">{{ formatDate(b.tgl_selesai) }}</div>
                            </td>


                            <!-- Status -->
                            <td class="px-5 py-4">
                                <div class="flex flex-col gap-1.5">
                                    <!-- Badge Status Utama -->
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold shadow-sm inline-flex items-center gap-1 w-fit"
                                          :class="getStatusMeta(b).class">
                                        <svg v-if="b.status === 'waiting_confirmation' || b.status === 'plotting'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z"/></svg>
                                        <template v-else-if="b.status === 'confirmed'">
                                            <svg v-if="getStatusMeta(b).label.startsWith('H - 14')" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z"/></svg>
                                        </template>
                                        <svg v-else-if="b.status === 'final'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        <svg v-else-if="b.status === 'cancelled'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z"/></svg>
                                        <svg v-else-if="b.status === 'done'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        {{ getStatusMeta(b).label }}
                                    </span>

                                    <!-- Catatan admin jika dibatalkan -->
                                    <div v-if="b.status === 'cancelled' && b.catatan_admin"
                                         class="p-1.5 bg-rose-50 border border-rose-100 rounded-lg text-[10px] text-rose-600 italic max-w-[180px] line-clamp-2 flex items-start gap-1">
                                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <span>"{{ b.catatan_admin }}"</span>
                                    </div>

                                    <!-- Usulan Perubahan Tanggal -->
                                    <div v-if="b.status_perubahan && b.status_perubahan !== 'none'"
                                         class="px-2 py-1 rounded-md text-[10px] font-medium inline-flex items-center gap-1 w-fit"
                                         :class="CHANGE_META[b.status_perubahan]?.class">
                                        <svg v-if="b.status_perubahan === 'pending'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        <svg v-else-if="b.status_perubahan === 'approved'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        <svg v-else-if="b.status_perubahan === 'rejected'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        <span>
                                            {{ CHANGE_META[b.status_perubahan]?.label }}
                                            <span v-if="b.status_perubahan === 'pending'" class="font-normal text-gray-500">
                                                ({{ formatDate(b.proposed_tgl_mulai) }} – {{ formatDate(b.proposed_tgl_selesai) }})
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Aksi -->
                            <td class="px-5 py-4">
                                <div class="flex flex-col gap-2">
                                    <button @click="openDetail(b)"
                                            title="Lihat Detail Rekapan"
                                            class="group/btn flex items-center justify-center gap-1.5 bg-white hover:bg-slate-50 text-slate-700 hover:text-blue-600 text-[11px] font-bold py-2 px-3 rounded-xl transition-all border border-slate-200 hover:border-blue-300 shadow-sm hover:shadow cursor-pointer">
                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover/btn:text-blue-500 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Lihat Detail
                                    </button>
                                    <button @click="downloadExcel(b)"
                                            title="Download Excel"
                                            class="group/btn flex items-center justify-center gap-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[11px] font-bold py-2 px-3 rounded-xl transition-all border border-emerald-200 hover:border-emerald-300 shadow-sm hover:shadow cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                        Download
                                    </button>
                                </div>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ── DRAWER: Detail Rekapan (Slide dari Kanan) ── -->
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="showRecapModal && selectedBooking"
                class="fixed inset-0 bg-white/60 backdrop-blur-sm z-40"
                @click="showRecapModal = false"
            />
        </Transition>

        <!-- Drawer Panel -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div 
                v-if="showRecapModal && selectedBooking"
                class="fixed right-0 top-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 flex flex-col"
            >
                <!-- Drawer Header -->
                <div class="px-6 py-5 flex justify-between items-center bg-white border-b border-gray-200 shrink-0 relative overflow-hidden">
                    <!-- Decorative subtle circle or gradient -->
                    <div class="absolute -top-12 -right-12 w-32 h-32 bg-blue-500/5 rounded-full blur-2xl"></div>
                    <div class="relative flex items-center justify-between w-full">
                        <div class="flex-1 min-w-0 pr-4">
                            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                Detail Rekapan Booking
                            </h2>
                            <p class="text-[11px] text-gray-400 mt-0.5">ID: #{{ String(selectedBooking.id).padStart(5, '0') }}</p>
                        </div>
                        <button 
                            @click="showRecapModal = false" 
                            class="text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 p-1.5 rounded-full transition-all cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Drawer Body -->
                <div class="flex-1 overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                    <div class="p-6 space-y-6">

                        <!-- Nama Kegiatan & Status -->
                        <div class="p-4 bg-blue-50 rounded-xl border border-blue-100">
                            <div class="text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-1">Nama Kegiatan</div>
                            <h3 class="text-lg font-bold text-gray-900 leading-snug">{{ selectedBooking.nama_training }}</h3>
                            <div class="mt-2">
                                <span class="text-xs font-semibold px-2.5 py-1 rounded-full" :class="getStatusMeta(selectedBooking).class">
                                    {{ getStatusMeta(selectedBooking).label }}
                                </span>
                            </div>
                        </div>

                        <!-- Info Grid 2x2 -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" /></svg>
                                    Ruangan
                                </div>
                                <p class="text-sm font-bold text-gray-900">{{ selectedBooking.ruangan?.nama_ruang ?? selectedBooking.nama_ruang ?? 'Ruang Gabungan' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ selectedBooking.gabung_ruang ? 'Gabungan Ruang 2 & 3' : (selectedBooking.ruangan?.lokasi_gedung ?? '-') }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                    Waktu Pelaksanaan
                                </div>
                                <p class="text-sm font-bold text-gray-900">{{ formatDate(selectedBooking.tgl_mulai) }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">s/d {{ formatDate(selectedBooking.tgl_selesai) }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                    PIC (Penanggung Jawab)
                                </div>
                                <p class="text-sm font-bold text-gray-900">{{ selectedBooking.pic }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                                    Kapasitas
                                </div>
                                <p class="text-sm font-bold text-gray-900">{{ selectedBooking.jumlah_peserta }} <span class="font-normal text-xs text-gray-500">Peserta</span></p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ selectedBooking.jumlah_panitia }} Panitia</p>
                            </div>
                        </div>

                        <!-- Fasilitas & Catatan -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Fasilitas Preferensi</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span v-if="selectedBooking.layout_preferensi" class="px-2.5 py-1 bg-white border border-gray-200 rounded-lg text-[10px] font-bold text-gray-600 uppercase shadow-xs">Layout: {{ selectedBooking.layout_preferensi }}</span>
                                    <span v-if="selectedBooking.is_hybrid" class="px-2.5 py-1 bg-violet-100 border border-violet-200 rounded-lg text-[10px] font-bold text-violet-700 uppercase">Hybrid Mode</span>
                                    <span v-if="selectedBooking.is_flipchart" class="px-2.5 py-1 bg-orange-100 border border-orange-200 rounded-lg text-[10px] font-bold text-orange-700 uppercase">Flipchart</span>
                                    <span v-if="selectedBooking.is_pena_mini_note" class="px-2.5 py-1 bg-teal-100 border border-teal-200 rounded-lg text-[10px] font-bold text-teal-700 uppercase">Pena & Mini Note</span>
                                    <span v-if="!selectedBooking.layout_preferensi && !selectedBooking.is_hybrid && !selectedBooking.is_flipchart && !selectedBooking.is_pena_mini_note" class="text-xs text-gray-400 italic">Tidak ada</span>
                                </div>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Catatan Tambahan</h4>
                                <p v-if="selectedBooking.catatan_user" class="text-xs text-gray-700 italic leading-relaxed">"{{ selectedBooking.catatan_user }}"</p>
                                <p v-else class="text-xs text-gray-400 italic">Tidak ada catatan dari pengaju.</p>
                                
                                <template v-if="selectedBooking.catatan_admin">
                                    <h4 class="text-[10px] font-bold text-red-400 uppercase tracking-widest mb-2 mt-4">Catatan Admin</h4>
                                    <p class="text-xs text-red-700 font-semibold italic leading-relaxed">"{{ selectedBooking.catatan_admin }}"</p>
                                </template>
                            </div>
                        </div>

                        <!-- Daftar Peserta & Panitia -->
                        <div v-if="selectedBooking.participants && selectedBooking.participants.length > 0">
                            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-4 h-px bg-gray-200 inline-block"></span>
                                Daftar Peserta &amp; Panitia
                                <span class="w-4 h-px bg-gray-200 inline-block"></span>
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div v-for="p in selectedBooking.participants" :key="p.id" 
                                     class="flex items-center gap-3 p-3 border border-gray-100 bg-white rounded-xl shadow-xs hover:border-blue-100 hover:bg-blue-50/30 transition-colors">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0" 
                                         :class="p.tipe === 'panitia' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'">
                                        {{ getInitials(p.nama) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-gray-800 leading-snug truncate">{{ p.nama }}</p>
                                        <div class="flex flex-wrap gap-x-2 gap-y-0.5 mt-0.5 text-[9px] text-gray-400 font-medium">
                                            <span v-if="p.nrp">NRP: <strong class="text-gray-600">{{ p.nrp }}</strong></span>
                                            <span v-if="p.jabatan" class="truncate max-w-[90px]" :title="p.jabatan">{{ p.jabatan }}</span>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded shrink-0"
                                          :class="p.tipe === 'panitia' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'">
                                        {{ p.tipe }}
                                    </span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Drawer Footer -->
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-between items-center shrink-0">
                    <button @click="downloadExcel(selectedBooking)" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition shadow-sm inline-flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download Excel
                    </button>
                    <button @click="showRecapModal = false" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-50 transition shadow-xs cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>

</template>
