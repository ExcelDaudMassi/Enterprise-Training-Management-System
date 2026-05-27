<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    auth: Object,
    bookings: Array,
})

const STATUS_META = {
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
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function diffDays(tgl_mulai) {
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const start = new Date(tgl_mulai)
    start.setHours(0, 0, 0, 0)
    return Math.ceil((start - today) / (1000 * 60 * 60 * 24))
}

// ─── Modal: Cancel ────────────────────────────────────────
const selectedBooking = ref(null)
const showCancelModal  = ref(false)
const cancelLoading    = ref(false)
const cancelError      = ref('')

function openCancel(b) {
    selectedBooking.value = b
    cancelError.value = ''
    showCancelModal.value = true
}

async function submitCancel() {
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

// ─── Drawer: Detail Booking ───────────────────────────────
const selectedDetail  = ref(null)
const showDetailDrawer = ref(false)
const activeTab        = ref('info')

function openDetail(b) {
    selectedDetail.value = b
    activeTab.value = 'info'
    showDetailDrawer.value = true
}

function closeDetail() {
    showDetailDrawer.value = false
}

function getInitials(name) {
    if (!name) return 'BK'
    return name
        .trim()
        .split(/\s+/)
        .map(word => word[0])
        .join('')
        .substring(0, 2)
        .toUpperCase()
}

function getAvatarBg(id) {
    const colors = [
        'bg-blue-100 text-blue-700',
        'bg-emerald-100 text-emerald-700',
        'bg-purple-100 text-purple-700',
        'bg-amber-100 text-amber-700',
        'bg-indigo-100 text-indigo-700',
        'bg-pink-100 text-pink-700',
        'bg-cyan-100 text-cyan-700'
    ]
    return colors[id % colors.length]
}
</script>

<template>
    <UserLayout :auth="auth">
        <div class="w-full space-y-6">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.03 0 1.9.693 2.166 1.638m-7.377 2.24c-.407-.03-8.15-.08-8.15.08v12.25c0 1.135.845 2.098 1.976 2.192a48.203 48.203 0 0 0 3.86 0" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Booking Aktif</h1>
                        <p class="text-sm text-gray-500 mt-1">Booking yang sedang menunggu persetujuan atau sudah dikonfirmasi</p>
                    </div>
                </div>
                <a href="/user/booking/create"
                   class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Buat Booking Baru
                </a>
            </div>

            <!-- Empty state -->
            <div v-if="bookings.length === 0"
                 class="bg-white rounded-xl border border-dashed border-gray-200 py-20 text-center flex flex-col items-center justify-center p-6">
                <div class="p-4 bg-gray-50 text-gray-400 rounded-full mb-4">
                    <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">Tidak ada booking aktif saat ini.</p>
                <p class="text-sm text-gray-400 mt-1">Booking yang sudah selesai atau dibatalkan ada di menu Riwayat Booking.</p>
                <a href="/user/booking/create"
                   class="inline-flex items-center gap-1.5 mt-6 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg text-sm transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Buat Booking Sekarang
                </a>
            </div>

            <!-- Booking Lists Table -->
            <div v-else class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden w-full">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50/50 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                <th class="py-4 px-6 w-[120px] whitespace-nowrap">No. Booking</th>
                                <th class="py-4 px-6 min-w-[260px]">Kegiatan & PIC</th>
                                <th class="py-4 px-6">Ruangan</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6">Tanggal Mulai</th>
                                <th class="py-4 px-6">Tanggal Selesai</th>
                                <th class="py-4 px-6">Kapasitas</th>
                                <th class="py-4 px-6 text-right w-[140px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            <tr v-for="b in bookings" :key="b.id" class="hover:bg-gray-50/40 transition-colors align-middle">
                                <!-- ID Column -->
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <span class="font-bold text-blue-600 text-xs">#{{ b.id }}</span>
                                </td>
                                
                                <!-- Customer/Training Column -->
                                <td class="py-4 px-6">
                                    <div class="min-w-0">
                                        <div class="font-bold text-gray-900 text-sm truncate leading-snug">{{ b.nama_training }}</div>
                                        <div class="text-[10px] text-gray-500 mt-0.5">PIC: <span class="font-semibold text-gray-700">{{ b.pic }}</span> · Diajukan: {{ b.created_at }}</div>
                                    </div>
                                </td>
                                
                                <!-- Ruangan Column -->
                                <td class="py-4 px-6">
                                    <div class="font-medium text-gray-800">{{ b.nama_ruang }}</div>
                                </td>
                                
                                <!-- Status Column -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold shrink-0 flex items-center gap-1"
                                              :class="STATUS_META[b.status]?.class">
                                            <svg v-if="b.status === 'waiting_confirmation'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z" />
                                            </svg>
                                            <svg v-else-if="b.status === 'confirmed'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z" />
                                            </svg>
                                            <svg v-else-if="b.status === 'final'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766l.002-.001a1.56 1.56 0 0 1 1.883 1.883l-.001.002c-.14.468-.382.89-.766 1.208l-3.03 2.496ZM11.42 15.17l-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243 3 3 0 0 0 4.243 4.243Zm0 0L7.88 9.75a3 3 0 0 0-3 3v.375c0 .621.504 1.125 1.125 1.125h.375M16.5 7.5a1.5 1.5 0 1 1-3 0a1.5 1.5 0 0 1 3 0Z" />
                                            </svg>
                                            {{ STATUS_META[b.status]?.label }}
                                        </span>

                                        <!-- Countdown H-X -->
                                        <span v-if="b.tgl_mulai && diffDays(b.tgl_mulai) > 0"
                                              class="inline-flex items-center gap-1 text-[9px] bg-blue-50 text-blue-700 border border-blue-100 px-1.5 py-0.5 rounded font-semibold shrink-0">
                                            H-{{ diffDays(b.tgl_mulai) }}
                                        </span>
                                        <span v-else-if="b.tgl_mulai && diffDays(b.tgl_mulai) === 0"
                                              class="inline-flex items-center gap-1 text-[9px] bg-orange-50 text-orange-700 border border-orange-100 px-1.5 py-0.5 rounded font-semibold shrink-0 animate-pulse">
                                            Hari ini!
                                        </span>
                                    </div>
                                </td>
                                
                                <!-- Start Date Column -->
                                <td class="py-4 px-6 font-medium text-gray-800">
                                    {{ formatDate(b.tgl_mulai) }}
                                </td>
                                
                                <!-- End Date Column -->
                                <td class="py-4 px-6 font-medium text-gray-800">
                                    {{ formatDate(b.tgl_selesai) }}
                                </td>
                                
                                <!-- Capacity Column -->
                                <td class="py-4 px-6">
                                    <div class="font-bold text-gray-800">{{ b.jumlah_peserta }} <span class="text-[10px] font-normal text-gray-500">Peserta</span></div>
                                    <div class="text-[10px] text-gray-400 font-medium mt-0.5">{{ b.jumlah_panitia }} <span class="font-normal">Panitia</span></div>
                                </td>
                                
                                <!-- Actions Column -->
                                <td class="py-4 px-6 text-right">
                                    <button @click="openDetail(b)"
                                            class="inline-flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white py-1.5 px-3 rounded-lg text-xs font-bold transition shadow-xs active:scale-[0.98]">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        Lihat Detail
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Modal: Konfirmasi Batalkan -->
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

        <!-- Drawer: Detail Booking (Slide-over dari Kanan) -->
        <Teleport to="body">
            <!-- Backdrop overlay -->
            <transition
                enter-active-class="transition-opacity duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showDetailDrawer" 
                     @click="closeDetail"
                     class="fixed inset-0 bg-black/40 z-40 backdrop-blur-xs"></div>
            </transition>

            <!-- Slide-over panel -->
            <transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <div v-if="showDetailDrawer && selectedDetail" 
                     class="fixed inset-y-0 right-0 w-[50vw] min-w-[560px] bg-white shadow-2xl z-45 flex flex-col border-l border-gray-150">
                    
                    <!-- Drawer Header -->
                    <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Detail Booking</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Informasi lengkap pengajuan booking ruangan</p>
                        </div>
                        <button @click="closeDetail" 
                                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Drawer Body (Scrollable) -->
                    <div class="flex-1 overflow-y-auto">

                        <!-- Status & Training Header -->
                        <div class="px-6 pt-5 pb-4 space-y-4 border-b border-gray-100">
                            <!-- Status Row -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold shrink-0 flex items-center gap-1.5"
                                      :class="STATUS_META[selectedDetail.status]?.class">
                                    <svg v-if="selectedDetail.status === 'waiting_confirmation'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <svg v-else-if="selectedDetail.status === 'confirmed'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <svg v-else-if="selectedDetail.status === 'final'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766l.002-.001a1.56 1.56 0 0 1 1.883 1.883l-.001.002c-.14.468-.382.89-.766 1.208l-3.03 2.496ZM11.42 15.17l-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243 3 3 0 0 0 4.243 4.243Z" />
                                    </svg>
                                    {{ STATUS_META[selectedDetail.status]?.label }}
                                </span>

                                <span v-if="selectedDetail.tgl_mulai && diffDays(selectedDetail.tgl_mulai) > 0"
                                      class="inline-flex items-center gap-1 text-[10px] bg-blue-50 text-blue-700 border border-blue-100 px-2.5 py-1 rounded-full font-semibold shrink-0">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    H-{{ diffDays(selectedDetail.tgl_mulai) }} lagi
                                </span>
                                <span v-else-if="selectedDetail.tgl_mulai && diffDays(selectedDetail.tgl_mulai) === 0"
                                      class="inline-flex items-center gap-1 text-[10px] bg-orange-50 text-orange-700 border border-orange-100 px-2.5 py-1 rounded-full font-semibold shrink-0 animate-pulse">
                                    Hari ini!
                                </span>
                            </div>

                            <!-- Training Name -->
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nama Kegiatan</p>
                                <p class="text-lg font-bold text-gray-900 leading-snug mt-0.5">{{ selectedDetail.nama_training }}</p>
                                <p class="text-xs text-gray-500 mt-1">No. Booking: <span class="font-semibold text-gray-700">#{{ selectedDetail.id }}</span> · Diajukan: {{ selectedDetail.created_at }}</p>
                            </div>
                        </div>

                        <!-- Info Umum — 2-column compact grid -->
                        <div class="px-6 pt-5 pb-4">

                            <!-- Grid 2 kolom -->
                            <div class="grid grid-cols-2 gap-3">

                                <!-- Ruangan -->
                                <div class="flex items-start gap-2.5 p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div class="p-1.5 bg-blue-50 text-blue-600 rounded-lg shrink-0 mt-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Ruangan</p>
                                        <p class="text-xs font-bold text-gray-800 mt-0.5 leading-snug">{{ selectedDetail.nama_ruang }}</p>
                                        <p v-if="selectedDetail.gabung_ruang" class="text-[9px] text-blue-600 font-medium mt-0.5">Gabungan</p>
                                    </div>
                                </div>

                                <!-- Waktu Pelaksanaan -->
                                <div class="flex items-start gap-2.5 p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg shrink-0 mt-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Waktu</p>
                                        <p class="text-xs font-bold text-gray-800 mt-0.5 leading-snug">{{ formatDate(selectedDetail.tgl_mulai) }}</p>
                                        <p class="text-[10px] text-gray-500 mt-0.5">s/d {{ formatDate(selectedDetail.tgl_selesai) }}</p>
                                    </div>
                                </div>

                                <!-- Peserta & Panitia -->
                                <div class="flex items-start gap-2.5 p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div class="p-1.5 bg-purple-50 text-purple-600 rounded-lg shrink-0 mt-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Peserta & Panitia</p>
                                        <div class="flex items-center gap-3 mt-1">
                                            <div>
                                                <span class="text-sm font-bold text-gray-800">{{ selectedDetail.jumlah_peserta }}</span>
                                                <span class="text-[10px] text-gray-500 ml-1">peserta</span>
                                            </div>
                                            <div class="w-px h-3 bg-gray-300"></div>
                                            <div>
                                                <span class="text-sm font-bold text-gray-800">{{ selectedDetail.jumlah_panitia }}</span>
                                                <span class="text-[10px] text-gray-500 ml-1">panitia</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PIC / Pengaju -->
                                <div class="flex items-start gap-2.5 p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg shrink-0 mt-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">PIC</p>
                                        <p class="text-xs font-bold text-gray-800 mt-0.5 leading-snug truncate">{{ selectedDetail.pic }}</p>
                                        <p class="text-[10px] text-gray-500 mt-0.5">{{ selectedDetail.created_at }}</p>
                                    </div>
                                </div>

                                <!-- Fasilitas & Setup — full width -->
                                <div class="col-span-2 flex items-start gap-2.5 p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div class="p-1.5 bg-amber-50 text-amber-600 rounded-lg shrink-0 mt-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider mb-1.5">Fasilitas &amp; Setup</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span v-if="selectedDetail.layout_preferensi"
                                                  class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-100 rounded-md text-[10px] font-semibold">
                                                Layout: {{ selectedDetail.layout_preferensi }}
                                            </span>
                                            <span v-if="selectedDetail.is_hybrid"
                                                  class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-50 text-violet-700 border border-violet-100 rounded-md text-[10px] font-semibold">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                </svg>
                                                Hybrid
                                            </span>
                                            <span v-if="selectedDetail.is_flipchart"
                                                  class="inline-flex items-center gap-1 px-2 py-0.5 bg-orange-50 text-orange-700 border border-orange-100 rounded-md text-[10px] font-semibold">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                                                </svg>
                                                Flipchart
                                            </span>
                                            <span v-if="!selectedDetail.layout_preferensi && !selectedDetail.is_hybrid && !selectedDetail.is_flipchart"
                                                  class="text-[10px] text-gray-400 italic">Tidak ada fasilitas tambahan</span>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Catatan User (full width, di bawah grid) -->
                            <div v-if="selectedDetail.catatan_user"
                                 class="mt-3 p-3 bg-blue-50 border border-blue-100 rounded-xl">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-blue-800">Catatan dari Pengaju</p>
                                <p class="text-xs text-blue-900 leading-relaxed mt-1">{{ selectedDetail.catatan_user }}</p>
                            </div>

                            <!-- Catatan Admin (full width, di bawah grid) -->
                            <div v-if="selectedDetail.catatan_admin"
                                 class="mt-3 p-3 bg-amber-50 border border-amber-100 rounded-xl">
                                <div class="flex items-center gap-1.5 text-amber-800 mb-1">
                                    <svg class="w-3 h-3 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0zm-9-3.75h.008v.008H12V8.25z" />
                                    </svg>
                                    <p class="text-[9px] font-bold uppercase tracking-wider">Catatan Admin</p>
                                </div>
                                <p class="text-xs text-amber-900 leading-relaxed font-medium">{{ selectedDetail.catatan_admin }}</p>
                            </div>

                        </div>


                        <!-- ── Daftar Peserta ─────────────────────────────── -->
                        <div class="px-6 pb-2">
                            <div class="border-t border-gray-100 pt-5">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm font-bold text-gray-800">Daftar Peserta</p>
                                    </div>
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold">{{ selectedDetail.jumlah_peserta }} orang</span>
                                </div>

                                <!-- empty state -->
                                <div v-if="!selectedDetail.participants?.filter(p => p.tipe === 'peserta').length"
                                     class="text-center py-6 border border-dashed border-gray-200 rounded-xl">
                                    <p class="text-xs text-gray-400">Belum ada data peserta</p>
                                </div>

                                <!-- list -->
                                <div v-else class="space-y-2">
                                    <div v-for="(p, i) in selectedDetail.participants?.filter(p => p.tipe === 'peserta')" :key="p.id"
                                         class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 hover:bg-gray-50/60 transition-colors">
                                        <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">
                                            {{ i + 1 }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-gray-800 leading-snug">{{ p.nama }}</p>
                                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
                                                <span v-if="p.nrp" class="text-[10px] text-gray-500">NRP: <span class="font-semibold text-gray-700">{{ p.nrp }}</span></span>
                                                <span v-if="p.jabatan" class="text-[10px] text-gray-500">{{ p.jabatan }}</span>
                                                <span v-if="p.site" class="text-[10px] text-gray-500">{{ p.site }}</span>
                                                <span v-if="p.gender" class="text-[10px] text-gray-500 capitalize">{{ p.gender }}</span>
                                                <span v-if="p.no_hp" class="text-[10px] text-gray-500">{{ p.no_hp }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── Daftar Panitia ─────────────────────────────── -->
                        <div class="px-6 pb-6">
                            <div class="border-t border-gray-100 pt-5">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="p-1.5 bg-violet-50 text-violet-600 rounded-lg">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm font-bold text-gray-800">Daftar Panitia</p>
                                    </div>
                                    <span class="px-2 py-0.5 bg-violet-100 text-violet-700 rounded-full text-[10px] font-bold">{{ selectedDetail.jumlah_panitia }} orang</span>
                                </div>

                                <!-- empty state -->
                                <div v-if="!selectedDetail.participants?.filter(p => p.tipe === 'panitia').length"
                                     class="text-center py-6 border border-dashed border-gray-200 rounded-xl">
                                    <p class="text-xs text-gray-400">Belum ada data panitia</p>
                                </div>

                                <!-- list -->
                                <div v-else class="space-y-2">
                                    <div v-for="(p, i) in selectedDetail.participants?.filter(p => p.tipe === 'panitia')" :key="p.id"
                                         class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 hover:bg-gray-50/60 transition-colors">
                                        <div class="w-6 h-6 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">
                                            {{ i + 1 }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-gray-800 leading-snug">{{ p.nama }}</p>
                                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
                                                <span v-if="p.nrp" class="text-[10px] text-gray-500">NRP: <span class="font-semibold text-gray-700">{{ p.nrp }}</span></span>
                                                <span v-if="p.jabatan" class="text-[10px] text-gray-500">{{ p.jabatan }}</span>
                                                <span v-if="p.site" class="text-[10px] text-gray-500">{{ p.site }}</span>
                                                <span v-if="p.gender" class="text-[10px] text-gray-500 capitalize">{{ p.gender }}</span>
                                                <span v-if="p.no_hp" class="text-[10px] text-gray-500">{{ p.no_hp }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Drawer Footer Actions -->
                    <div class="p-6 border-t border-gray-100 bg-gray-50/50 space-y-3">
                        <!-- Download Ticket/PDF -->
                        <a :href="`/user/booking/${selectedDetail.id}/pdf`" 
                           target="_blank"
                           class="w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white py-3 px-4 rounded-xl text-sm font-bold transition shadow-sm active:scale-[0.99]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Unduh Tiket / PDF
                        </a>

                        <!-- Cancel Booking (If can cancel) -->
                        <button v-if="selectedDetail.can_cancel" 
                                @click="openCancel(selectedDetail)"
                                class="w-full inline-flex items-center justify-center gap-2 border border-red-200 text-red-650 hover:bg-red-50 hover:text-red-700 py-3 px-4 rounded-xl text-sm font-bold transition active:scale-[0.99]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Batalkan Booking
                        </button>
                    </div>

                </div>
            </transition>
        </Teleport>
    </UserLayout>
</template>
