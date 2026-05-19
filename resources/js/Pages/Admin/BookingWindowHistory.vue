<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    auth: Object,
    windows: Array,
})

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
    <AdminLayout :auth="auth">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900">Riwayat Window Booking</h1>
            <p class="text-sm text-gray-500 mt-1">Daftar semua periode pembukaan window booking beserta rekapitulasi datanya.</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tahun Target</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal Buka</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal Tutup</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Booking</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="windows.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">
                            Belum ada riwayat window booking.
                        </td>
                    </tr>
                    <tr v-for="w in windows" :key="w.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm text-gray-900 font-medium">
                            {{ w.tahun }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ formatDate(w.start_date) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ formatDate(w.end_date) }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span v-if="w.is_active" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Aktif
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                Tutup
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-sm font-bold text-gray-700">{{ w.total_booking }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
