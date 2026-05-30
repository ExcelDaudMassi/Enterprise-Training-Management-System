<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    auth:     Object,
    rooms:    Array,
    allRooms: Array,
})

// ── Form Tambah / Edit ───────────────────────────────────────────
const form = useForm({
    nama_ruang:        '',
    lokasi_gedung:     '',
    kapasitas_max:     '',
    bisa_digabung:     false,
    pasangan_ruang_id: null,
})

// ── Modal State ──────────────────────────────────────────────────
const showFormModal   = ref(false)
const showDeleteModal = ref(false)
const isEditing       = ref(false)
const editingRoom     = ref(null)
const deletingRoom    = ref(null)

// ── Computed: Pilihan pasangan (excludes diri sendiri saat edit) ──
const pasanganOptions = computed(() => {
    return props.allRooms.filter(r => {
        if (isEditing.value && editingRoom.value) {
            return r.id !== editingRoom.value.id
        }
        return true
    })
})

// ── Form Helpers ─────────────────────────────────────────────────
function openCreate() {
    isEditing.value = false
    editingRoom.value = null
    form.reset()
    form.clearErrors()
    showFormModal.value = true
}

function openEdit(room) {
    isEditing.value = true
    editingRoom.value = room
    form.nama_ruang        = room.nama_ruang
    form.lokasi_gedung     = room.lokasi_gedung ?? ''
    form.kapasitas_max     = room.kapasitas_max
    form.bisa_digabung     = room.bisa_digabung
    form.pasangan_ruang_id = room.pasangan_ruang_id
    form.clearErrors()
    showFormModal.value = true
}

function closeFormModal() {
    showFormModal.value = false
    form.reset()
    form.clearErrors()
}

