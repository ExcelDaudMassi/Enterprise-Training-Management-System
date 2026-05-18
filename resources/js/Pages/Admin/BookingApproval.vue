<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    auth:         Object,
    bookings:     Array,
    activeFilter: { type: String, default: 'all' },
})

// Map filter dari dashboard ke tab yang relevan
const filterToTab = {
    'waiting_confirmation': 'waiting_confirmation',
    'confirmed':            'confirmed',
    'cancelled':            'cancelled',
    'urgent':               'urgent',
    'overdue':              'overdue',
    'all':                  'all',
}
const activeTab = ref(filterToTab[props.activeFilter] ?? 'all')

const tabs = [
    { key: 'waiting_confirmation', label: 'Menunggu' },
    { key: 'confirmed',            label: 'Disetujui' },
    { key: 'cancelled',            label: 'Ditolak' },
    { key: 'urgent',               label: '🚨 H-14' },
    { key: 'overdue',              label: '⛔ Lewat Tenggat' },
    { key: 'all',                  label: 'Semua' },
]

const statusMeta = {
    waiting_confirmation: { label: 'Menunggu', class: 'bg-yellow-100 text-yellow-800' },
    confirmed:            { label: 'Disetujui', class: 'bg-green-100 text-green-800' },
    cancelled:            { label: 'Ditolak', class: 'bg-red-100 text-red-800' },
    plotting:             { label: 'Plotting', class: 'bg-blue-100 text-blue-800' },
}

function tabCount(key) {
    if (key === 'all') return props.bookings.length
    // 'urgent' dan 'overdue' — data sudah difilter dari backend, pakai semua
    if (key === activeTab.value && ['urgent', 'overdue'].includes(key)) return props.bookings.length
    if (['urgent', 'overdue'].includes(key)) return '-'
    return props.bookings.filter(b => b.status === (key === 'urgent' ? 'waiting_confirmation' : key)).length
}

const filteredBookings = computed(() => {
    if (activeTab.value === 'all') return props.bookings
    return props.bookings.filter(b => b.status === activeTab.value)
})

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function approveBooking(booking) {
    useForm({}).post(`/admin/bookings/${booking.id}/approve`, { preserveScroll: true })
}

function rejectBooking(booking) {
    if (!confirm(`Tolak booking "${booking.nama_training}"?`)) return
    useForm({}).post(`/admin/bookings/${booking.id}/reject`, { preserveScroll: true })
}
</script>


<template>
    <AdminLayout :auth="auth">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-lg font-bold text-gray-800">Manajemen Booking</h1>
        </div>

        <!-- Active filter banner -->
        <div v-if="activeFilter !== 'all'" class="mb-3 flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700 font-medium">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
            Filter aktif dari Dashboard: <strong class="ml-1">{{ tabs.find(t => t.key === activeFilter)?.label ?? activeFilter }}</strong>
        </div>

        <!-- Tabs -->
        <div class="flex flex-wrap gap-1 mb-4 bg-gray-100 p-1 rounded-lg w-fit">
            <button
                v-for="tab in tabs" :key="tab.key"
                @click="activeTab = tab.key"
                class="px-4 py-1.5 rounded text-sm font-medium transition"
                :class="activeTab === tab.key ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
                {{ tab.label }}
                <span class="ml-1 text-xs text-gray-400">({{ tabCount(tab.key) }})</span>
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acara / Pemohon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruangan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jadwal</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Peserta</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Info</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <tr v-if="filteredBookings.length === 0">
                        <td colspan="7" class="px-6 py-10 text-center text-gray-400 text-sm">
                            Tidak ada data booking.
                        </td>
                    </tr>
                    <tr v-for="b in filteredBookings" :key="b.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-gray-900">{{ b.nama_training }}</div>
                            <div class="text-xs text-gray-500">{{ b.pemohon }} · Divisi: {{ b.divisi }}</div>
                            <div class="text-xs text-gray-400">PIC: {{ b.pic }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ b.ruangan }}
                            <div class="text-xs text-gray-400 capitalize">Layout: {{ b.layout || '-' }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-700">
                            {{ formatDate(b.tgl_mulai) }}<br/>s/d {{ formatDate(b.tgl_selesai) }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-700 text-center">
                            <div>{{ b.jumlah_peserta }} peserta</div>
                            <div>{{ b.jumlah_panitia }} panitia</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 space-y-0.5">
                            <div v-if="b.is_hybrid" class="flex items-center gap-1">📹 Hybrid</div>
                            <div v-if="b.is_flipchart" class="flex items-center gap-1">📝 Flipchart</div>
                            <div v-if="!b.is_hybrid && !b.is_flipchart" class="text-gray-400">-</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="statusMeta[b.status]?.class ?? 'bg-gray-100 text-gray-600'">
                                {{ statusMeta[b.status]?.label ?? b.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="b.status === 'waiting_confirmation'" class="flex gap-2">
                                <button @click="approveBooking(b)"
                                    class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium py-1 px-3 rounded transition">
                                    ✓ Setujui
                                </button>
                                <button @click="rejectBooking(b)"
                                    class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-medium py-1 px-3 rounded transition">
                                    ✕ Tolak
                                </button>
                            </div>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
