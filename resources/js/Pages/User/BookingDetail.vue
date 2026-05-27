<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    auth: Object,
    booking: Object
})

const STATUS_META = {
    waiting_confirmation: { label: 'Menunggu Persetujuan', class: 'bg-yellow-100 text-yellow-800' },
    confirmed:            { label: 'Disetujui',            class: 'bg-indigo-100 text-indigo-800 border border-indigo-200' },
    cancelled:            { label: 'Dibatalkan',           class: 'bg-red-100 text-red-800' },
    final:                { label: 'Final ACC / Persiapan Lapangan', class: 'bg-green-100 text-green-800 border border-green-200' },
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

const showCancelModal = ref(false)
const cancelLoading = ref(false)
const cancelError = ref('')

async function submitCancel() {
    cancelLoading.value = true
    cancelError.value = ''
    try {
        await window.axios.post(`/api/booking/${props.booking.id}/cancel`)
        showCancelModal.value = false
        window.location.reload()
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
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <a href="/user/booking/history" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg text-sm transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Kembali
                    </a>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Detail Booking #{{ String(booking.id).padStart(5, '0') }}</h1>
                </div>
                <div class="flex gap-2">
                    <button v-if="booking.can_cancel" @click="showCancelModal = true" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        Batalkan Booking
                    </button>
                    <a :href="`/user/booking/${booking.id}/pdf`" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm flex items-center gap-2" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Unduh Tiket / Bukti PDF
                    </a>
                </div>
            </div>

            <!-- Banner Persiapan Lapangan -->
            <div v-if="booking.status === 'final'" class="bg-indigo-50 border border-indigo-100 p-4 rounded-xl flex items-start shadow-sm animate-fade-in">
                <div class="flex-shrink-0 text-indigo-500 mt-0.5">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-semibold text-indigo-800">Ruangan sedang dipersiapkan Tim Lapangan</h3>
                    <p class="text-sm text-indigo-700 mt-1">
                        Pemesanan Anda telah mendapatkan persetujuan akhir. Tim lapangan kami sedang mempersiapkan ruangan beserta fasilitas yang Anda minta.
                    </p>
                </div>
            </div>

            <!-- Ringkasan Acara -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-800">Informasi Acara</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 text-sm">
                        <div>
                            <p class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-1">Nama Training</p>
                            <p class="font-semibold text-gray-900 text-base">{{ booking.nama_training }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-1">Status</p>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold inline-flex items-center gap-1" :class="STATUS_META[booking.status]?.class ?? 'bg-gray-100 text-gray-600'">
                                <!-- Hourglass SVG for waiting_confirmation -->
                                <svg v-if="booking.status === 'waiting_confirmation'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <!-- Check Circle SVG for confirmed -->
                                <svg v-else-if="booking.status === 'confirmed'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <!-- Trash/Cancelled SVG -->
                                <svg v-else-if="booking.status === 'cancelled'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <!-- Wrench SVG for final -->
                                <svg v-else-if="booking.status === 'final'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766l.002-.001a1.56 1.56 0 0 1 1.883 1.883l-.001.002c-.14.468-.382.89-.766 1.208l-3.03 2.496ZM11.42 15.17l-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243 3 3 0 0 0 4.243 4.243Zm0 0L7.88 9.75a3 3 0 0 0-3 3v.375c0 .621.504 1.125 1.125 1.125h.375M16.5 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                </svg>
                                {{ STATUS_META[booking.status]?.label ?? booking.status }}
                            </span>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-1">Ruangan</p>
                            <p class="font-medium text-gray-900">{{ booking.nama_ruang }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-1">Tanggal Pelaksanaan</p>
                            <p class="font-medium text-gray-900">{{ formatDate(booking.tgl_mulai) }} s/d {{ formatDate(booking.tgl_selesai) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-1">PIC</p>
                            <p class="font-medium text-gray-900">{{ booking.pic }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-1">Kebutuhan Tambahan</p>
                            <p class="text-gray-700">Layout: <span class="capitalize font-medium">{{ booking.layout_preferensi }}</span> | Hybrid: <span class="font-medium">{{ booking.is_hybrid ? 'Ya' : 'Tidak' }}</span> | Flipchart: <span class="font-medium">{{ booking.is_flipchart ? 'Ya' : 'Tidak' }}</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manifest Peserta -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">Manifest Peserta</h2>
                    <span class="text-xs font-medium bg-gray-200 text-gray-700 px-2 py-1 rounded">Total: {{ booking.participants.length }} orang</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NRP</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Site</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No HP</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="!booking.participants || booking.participants.length === 0">
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">Tidak ada data peserta.</td>
                            </tr>
                            <tr v-for="p in booking.participants" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3 whitespace-nowrap font-medium text-gray-900">{{ p.nama }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-gray-600 font-mono text-xs">{{ p.nrp || 'N/A' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap capitalize">
                                    <span :class="p.tipe === 'panitia' ? 'text-blue-600 bg-blue-50 px-2 py-0.5 rounded text-xs' : 'text-gray-600'">{{ p.tipe }}</span>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-gray-600">{{ p.jabatan || '-' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-gray-600">{{ p.site || '-' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-gray-600">{{ p.no_hp || '-' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-gray-600">{{ p.gender || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Batal -->
        <Teleport to="body">
            <div v-if="showCancelModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 transform transition-all">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-center text-gray-900 mb-2">Batalkan Booking?</h2>
                    <p class="text-sm text-center text-gray-600 mb-4">Anda yakin ingin membatalkan jadwal ini secara permanen? Ruangan akan langsung terlepas dan dapat dipesan oleh divisi lain.</p>
                    
                    <div v-if="cancelError" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded p-3">
                        {{ cancelError }}
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button @click="showCancelModal = false" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Tutup
                        </button>
                        <button @click="submitCancel" :disabled="cancelLoading" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition">
                            {{ cancelLoading ? 'Membatalkan...' : 'Ya, Batalkan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </UserLayout>
</template>
