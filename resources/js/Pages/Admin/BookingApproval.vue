<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    auth:         Object,
    bookings:     Array,
    activeFilter: { type: String, default: 'all' },
})

// ─── Tabs ─────────────────────────────────────────────────────
const tabs = [
    { key: 'waiting_confirmation', label: 'Menunggu ACC' },
    { key: 'h14',                  label: '🚨 H-14 (ACC Final)' },
    { key: 'overdue',              label: '⛔ Lewat Tenggat' },
    { key: 'date_changes',         label: '📅 Ubah Tanggal' },
    { key: 'confirmed',            label: 'Confirmed' },
    { key: 'final',                label: '✅ Final' },
    { key: 'cancelled',            label: 'Dibatalkan' },
    { key: 'all',                  label: 'Semua' },
]

const activeTab = ref(props.activeFilter ?? 'all')

function switchTab(key) {
    activeTab.value = key
    router.get('/admin/bookings', { filter: key }, { preserveScroll: true, preserveState: true })
}

// ─── Status display helpers ───────────────────────────────────
const STATUS_META = {
    waiting_confirmation: { label: 'Menunggu',  class: 'bg-yellow-100 text-yellow-800' },
    confirmed:            { label: 'Disetujui', class: 'bg-green-100 text-green-800' },
    cancelled:            { label: 'Ditolak',   class: 'bg-red-100 text-red-800' },
    final:                { label: 'Final',     class: 'bg-blue-100 text-blue-800' },
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

// ─── Modal: ACC Tahap 1 Approve ───────────────────────────────
const showApproveModal    = ref(false)
const selectedBooking     = ref(null)
const approveForm         = useForm({})

function openApprove(b) {
    selectedBooking.value = b
    showApproveModal.value = true
}
function submitApprove() {
    approveForm.post(`/admin/bookings/${selectedBooking.value.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => { showApproveModal.value = false }
    })
}

// ─── Modal: ACC Tahap 1 Reject ────────────────────────────────
const showRejectModal  = ref(false)
const rejectForm       = useForm({ catatan_admin: '' })

function openReject(b) {
    selectedBooking.value = b
    rejectForm.reset()
    showRejectModal.value = true
}
function submitReject() {
    rejectForm.post(`/admin/bookings/${selectedBooking.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => { showRejectModal.value = false; rejectForm.reset() }
    })
}

// ─── Modal: ACC Final (Tahap 4) ───────────────────────────────
const showFinalModal  = ref(false)
const finalForm       = useForm({})

function openFinal(b) {
    selectedBooking.value = b
    showFinalModal.value = true
}
function submitFinal() {
    finalForm.post(`/admin/bookings/${selectedBooking.value.id}/final`, {
        preserveScroll: true,
        onSuccess: () => { showFinalModal.value = false }
    })
}

// ─── Modal: ACC Terlambat (Tahap 5) ──────────────────────────
const showFinalLateModal  = ref(false)
const finalLateForm       = useForm({ catatan_acc_terlambat: '' })

function openFinalLate(b) {
    selectedBooking.value = b
    finalLateForm.reset()
    showFinalLateModal.value = true
}
function submitFinalLate() {
    finalLateForm.post(`/admin/bookings/${selectedBooking.value.id}/final-late`, {
        preserveScroll: true,
        onSuccess: () => { showFinalLateModal.value = false; finalLateForm.reset() }
    })
}

// ─── Modal: Setujui Perubahan Tanggal ────────────────────────
const showApproveDateModal = ref(false)
const approveDateForm      = useForm({})

function openApproveDate(b) {
    selectedBooking.value = b
    showApproveDateModal.value = true
}
function submitApproveDate() {
    approveDateForm.post(`/admin/bookings/${selectedBooking.value.id}/approve-date-change`, {
        preserveScroll: true,
        onSuccess: () => { showApproveDateModal.value = false }
    })
}

// ─── Modal: Tolak Perubahan Tanggal ──────────────────────────
const showRejectDateModal = ref(false)
const rejectDateForm      = useForm({ catatan_admin: '' })

function openRejectDate(b) {
    selectedBooking.value = b
    rejectDateForm.reset()
    showRejectDateModal.value = true
}
function submitRejectDate() {
    rejectDateForm.post(`/admin/bookings/${selectedBooking.value.id}/reject-date-change`, {
        preserveScroll: true,
        onSuccess: () => { showRejectDateModal.value = false; rejectDateForm.reset() }
    })
}
</script>

<template>
    <AdminLayout :auth="auth">
        <div class="mb-4">
            <h1 class="text-lg font-bold text-gray-800">Manajemen Booking</h1>
        </div>

        <!-- Filter aktif banner -->
        <div v-if="activeFilter !== 'all'"
             class="mb-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-700">
            Filter aktif: <strong>{{ tabs.find(t => t.key === activeFilter)?.label ?? activeFilter }}</strong>
        </div>

        <!-- Tabs -->
        <div class="flex flex-wrap gap-1 mb-4 bg-gray-100 p-1 rounded w-fit">
            <button
                v-for="tab in tabs" :key="tab.key"
                @click="switchTab(tab.key)"
                class="px-3 py-1.5 rounded text-xs font-medium transition"
                :class="activeTab === tab.key
                    ? 'bg-white text-gray-800 shadow-sm'
                    : 'text-gray-500 hover:text-gray-700'"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acara / Pemohon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruangan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jadwal</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Peserta</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <tr v-if="bookings.length === 0">
                        <td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">
                            Tidak ada data booking.
                        </td>
                    </tr>
                    <tr v-for="b in bookings" :key="b.id" class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-900">{{ b.nama_training }}</div>
                            <div class="text-xs text-gray-500">{{ b.pemohon }} · {{ b.divisi }}</div>
                            <div class="text-xs text-gray-400">PIC: {{ b.pic }}</div>
                        </td>
                        <td class="px-4 py-3">
                            {{ b.ruangan }}
                            <div class="text-xs text-gray-400 capitalize">{{ b.layout || '-' }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-700">
                            {{ formatDate(b.tgl_mulai) }}<br/>s/d {{ formatDate(b.tgl_selesai) }}
                            <!-- Countdown -->
                            <div v-if="b.days_to_start !== null && b.status === 'confirmed' && b.days_to_start >= 0"
                                 class="mt-1 font-semibold"
                                 :class="b.days_to_start <= 14 ? 'text-red-600' : 'text-gray-500'">
                                H-{{ b.days_to_start }}
                            </div>
                            <div v-if="b.is_overdue_acc2" class="text-red-600 font-semibold mt-1">
                                ⛔ Tanggal sudah lewat
                            </div>
                            <!-- Usulan Ubah Tanggal -->
                            <div v-if="b.has_pending_date_change" class="mt-1 text-orange-600 text-xs font-medium">
                                📅 Usulan: {{ formatDate(b.proposed_tgl_mulai) }} – {{ formatDate(b.proposed_tgl_selesai) }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-700 text-center">
                            <div>{{ b.jumlah_peserta }} peserta</div>
                            <div class="text-gray-400">{{ b.jumlah_panitia }} panitia</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="STATUS_META[b.status]?.class ?? 'bg-gray-100 text-gray-600'">
                                {{ STATUS_META[b.status]?.label ?? b.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-1">
                                <!-- Tahap 1: ACC Awal -->
                                <template v-if="b.status === 'waiting_confirmation'">
                                    <button @click="openApprove(b)"
                                            class="bg-green-600 hover:bg-green-700 text-white text-xs py-1 px-2 rounded">
                                        ✓ Setujui
                                    </button>
                                    <button @click="openReject(b)"
                                            class="bg-red-100 hover:bg-red-200 text-red-700 text-xs py-1 px-2 rounded">
                                        ✕ Tolak
                                    </button>
                                </template>

                                <!-- Tahap 3: Perubahan Tanggal -->
                                <template v-if="b.has_pending_date_change">
                                    <button @click="openApproveDate(b)"
                                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs py-1 px-2 rounded">
                                        ✓ ACC Ubah Tanggal
                                    </button>
                                    <button @click="openRejectDate(b)"
                                            class="bg-orange-100 hover:bg-orange-200 text-orange-700 text-xs py-1 px-2 rounded">
                                        ✕ Tolak Ubah Tanggal
                                    </button>
                                </template>

                                <!-- Tahap 4: ACC Final -->
                                <template v-if="b.can_be_finalized && !b.is_overdue_acc2">
                                    <button @click="openFinal(b)"
                                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-1 px-2 rounded">
                                        🏁 ACC Final
                                    </button>
                                </template>

                                <!-- Tahap 5: ACC Terlambat -->
                                <template v-if="b.can_be_finalized && b.is_overdue_acc2">
                                    <button @click="openFinalLate(b)"
                                            class="bg-amber-500 hover:bg-amber-600 text-white text-xs py-1 px-2 rounded">
                                        ⚠️ ACC Terlambat
                                    </button>
                                </template>

                                <span v-if="b.status === 'final' || b.status === 'cancelled'"
                                      class="text-xs text-gray-400">—</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ── Modal: Konfirmasi ACC Tahap 1 ─────────────────── -->
        <Teleport to="body">
            <div v-if="showApproveModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-3">Konfirmasi Persetujuan</h2>
                    <div class="text-sm text-gray-700 space-y-1 mb-4">
                        <div><strong>Training:</strong> {{ selectedBooking?.nama_training }}</div>
                        <div><strong>Ruangan:</strong> {{ selectedBooking?.ruangan }}</div>
                        <div><strong>Jadwal:</strong> {{ formatDate(selectedBooking?.tgl_mulai) }} – {{ formatDate(selectedBooking?.tgl_selesai) }}</div>
                        <div><strong>Pemohon:</strong> {{ selectedBooking?.pemohon }} ({{ selectedBooking?.divisi }})</div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showApproveModal = false"
                                class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                        <button @click="submitApprove" :disabled="approveForm.processing"
                                class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm disabled:opacity-50">
                            {{ approveForm.processing ? 'Memproses...' : 'Ya, Setujui' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Tolak Tahap 1 ───────────────────────────── -->
        <Teleport to="body">
            <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-1">Penolakan Booking</h2>
                    <p class="text-xs text-gray-500 mb-4">
                        Booking <strong>{{ selectedBooking?.nama_training }}</strong> akan dibatalkan.
                    </p>
                    <form @submit.prevent="submitReject">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Penolakan <span class="text-red-500">*</span></label>
                        <textarea v-model="rejectForm.catatan_admin" required rows="3"
                                  placeholder="Contoh: Jadwal bentrok, ruangan sedang direnovasi..."
                                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        <p v-if="rejectForm.errors.catatan_admin" class="text-xs text-red-500 mt-1">{{ rejectForm.errors.catatan_admin }}</p>
                        <div class="flex gap-3 mt-4">
                            <button type="button" @click="showRejectModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="rejectForm.processing || !rejectForm.catatan_admin"
                                    class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm disabled:opacity-50">
                                {{ rejectForm.processing ? 'Menyimpan...' : '✕ Tolak' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: ACC Final ───────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showFinalModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-3">🏁 Konfirmasi ACC Final</h2>
                    <div class="text-sm text-gray-700 space-y-1 mb-4">
                        <div><strong>Training:</strong> {{ selectedBooking?.nama_training }}</div>
                        <div><strong>Jadwal:</strong> {{ formatDate(selectedBooking?.tgl_mulai) }} – {{ formatDate(selectedBooking?.tgl_selesai) }}</div>
                        <div><strong>Peserta:</strong> {{ selectedBooking?.jumlah_peserta }} orang, {{ selectedBooking?.jumlah_panitia }} panitia</div>
                    </div>
                    <p class="text-xs text-orange-600 mb-4">
                        ⚠️ Setelah ACC Final, user tidak dapat mengubah booking ini. Pastikan semua data sudah benar.
                    </p>
                    <div class="flex gap-3">
                        <button @click="showFinalModal = false"
                                class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                        <button @click="submitFinal" :disabled="finalForm.processing"
                                class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm disabled:opacity-50">
                            {{ finalForm.processing ? 'Memproses...' : '🏁 ACC & Final' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: ACC Terlambat ───────────────────────────── -->
        <Teleport to="body">
            <div v-if="showFinalLateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-1">⚠️ ACC Terlambat</h2>
                    <p class="text-xs text-red-600 mb-4">Tanggal training sudah lewat. Mohon isi alasan ACC terlambat.</p>
                    <form @submit.prevent="submitFinalLate">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan ACC Terlambat <span class="text-red-500">*</span></label>
                        <textarea v-model="finalLateForm.catatan_acc_terlambat" required rows="3"
                                  placeholder="Contoh: Training tetap berjalan, laporan menyusul..."
                                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        <p v-if="finalLateForm.errors.catatan_acc_terlambat" class="text-xs text-red-500 mt-1">
                            {{ finalLateForm.errors.catatan_acc_terlambat }}
                        </p>
                        <div class="flex gap-3 mt-4">
                            <button type="button" @click="showFinalLateModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="finalLateForm.processing || !finalLateForm.catatan_acc_terlambat"
                                    class="flex-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded text-sm disabled:opacity-50">
                                {{ finalLateForm.processing ? 'Menyimpan...' : '⚠️ Konfirmasi ACC Terlambat' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Setujui Ubah Tanggal ────────────────────── -->
        <Teleport to="body">
            <div v-if="showApproveDateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-3">Setujui Perubahan Tanggal</h2>
                    <div class="text-sm text-gray-700 space-y-1 mb-4">
                        <div><strong>Training:</strong> {{ selectedBooking?.nama_training }}</div>
                        <div class="text-gray-400 line-through text-xs">
                            Lama: {{ formatDate(selectedBooking?.tgl_mulai) }} – {{ formatDate(selectedBooking?.tgl_selesai) }}
                        </div>
                        <div class="text-green-700 font-medium">
                            Baru: {{ formatDate(selectedBooking?.proposed_tgl_mulai) }} – {{ formatDate(selectedBooking?.proposed_tgl_selesai) }}
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showApproveDateModal = false"
                                class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                        <button @click="submitApproveDate" :disabled="approveDateForm.processing"
                                class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm disabled:opacity-50">
                            {{ approveDateForm.processing ? 'Memproses...' : '✓ Setujui' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Tolak Ubah Tanggal ──────────────────────── -->
        <Teleport to="body">
            <div v-if="showRejectDateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-1">Tolak Perubahan Tanggal</h2>
                    <p class="text-xs text-gray-500 mb-4">
                        Tanggal lama akan tetap berlaku. Booking tetap berstatus Confirmed.
                    </p>
                    <form @submit.prevent="submitRejectDate">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan <span class="text-red-500">*</span></label>
                        <textarea v-model="rejectDateForm.catatan_admin" required rows="3"
                                  placeholder="Contoh: Tanggal baru bentrok dengan event lain..."
                                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        <div class="flex gap-3 mt-4">
                            <button type="button" @click="showRejectDateModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="rejectDateForm.processing || !rejectDateForm.catatan_admin"
                                    class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded text-sm disabled:opacity-50">
                                {{ rejectDateForm.processing ? 'Menyimpan...' : '✕ Tolak' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </AdminLayout>
</template>