function submitForm() {
    if (isEditing.value && editingRoom.value) {
        form.put(`/admin/rooms/${editingRoom.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        })
    } else {
        form.post('/admin/rooms', {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        })
    }
}

// ── Delete Helpers ────────────────────────────────────────────────
const deleteForm = useForm({})

function openDelete(room) {
    deletingRoom.value   = room
    showDeleteModal.value = true
}
function closeDeleteModal() {
    showDeleteModal.value = false
    deletingRoom.value = null
}
function submitDelete() {
    deleteForm.delete(`/admin/rooms/${deletingRoom.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    })
}

// ── Search ────────────────────────────────────────────────────────
const searchQuery = ref('')
const filteredRooms = computed(() => {
    if (!searchQuery.value) return props.rooms
    const q = searchQuery.value.toLowerCase()
    return props.rooms.filter(r =>
        r.nama_ruang.toLowerCase().includes(q) ||
        (r.lokasi_gedung ?? '').toLowerCase().includes(q)
    )
})
</script>

<template>
        <!-- ── Page Header ──────────────────────────────────────── -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Master Data Ruangan</h1>
                <p class="text-sm text-gray-500 mt-0.5">Kelola kapasitas, lokasi, dan konfigurasi gabungan ruang training.</p>
            </div>
            <button
                @click="openCreate"
                class="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Ruangan
            </button>
        </div>

        <!-- ── Stats Bar ─────────────────────────────────────────── -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-gray-800">{{ rooms.length }}</p>
                    <p class="text-xs text-gray-500">Total Ruangan</p>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-gray-800">{{ rooms.filter(r => r.bisa_digabung).length }}</p>
                    <p class="text-xs text-gray-500">Bisa Digabungkan</p>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-gray-800">{{ rooms.reduce((a, r) => a + r.kapasitas_max, 0) }}</p>
                    <p class="text-xs text-gray-500">Total Kapasitas (orang)</p>
                </div>
            </div>
        </div>

        <!-- ── Search ────────────────────────────────────────────── -->
        <div class="mb-4 relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Cari nama ruangan atau lokasi gedung..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white shadow-sm"
            />
        </div>

        <!-- ── Room Cards Grid ───────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <!-- Empty state -->
            <div v-if="filteredRooms.length === 0" class="col-span-3 py-16 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <p class="text-sm font-medium">Tidak ada ruangan ditemukan.</p>
            </div>

            <div
                v-for="room in filteredRooms" :key="room.id"
                class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden"
            >
                <!-- Card Header -->
                <div class="px-5 py-4 bg-gradient-to-br from-slate-700 to-slate-800 flex items-start justify-between">
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-white truncate">{{ room.nama_ruang }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ room.lokasi_gedung || 'Lokasi tidak diisi' }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1.5 ml-3 flex-shrink-0">
                        <span v-if="room.bisa_digabung"
                              class="px-2 py-0.5 rounded-full bg-teal-400/20 text-teal-300 text-[10px] font-bold tracking-wide border border-teal-500/30">
                            🔗 GABUNG
                        </span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="px-5 py-4 space-y-3">
                    <!-- Kapasitas -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Kapasitas Maksimal</p>
                            <p class="text-sm font-bold text-gray-800">{{ room.kapasitas_max }} orang</p>
                        </div>
                    </div>

                    <!-- Pasangan Ruang -->
                    <div v-if="room.bisa_digabung" class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Pasangan Gabungan</p>
                            <p class="text-sm font-medium text-teal-700">{{ room.pasangan_nama ?? '(Belum diset)' }}</p>
                        </div>
                    </div>

                    <!-- Booking Aktif -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Booking Aktif</p>
                            <p class="text-sm font-bold text-gray-800">{{ room.total_bookings }} booking</p>
                        </div>
                    </div>
                </div>

                <!-- Card Footer: Actions -->
                <div class="px-5 py-3 border-t border-gray-100 flex gap-2">
                    <button @click="openEdit(room)"
                        class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold transition border border-blue-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Edit
                    </button>
                    <button @click="openDelete(room)"
                        class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg text-xs font-semibold transition border border-red-200"
                        :disabled="room.total_bookings > 0"
                        :title="room.total_bookings > 0 ? 'Tidak bisa dihapus — ruangan memiliki booking aktif' : 'Hapus ruangan'"
                        :class="room.total_bookings > 0 ? 'opacity-40 cursor-not-allowed' : ''"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Hapus
                    </button>
                </div>
            </div>
        </div>


        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- MODAL: Form Tambah / Edit Ruangan                        -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showFormModal"
                     class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
                     @click.self="closeFormModal">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                        <!-- Modal Header -->
                        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r"
                             :class="isEditing ? 'from-blue-700 to-blue-600' : 'from-slate-700 to-slate-600'">
                            <h2 class="text-base font-bold text-white">{{ isEditing ? 'Edit Ruangan' : 'Tambah Ruangan Baru' }}</h2>
                            <p class="text-xs mt-0.5" :class="isEditing ? 'text-blue-200' : 'text-slate-400'">
                                {{ isEditing ? `Mengedit: ${editingRoom?.nama_ruang}` : 'Isi informasi ruangan training baru.' }}
                            </p>
                        </div>

                        <!-- Modal Body -->
                        <form @submit.prevent="submitForm" class="px-6 py-5 space-y-4">

                            <!-- Nama Ruangan -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Ruangan <span class="text-red-500">*</span></label>
                                <input
                                    v-model="form.nama_ruang"
                                    type="text"
                                    required
                                    placeholder="Contoh: Ruang 7"
                                    class="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :class="form.errors.nama_ruang ? 'border-red-400' : 'border-gray-200'"
                                />
                                <p v-if="form.errors.nama_ruang" class="text-xs text-red-500 mt-1">{{ form.errors.nama_ruang }}</p>
                            </div>

                            <!-- Lokasi Gedung -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Lokasi Gedung</label>
                                <input
                                    v-model="form.lokasi_gedung"
                                    type="text"
                                    placeholder="Contoh: Gedung A Lantai 2"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.lokasi_gedung" class="text-xs text-red-500 mt-1">{{ form.errors.lokasi_gedung }}</p>
                            </div>

                            <!-- Kapasitas -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Kapasitas Maksimal (orang) <span class="text-red-500">*</span></label>
                                <input
                                    v-model="form.kapasitas_max"
                                    type="number"
                                    required
                                    min="1"
                                    max="500"
                                    placeholder="Contoh: 30"
                                    class="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :class="form.errors.kapasitas_max ? 'border-red-400' : 'border-gray-200'"
                                />
                                <p v-if="form.errors.kapasitas_max" class="text-xs text-red-500 mt-1">{{ form.errors.kapasitas_max }}</p>
                            </div>

                            <!-- Bisa Digabung Toggle -->
                            <div class="bg-teal-50 rounded-xl p-4 border border-teal-100">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <div class="relative flex-shrink-0">
                                        <input type="checkbox" v-model="form.bisa_digabung" class="sr-only peer" />
                                        <div class="w-10 h-6 bg-gray-300 peer-checked:bg-teal-500 rounded-full transition-colors"></div>
                                        <div class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">Bisa Digabungkan</p>
                                        <p class="text-xs text-gray-500">Ruangan ini dapat digabung dengan ruangan pasangannya.</p>
                                    </div>
                                </label>

                                <!-- Pasangan Ruang (tampil hanya jika bisa_digabung) -->
                                <div v-if="form.bisa_digabung" class="mt-3">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Ruangan Pasangan</label>
                                    <select
                                        v-model="form.pasangan_ruang_id"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
                                    >
                                        <option :value="null">— Pilih ruangan pasangan —</option>
                                        <option v-for="r in pasanganOptions" :key="r.id" :value="r.id">
                                            {{ r.nama_ruang }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.pasangan_ruang_id" class="text-xs text-red-500 mt-1">{{ form.errors.pasangan_ruang_id }}</p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-2">
                                <button type="button" @click="closeFormModal"
                                    class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                    Batal
                                </button>
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors shadow-sm disabled:opacity-60"
                                    :class="isEditing ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-700 hover:bg-slate-800'">
                                    {{ form.processing ? 'Menyimpan...' : (isEditing ? '✓ Perbarui Ruangan' : '✓ Tambah Ruangan') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>


        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- MODAL: Konfirmasi Hapus Ruangan                          -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showDeleteModal"
                     class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
                     @click.self="closeDeleteModal">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                        <div class="p-6">
                            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 text-center mb-1">Hapus Ruangan?</h3>
                            <p class="text-sm text-gray-500 text-center">
                                Ruangan <strong>{{ deletingRoom?.nama_ruang }}</strong> akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                        <div class="px-6 pb-6 flex gap-3">
                            <button @click="closeDeleteModal"
                                class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Batal
                            </button>
                            <button @click="submitDelete" :disabled="deleteForm.processing"
                                class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white rounded-lg text-sm font-semibold">
                                {{ deleteForm.processing ? 'Menghapus...' : '🗑 Hapus' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

</template>
