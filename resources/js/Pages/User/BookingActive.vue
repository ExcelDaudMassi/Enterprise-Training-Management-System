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
        cancelError.value = e.response?.data?.message ?? 'An error occurred.'
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
                        <h1 class="text-2xl font-bold text-gray-800">Active Bookings</h1>
                        <p class="text-sm text-gray-500 mt-1">Bookings waiting for approval or already confirmed</p>
                    </div>
                </div>
                <a href="/user/booking/create"
                   class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create New Booking
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
                <p class="text-gray-500 font-medium">There are no active bookings at this time.</p>
                <p class="text-sm text-gray-400 mt-1">Completed or cancelled bookings are in the Booking History menu.</p>
                <a href="/user/booking/create"
                   class="inline-flex items-center gap-1.5 mt-6 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg text-sm transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create Booking Now
                </a>
            </div>

            <!-- Booking Cards Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 w-full">
                <div v-for="b in bookings" :key="b.id" class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden flex flex-col group relative">
                    <div class="p-5 flex-1 flex flex-col">
                        <!-- Header: Type & Status -->
                        <div class="flex items-center justify-between gap-2 mb-4">
                            <!-- Badge Tipe Booking -->
                            <span v-if="b.tipe_booking === 'early'"
                                class="inline-flex items-center gap-1.5 text-[10px] font-black px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 uppercase tracking-wider shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse shrink-0"></span>
                                Early
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1.5 text-[10px] font-black px-2.5 py-1 rounded-full bg-blue-50 text-blue-600 border border-blue-200 uppercase tracking-wider shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                                Regular
                            </span>
                            
                            <!-- Status Badge -->
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold shadow-sm uppercase tracking-wider shrink-0"
                                  :class="STATUS_META[b.status]?.class ?? 'bg-slate-100 text-slate-600 border border-slate-200'">
                                {{ STATUS_META[b.status]?.label ?? b.status }}
                            </span>
                        </div>

                        <!-- Title & PIC -->
                        <div class="mb-5">
                            <h3 class="text-base font-bold text-slate-900 group-hover:text-blue-700 transition-colors line-clamp-2 leading-tight mb-2" :title="b.nama_training">{{ b.nama_training }}</h3>
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span class="font-medium text-slate-600 truncate">{{ b.pic }}</span>
                                <span class="text-slate-300 mx-0.5">•</span>
                                <span class="text-[10px] text-slate-400 truncate">{{ b.created_at }}</span>
                            </div>
                        </div>

                        <!-- Detailed Info Grid -->
                        <div class="grid grid-cols-2 gap-4 mb-5 p-4 bg-slate-50/50 rounded-xl border border-slate-100/60">
                            <!-- Schedule -->
                            <div class="col-span-2">
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Schedule
                                </div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="font-bold text-slate-700 text-[11px] bg-white border border-slate-200 px-2 py-1 rounded shadow-sm">{{ formatDate(b.tgl_mulai) }}</div>
                                    <span class="text-slate-400 text-[10px] font-medium">to</span>
                                    <div class="font-bold text-slate-700 text-[11px] bg-white border border-slate-200 px-2 py-1 rounded shadow-sm">{{ formatDate(b.tgl_selesai) }}</div>
                                </div>
                                
                                <!-- Countdown H-X -->
                                <div v-if="b.tgl_mulai && diffDays(b.tgl_mulai) > 0"
                                     class="mt-2.5 inline-flex items-center font-bold text-[10px] shadow-sm px-2 py-0.5 rounded border uppercase tracking-wider"
                                     :class="diffDays(b.tgl_mulai) <= 14
                                         ? 'text-red-700 bg-red-50 border-red-200'
                                         : 'text-blue-700 bg-blue-50 border-blue-200'">
                                    H - {{ diffDays(b.tgl_mulai) }}
                                </div>
                                <div v-else-if="b.tgl_mulai && diffDays(b.tgl_mulai) === 0"
                                     class="mt-2.5 inline-flex items-center gap-1 text-[10px] bg-orange-50 text-orange-700 border border-orange-200 px-2 py-0.5 rounded font-bold animate-pulse uppercase tracking-wider shadow-sm">
                                    Today!
                                </div>
                            </div>

                            <!-- Location -->
                            <div class="col-span-1">
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Room
                                </div>
                                <div class="inline-flex items-center gap-1 bg-white text-slate-700 font-bold text-[11px] px-2 py-1 rounded-md border border-slate-200 shadow-sm w-full">
                                    <span class="truncate">{{ b.nama_ruang ?? 'Combined Room' }}</span>
                                </div>
                                <div v-if="b.layout_preferensi" class="text-[10px] text-slate-500 capitalize mt-1.5 flex items-center gap-1 font-medium">
                                    Layout: <span class="font-bold text-slate-600">{{ b.layout_preferensi }}</span>
                                </div>
                            </div>

                            <!-- Participants -->
                            <div class="col-span-1">
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    Capacity
                                </div>
                                <!-- Early Booking: Belum diisi -->
                                <template v-if="b.tipe_booking === 'early' && b.jumlah_peserta === 0">
                                    <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 px-2 py-1 rounded-md">
                                        <span class="text-sm font-black text-amber-600 leading-none">—</span>
                                        <span class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">TBA</span>
                                    </div>
                                </template>
                                <!-- Regular / Early diisi -->
                                <template v-else>
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center justify-between bg-blue-50/70 border border-blue-100 px-2 py-1 rounded-md">
                                            <span class="text-[9px] font-bold text-blue-600 uppercase tracking-wider">Peserta</span>
                                            <span class="text-xs font-black text-blue-700">{{ b.jumlah_peserta }}</span>
                                        </div>
                                        <div class="flex items-center justify-between bg-amber-50/70 border border-amber-100 px-2 py-1 rounded-md">
                                            <span class="text-[9px] font-bold text-amber-600 uppercase tracking-wider">Panitia</span>
                                            <span class="text-xs font-black text-amber-700">{{ b.jumlah_panitia }}</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Facilities -->
                        <div class="mt-auto">
                            <div class="flex flex-wrap gap-1.5">
                                <div v-if="b.is_hybrid" class="flex items-center gap-1.5 bg-purple-50 text-purple-700 font-bold px-2 py-0.5 rounded border border-purple-100 text-[9px] uppercase tracking-wider">
                                    <span class="w-1.5 h-1.5 rounded-full bg-purple-500 flex-shrink-0 animate-pulse"></span>Hybrid
                                </div>
                                <div v-if="b.is_flipchart" class="flex items-center gap-1.5 bg-orange-50 text-orange-700 font-bold px-2 py-0.5 rounded border border-orange-100 text-[9px] uppercase tracking-wider">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500 flex-shrink-0"></span>Flipchart
                                </div>
                                <div v-if="b.is_pena_mini_note" class="flex items-center gap-1.5 bg-teal-50 text-teal-700 font-bold px-2 py-0.5 rounded border border-teal-100 text-[9px] uppercase tracking-wider">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500 flex-shrink-0"></span>Pen & Notes
                                </div>
                                <div v-if="!b.is_hybrid && !b.is_flipchart && !b.is_pena_mini_note" class="text-slate-400 text-[10px] font-medium italic">
                                    No extra facilities requested.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex gap-2 shrink-0">
                        <a :href="`/user/booking/${b.id}/detail`"
                           class="flex-1 group/btn flex items-center justify-center gap-1.5 bg-white hover:bg-blue-600 text-slate-700 hover:text-white text-[11px] font-bold py-2.5 px-3 rounded-lg transition-all border border-slate-200 hover:border-blue-600 shadow-sm hover:shadow">
                            <svg class="w-4 h-4 text-slate-400 group-hover/btn:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            View Details
                        </a>
                        
                        <!-- Tombol khusus Early Booking: input peserta -->
                        <a v-if="b.tipe_booking === 'early' && b.jumlah_peserta === 0"
                           :href="`/user/booking/${b.id}/detail?action=fill_participants`"
                           class="flex-[0.8] group/btn flex items-center justify-center gap-1.5 bg-red-500 hover:bg-red-600 text-white text-[11px] font-bold py-2.5 px-3 rounded-lg transition-all shadow-sm hover:shadow animate-pulse border border-red-600 hover:border-red-700">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Isi Peserta!
                        </a>
                    </div>
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
                    <h2 class="text-lg font-bold text-center text-gray-900 mb-2">Cancel Booking?</h2>
                    <p class="text-sm text-center text-gray-600 mb-1">
                        Are you sure you want to cancel:
                        <strong class="text-gray-800 block mt-0.5">{{ selectedBooking?.nama_training }}</strong>?
                    </p>
                    <p class="text-xs text-center text-red-500 mb-4">This action is permanent and cannot be undone.</p>
                    <div v-if="cancelError" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded p-3">
                        {{ cancelError }}
                    </div>
                    <div class="flex gap-3">
                        <button @click="showCancelModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none">
                            Back
                        </button>
                        <button @click="submitCancel" :disabled="cancelLoading"
                                class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-bold disabled:opacity-50 transition cursor-pointer select-none">
                            {{ cancelLoading ? 'Cancelling...' : 'Yes, Cancel' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
