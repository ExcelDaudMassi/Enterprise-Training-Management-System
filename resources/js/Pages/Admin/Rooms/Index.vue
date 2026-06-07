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
        <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent">Room Master Data</h1>
                <p class="text-sm text-slate-500 mt-1 font-medium">Manage capacity, location, and combined configuration of training rooms.</p>
            </div>
            <button
                @click="openCreate"
                class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition-all active:scale-95"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add Room
            </button>
        </div>

        <!-- ── Stats Bar ─────────────────────────────────────────── -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white/80 backdrop-blur rounded-2xl p-4 border border-slate-200 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-600 shadow-inner">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-slate-800 leading-none">{{ rooms.length }}</p>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-1.5">Total Rooms</p>
                </div>
            </div>
            <div class="bg-white/80 backdrop-blur rounded-2xl p-4 border border-slate-200 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-200 flex items-center justify-center text-emerald-600 shadow-inner">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-slate-800 leading-none">{{ rooms.filter(r => r.bisa_digabung).length }}</p>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-1.5">Combinable</p>
                </div>
            </div>
            <div class="bg-white/80 backdrop-blur rounded-2xl p-4 border border-slate-200 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-100 to-amber-200 flex items-center justify-center text-amber-600 shadow-inner">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-black text-slate-800 leading-none">{{ rooms.reduce((a, r) => a + r.kapasitas_max, 0) }}</p>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-1.5">Capacity (People)</p>
                </div>
            </div>
        </div>

        <!-- ── Search ────────────────────────────────────────────── -->
        <div class="mb-6 relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-blue-500 text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
            </div>
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Search room name or building location..."
                class="w-full pl-11 pr-4 py-3 text-sm border-0 ring-1 ring-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 bg-white shadow-sm transition-all placeholder:text-slate-400"
            />
        </div>

        <!-- ── Room Cards Grid ───────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <!-- Empty state -->
            <div v-if="filteredRooms.length === 0" class="col-span-3 py-16 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <p class="text-sm font-medium">No rooms found.</p>
            </div>

            <div
                v-for="room in filteredRooms" :key="room.id"
                class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden group"
            >
                <!-- Card Header -->
                <div class="px-5 py-5 border-b border-slate-100 flex items-start justify-between bg-white relative overflow-hidden group-hover:bg-slate-50/50 transition-colors">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-blue-100 rounded-full blur-2xl opacity-50 group-hover:opacity-100 transition-opacity"></div>
                    <div class="min-w-0 relative z-10">
                        <h3 class="text-lg font-black text-slate-800 truncate group-hover:text-blue-700 transition-colors">{{ room.nama_ruang }}</h3>
                        <p class="text-[11px] text-slate-500 mt-1 font-medium">{{ room.lokasi_gedung || 'Location not filled' }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1.5 ml-3 flex-shrink-0 relative z-10">
                        <span v-if="room.bisa_digabung"
                              class="px-2 py-0.5 rounded-md bg-teal-400/20 text-teal-300 text-[10px] font-bold tracking-wide border border-teal-500/30">
                            🔗 COMBINE
                        </span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="px-5 py-5 space-y-4">
                    <!-- Kapasitas -->
                    <div class="flex items-center gap-3.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0 border border-blue-100">
                            <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Max Capacity</p>
                            <p class="text-sm font-black text-slate-800">{{ room.kapasitas_max }} people</p>
                        </div>
                    </div>


                    <!-- Booking Aktif -->
                    <div class="flex items-center gap-3.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0 border border-amber-100">
                            <svg class="w-4.5 h-4.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Active Bookings</p>
                            <p class="text-sm font-black text-slate-800">{{ room.total_bookings }} bookings</p>
                        </div>
                    </div>
                </div>

                <!-- Card Footer: Actions -->
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex gap-2.5">
                    <button @click="openEdit(room)"
                        class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-white hover:bg-blue-50 text-slate-700 hover:text-blue-700 rounded-xl text-xs font-bold transition-all border border-slate-200 hover:border-blue-300 shadow-sm hover:shadow active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Edit
                    </button>
                    <button @click="openDelete(room)"
                        class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-white hover:bg-rose-50 text-slate-700 hover:text-rose-600 rounded-xl text-xs font-bold transition-all border border-slate-200 hover:border-rose-300 shadow-sm hover:shadow active:scale-95"
                        :disabled="room.total_bookings > 0"
                        :title="room.total_bookings > 0 ? 'Cannot be deleted — room has active bookings' : 'Delete room'"
                        :class="room.total_bookings > 0 ? 'opacity-40 cursor-not-allowed hover:bg-white hover:text-slate-700 hover:border-slate-200 hover:shadow-sm active:scale-100' : ''"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete
                    </button>
                </div>
            </div>
        </div>


        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- MODAL: Form Tambah / Edit Ruangan                        -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showFormModal"
                     class="fixed inset-0 bg-white/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
                     @click.self="closeFormModal">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-gray-200">

                        <!-- Modal Header -->
                        <div class="px-6 py-5 border-b border-gray-200 bg-white flex justify-between items-center shrink-0">
                            <div>
                                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" /></svg>
                                    {{ isEditing ? 'Edit Room' : 'Add New Room' }}
                                </h2>
                                <p class="text-xs text-gray-500 mt-1 font-medium">
                                    {{ isEditing ? `Editing: ${editingRoom?.nama_ruang}` : 'Fill in new training room information.' }}
                                </p>
                            </div>
                            <button @click="closeFormModal" class="text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 p-1.5 rounded-full transition-all cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <form @submit.prevent="submitForm" class="px-6 py-5 space-y-4">

                            <!-- Nama Ruangan -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Room Name <span class="text-red-500">*</span></label>
                                <input
                                    v-model="form.nama_ruang"
                                    type="text"
                                    required
                                    placeholder="Example: Room 7"
                                    class="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :class="form.errors.nama_ruang ? 'border-red-400' : 'border-gray-200'"
                                />
                                <p v-if="form.errors.nama_ruang" class="text-xs text-red-500 mt-1">{{ form.errors.nama_ruang }}</p>
                            </div>

                            <!-- Lokasi Gedung -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Building Location</label>
                                <input
                                    v-model="form.lokasi_gedung"
                                    type="text"
                                    placeholder="Example: Building A Floor 2"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.lokasi_gedung" class="text-xs text-red-500 mt-1">{{ form.errors.lokasi_gedung }}</p>
                            </div>

                            <!-- Kapasitas -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Capacity (people) <span class="text-red-500">*</span></label>
                                <input
                                    v-model="form.kapasitas_max"
                                    type="number"
                                    required
                                    min="1"
                                    max="500"
                                    placeholder="Example: 30"
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
                                        <p class="text-sm font-semibold text-gray-800">Can be Combined</p>
                                        <p class="text-xs text-gray-500">This room can be combined with its paired room.</p>
                                    </div>
                                </label>

                                <!-- Pasangan Ruang (tampil hanya jika bisa_digabung) -->
                                <div v-if="form.bisa_digabung" class="mt-3">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Paired Room</label>
                                    <select
                                        v-model="form.pasangan_ruang_id"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
                                    >
                                        <option :value="null">— Select paired room —</option>
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
                                    class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-bold transition shadow-sm cursor-pointer select-none disabled:opacity-60">
                                    {{ form.processing ? 'Saving...' : (isEditing ? 'Update' : 'Add Room') }}
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
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            >
                <div v-if="showDeleteModal"
                     class="fixed inset-0 bg-white/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
                     @click.self="closeDeleteModal">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden border border-gray-200">
                        <div class="p-6">
                            <div class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4 border border-red-200">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 text-center mb-1.5">Delete Room?</h3>
                            <p class="text-sm text-gray-600 text-center leading-relaxed">
                                Room <strong class="text-gray-800">{{ deletingRoom?.nama_ruang }}</strong> will be permanently deleted. This action cannot be undone.
                            </p>
                        </div>
                        <div class="px-6 pb-6 flex gap-3">
                            <button @click="closeDeleteModal"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none">
                                Cancel
                            </button>
                            <button @click="submitDelete" :disabled="deleteForm.processing"
                                class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white rounded-md text-sm font-bold transition shadow-sm cursor-pointer select-none">
                                {{ deleteForm.processing ? 'Deleting...' : 'Yes, Delete' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

</template>
