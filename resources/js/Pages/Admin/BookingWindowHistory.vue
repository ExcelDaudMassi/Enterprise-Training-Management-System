<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

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
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900">Booking Window History</h1>
            <p class="text-sm text-gray-500 mt-1">List of all booking window periods along with their data recapitulation.</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Target Year</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Open Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Close Date</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Bookings</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="windows.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">
                            No booking window history yet.
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
                                Active
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                Closed
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-sm font-bold text-gray-700">{{ w.total_booking }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
</template>
