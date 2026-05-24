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
    confirmed:            { label: 'Disetujui',            class: 'bg-green-100 text-green-800' },
    cancelled:            { label: 'Dibatalkan',           class: 'bg-red-100 text-red-800' },
    final:                { label: 'Final',                class: 'bg-blue-100 text-blue-800' },
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
                    <a href="/user/booking/history" class="text-gray-500 hover:text-gray-700 bg-gray-100 px-3 py-1.5 rounded text-sm transition">
                        &larr; Kembali
                    </a>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Detail Booking #{{ String(booking.id).padStart(5, '0') }}</h1>
                </div>
                <div class="flex gap-2">
                    <button v-if="booking.can_cancel" @click="showCancelModal = true" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded text-sm transition shadow-sm">
                        Batalkan Booking
                    </button>
                    <a :href="`/user/booking/${booking.id}/pdf`" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded text-sm transition shadow-sm flex items-center gap-2" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Unduh Tiket / Bukti PDF
                    </a>
                </div>
            </div>

            <!-- Ringkasan Acara -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-800">Informasi Acara</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 text-sm">
                        <div>
                            <p class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-1">Nama Training</p>
                            <p class="font-semibold text-gray-900">{{ booking.nama_training }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-1">Status</p>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="STATUS_META[booking.status]?.class ?? 'bg-gray-100 text-gray-600'">
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
