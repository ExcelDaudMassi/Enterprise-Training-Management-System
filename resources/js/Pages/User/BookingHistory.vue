<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { Link } from '@inertiajs/vue3'
import { inject } from 'vue'

defineProps({
    auth: Object,
    bookings: Array
})

const isWindowActive = inject('isWindowActive')

function formatStatus(status) {
    if (status === 'waiting_confirmation') return { label: 'Menunggu Persetujuan', class: 'bg-yellow-100 text-yellow-800' }
    if (status === 'confirmed') return { label: 'Disetujui', class: 'bg-green-100 text-green-800' }
    if (status === 'cancelled') return { label: 'Dibatalkan', class: 'bg-red-100 text-red-800' }
    return { label: status, class: 'bg-gray-100 text-gray-800' }
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
    <UserLayout :auth="auth">
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-800">Riwayat Booking</h1>
                <div v-if="!isWindowActive"
                     class="bg-gray-300 text-gray-500 text-sm font-medium px-4 py-2 rounded cursor-not-allowed border border-gray-400"
                     title="Window Booking sedang ditutup oleh Admin.">
                    + Buat Booking Baru (Tutup)
                </div>
                <Link v-else href="/user/booking/create" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded text-sm transition">
                    + Buat Booking Baru
                </Link>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acara</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ruangan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jadwal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="bookings.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                Anda belum memiliki riwayat pemesanan ruangan.
                            </td>
                        </tr>
                        <tr v-for="booking in bookings" :key="booking.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ booking.nama_training }}</div>
                                <div class="text-xs text-gray-500">PIC: {{ booking.pic }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 font-semibold">{{ booking.ruangan?.nama_ruang || 'Ruang Gabungan' }}</div>
                                <div class="text-xs text-gray-500">{{ booking.gabung_ruang ? 'Gabungan' : booking.ruangan?.lokasi_gedung }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ formatDate(booking.tgl_mulai) }}</div>
                                <div class="text-xs text-gray-500">s/d {{ formatDate(booking.tgl_selesai) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                      :class="formatStatus(booking.status).class">
                                    {{ formatStatus(booking.status).label }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </UserLayout>
</template>
