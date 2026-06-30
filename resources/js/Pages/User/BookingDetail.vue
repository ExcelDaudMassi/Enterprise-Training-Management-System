<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { ref, inject } from 'vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: UserLayout })

const props = defineProps({
    auth: Object,
    booking: Object
})

const collapseDetailMenu = inject('collapseDetailMenu', null)

function handleBack(url, e) {
    if (collapseDetailMenu) {
        e.preventDefault()
        e.stopPropagation()
        collapseDetailMenu()
        setTimeout(() => {
            router.visit(url)
        }, 450)
    }
}

const STATUS_META = {
    pending:   { label: 'Pending',   class: 'bg-yellow-50 text-yellow-850 border border-yellow-200' },
    confirmed: { label: 'Confirmed', class: 'bg-indigo-50 text-indigo-850 border border-indigo-200' },
    finalized: { label: 'Finalized', class: 'bg-green-50 text-green-850 border border-green-200' },
    rejected:  { label: 'Rejected',  class: 'bg-rose-50 text-rose-850 border border-rose-200' },
    cancelled: { label: 'Cancelled', class: 'bg-slate-50 text-slate-800 border border-slate-200' },
    completed: { label: 'Completed', class: 'bg-emerald-50 text-emerald-850 border border-emerald-250' },
    
    // Fallbacks for older data
    waiting_confirmation: { label: 'Pending',   class: 'bg-yellow-50 text-yellow-850 border border-yellow-200' },
    final:                { label: 'Finalized', class: 'bg-green-50 text-green-850 border border-green-200' },
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

// ─── Modal & Action State ─────────────────────────────────────
const showCancelModal = ref(false)
const cancelLoading = ref(false)
const cancelError = ref('')

// Participant Modal — two tabs: 'add' | 'excel'
const showParticipantModal = ref(false)
const participantModalTab = ref('add')       // default to form tab
const participantLoading = ref(false)
const participantError = ref('')
const participantSuccess = ref('')

// Form state for single-add tab
const addForm = ref({
    tipe:    'peserta',
    nama:    '',
    nrp:     '',
    jabatan: '',
    site:    '',
    no_hp:   '',
    gender:  '',
})

function resetAddForm() {
    addForm.value = { tipe: 'peserta', nama: '', nrp: '', jabatan: '', site: '', no_hp: '', gender: '' }
}

function openParticipantModal(tab = 'add') {
    participantModalTab.value = tab
    participantError.value = ''
    participantSuccess.value = ''
    resetAddForm()
    showParticipantModal.value = true
}

const showDateModal = ref(false)
const dateLoading = ref(false)
const dateError = ref('')
const dateForm = ref({ proposed_tgl_mulai: '', proposed_tgl_selesai: '', alasan: '' })

function openDateChange() {
    dateError.value = ''
    dateForm.value = {
        proposed_tgl_mulai:   props.booking.tgl_mulai ?? '',
        proposed_tgl_selesai: props.booking.tgl_selesai ?? '',
        alasan: ''
    }
    showDateModal.value = true
}

async function submitDateChange() {
    dateLoading.value = true
    dateError.value   = ''
    try {
        await window.axios.post(`/api/booking/${props.booking.id}/request-date-change`, dateForm.value)
        showDateModal.value = false
        window.location.reload()
    } catch (e) {
        dateError.value = e.response?.data?.message
            ?? e.response?.data?.errors?.proposed_tgl_mulai?.[0]
            ?? 'An error occurred.'
    } finally {
        dateLoading.value = false
    }
}

async function submitCancel() {
    cancelLoading.value = true
    cancelError.value = ''
    try {
        await window.axios.post(`/api/booking/${props.booking.id}/cancel`)
        showCancelModal.value = false
        window.location.reload()
    } catch (e) {
        cancelError.value = e.response?.data?.message ?? 'An error occurred.'
    } finally {
        cancelLoading.value = false
    }
}

// ── Add single participant (APPEND — tidak menghapus yang lama) ──
async function addSingleParticipant() {
    if (!addForm.value.nama.trim()) {
        participantError.value = 'Nama peserta wajib diisi.'
        return
    }
    participantLoading.value = true
    participantError.value = ''
    participantSuccess.value = ''
    try {
        const payload = {
            tipe:    addForm.value.tipe,
            nama:    addForm.value.nama.trim(),
            nrp:     addForm.value.nrp.trim() || null,
            jabatan: addForm.value.jabatan.trim() || null,
            site:    addForm.value.site.trim() || null,
            no_hp:   addForm.value.no_hp.trim() || null,
            gender:  addForm.value.gender || null,
        }
        await window.axios.post(`/api/booking/${props.booking.id}/add-participant`, payload)
        participantSuccess.value = `${payload.tipe === 'peserta' ? 'Peserta' : 'Panitia'} "${payload.nama}" berhasil ditambahkan!`
        resetAddForm()
        // Reload hanya data booking (participants) tanpa full page reload
        router.reload({ only: ['booking'], preserveState: true, preserveScroll: true })
    } catch (err) {
        const errors = err.response?.data?.errors
        if (errors) {
            participantError.value = Object.values(errors).flat().join(' ')
        } else {
            participantError.value = err.response?.data?.message ?? 'Gagal menambahkan peserta.'
        }
    } finally {
        participantLoading.value = false
    }
}

// ── Upload Excel Phase 1: Preview & deteksi duplikat ──
const excelPreviewData  = ref(null)   // data dari endpoint preview
const selectedExcelFile = ref(null)   // file object disimpan untuk fase konfirmasi

async function handleExcelSelected(e) {
    const file = e.target.files[0]
    if (!file) return

    // Reset state
    e.target.value = ''
    selectedExcelFile.value = file
    excelPreviewData.value = null
    participantLoading.value = true
    participantError.value = ''
    participantSuccess.value = ''

    try {
        const fd = new FormData()
        fd.append('file_peserta', file)
        const res = await window.axios.post(
            `/api/booking/${props.booking.id}/preview-excel-participants`, fd
        )
        excelPreviewData.value = res.data
    } catch (err) {
        participantError.value = err.response?.data?.message ?? 'Gagal membaca file Excel.'
        selectedExcelFile.value = null
    } finally {
        participantLoading.value = false
    }
}

// ── Upload Excel Phase 2: Konfirmasi → eksekusi replace ──
async function confirmExcelReplace() {
    if (!selectedExcelFile.value) return
    participantLoading.value = true
    participantError.value = ''
    try {
        const fd = new FormData()
        fd.append('file_peserta', selectedExcelFile.value)
        await window.axios.post(`/api/booking/${props.booking.id}/update-participants`, fd)
        excelPreviewData.value = null
        selectedExcelFile.value = null
        showParticipantModal.value = false
        router.reload({ only: ['booking'], preserveState: true, preserveScroll: true })
    } catch (err) {
        participantError.value = err.response?.data?.message ?? 'Gagal mengupload file Excel.'
    } finally {
        participantLoading.value = false
    }
}

function cancelExcelPreview() {
    excelPreviewData.value = null
    selectedExcelFile.value = null
    participantError.value = ''
}


function getInitials(name) {
    if (!name) return 'BK'
    return name
        .trim()
        .split(/\s+/)
        .map(word => word[0])
        .join('')
        .substring(0, 2)
        .toUpperCase()
}

function getAvatarBg(id) {
    const colors = [
        'bg-blue-50 text-blue-700 border border-blue-100/50',
        'bg-emerald-50 text-emerald-700 border border-emerald-100/50',
        'bg-purple-50 text-purple-700 border border-purple-100/50',
        'bg-amber-50 text-amber-700 border border-amber-100/50',
        'bg-indigo-50 text-indigo-700 border border-indigo-100/50',
        'bg-pink-50 text-pink-700 border border-pink-100/50',
        'bg-cyan-50 text-cyan-700 border border-cyan-100/50'
    ]
    return colors[id % colors.length]
}
</script>

<template>
    <div class="max-w-6xl mx-auto space-y-6">
            
            <!-- Navigation Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link href="/user/booking/active" 
                       @click="handleBack('/user/booking/active', $event)"
                       class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-800 bg-white hover:bg-gray-50 border border-gray-200 px-3.5 py-1.5 rounded-md text-xs font-bold transition shadow-sm select-none">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Back
                    </Link>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800">Booking Detail</h1>
                        <p class="text-[11px] text-gray-500 mt-0.5">Complete information of your division's room booking</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-md text-xs font-semibold inline-flex items-center gap-1.5" 
                          :class="STATUS_META[booking.status]?.class ?? 'bg-gray-100 text-gray-600 border border-gray-200'">
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" 
                                  :class="['pending', 'waiting_confirmation'].includes(booking.status) ? 'bg-yellow-400' : (booking.status === 'confirmed' ? 'bg-indigo-400' : (['finalized', 'final', 'completed'].includes(booking.status) ? 'bg-green-400' : 'bg-red-400'))"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2"
                                  :class="['pending', 'waiting_confirmation'].includes(booking.status) ? 'bg-yellow-500' : (booking.status === 'confirmed' ? 'bg-indigo-500' : (['finalized', 'final', 'completed'].includes(booking.status) ? 'bg-green-500' : 'bg-red-500'))"></span>
                        </span>
                        {{ STATUS_META[booking.status]?.label ?? booking.status }}
                    </span>
                </div>
            </div>

            <!-- Main Two-Column Layout Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Left Column (General info & Manifests) -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- General Booking Info Card -->
                    <div class="bg-white rounded-md border border-gray-100 shadow-sm p-6 flex flex-col space-y-5">
                        
                        <!-- Header / Title -->
                        <div class="border-b border-gray-100 pb-4">
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Event Name</div>
                            <h2 class="text-lg font-bold text-gray-900 leading-snug mt-1">{{ booking.nama_training }}</h2>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-gray-500 mt-2">
                                <span>Booking No.: <strong class="text-gray-700">#{{ String(booking.id).padStart(5, '0') }}</strong></span>
                                <span class="text-gray-300">•</span>
                                <span>Submitted on: <strong class="text-gray-700">{{ booking.created_at || '-' }}</strong></span>
                            </div>
                        </div>

                        <!-- Info Grid 2x2 -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            
                            <!-- Ruangan -->
                            <div class="flex items-start gap-3 p-3.5 bg-gray-50 border border-gray-150 rounded-sm">
                                <div class="p-2 bg-blue-50 text-blue-600 rounded-md shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Room</p>
                                    <p class="text-sm font-bold text-gray-800 mt-0.5 leading-snug">{{ booking.nama_ruang }}</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5">{{ booking.gabung_ruang ? 'Combined Room 2 & 3' : (booking.ruangan?.lokasi_gedung ?? '-') }}</p>
                                </div>
                            </div>

                            <!-- Waktu Pelaksanaan -->
                            <div class="flex items-start gap-3 p-3.5 bg-gray-50 border border-gray-150 rounded-sm">
                                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-md shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Execution Time</p>
                                    <p class="text-sm font-bold text-gray-800 mt-0.5 leading-snug">{{ formatDate(booking.tgl_mulai) }}</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5">to {{ formatDate(booking.tgl_selesai) }}</p>
                                </div>
                            </div>

                            <!-- Kapasitas -->
                            <div class="flex items-start gap-3 p-3.5 bg-gray-50 border border-gray-150 rounded-sm">
                                <div class="p-2 bg-purple-50 text-purple-600 rounded-md shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Applicant Capacity</p>
                                    <div class="flex items-center gap-3 mt-1.5">
                                        <div>
                                            <span class="text-sm font-bold text-gray-800">{{ booking.participants?.filter(p => p.tipe === 'peserta').length ?? booking.jumlah_peserta }}</span>
                                            <span class="text-[10px] text-gray-500 ml-1">Participants</span>
                                        </div>
                                        <div class="w-px h-3 bg-gray-300"></div>
                                        <div>
                                            <span class="text-sm font-bold text-gray-800">{{ booking.participants?.filter(p => p.tipe === 'panitia').length ?? booking.jumlah_panitia }}</span>
                                            <span class="text-[10px] text-gray-500 ml-1">Organizer</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PIC -->
                            <div class="flex items-start gap-3 p-3.5 bg-gray-50 border border-gray-150 rounded-sm">
                                <div class="p-2 bg-indigo-50 text-indigo-600 rounded-md shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Person in Charge (PIC)</p>
                                    <p class="text-sm font-bold text-gray-800 mt-0.5 leading-snug truncate">{{ booking.pic }}</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5">Division: {{ auth.user.divisi ?? '-' }}</p>
                                </div>
                            </div>

                        </div>

                        <!-- Fasilitas & Setup -->
                        <div class="p-3.5 bg-gray-50 border border-gray-150 rounded-sm">
                            <div class="flex items-start gap-2.5">
                                <div class="p-1.5 bg-amber-50 text-amber-600 rounded-md shrink-0">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Facilities & Setup Preferences</p>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <span v-if="booking.layout_preferensi" 
                                              class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 border border-blue-100 text-blue-700 rounded-md text-[10px] font-bold uppercase tracking-wide">
                                            Layout: {{ booking.layout_preferensi }}
                                        </span>
                                        <span v-if="booking.is_hybrid" 
                                              class="inline-flex items-center gap-1 px-2.5 py-1 bg-violet-50 border border-violet-100 text-violet-700 rounded-md text-[10px] font-bold">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                                            </svg>
                                            HYBRID MODE
                                        </span>
                                        <span v-if="booking.is_flipchart" 
                                            class="inline-flex items-center justify-center h-5 px-2 text-[10px] font-bold tracking-wider rounded bg-orange-100 text-orange-700 uppercase border border-orange-200">
                                            FLIPCHART
                                        </span>
                                        <span v-if="booking.is_pena_mini_note" 
                                            class="inline-flex items-center justify-center h-5 px-2 text-[10px] font-bold tracking-wider rounded bg-teal-100 text-teal-700 uppercase border border-teal-200">
                                            PEN & MINI NOTE
                                        </span>
                                        <span v-if="!booking.layout_preferensi && !booking.is_hybrid && !booking.is_flipchart && !booking.is_pena_mini_note"
                                            class="text-xs text-gray-400 font-medium italic">No layout preferences / additional facilities.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan User -->
                        <div v-if="booking.catatan_user" class="p-3.5 bg-blue-50/50 border border-blue-100 rounded-sm">
                            <p class="text-[9px] font-bold text-blue-700 uppercase tracking-widest">Additional Applicant Notes</p>
                            <p class="text-xs text-blue-900 mt-1 leading-relaxed italic">"{{ booking.catatan_user }}"</p>
                        </div>

                        <!-- Catatan Admin -->
                        <div v-if="booking.catatan_admin" class="p-3.5 bg-amber-50/50 border border-amber-100 rounded-sm">
                            <div class="flex items-center gap-1.5 text-amber-800">
                                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                                <p class="text-[9px] font-bold uppercase tracking-widest">Admin Notes</p>
                            </div>
                            <p class="text-xs text-amber-950 mt-1 leading-relaxed font-semibold">"{{ booking.catatan_admin }}"</p>
                        </div>

                    </div>

                    <!-- Manifest Section (Peserta & Panitia) -->
                    <div class="bg-white rounded-md border border-gray-100 shadow-sm p-6 space-y-6">
                        
                        <!-- Manifest Peserta -->
                        <div>
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="p-1.5 bg-blue-50 text-blue-600 rounded-md shrink-0">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.978 11.978 0 0 1 12 20.25a11.98 11.98 0 0 1-3-1.013v-.109m6 0c0-.529-.029-1.049-.086-1.56M9 19.128v-.003c0-1.113.285-2.16.786-3.07M9 19.128v.109A12.042 12.042 0 0 1 5 18.25c-1.391-.772-2.5-1.954-3.125-3.376a4.125 4.125 0 0 1 7.533-2.493m0 0A3.125 3.125 0 1 1 12 9.75a3.125 3.125 0 0 1-2.593 3.031m4.5 4.52c0-.529-.029-1.049-.086-1.56m-4.5 1.56a12.188 12.188 0 0 1-3.97-1.56" />
                                        </svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wide">Participant List</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button v-if="booking.can_update_participants"
                                            @click="openParticipantModal('add')"
                                            class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-600 hover:text-blue-700 border border-blue-200 hover:border-blue-300 bg-blue-50 hover:bg-blue-100 px-2 py-0.5 rounded transition select-none cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                        Add
                                    </button>
                                    <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full select-none">
                                        {{ booking.participants?.filter(p => p.tipe === 'peserta').length ?? 0 }} Participants
                                    </span>
                                </div>
                            </div>

                            <div v-if="!booking.participants?.filter(p => p.tipe === 'peserta').length" 
                                 class="text-center py-8 border border-dashed border-gray-250 rounded-sm">
                                <p class="text-xs text-gray-400">No participant data registered yet</p>
                            </div>
                            
                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="(p, i) in booking.participants?.filter(p => p.tipe === 'peserta')" 
                                     :key="p.id" 
                                     class="flex items-start gap-3 p-3 border border-gray-100 hover:border-gray-200 bg-white rounded-sm transition shadow-2xs">
                                    <div class="w-7 h-7 rounded-md flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5" 
                                         :class="getAvatarBg(i)">
                                        {{ getInitials(p.nama) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-gray-800 leading-snug truncate">{{ p.nama }}</p>
                                        <div class="flex flex-wrap gap-x-2.5 gap-y-0.5 mt-1 text-[9px] text-gray-500 font-medium">
                                            <span v-if="p.nrp">NRP: <strong class="text-gray-700">{{ p.nrp }}</strong></span>
                                            <span v-if="p.jabatan" class="truncate max-w-[80px]" :title="p.jabatan">{{ p.jabatan }}</span>
                                            <span v-if="p.site">{{ p.site }}</span>
                                            <span v-if="p.no_hp">{{ p.no_hp }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Manifest Panitia -->
                        <div>
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4 pt-4">
                                <div class="flex items-center gap-2">
                                    <div class="p-1.5 bg-violet-50 text-violet-600 rounded-md shrink-0">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wide">Organizer List</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button v-if="booking.can_update_participants"
                                            @click="() => { openParticipantModal('add'); addForm.tipe = 'panitia' }"
                                            class="inline-flex items-center gap-1 text-[10px] font-bold text-violet-600 hover:text-violet-700 border border-violet-200 hover:border-violet-300 bg-violet-50 hover:bg-violet-100 px-2 py-0.5 rounded transition select-none cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                        Add
                                    </button>
                                    <span class="bg-violet-100 text-violet-700 text-[10px] font-bold px-2 py-0.5 rounded-full select-none">
                                        {{ booking.participants?.filter(p => p.tipe === 'panitia').length ?? 0 }} Organizer
                                    </span>
                                </div>
                            </div>

                            <div v-if="!booking.participants?.filter(p => p.tipe === 'panitia').length" 
                                 class="text-center py-8 border border-dashed border-gray-250 rounded-sm">
                                <p class="text-xs text-gray-400">No special organizers registered</p>
                            </div>
                            
                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="(p, i) in booking.participants?.filter(p => p.tipe === 'panitia')" 
                                     :key="p.id" 
                                     class="flex items-start gap-3 p-3 border border-gray-100 hover:border-gray-200 bg-white rounded-sm transition shadow-2xs">
                                    <div class="w-7 h-7 rounded-md flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5" 
                                         :class="getAvatarBg(i + 4)">
                                        {{ getInitials(p.nama) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-gray-800 leading-snug truncate">{{ p.nama }}</p>
                                        <div class="flex flex-wrap gap-x-2.5 gap-y-0.5 mt-1 text-[9px] text-gray-500 font-medium">
                                            <span v-if="p.nrp">NRP: <strong class="text-gray-700">{{ p.nrp }}</strong></span>
                                            <span v-if="p.jabatan" class="truncate max-w-[80px]" :title="p.jabatan">{{ p.jabatan }}</span>
                                            <span v-if="p.site">{{ p.site }}</span>
                                            <span v-if="p.no_hp">{{ p.no_hp }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Right Column (Sidebar Action Panels) -->
                <div class="space-y-6">
                    
                    <!-- Booking Actions Panel -->
                    <div class="bg-white rounded-md border border-gray-100 shadow-sm p-5 space-y-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">Submission Actions</h3>
                        
                        <!-- Download Ticket -->
                        <a :href="`/user/booking/${booking.id}/pdf`" 
                           target="_blank"
                           class="w-full inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white py-2.5 px-4 rounded-md text-xs font-bold transition shadow-sm active:scale-[0.98] select-none">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Download Ticket / PDF
                        </a>

                        <!-- Ubah Tanggal Booking -->
                        <button v-if="booking.can_request_date_change" 
                                @click="openDateChange" 
                                class="w-full inline-flex items-center justify-center gap-2 border border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100 py-2.5 px-4 rounded-md text-xs font-bold transition shadow-sm active:scale-[0.98] select-none cursor-pointer">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            Request Date Change
                        </button>

                        <!-- Update Participants -->
                        <button v-if="booking.can_update_participants" 
                                @click="openParticipantModal('add')" 
                                class="w-full inline-flex items-center justify-center gap-2 border border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100 py-2.5 px-4 rounded-md text-xs font-bold transition shadow-sm active:scale-[0.98] select-none cursor-pointer">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Tambah / Update Peserta
                        </button>

                        <!-- Cancel Booking -->
                        <button v-if="booking.can_cancel" 
                                @click="showCancelModal = true" 
                                class="w-full inline-flex items-center justify-center gap-2 border border-red-200 text-red-650 bg-white hover:bg-red-50 hover:text-red-750 py-2.5 px-4 rounded-md text-xs font-bold transition shadow-sm active:scale-[0.98] select-none cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Cancel Booking
                        </button>
                    </div>

                    <!-- Process Timeline (Status Tracker) Card -->
                    <div class="bg-white rounded-md border border-gray-100 shadow-sm p-5 space-y-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">Submission Flow Status</h3>
                        
                        <!-- Timeline Steps -->
                        <div class="space-y-4 font-sans text-xs">
                            
                            <!-- Step 1: Diajukan -->
                            <div class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <div class="w-5 h-5 rounded-full bg-green-100 border border-green-200 text-green-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                        ✓
                                    </div>
                                    <div class="w-0.5 h-6 bg-green-250 mt-1"></div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-gray-800">Submitted to Admin</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5">{{ booking.created_at || 'Completed' }}</p>
                                </div>
                            </div>

                            <!-- Step 2: Persetujuan Admin (pending, confirmed, finalized, completed) -->
                            <div class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] shrink-0"
                                         :class="(['confirmed', 'finalized', 'final', 'completed'].includes(booking.status)) ? 'bg-green-100 border border-green-200 text-green-700' : ((['cancelled', 'rejected'].includes(booking.status)) ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-blue-50 border border-blue-200 text-blue-700 animate-pulse')">
                                        {{ (['confirmed', 'finalized', 'final', 'completed'].includes(booking.status)) ? '✓' : ((['cancelled', 'rejected'].includes(booking.status)) ? '✕' : '2') }}
                                    </div>
                                    <div class="w-0.5 h-6 mt-1" :class="(['finalized', 'final', 'completed'].includes(booking.status)) ? 'bg-green-250' : 'bg-gray-200'"></div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-gray-800" :class="['pending', 'waiting_confirmation'].includes(booking.status) ? 'text-blue-700 font-bold' : ''">Admin Approval</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5">
                                        {{ (['confirmed', 'finalized', 'final', 'completed'].includes(booking.status)) ? 'Approved by Admin' : ((['cancelled', 'rejected'].includes(booking.status)) ? (booking.status === 'rejected' ? 'Booking Rejected' : 'Booking Cancelled') : 'Waiting for admin approval') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Step 3: ACC Final & Persiapan Ruangan -->
                            <div class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] shrink-0"
                                         :class="(['finalized', 'final', 'completed'].includes(booking.status)) ? 'bg-green-100 border border-green-200 text-green-700' : 'bg-gray-50 border border-gray-200 text-gray-400'">
                                        {{ (['finalized', 'final', 'completed'].includes(booking.status)) ? '✓' : '3' }}
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-gray-800" :class="(['finalized', 'final', 'completed'].includes(booking.status)) ? 'text-green-700 font-bold' : 'text-gray-400'">Room Preparation</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5">
                                        {{ (['finalized', 'final', 'completed'].includes(booking.status)) ? 'The field team is preparing the facilities' : 'Preparation is done after the status is approved' }}
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- ── MODAL: Konfirmasi Batalkan Booking ── -->
    <Teleport to="body">
        <Transition 
            enter-active-class="transition-all ease-out duration-300" 
            enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0" 
            enter-to-class="opacity-100 scale-100 translate-y-0" 
            leave-active-class="transition-all ease-in duration-200" 
            leave-from-class="opacity-100 scale-100 translate-y-0" 
            leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0">
            <div v-if="showCancelModal" class="fixed inset-0 bg-white/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl border border-gray-250 shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-50 border border-red-200 rounded-full mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-center text-gray-900 mb-2">Cancel Booking?</h2>
                    <p class="text-sm text-center text-gray-600 mb-1">
                        Are you sure you want to cancel the event booking:
                        <strong class="text-gray-800 block mt-0.5">{{ booking.nama_training }}</strong>?
                    </p>
                    <p class="text-[11px] text-center text-red-500 mb-4">This action is permanent and the room will be released immediately.</p>
                    
                    <div v-if="cancelError" class="mb-4 text-xs text-red-600 bg-red-50 border border-red-150 rounded-sm p-3 font-semibold">
                        {{ cancelError }}
                    </div>
                    
                    <div class="flex gap-3">
                        <button @click="showCancelModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-xs font-bold text-gray-700 hover:bg-gray-50 transition select-none cursor-pointer">
                            Close
                        </button>
                        <button @click="submitCancel" :disabled="cancelLoading"
                                class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-xs font-bold disabled:opacity-50 transition select-none cursor-pointer">
                            {{ cancelLoading ? 'Cancelling...' : 'Yes, Cancel' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

        <!-- ── MODAL: Update / Tambah Peserta (2 Tab) ── -->
    <Teleport to="body">
        <Transition 
            enter-active-class="transition-all ease-out duration-300" 
            enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0" 
            enter-to-class="opacity-100 scale-100 translate-y-0" 
            leave-active-class="transition-all ease-in duration-200" 
            leave-from-class="opacity-100 scale-100 translate-y-0" 
            leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0">
            <div v-if="showParticipantModal" class="fixed inset-0 bg-white/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-2xl w-full max-w-lg">

                    <!-- Modal Header -->
                    <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Kelola Peserta</h2>
                            <p class="text-[11px] text-gray-400 mt-0.5">{{ booking.nama_training }}</p>
                        </div>
                        <button @click="showParticipantModal = false" :disabled="participantLoading"
                                class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Tab Switcher -->
                    <div class="flex border-b border-gray-100 px-6">
                        <button @click="participantModalTab = 'add'; participantError = ''; participantSuccess = ''; cancelExcelPreview()"
                                class="py-2.5 px-4 text-xs font-bold border-b-2 transition-colors -mb-px cursor-pointer"
                                :class="participantModalTab === 'add' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                Tambah Peserta
                            </span>
                        </button>
                        <button @click="participantModalTab = 'excel'; participantError = ''; participantSuccess = ''; cancelExcelPreview()"
                                class="py-2.5 px-4 text-xs font-bold border-b-2 transition-colors -mb-px cursor-pointer"
                                :class="participantModalTab === 'excel' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                                Upload Excel
                            </span>
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">

                        <!-- ── TAB: Tambah Peserta (Form Manual) ── -->
                        <div v-if="participantModalTab === 'add'" class="space-y-3">

                            <!-- Tipe Toggle -->
                            <div class="flex rounded-md border border-gray-200 overflow-hidden">
                                <button @click="addForm.tipe = 'peserta'"
                                        class="flex-1 py-2 text-xs font-bold transition cursor-pointer"
                                        :class="addForm.tipe === 'peserta' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'">
                                    Peserta
                                </button>
                                <button @click="addForm.tipe = 'panitia'"
                                        class="flex-1 py-2 text-xs font-bold transition cursor-pointer"
                                        :class="addForm.tipe === 'panitia' ? 'bg-violet-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'">
                                    Panitia
                                </button>
                            </div>

                            <!-- Nama (required) -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">
                                    Nama <span class="text-red-500">*</span>
                                </label>
                                <input v-model="addForm.nama" type="text" placeholder="Nama lengkap"
                                       class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition" />
                            </div>

                            <!-- Grid: NRP + Gender -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">NRP</label>
                                    <input v-model="addForm.nrp" type="text" placeholder="Kosong = N/A"
                                           class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Gender</label>
                                    <select v-model="addForm.gender"
                                            class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition bg-white">
                                        <option value="">— Pilih —</option>
                                        <option value="L">Laki-laki (L)</option>
                                        <option value="P">Perempuan (P)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Grid: Jabatan + Site -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Jabatan</label>
                                    <input v-model="addForm.jabatan" type="text" placeholder="Jabatan / posisi"
                                           class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Site</label>
                                    <input v-model="addForm.site" type="text" placeholder="Lokasi / site"
                                           class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition" />
                                </div>
                            </div>

                            <!-- No HP -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">No. HP</label>
                                <input v-model="addForm.no_hp" type="text" placeholder="Nomor HP (opsional)"
                                       class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition" />
                            </div>

                            <!-- Loading -->
                            <div v-if="participantLoading" class="flex items-center justify-center gap-2 text-xs text-blue-600 py-1 font-semibold">
                                <span class="w-3.5 h-3.5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></span>
                                Menyimpan data...
                            </div>

                            <!-- Error -->
                            <div v-if="participantError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-md p-3 font-semibold">
                                {{ participantError }}
                            </div>

                            <!-- Success (persistent until next action) -->
                            <div v-if="participantSuccess" class="text-xs text-green-700 bg-green-50 border border-green-200 rounded-md p-3 font-semibold flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                {{ participantSuccess }}
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2 pt-1">
                                <button @click="showParticipantModal = false" :disabled="participantLoading"
                                        class="flex-1 py-2.5 border border-gray-200 text-gray-600 rounded-md text-xs font-bold hover:bg-gray-50 transition cursor-pointer select-none">
                                    Tutup
                                </button>
                                <button @click="addSingleParticipant" :disabled="participantLoading || !addForm.nama.trim()"
                                        class="flex-1 py-2.5 rounded-md text-xs font-bold text-white transition shadow-sm cursor-pointer select-none disabled:opacity-50"
                                        :class="addForm.tipe === 'peserta' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-violet-600 hover:bg-violet-700'">
                                    {{ participantLoading ? 'Menyimpan...' : `+ Tambah ${addForm.tipe === 'peserta' ? 'Peserta' : 'Panitia'}` }}
                                </button>
                            </div>
                        </div>

                        <!-- ── TAB: Upload Excel (Replace All) ── -->
                        <div v-if="participantModalTab === 'excel'" class="space-y-4">

                            <!-- Phase 1: Pilih File (tampil selama belum ada preview) -->
                            <template v-if="!excelPreviewData">
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-md text-xs text-amber-800 font-semibold flex items-start gap-2">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                    <span>Upload Excel akan <strong>mengganti semua</strong> data peserta &amp; panitia yang ada. Sistem akan memeriksa duplikat sebelum mengganti.</span>
                                </div>

                                <div class="border-2 border-dashed border-gray-200 bg-gray-50/50 hover:bg-gray-50 p-6 text-center rounded-md transition cursor-pointer relative"
                                     :class="participantLoading ? 'opacity-60 pointer-events-none' : ''">
                                    <input type="file" accept=".xlsx"
                                           @change="handleExcelSelected"
                                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                    <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5l3 3m0 0l3-3m-3 3v-6m1.06-4.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                    </svg>
                                    <p class="text-xs font-bold text-gray-600">{{ participantLoading ? 'Membaca file...' : 'Klik untuk pilih file XLSX' }}</p>
                                    <p class="text-[9px] text-gray-400 mt-1">Maksimum 2MB · Format: .xlsx</p>
                                </div>

                                <!-- Loading spinner saat baca file -->
                                <div v-if="participantLoading" class="flex items-center justify-center gap-2 text-xs text-blue-600 font-semibold">
                                    <span class="w-3.5 h-3.5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></span>
                                    Membaca & memverifikasi file...
                                </div>
                            </template>

                            <!-- Phase 2: Konfirmasi Preview (tampil setelah preview berhasil) -->
                            <template v-if="excelPreviewData">

                                <!-- Badge format -->
                                <div class="flex items-center gap-2 justify-center">
                                    <span v-if="excelPreviewData.is_dual_sheet"
                                          class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
                                        Format Dual Sheet (Peserta + Panitia)
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200">
                                        Format Single Sheet (Peserta saja)
                                    </span>
                                </div>

                                <!-- Stats Row -->
                                <div v-if="excelPreviewData.is_dual_sheet" class="grid grid-cols-4 gap-2">
                                    <div class="bg-blue-50 border border-blue-100 rounded-md p-2.5 text-center">
                                        <p class="text-base font-bold text-blue-700">{{ excelPreviewData.total_excel }}</p>
                                        <p class="text-[9px] text-blue-500 font-semibold mt-0.5">Total</p>
                                    </div>
                                    <div class="bg-sky-50 border border-sky-100 rounded-md p-2.5 text-center">
                                        <p class="text-base font-bold text-sky-700">{{ excelPreviewData.total_peserta }}</p>
                                        <p class="text-[9px] text-sky-500 font-semibold mt-0.5">Peserta</p>
                                    </div>
                                    <div class="bg-violet-50 border border-violet-100 rounded-md p-2.5 text-center">
                                        <p class="text-base font-bold text-violet-700">{{ excelPreviewData.total_panitia }}</p>
                                        <p class="text-[9px] text-violet-500 font-semibold mt-0.5">Panitia</p>
                                    </div>
                                    <div class="rounded-md p-2.5 text-center"
                                         :class="excelPreviewData.total_duplikat > 0 ? 'bg-orange-50 border border-orange-200' : 'bg-green-50 border border-green-200'">
                                        <p class="text-base font-bold" :class="excelPreviewData.total_duplikat > 0 ? 'text-orange-700' : 'text-green-700'">{{ excelPreviewData.total_duplikat }}</p>
                                        <p class="text-[9px] font-semibold mt-0.5" :class="excelPreviewData.total_duplikat > 0 ? 'text-orange-500' : 'text-green-500'">Duplikat</p>
                                    </div>
                                </div>
                                <div v-else class="grid grid-cols-3 gap-2">
                                    <div class="bg-blue-50 border border-blue-100 rounded-md p-3 text-center">
                                        <p class="text-lg font-bold text-blue-700">{{ excelPreviewData.total_excel }}</p>
                                        <p class="text-[10px] text-blue-500 font-semibold mt-0.5">Dari Excel</p>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-md p-3 text-center">
                                        <p class="text-lg font-bold text-gray-700">{{ excelPreviewData.total_existing }}</p>
                                        <p class="text-[10px] text-gray-500 font-semibold mt-0.5">Existing</p>
                                    </div>
                                    <div class="rounded-md p-3 text-center"
                                         :class="excelPreviewData.total_duplikat > 0 ? 'bg-orange-50 border border-orange-200' : 'bg-green-50 border border-green-200'">
                                        <p class="text-lg font-bold" :class="excelPreviewData.total_duplikat > 0 ? 'text-orange-700' : 'text-green-700'">{{ excelPreviewData.total_duplikat }}</p>
                                        <p class="text-[10px] font-semibold mt-0.5" :class="excelPreviewData.total_duplikat > 0 ? 'text-orange-500' : 'text-green-500'">Duplikat</p>
                                    </div>
                                </div>


                                <!-- Alert: Ada duplikat -->
                                <div v-if="excelPreviewData.total_duplikat > 0" class="p-3 bg-orange-50 border border-orange-200 rounded-md">
                                    <p class="text-xs font-bold text-orange-800 flex items-center gap-1.5 mb-2">
                                        <svg class="w-4 h-4 text-orange-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                        {{ excelPreviewData.total_duplikat }} peserta di Excel sudah terdaftar sebelumnya:
                                    </p>
                                    <ul class="space-y-1 max-h-28 overflow-y-auto">
                                        <li v-for="dup in excelPreviewData.duplikat" :key="dup.nrp + dup.nama"
                                            class="text-[11px] text-orange-700 bg-white/70 border border-orange-100 rounded px-2 py-1 flex items-center justify-between gap-2">
                                            <span class="font-bold truncate">{{ dup.nama }}</span>
                                            <span class="text-orange-500 text-[10px] shrink-0">{{ dup.alasan }}</span>
                                        </li>
                                    </ul>
                                    <p class="text-[10px] text-orange-600 mt-2">Jika Anda lanjutkan, semua data lama (termasuk peserta di atas) akan <strong>diganti</strong> dengan data dari Excel.</p>
                                </div>

                                <!-- Alert: Tidak ada duplikat -->
                                <div v-else class="p-3 bg-green-50 border border-green-200 rounded-md text-xs text-green-800 font-semibold flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    Tidak ada data duplikat ditemukan. Aman untuk diganti.
                                </div>

                                <!-- Nama file -->
                                <p class="text-[11px] text-gray-500 text-center truncate">
                                    📄 {{ selectedExcelFile?.name }}
                                </p>
                            </template>

                            <!-- Error -->
                            <div v-if="participantError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-md p-3 font-semibold">
                                {{ participantError }}
                            </div>

                            <!-- Tombol aksi -->
                            <div class="flex gap-2">
                                <button @click="excelPreviewData ? cancelExcelPreview() : (showParticipantModal = false)"
                                        :disabled="participantLoading"
                                        class="flex-1 py-2.5 border border-gray-200 text-gray-600 rounded-md text-xs font-bold hover:bg-gray-50 transition cursor-pointer select-none disabled:opacity-50">
                                    {{ excelPreviewData ? 'Ganti File' : 'Batal' }}
                                </button>
                                <button v-if="excelPreviewData"
                                        @click="confirmExcelReplace"
                                        :disabled="participantLoading"
                                        class="flex-1 py-2.5 rounded-md text-xs font-bold text-white transition shadow-sm cursor-pointer select-none disabled:opacity-50"
                                        :class="excelPreviewData.total_duplikat > 0 ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700'">
                                    <span v-if="participantLoading" class="flex items-center justify-center gap-1.5">
                                        <span class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                        Mengganti...
                                    </span>
                                    <span v-else>
                                        {{ excelPreviewData.total_duplikat > 0 ? '⚠ Ya, Tetap Ganti Semua' : '✓ Ganti Semua Data' }}
                                    </span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

        <!-- ── MODAL: Ubah Tanggal ── -->
    <Teleport to="body">
        <Transition 
            enter-active-class="transition-all ease-out duration-300" 
            enter-from-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0" 
            enter-to-class="opacity-100 scale-100 translate-y-0" 
            leave-active-class="transition-all ease-in duration-200" 
            leave-from-class="opacity-100 scale-100 translate-y-0" 
            leave-to-class="opacity-0 scale-95 translate-y-4 sm:translate-y-0">
            <div v-if="showDateModal" class="fixed inset-0 bg-white/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 border border-gray-250">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-blue-50 border border-blue-100 rounded-full mb-4 shadow-xs">
                        <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <h2 class="text-base font-bold text-center text-gray-900 mb-1">Request Date Change</h2>
                    <p class="text-[11px] text-center text-gray-500 mb-4">
                        The new date needs to be approved by the admin. The old date remains valid until approved.
                    </p>
                    <div class="space-y-3 mb-4">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1">New Start Date</label>
                            <input type="date" v-model="dateForm.proposed_tgl_mulai"
                                   class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1">New End Date</label>
                            <input type="date" v-model="dateForm.proposed_tgl_selesai"
                                   class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1">Reason (Optional)</label>
                            <textarea v-model="dateForm.alasan" rows="2"
                                      placeholder="For example: The speaker cannot attend..."
                                      class="w-full border border-gray-200 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                        </div>
                    </div>
                    <div v-if="dateError" class="mt-3 text-xs text-red-650 bg-red-50 border border-red-150 rounded-sm p-3 font-semibold mb-4">
                        {{ dateError }}
                    </div>
                    <div class="flex gap-3">
                        <button @click="showDateModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-xs font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer select-none">
                            Cancel
                        </button>
                        <button @click="submitDateChange" :disabled="dateLoading"
                                class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-xs font-bold disabled:opacity-50 transition shadow-sm cursor-pointer select-none">
                            {{ dateLoading ? 'Sending...' : 'Request Change' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

</template>
