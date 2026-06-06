<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: UserLayout })

const props = defineProps({
    auth: Object,
    bookings: Array,
})

const STATUS_META = {
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
        window.Echo.leaveChannel('bookings')
    }
})
</script>

<template>
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

            <!-- Booking Lists Table - Admin Style -->
            <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden w-full">
                <div class="overflow-x-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                    <table class="min-w-full table-fixed divide-y divide-slate-100">
                        <thead class="bg-slate-50/80 backdrop-blur-sm border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[24%]">Acara / PIC</th>
                                <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[12%]">Ruangan</th>
                                <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[16%]">Jadwal</th>
                                <th class="px-5 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[10%]">Peserta</th>
                                <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[12%]">Fasilitas</th>
                                <th class="px-5 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[12%]">Status</th>
                                <th class="px-5 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider w-[14%]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <tr v-for="b in bookings" :key="b.id" class="hover:bg-slate-50/50 transition-all duration-200 align-top group">

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
                                        {{ b.nama_ruang ?? 'Ruang Gabungan' }}
                                    </div>
                                    <div v-if="b.layout_preferensi" class="text-[11px] text-slate-400 capitalize mt-2 flex items-center gap-1">
                                        Layout: <span class="font-medium text-slate-600">{{ b.layout_preferensi }}</span>
                                    </div>
                                </td>

                                <!-- Jadwal -->
                                <td class="px-5 py-4 text-xs text-slate-700">
                                    <div class="font-bold text-slate-800 bg-slate-50 border border-slate-100 px-2 py-1 rounded-md inline-block mb-1">{{ formatDate(b.tgl_mulai) }}</div>
                                    <div class="text-slate-400 text-[10px] ml-2 mb-1">s/d</div>
                                    <div class="font-bold text-slate-800 bg-slate-50 border border-slate-100 px-2 py-1 rounded-md inline-block">{{ formatDate(b.tgl_selesai) }}</div>
                                    <!-- Countdown H-X -->
                                    <div v-if="b.tgl_mulai && diffDays(b.tgl_mulai) > 0"
                                         class="mt-2 font-bold text-[11px] w-fit shadow-sm px-2 py-0.5 rounded-md"
                                         :class="diffDays(b.tgl_mulai) <= 14
                                             ? 'text-red-700 bg-red-50 border border-red-200'
                                             : 'text-slate-600 bg-slate-100 border border-slate-200'">
                                        H-{{ diffDays(b.tgl_mulai) }}
                                    </div>
                                    <div v-else-if="b.tgl_mulai && diffDays(b.tgl_mulai) === 0"
                                         class="mt-2 inline-flex items-center gap-1 text-[11px] bg-orange-50 text-orange-700 border border-orange-100 px-2 py-0.5 rounded-md font-bold animate-pulse">
                                        Hari ini!
                                    </div>
                                </td>

                                <!-- Peserta -->
                                <td class="px-5 py-4 text-center">
                                    <div class="inline-flex flex-col items-center bg-blue-50/50 border border-blue-100 px-3 py-1.5 rounded-xl">
                                        <div class="text-lg font-black text-blue-700 leading-none">{{ b.jumlah_peserta }}</div>
                                        <div class="text-[10px] font-bold text-blue-500 uppercase mt-0.5">Peserta</div>
                                    </div>
                                    <div class="text-[11px] font-semibold text-amber-600 mt-1.5 bg-amber-50 rounded-md py-0.5 border border-amber-100">+{{ b.jumlah_panitia }} Panitia</div>
                                </td>

                                <!-- Fasilitas -->
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

                                <!-- Status -->
                                <td class="px-5 py-4 text-center">
                                    <span class="px-3 py-1.5 rounded-full text-[11px] font-bold shadow-sm"
                                          :class="STATUS_META[b.status]?.class ?? 'bg-slate-100 text-slate-600 border border-slate-200'">
                                        {{ STATUS_META[b.status]?.label ?? b.status }}
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="px-5 py-4">
                                    <div class="flex flex-col gap-2">
                                        <a :href="`/user/booking/${b.id}/detail`"
                                           class="group/btn flex items-center justify-center gap-1.5 bg-white hover:bg-slate-50 text-slate-700 hover:text-blue-600 text-[11px] font-bold py-2 px-3 rounded-xl transition-all border border-slate-200 hover:border-blue-300 shadow-sm hover:shadow">
                                            <svg class="w-3.5 h-3.5 text-slate-400 group-hover/btn:text-blue-500 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Lihat Detail
                                        </a>
                                    </div>
                                </td>

                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

    </div>

        <!-- Modal: Konfirmasi Batalkan -->
    <Teleport to="body">
        <Transition 
            enter-active-class="transition-all ease-out duration-300" 
            enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0" 
            enter-to-class="opacity-100 scale-100 translate-y-0" 
            leave-active-class="transition-all ease-in duration-200" 
            leave-from-class="opacity-100 scale-100 translate-y-0" 
            leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0">
            <div v-if="showCancelModal" class="fixed inset-0 bg-white/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl border border-blue-900 shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-50 border border-red-200 rounded-full mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-center text-gray-900 mb-2">Batalkan Booking?</h2>
                    <p class="text-sm text-center text-gray-600 mb-1">
                        Anda yakin ingin membatalkan:
                        <strong class="text-gray-800 block mt-0.5">{{ selectedBooking?.nama_training }}</strong>?
                    </p>
                    <p class="text-xs text-center text-red-500 mb-4">Tindakan ini permanen dan tidak dapat diurungkan.</p>
                    <div v-if="cancelError" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded p-3">
                        {{ cancelError }}
                    </div>
                    <div class="flex gap-3">
                        <button @click="showCancelModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none">
                            Kembali
                        </button>
                        <button @click="submitCancel" :disabled="cancelLoading"
                                class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-bold disabled:opacity-50 transition cursor-pointer select-none">
                            {{ cancelLoading ? 'Membatalkan...' : 'Ya, Batalkan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
