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
                                    <a :href="`/user/booking/${b.id}/detail`"
                                       class="inline-flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white py-1.5 px-3 rounded-md text-xs font-bold transition shadow-xs active:scale-[0.98]">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        Lihat Detail
                                    </a>
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
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Kembali
                        </button>
                        <button @click="submitCancel" :disabled="cancelLoading"
                                class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-medium disabled:opacity-50 transition">
                            {{ cancelLoading ? 'Membatalkan...' : 'Ya, Batalkan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </UserLayout>
</template>
