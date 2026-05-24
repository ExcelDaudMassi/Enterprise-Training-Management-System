<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    auth:     Object,
    bookings: Array
})

const page = usePage()
const isWindowActive = computed(() => page.props.bookingWindow?.is_active ?? true)

// ─── Status helpers ───────────────────────────────────────────
const STATUS_META = {
    waiting_confirmation: { label: 'Menunggu Persetujuan', class: 'bg-yellow-100 text-yellow-800' },
    confirmed:            { label: 'Disetujui',            class: 'bg-green-100 text-green-800' },
    cancelled:            { label: 'Dibatalkan',           class: 'bg-red-100 text-red-800' },
    final:                { label: 'Final',                class: 'bg-blue-100 text-blue-800' },
}

const CHANGE_META = {
    pending:  { label: '⏳ Menunggu Persetujuan Ubah Tanggal', class: 'text-orange-600 bg-orange-50' },
    approved: { label: '✅ Perubahan Tanggal Disetujui',       class: 'text-green-700 bg-green-50' },
    rejected: { label: '❌ Perubahan Tanggal Ditolak',         class: 'text-red-700 bg-red-50' },
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

// ─── State modals ─────────────────────────────────────────────
const selectedBooking    = ref(null)
const showCancelModal    = ref(false)
const showDateModal      = ref(false)
const showParticipantModal = ref(false)

// Form: Batalkan
const cancelLoading = ref(false)
const cancelError   = ref('')

// Form: Ubah Tanggal
const dateForm = ref({ proposed_tgl_mulai: '', proposed_tgl_selesai: '', alasan: '' })
const dateLoading = ref(false)
const dateError   = ref('')

// Form: Update Peserta (manual, bukan upload Excel untuk kesederhanaan)
const participantLoading = ref(false)
const participantError   = ref('')

// ─── Aksi: Buka Modal ─────────────────────────────────────────
function openCancel(b) {
    selectedBooking.value = b
    cancelError.value = ''
    showCancelModal.value = true
}

function openDateChange(b) {
    selectedBooking.value = b
    dateError.value = ''
    dateForm.value = {
        proposed_tgl_mulai:   b.tgl_mulai ?? '',
        proposed_tgl_selesai: b.tgl_selesai ?? '',
        alasan: ''
    }
    showDateModal.value = true
}

function openParticipantUpdate(b) {
    selectedBooking.value = b
    participantError.value = ''
    showParticipantModal.value = true
}

// ─── Aksi: Batalkan Booking ───────────────────────────────────
async function submitCancel() {
    if (!selectedBooking.value) return
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

// ─── Aksi: Ajukan Perubahan Tanggal ──────────────────────────
async function submitDateChange() {
    if (!selectedBooking.value) return
    dateLoading.value = true
    dateError.value   = ''
    try {
        await window.axios.post(`/api/booking/${selectedBooking.value.id}/request-date-change`, dateForm.value)
        showDateModal.value = false
        router.reload({ only: ['bookings'] })
    } catch (e) {
        dateError.value = e.response?.data?.message
            ?? e.response?.data?.errors?.proposed_tgl_mulai?.[0]
            ?? 'Terjadi kesalahan.'
    } finally {
        dateLoading.value = false
    }
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
                <a v-else href="/user/booking/create"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded text-sm transition">
                    + Buat Booking Baru
                </a>
            </div>

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acara</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruangan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jadwal</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Peserta</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="bookings.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                Anda belum memiliki riwayat pemesanan ruangan.
                            </td>
                        </tr>
                        <tr v-for="b in bookings" :key="b.id" class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ b.nama_training }}</div>
                                <div class="text-xs text-gray-500">PIC: {{ b.pic }}</div>
                                <div class="text-xs text-gray-400">Diajukan: {{ b.created_at }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800">{{ b.ruangan?.nama_ruang ?? 'Ruang Gabungan' }}</div>
                                <div class="text-xs text-gray-400">{{ b.gabung_ruang ? 'Gabungan Ruang 2+3' : b.ruangan?.lokasi_gedung }}</div>
                                <div class="text-xs text-gray-400 capitalize">{{ b.layout_preferensi }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ formatDate(b.tgl_mulai) }}</div>
                                <div class="text-xs text-gray-500">s/d {{ formatDate(b.tgl_selesai) }}</div>
                                <!-- Usulan Perubahan Tanggal -->
                                <div v-if="b.status_perubahan !== 'none' && b.status_perubahan"
                                     class="mt-1 px-2 py-0.5 rounded text-xs font-medium"
                                     :class="CHANGE_META[b.status_perubahan]?.class">
                                    {{ CHANGE_META[b.status_perubahan]?.label }}
                                    <span v-if="b.status_perubahan === 'pending'">
                                        ({{ formatDate(b.proposed_tgl_mulai) }} – {{ formatDate(b.proposed_tgl_selesai) }})
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div>{{ b.jumlah_peserta }} peserta</div>
                                <div class="text-xs text-gray-400">{{ b.jumlah_panitia }} panitia</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                      :class="STATUS_META[b.status]?.class ?? 'bg-gray-100 text-gray-600'">
                                    {{ STATUS_META[b.status]?.label ?? b.status }}
                                </span>
                                <!-- Catatan admin jika ditolak -->
                                <div v-if="b.status === 'cancelled' && b.catatan_admin"
                                     class="mt-1 text-xs text-red-500 italic">
                                    "{{ b.catatan_admin }}"
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1">
                                    <!-- Batalkan -->
                                    <!-- Lihat Detail -->
                                    <a :href="`/user/booking/${b.id}/detail`"
                                       class="text-xs text-blue-600 hover:text-blue-800 font-medium text-left">
                                        Lihat Detail &rarr;
                                    </a>
                                    <!-- Batalkan -->
                                    <button v-if="b.can_cancel"
                                            @click="openCancel(b)"
                                            class="text-xs text-red-600 hover:underline text-left">
                                        Batalkan
                                    </button>
                                    <!-- Update Peserta -->
                                    <button v-if="b.can_update_participants"
                                            @click="openParticipantUpdate(b)"
                                            class="text-xs text-gray-600 hover:underline text-left">
                                        Update Peserta
                                    </button>
                                    <!-- Final: tidak ada aksi -->
                                    <span v-if="b.status === 'final'" class="text-xs text-gray-400">—</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Modal: Konfirmasi Batalkan ────────────────────── -->
        <Teleport to="body">
            <div v-if="showCancelModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-2">Batalkan Booking</h2>
                    <p class="text-sm text-gray-600 mb-1">
                        Anda akan membatalkan: <strong>{{ selectedBooking?.nama_training }}</strong>
                    </p>
                    <p class="text-xs text-red-600 mb-4">⚠️ Pembatalan bersifat permanen dan tidak bisa diurungkan.</p>
                    <div v-if="cancelError" class="mb-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">
                        {{ cancelError }}
                    </div>
                    <div class="flex gap-3">
                        <button @click="showCancelModal = false"
                                class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">
                            Kembali
                        </button>
                        <button @click="submitCancel" :disabled="cancelLoading"
                                class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm disabled:opacity-50">
                            {{ cancelLoading ? 'Membatalkan...' : 'Ya, Batalkan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Ubah Tanggal ────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showDateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-1">Ajukan Perubahan Tanggal</h2>
                    <p class="text-xs text-gray-500 mb-4">
                        Tanggal baru perlu disetujui admin. Tanggal lama tetap berlaku sampai disetujui.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Mulai Baru</label>
                            <input type="date" v-model="dateForm.proposed_tgl_mulai"
                                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Selesai Baru</label>
                            <input type="date" v-model="dateForm.proposed_tgl_selesai"
                                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Alasan (Opsional)</label>
                            <textarea v-model="dateForm.alasan" rows="2"
                                      placeholder="Misalnya: Narasumber tidak bisa hadir..."
                                      class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        </div>
                    </div>
                    <div v-if="dateError" class="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">
                        {{ dateError }}
                    </div>
                    <div class="flex gap-3 mt-4">
                        <button @click="showDateModal = false"
                                class="flex-1 px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="submitDateChange" :disabled="dateLoading"
                                class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm disabled:opacity-50">
                            {{ dateLoading ? 'Mengirim...' : 'Ajukan Perubahan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal: Update Peserta ──────────────────────────── -->
        <Teleport to="body">
            <div v-if="showParticipantModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-2">Update Data Peserta</h2>
                    <p class="text-sm text-gray-600 mb-4">
                        Upload ulang file Excel peserta untuk booking: <strong>{{ selectedBooking?.nama_training }}</strong>
                    </p>
                    <div class="border-2 border-dashed border-gray-300 rounded p-4 text-center mb-4">
                        <input type="file" accept=".xlsx"
                               @change="async (e) => {
                                   const file = e.target.files[0]
                                   if (!file) return
                                   participantLoading = true
                                   participantError = ''
                                   try {
                                       const fd = new FormData()
                                       fd.append('file_peserta', file)
                                       await window.axios.post(`/api/booking/${selectedBooking.id}/update-participants`, fd)
                                       showParticipantModal = false
                                       $router.reload({ only: ['bookings'] })
                                   } catch (err) {
                                       participantError = err.response?.data?.message ?? 'Gagal upload.'
                                   } finally {
                                       participantLoading = false
                                   }
                               }"
                               class="text-sm text-gray-600" />
                        <p class="text-xs text-gray-400 mt-2">Format: .xlsx | Maks 2MB</p>
                    </div>
                    <div v-if="participantError" class="text-sm text-red-600 mb-3">{{ participantError }}</div>
                    <button @click="showParticipantModal = false"
                            class="w-full px-4 py-2 border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50">
                        Tutup
                    </button>
                </div>
            </div>
        </Teleport>

    </UserLayout>
</template>
