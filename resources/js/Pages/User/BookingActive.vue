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
        icon: '⏳',
    },
    confirmed: {
        label: 'Disetujui',
        class: 'bg-green-100 text-green-800 border border-green-200',
        icon: '✅',
    },
    final: {
        label: 'Final ACC / Persiapan Lapangan',
        class: 'bg-indigo-100 text-indigo-800 border border-indigo-200',
        icon: '🔧',
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
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">📋 Booking Aktif</h1>
                    <p class="text-sm text-gray-500 mt-1">Booking yang sedang menunggu persetujuan atau sudah dikonfirmasi</p>
                </div>
                <a href="/user/booking/create"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm">
                    + Buat Booking Baru
                </a>
            </div>

            <!-- Empty state -->
            <div v-if="bookings.length === 0"
                 class="bg-white rounded-xl border border-dashed border-gray-200 py-20 text-center">
                <div class="text-5xl mb-4">📭</div>
                <p class="text-gray-500 font-medium">Tidak ada booking aktif saat ini.</p>
                <p class="text-sm text-gray-400 mt-1">Booking yang sudah selesai atau dibatalkan ada di menu Riwayat Booking.</p>
                <a href="/user/booking/create"
                   class="inline-block mt-6 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-5 rounded-lg text-sm transition">
                    Buat Booking Sekarang
                </a>
            </div>

            <!-- Booking cards -->
            <div v-else class="space-y-4">
                <div v-for="b in bookings" :key="b.id"
                     class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                    <div class="flex flex-col md:flex-row">

                        <!-- Status bar vertikal kiri -->
                        <div :class="b.status === 'final' ? 'bg-indigo-500' : (b.status === 'confirmed' ? 'bg-green-500' : 'bg-yellow-400')"
                             class="w-full md:w-1.5 h-1.5 md:h-auto flex-shrink-0 rounded-t-xl md:rounded-none md:rounded-l-xl"></div>

                        <div class="flex-1 p-5">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <!-- Info utama -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="font-bold text-gray-900 text-base truncate">{{ b.nama_training }}</h2>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold shrink-0"
                                              :class="STATUS_META[b.status]?.class">
                                            {{ STATUS_META[b.status]?.icon }} {{ STATUS_META[b.status]?.label }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">PIC: <span class="font-medium text-gray-700">{{ b.pic }}</span> · Diajukan: {{ b.created_at }}</p>

                                    <!-- Detail grid -->
                                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                                        <div>
                                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">Ruangan</p>
                                            <p class="font-medium text-gray-800">{{ b.nama_ruang }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">Tanggal</p>
                                            <p class="font-medium text-gray-800">{{ formatDate(b.tgl_mulai) }}</p>
                                            <p class="text-xs text-gray-500">s/d {{ formatDate(b.tgl_selesai) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">Peserta</p>
                                            <p class="font-medium text-gray-800">{{ b.jumlah_peserta }} peserta</p>
                                            <p class="text-xs text-gray-500">{{ b.jumlah_panitia }} panitia</p>
                                        </div>
                                    </div>

                                    <!-- Countdown H-X -->
                                    <div v-if="b.tgl_mulai" class="mt-3">
                                        <span v-if="diffDays(b.tgl_mulai) > 0"
                                              class="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded-full font-medium">
                                            🗓️ H-{{ diffDays(b.tgl_mulai) }} lagi
                                        </span>
                                        <span v-else-if="diffDays(b.tgl_mulai) === 0"
                                              class="inline-flex items-center gap-1 text-xs bg-orange-50 text-orange-700 border border-orange-100 px-2 py-0.5 rounded-full font-medium">
                                            🔥 Hari ini!
                                        </span>
                                        <span v-else
                                              class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">
                                            Sudah lewat
                                        </span>
                                    </div>
                                </div>

                                <!-- Aksi -->
                                <div class="flex flex-row sm:flex-col gap-2 sm:items-end shrink-0">
                                    <a :href="`/user/booking/${b.id}/detail`"
                                       class="inline-flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition shadow-sm">
                                        📄 Lihat Detail
                                    </a>
                                    <button v-if="b.can_cancel" @click="openCancel(b)"
                                            class="inline-flex items-center gap-1 border border-red-200 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                        🗑️ Batalkan
                                    </button>
                                    <a :href="`/user/booking/${b.id}/pdf`" target="_blank"
                                       class="inline-flex items-center gap-1 border border-gray-200 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                        ⬇️ PDF
                                    </a>
                                </div>
                            </div>

                            <!-- Catatan admin jika ada -->
                            <div v-if="b.catatan_admin"
                                 class="mt-3 p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                                <span class="font-semibold">Catatan Admin:</span> {{ b.catatan_admin }}
                            </div>
                        </div>
                    </div>
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
    </UserLayout>
</template>
