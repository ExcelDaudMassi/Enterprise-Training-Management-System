<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: UserLayout })

const props = defineProps({
    auth:     Object,
    bookings: Array
})

const page = usePage()
const isWindowActive = computed(() => page.props.bookingWindow?.is_active ?? true)

// ─── Status helpers ───────────────────────────────────────────
const STATUS_META = {
    waiting_confirmation: { label: 'Menunggu Persetujuan', class: 'bg-yellow-100 text-yellow-800' },
    confirmed:            { label: 'Disetujui',            class: 'bg-indigo-100 text-indigo-800 border border-indigo-200' },
    cancelled:            { label: 'Dibatalkan',           class: 'bg-red-100 text-red-800' },
    final:                { label: 'Final ACC / Persiapan Lapangan', class: 'bg-green-100 text-green-800 border border-green-200' },
}

const CHANGE_META = {
    pending:  { label: 'Menunggu Persetujuan Ubah Tanggal', class: 'text-orange-700 bg-orange-50 border border-orange-100' },
    approved: { label: 'Perubahan Tanggal Disetujui',       class: 'text-green-700 bg-green-50 border border-green-100' },
    rejected: { label: 'Perubahan Tanggal Ditolak',         class: 'text-red-700 bg-red-50 border border-red-100' },
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
    <div class="max-w-6xl mx-auto space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Riwayat Booking</h1>
                        <p class="text-sm text-gray-500 mt-1">Daftar seluruh riwayat pemesanan ruangan dan status pengajuannya.</p>
                    </div>
                </div>
                <div v-if="!isWindowActive"
                     class="bg-gray-150 text-gray-400 text-sm font-semibold px-4 py-2 rounded-lg cursor-not-allowed border border-gray-200 flex items-center gap-1.5 shrink-0"
                     title="Window Booking sedang ditutup oleh Admin.">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Buat Booking Baru (Tutup)
                </div>
                <a v-else href="/user/booking/create"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm flex items-center gap-1.5 shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Buat Booking Baru
                </a>
            </div>

            <div class="bg-white rounded-xl shadow overflow-hidden border border-gray-100">
                <table class="min-w-full divide-y divide-gray-250 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Acara</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Ruangan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jadwal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Peserta</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr v-if="bookings.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">
                                Anda belum memiliki riwayat pemesanan ruangan.
                            </td>
                        </tr>
                        <tr v-for="b in bookings" :key="b.id" class="hover:bg-gray-50 align-top transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-gray-900 text-sm">{{ b.nama_training }}</div>
                                <div class="text-xs text-gray-500 mt-1">PIC: {{ b.pic }}</div>
                                <div class="text-[10px] text-gray-450 mt-0.5">Diajukan: {{ b.created_at }}</div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-gray-800 text-sm">{{ b.ruangan?.nama_ruang ?? 'Ruang Gabungan' }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ b.gabung_ruang ? 'Gabungan Ruang 2+3' : b.ruangan?.lokasi_gedung }}</div>
                                <div class="text-[10px] text-gray-400 capitalize mt-0.5">{{ b.layout_preferensi }}</div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-medium text-gray-800 text-xs">{{ formatDate(b.tgl_mulai) }}</div>
                                <div class="text-xs text-gray-500">s/d {{ formatDate(b.tgl_selesai) }}</div>
                                <!-- Usulan Perubahan Tanggal -->
                                <div v-if="b.status_perubahan !== 'none' && b.status_perubahan"
                                     class="mt-1.5 px-2 py-1 rounded-lg text-[10px] font-medium inline-flex items-center gap-1"
                                     :class="CHANGE_META[b.status_perubahan]?.class">
                                    <!-- Pending Change SVG -->
                                    <svg v-if="b.status_perubahan === 'pending'" class="w-3 h-3 shrink-0 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <!-- Approved Change SVG -->
                                    <svg v-else-if="b.status_perubahan === 'approved'" class="w-3 h-3 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    <!-- Rejected Change SVG -->
                                    <svg v-else-if="b.status_perubahan === 'rejected'" class="w-3 h-3 shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                    <span>
                                        {{ CHANGE_META[b.status_perubahan]?.label }}
                                        <span v-if="b.status_perubahan === 'pending'" class="block sm:inline font-normal text-gray-500 mt-0.5 sm:mt-0">
                                            ({{ formatDate(b.proposed_tgl_mulai) }} – {{ formatDate(b.proposed_tgl_selesai) }})
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <div class="font-medium text-gray-800 text-xs">{{ b.jumlah_peserta }} peserta</div>
                                <div class="text-[10px] text-gray-400 mt-1">{{ b.jumlah_panitia }} panitia</div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold inline-flex items-center gap-1"
                                      :class="STATUS_META[b.status]?.class ?? 'bg-gray-100 text-gray-600'">
                                    <!-- Hourglass SVG for waiting_confirmation -->
                                    <svg v-if="b.status === 'waiting_confirmation'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <!-- Check Circle SVG for confirmed -->
                                    <svg v-else-if="b.status === 'confirmed'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <!-- Cancelled SVG -->
                                    <svg v-else-if="b.status === 'cancelled'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <!-- Wrench SVG for final -->
                                    <svg v-else-if="b.status === 'final'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766l.002-.001a1.56 1.56 0 0 1 1.883 1.883l-.001.002c-.14.468-.382.89-.766 1.208l-3.03 2.496ZM11.42 15.17l-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243 3 3 0 0 0 4.243 4.243Zm0 0L7.88 9.75a3 3 0 0 0-3 3v.375c0 .621.504 1.125 1.125 1.125h.375M16.5 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                    </svg>
                                    {{ STATUS_META[b.status]?.label ?? b.status }}
                                </span>
                                <!-- Catatan admin jika ditolak -->
                                <div v-if="b.status === 'cancelled' && b.catatan_admin"
                                     class="mt-1.5 p-2 bg-red-50 border border-red-100 rounded-lg text-xs text-red-650 italic">
                                    "{{ b.catatan_admin }}"
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-col gap-1.5 items-start">
                                    <!-- Batalkan -->
                                    <button v-if="b.can_cancel"
                                            @click="openCancel(b)"
                                            class="text-xs text-red-650 hover:underline inline-flex items-center gap-1">
                                        Batalkan
                                    </button>
                                    <!-- Update Peserta -->
                                    <button v-if="b.can_update_participants"
                                            @click="openParticipantUpdate(b)"
                                            class="text-xs text-gray-500 hover:underline inline-flex items-center gap-1">
                                        Update Peserta
                                    </button>
                                    <!-- Final: tidak ada aksi -->
                                    <span v-if="b.status === 'final'" class="text-xs text-gray-300">—</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

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

</template>
