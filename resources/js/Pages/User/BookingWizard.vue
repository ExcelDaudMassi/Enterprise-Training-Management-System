<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'
import axios from 'axios'

const props = defineProps({ auth: Object })

// ============================================================
// WIZARD GLOBAL STATE
// ============================================================
const currentStage = ref(1)

// Stage 1 (Excel Upload & Kapasitas)
const participants        = ref([
    { nama: '', nrp: '', jabatan: '', site: '', no_hp: '', gender: 'L' }
])
const isManualInput       = ref(true)
const uploadedFileName    = ref('Input Manual')
const isUploadingExcel    = ref(false)
const excelSuccessMessage = ref('')
const eligibleRooms        = ref([])
const isCombined          = ref(false)
const activeYear          = ref(null)
const totalOrang          = ref(0)
const isChecking          = ref(false)
const stage1Error         = ref('')

// Roster Panitia (array interaktif)
const panitiaList = ref([
    { nama: '', nrp: '', jabatan: '', site: '', no_hp: '', gender: 'L' }
])

function addPanitia() {
    panitiaList.value.push({ nama: '', nrp: '', jabatan: '', site: '', no_hp: '', gender: 'L' })
}

function removePanitia(index) {
    if (panitiaList.value.length > 1) {
        panitiaList.value.splice(index, 1)
    }
}

const panitiaCount = computed(() => panitiaList.value.length)

const panitiaValid = computed(() =>
    panitiaList.value.every(p => 
        p.nama.trim() !== '' && 
        p.nrp.trim() !== '' &&
        (p.no_hp.trim() === '' || /^[0-9+]+$/.test(p.no_hp.trim()))
    )
)

// Stage 2 (Kalender)
const blockedDates      = ref({})
const startDate         = ref(null)
const endDate           = ref(null)
const rangeError        = ref('')
const isLoadingCalendar = ref(false)

// Stage 3 (Room Cards)
const availableRooms      = ref([])
const selectedRoom        = ref(null)
const isLoadingRooms      = ref(false)
const stage3Error         = ref('')
const isAutoFetchingRooms = ref(false)
const autoFetchRoomsError = ref('')

// Stage 4 (Detail)
const formStage4 = ref({
    nama_training: '',
    nama_pic: '',
    layout_preferensi: 'classroom',
    layout_custom_file: null,
    hybrid: false,
    flipchart: false,
    catatan: ''
})
const uploadedCustomFileName = ref('')
const isSavingStage4 = ref(false)
const stage4Error = ref('')

const isStage4Valid = computed(() => {
    if (!formStage4.value.nama_training.trim() || !formStage4.value.nama_pic.trim()) return false
    if (formStage4.value.layout_preferensi === 'custom' && !formStage4.value.layout_custom_file) return false
    return true
})

// Stage 5 (Submit)
const isSubmitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)
const bookingId = ref(null)
const termsAccepted = ref(false)

// ============================================================
// STATE PERSISTENCE (LOCAL STORAGE)
// ============================================================
const stateToSave = computed(() => {
    return {
        currentStage: currentStage.value,
        participants: participants.value,
        isManualInput: isManualInput.value,
        uploadedFileName: uploadedFileName.value,
        excelSuccessMessage: excelSuccessMessage.value,
        eligibleRooms: eligibleRooms.value,
        isCombined: isCombined.value,
        activeYear: activeYear.value,
        totalOrang: totalOrang.value,
        panitiaList: panitiaList.value,
        startDate: startDate.value,
        endDate: endDate.value,
        availableRooms: availableRooms.value,
        selectedRoom: selectedRoom.value,
        uploadedCustomFileName: uploadedCustomFileName.value,
        formStage4: {
            nama_training: formStage4.value.nama_training,
            nama_pic: formStage4.value.nama_pic,
            layout_preferensi: formStage4.value.layout_preferensi,
            hybrid: formStage4.value.hybrid,
            flipchart: formStage4.value.flipchart,
            catatan: formStage4.value.catatan,
        }
    }
})

// Langkah A: Auto-save form ke localStorage setiap kali ada perubahan
watch(stateToSave, (newState) => {
    // Jangan simpan jika form telah berhasil diajukan
    if (submitSuccess.value) return
    
    if (typeof window !== 'undefined') {
        localStorage.setItem('booking_wizard_state', JSON.stringify(newState))
    }
}, { deep: true })

// Langkah B: Restore form dari localStorage saat pertama kali dimuat
onMounted(() => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('booking_wizard_state')
        if (saved) {
            try {
                const data = JSON.parse(saved)
                
                if (data.currentStage !== undefined) currentStage.value = data.currentStage
                if (data.participants !== undefined) participants.value = data.participants
                if (data.isManualInput !== undefined) isManualInput.value = data.isManualInput
                if (data.uploadedFileName !== undefined) uploadedFileName.value = data.uploadedFileName
                if (data.excelSuccessMessage !== undefined) excelSuccessMessage.value = data.excelSuccessMessage
                if (data.eligibleRooms !== undefined) eligibleRooms.value = data.eligibleRooms
                if (data.isCombined !== undefined) isCombined.value = data.isCombined
                if (data.activeYear !== undefined) activeYear.value = data.activeYear
                if (data.totalOrang !== undefined) totalOrang.value = data.totalOrang
                if (data.panitiaList !== undefined) panitiaList.value = data.panitiaList
                if (data.startDate !== undefined) startDate.value = data.startDate
                if (data.endDate !== undefined) endDate.value = data.endDate
                if (data.availableRooms !== undefined) availableRooms.value = data.availableRooms
                if (data.selectedRoom !== undefined) selectedRoom.value = data.selectedRoom
                if (data.uploadedCustomFileName !== undefined) uploadedCustomFileName.value = data.uploadedCustomFileName
                
                if (data.formStage4 !== undefined) {
                    formStage4.value.nama_training = data.formStage4.nama_training || ''
                    formStage4.value.nama_pic = data.formStage4.nama_pic || ''
                    formStage4.value.layout_preferensi = data.formStage4.layout_preferensi || 'classroom'
                    formStage4.value.hybrid = data.formStage4.hybrid || false
                    formStage4.value.flipchart = data.formStage4.flipchart || false
                    formStage4.value.catatan = data.formStage4.catatan || ''
                }
            } catch (e) {
                console.error('Gagal memulihkan state formulir:', e)
            }
        }
    }
    
    // Smart Defaults: Isi Nama PIC secara otomatis jika kosong
    if (!formStage4.value.nama_pic && props.auth?.user?.name) {
        formStage4.value.nama_pic = props.auth.user.name
    }
})

function clearSavedState() {
    if (typeof window !== 'undefined') {
        localStorage.removeItem('booking_wizard_state')
    }
}

// ============================================================
// COMPUTED
// ============================================================
const participantCount = computed(() => participants.value.length)

const totalOrangComputed = computed(() =>
    participantCount.value + panitiaCount.value
)

const participantsValid = computed(() =>
    participants.value.length > 0 &&
    participants.value.every(p => 
        p.nama.trim() !== '' && 
        p.nrp.trim() !== '' &&
        p.jabatan.trim() !== '' && 
        p.site.trim() !== '' && 
        p.no_hp.trim() !== '' && 
        /^[0-9+]+$/.test(p.no_hp.trim()) &&
        (p.gender === 'L' || p.gender === 'P')
    )
)

const isStage1Valid = computed(() =>
    participantCount.value > 0 &&
    participantsValid.value &&
    panitiaCount.value > 0 &&
    panitiaValid.value
)

const isRangeSelected = computed(() => startDate.value && endDate.value)

const isRoomSelected = computed(() => selectedRoom.value !== null)

// ============================================================
// EARLY CONFLICT DETECTION (Background Check)
// ============================================================
watch([startDate, endDate], async ([newStart, newEnd]) => {
    if (newStart && newEnd && validateRange()) {
        isAutoFetchingRooms.value = true
        autoFetchRoomsError.value = ''
        try {
            const res = await axios.post('/api/booking/get-available-rooms', {
                start_date: newStart,
                end_date: newEnd,
                total_orang: totalOrang.value,
            })
            if (res.data.success) {
                availableRooms.value = res.data.rooms
                // Clear selected room if it's no longer available
                if (selectedRoom.value) {
                    const stillAvailable = availableRooms.value.find(r => r.id === selectedRoom.value.id && r.is_available)
                    if (!stillAvailable) selectedRoom.value = null
                }
            }
        } catch (err) {
            autoFetchRoomsError.value = 'Gagal mengecek ruangan otomatis.'
        } finally {
            isAutoFetchingRooms.value = false
        }
    } else {
        availableRooms.value = []
        selectedRoom.value = null
    }
})

// ============================================================
// STAGE 1: EXCEL & MANUAL ACTIONS
// ============================================================
async function handleExcelUpload(event) {
    const file = event.target.files[0]
    if (!file) return

    // Batasi ketat hanya berkas .xlsx
    if (!file.name.endsWith('.xlsx')) {
        stage1Error.value = 'Pemberitahuan: Format berkas harus berupa Excel asli (.xlsx)!'
        return
    }

    isUploadingExcel.value = true
    stage1Error.value         = ''
    excelSuccessMessage.value = ''
    
    const formData = new FormData()
    formData.append('file', file)

    try {
        const res = await axios.post('/api/booking/validate-participants', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
        
        if (res.data.success) {
            participants.value        = res.data.peserta
            uploadedFileName.value    = file.name
            isManualInput.value       = false
            excelSuccessMessage.value = `✓ Berhasil memuat ${res.data.total_peserta} peserta dari ${file.name}`
        }
    } catch (err) {
        // Ambil error custom dari backend: error.response.data.error
        stage1Error.value = err.response?.data?.error || 'Gagal membaca atau memvalidasi file Excel.'
        // Reset state jika error
        resetExcel()
    } finally {
        isUploadingExcel.value = false
        // Reset input file agar bisa upload file yang sama kembali
        event.target.value = ''
    }
}

function startManualInput() {
    isManualInput.value    = true
    uploadedFileName.value = 'Input Manual'
    participants.value     = [
        { nama: '', nrp: '', jabatan: '', site: '', no_hp: '', gender: 'L' }
    ]
    stage1Error.value      = ''
}

function addParticipant() {
    participants.value.push({ nama: '', nrp: '', jabatan: '', site: '', no_hp: '', gender: 'L' })
}

function removeParticipant(index) {
    if (participants.value.length > 1) {
        participants.value.splice(index, 1)
    } else {
        resetExcel()
    }
}

function resetExcel() {
    participants.value        = [
        { nama: '', nrp: '', jabatan: '', site: '', no_hp: '', gender: 'L' }
    ]
    uploadedFileName.value    = 'Input Manual'
    excelSuccessMessage.value = ''
    stage1Error.value         = ''
    isManualInput.value       = true
}

async function checkEligibility() {
    if (!isStage1Valid.value) return
    stage1Error.value = ''
    isChecking.value  = true

    try {
        const res = await axios.post('/api/booking/check-eligibility', {
            jumlah_peserta: participantCount.value,
            jumlah_panitia: panitiaCount.value,
        })
        if (res.data.success) {
            eligibleRooms.value = res.data.eligible_rooms
            activeYear.value    = res.data.booking_window.tahun
            totalOrang.value    = res.data.total_orang
            isCombined.value    = eligibleRooms.value.length === 1 &&
                                  eligibleRooms.value[0].is_combined === true

            currentStage.value = 2
            await loadAvailability()
        }
    } catch (err) {
        stage1Error.value = err.response?.data?.message || 'Terjadi kesalahan. Silakan coba lagi.'
    } finally {
        isChecking.value = false
    }
}

// ============================================================
// STAGE 2 ACTIONS (Kalender)
// ============================================================
const MONTH_NAMES = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
const DAY_NAMES   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab']
const todayStr    = new Date().toISOString().split('T')[0]

function getMonthGrid(year, monthIndex) {
    const firstDay    = new Date(year, monthIndex, 1).getDay()
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate()
    const cells = []
    for (let i = 0; i < firstDay; i++) cells.push(null)
    for (let d = 1; d <= daysInMonth; d++) cells.push(d)
    return cells
}

function toDateStr(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

function getDateStatus(dateStr) {
    const val = blockedDates.value[dateStr]
    if (!val) return 'available'
    return typeof val === 'object' ? (val.status || 'available') : val
}

function isInRange(dateStr) {
    if (!startDate.value) return false
    if (!endDate.value)   return dateStr === startDate.value
    return dateStr >= startDate.value && dateStr <= endDate.value
}

function isStartOrEnd(dateStr) {
    return dateStr === startDate.value || dateStr === endDate.value
}

function isPastDate(dateStr) {
    return dateStr < todayStr
}

function dayClass(dateStr) {
    const status     = getDateStatus(dateStr)
    const isEndpoint = isStartOrEnd(dateStr)
    const inRange    = isInRange(dateStr)
    const past       = isPastDate(dateStr)

    if (past)       return 'bg-gray-50 text-gray-300 cursor-not-allowed'
    if (isEndpoint) return 'bg-blue-600 text-white rounded-full cursor-pointer'
    if (inRange)    return 'bg-blue-100 text-blue-800 cursor-pointer'
    if (status === 'full')    return 'bg-red-100 text-red-400 cursor-not-allowed opacity-70'
    if (status === 'partial') return 'bg-yellow-50 text-yellow-700 cursor-pointer hover:bg-yellow-100'
    return 'text-gray-700 cursor-pointer hover:bg-green-100'
}

function dotClass(dateStr) {
    const status = getDateStatus(dateStr)
    if (status === 'full')    return 'bg-red-400'
    if (status === 'partial') return 'bg-yellow-400'
    return ''
}

function getMonthOriginClass(index) {
    let classes = ''
    
    // Untuk grid-cols-4 (layar besar lg)
    const colLg = index % 4
    if (colLg === 0) classes += ' lg:origin-left'
    else if (colLg === 3) classes += ' lg:origin-right'
    else classes += ' lg:origin-center'
    
    // Untuk grid-cols-3 (layar sedang sm)
    const colSm = index % 3
    if (colSm === 0) classes += ' sm:origin-left'
    else if (colSm === 2) classes += ' sm:origin-right'
    else classes += ' sm:origin-center'

    // Untuk grid-cols-2 (layar kecil xs)
    const colXs = index % 2
    if (colXs === 0) classes += ' origin-left'
    else classes += ' origin-right'
    
    return classes
}

function getDayTooltip(dateStr) {
    const data = blockedDates.value[dateStr]
    if (!data || !data.occupied_rooms || data.occupied_rooms.length === 0) {
        return ''
    }
    return 'Terisi:\n' + data.occupied_rooms.map(r => `• ${r.nama_ruang} (${r.nama_training})`).join('\n')
}

async function loadAvailability() {
    isLoadingCalendar.value = true
    blockedDates.value      = {}
    startDate.value         = null
    endDate.value           = null
    rangeError.value        = ''

    const roomIds = isCombined.value
        ? eligibleRooms.value[0].room_ids
        : eligibleRooms.value.map(r => r.id)

    try {
        const res = await axios.post('/api/booking/get-availability', {
            year:              activeYear.value,
            eligible_room_ids: roomIds,
            is_combined:       isCombined.value,
        })
        if (res.data.success) {
            blockedDates.value = res.data.blocked_dates
        }
    } catch (err) {
        console.error('Gagal memuat kalender:', err)
    } finally {
        isLoadingCalendar.value = false
    }
}

function selectDate(year, month, day) {
    const dateStr = toDateStr(year, month, day)
    const status  = getDateStatus(dateStr)

    if (status === 'full' || isPastDate(dateStr)) return

    rangeError.value = ''

    if (!startDate.value || (startDate.value && endDate.value)) {
        startDate.value = dateStr
        endDate.value   = null
    } else {
        if (dateStr < startDate.value) {
            endDate.value   = startDate.value
            startDate.value = dateStr
        } else {
            endDate.value = dateStr
        }

        if (!validateRange()) {
            startDate.value = null
            endDate.value   = null
        }
    }
}

function validateRange() {
    if (!startDate.value || !endDate.value) return false
    let current = new Date(startDate.value)
    const end   = new Date(endDate.value)

    while (current <= end) {
        const dateStr = current.toISOString().split('T')[0]
        // blockedDates[dateStr] adalah object { status, occupied_rooms }, bukan string
        const entry = blockedDates.value[dateStr]
        if (entry?.status === 'full') {
            rangeError.value = 'Rentang tanggal yang Anda pilih mengandung hari yang sudah penuh. Silakan pilih rentang lain.'
            return false
        }
        current.setDate(current.getDate() + 1)
    }
    return true
}

async function proceedToStage3() {
    if (!isRangeSelected.value) return
    
    // Jika data ruangan sudah di-fetch otomatis di background, langsung gunakan
    if (availableRooms.value.length > 0 && !isAutoFetchingRooms.value) {
        currentStage.value = 3
        return
    }

    isLoadingRooms.value = true
    stage3Error.value    = ''

    try {
        const res = await axios.post('/api/booking/get-available-rooms', {
            start_date:  startDate.value,
            end_date:    endDate.value,
            total_orang: totalOrang.value,
        })
        if (res.data.success) {
            availableRooms.value = res.data.rooms
            currentStage.value   = 3
        }
    } catch (err) {
        stage3Error.value = err.response?.data?.message || 'Gagal memuat data ruangan.'
    } finally {
        isLoadingRooms.value = false
    }
}

// ============================================================
// STAGE 3 ACTIONS
// ============================================================
function selectRoom(room) {
    if (!room.is_available) return
    selectedRoom.value = room
}

function isRoomSelected_(room) {
    if (!selectedRoom.value) return false
    return selectedRoom.value.id === room.id
}

function proceedToStage4() {
    if (!isRoomSelected.value) return
    currentStage.value = 4
}

function handleCustomLayoutUpload(event) {
    const file = event.target.files[0]
    if (!file) return
    if (file.size > 2 * 1024 * 1024) {
        stage4Error.value = 'Ukuran file maksimal 2MB.'
        return
    }
    formStage4.value.layout_custom_file = file
    uploadedCustomFileName.value = file.name
    stage4Error.value = ''
}

async function proceedToStage5() {
    if (!isStage4Valid.value) return
    isSavingStage4.value = true
    stage4Error.value = ''

    const formData = new FormData()
    formData.append('nama_training', formStage4.value.nama_training)
    formData.append('nama_pic', formStage4.value.nama_pic)
    formData.append('layout_preferensi', formStage4.value.layout_preferensi)
    if (formStage4.value.layout_preferensi === 'custom' && formStage4.value.layout_custom_file) {
        formData.append('layout_custom_file', formStage4.value.layout_custom_file)
    }
    formData.append('hybrid', formStage4.value.hybrid ? 1 : 0)
    formData.append('flipchart', formStage4.value.flipchart ? 1 : 0)
    formData.append('catatan', formStage4.value.catatan)

    try {
        const res = await axios.post('/api/booking/save-stage4', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        if (res.data.success) {
            currentStage.value = 5
        }
    } catch (err) {
        stage4Error.value = err.response?.data?.message || 'Gagal menyimpan detail booking.'
    } finally {
        isSavingStage4.value = false
    }
}

async function submitFinal() {
    isSubmitting.value = true
    submitError.value = ''

    try {
        const res = await axios.post('/api/booking/submit', {
            ruangan_id: selectedRoom.value.id,
            tgl_mulai: startDate.value,
            tgl_selesai: endDate.value,
            peserta: participants.value,
            panitia: panitiaList.value
        })

        if (res.data.success) {
            submitSuccess.value = true
            bookingId.value = res.data.booking_id
            
            // Langkah C: Menghapus localStorage state karena booking berhasil diajukan
            clearSavedState()
        } else {
            submitError.value = res.data.message || 'Terjadi kesalahan tidak diketahui.'
        }
    } catch (err) {
        // Handle Laravel Validation Errors (422)
        if (err.response?.status === 422) {
            const errors = err.response.data?.errors
            if (errors) {
                const firstKey = Object.keys(errors)[0]
                submitError.value = errors[firstKey][0]
            } else {
                submitError.value = err.response.data?.message || 'Data tidak valid.'
            }
        } else if (err.response?.data?.message) {
            submitError.value = err.response.data.message
        } else {
            submitError.value = 'Koneksi bermasalah. Silakan coba lagi.'
        }
    } finally {
        isSubmitting.value = false
    }
}

// ============================================================
// NAVIGATION
// ============================================================
function backToStage1() {
    currentStage.value   = 1
    // Reset semua state downstream (stage 2, 3, 4)
    blockedDates.value   = {}
    startDate.value      = null
    endDate.value        = null
    rangeError.value     = ''
    availableRooms.value = []
    selectedRoom.value   = null
    stage3Error.value    = ''
    formStage4.value     = {
        nama_training: '',
        nama_pic: '',
        layout_preferensi: 'classroom',
        layout_custom_file: null,
        hybrid: false,
        flipchart: false,
        catatan: ''
    }
    uploadedCustomFileName.value = ''
    stage4Error.value            = ''
}

function backToStage2() {
    currentStage.value   = 2
    // Reset state stage 3 dan 4
    availableRooms.value = []
    selectedRoom.value   = null
    stage3Error.value    = ''
    formStage4.value     = {
        nama_training: '',
        nama_pic: '',
        layout_preferensi: 'classroom',
        layout_custom_file: null,
        hybrid: false,
        flipchart: false,
        catatan: ''
    }
    uploadedCustomFileName.value = ''
    stage4Error.value            = ''
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

const STAGE_LABELS = ['Kapasitas', 'Tanggal', 'Ruangan', 'Detail', 'Review']
</script>

<template>
    <UserLayout :auth="auth">
        <div class="w-full">

            <!-- ======================================================= -->
            <!-- Header & Progress Bar -->
            <!-- ======================================================= -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Booking Ruang Training</h1>
                <p class="text-gray-500 text-sm mt-1">Ikuti langkah-langkah untuk menyelesaikan pemesanan ruangan.</p>

                <div class="mt-4 flex items-center">
                    <template v-for="(label, idx) in STAGE_LABELS" :key="idx">
                        <div class="flex items-center gap-1.5">
                            <div
                                class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 transition-colors"
                                :class="currentStage > idx + 1
                                    ? 'bg-green-500 text-white'
                                    : currentStage === idx + 1
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-200 text-gray-400'"
                            >
                                <span v-if="currentStage > idx + 1">✓</span>
                                <span v-else>{{ idx + 1 }}</span>
                            </div>
                            <span
                                class="text-xs hidden sm:inline"
                                :class="currentStage === idx + 1
                                    ? 'text-blue-700 font-semibold'
                                    : currentStage > idx + 1
                                        ? 'text-green-600'
                                        : 'text-gray-400'"
                            >{{ label }}</span>
                        </div>
                        <div
                            v-if="idx < 4"
                            class="flex-1 h-0.5 mx-2 transition-colors"
                            :class="currentStage > idx + 1 ? 'bg-green-400' : 'bg-gray-200'"
                        ></div>
                    </template>
                </div>
            </div>
            <!-- ======================================================= -->
            <!-- STAGE 1: Upload Excel & Roster Panitia -->
            <!-- ======================================================= -->
            <div v-if="currentStage === 1" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6">
                <h2 class="text-base font-bold text-gray-800 mb-1">Tahap 1: Daftar Peserta & Panitia</h2>
                <p class="text-xs text-gray-500 mb-6 leading-relaxed">
                    Pilih metode pengisian daftar peserta (via Unggah Excel atau Ketik Manual), lalu lengkapi roster panitia penyelenggara.
                </p>

                <div class="space-y-6">

                    <!-- Top bar: status + total + aksi -->
                    <div class="flex flex-wrap items-center justify-between gap-4 bg-gray-50 border border-gray-150 rounded-2xl px-5 py-4">
                        
                        <!-- Left side: Metode Input indicator & Excel options -->
                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Status Indicator -->
                            <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs">
                                <span class="w-5 h-5 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-[10px] font-bold">✓</span>
                                <span class="text-xs font-bold text-gray-700">Metode Input:</span>
                                <span class="text-xs text-gray-500 font-semibold px-2 py-0.5 rounded bg-gray-50 border border-gray-150">{{ uploadedFileName }}</span>
                            </div>

                            <!-- Excel Import Option (Sleek button next to status if manual or show reset button if uploaded) -->
                            <div class="flex items-center gap-2">
                                <template v-if="uploadedFileName === 'Input Manual'">
                                    <input type="file" id="excel-upload-bar" class="hidden" accept=".xlsx"
                                        @change="handleExcelUpload" :disabled="isUploadingExcel" />
                                    <label for="excel-upload-bar"
                                        class="bg-emerald-650 hover:bg-emerald-705 text-white text-xs font-black py-2 px-3.5 rounded-xl cursor-pointer transition flex items-center gap-1.5 shadow-3xs select-none"
                                        :class="{ 'opacity-70 cursor-not-allowed': isUploadingExcel }">
                                        <span v-if="isUploadingExcel" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                        <span v-else>📂</span>
                                        <span>{{ isUploadingExcel ? 'Memproses Excel...' : 'Impor dari Excel' }}</span>
                                    </label>
                                    <a :href="`/user/booking/download-template?v=${Date.now()}`"
                                        class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 hover:text-gray-800 text-[10px] font-extrabold py-2 px-3 rounded-xl transition inline-flex items-center gap-1.5 shadow-3xs select-none">
                                        📥 Template Excel
                                    </a>
                                </template>
                                <template v-else>
                                    <button @click="resetExcel"
                                        class="bg-red-50 hover:bg-red-100 text-red-650 border border-red-200 hover:border-red-300 text-xs font-black py-2 px-3.5 rounded-xl transition flex items-center gap-1.5 shadow-3xs">
                                        🔄 Ganti Metode
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Right side: Count summary & Next CTA -->
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Ringkasan kapasitas -->
                            <div class="flex items-center gap-3 text-xs font-semibold text-gray-500">
                                <span>Peserta: <strong class="text-gray-800 font-extrabold">{{ participantCount }}</strong></span>
                                <span class="text-gray-300">•</span>
                                <span>Panitia: <strong class="text-gray-800 font-extrabold">{{ panitiaCount }}</strong></span>
                                <span class="text-gray-300">•</span>
                                <span class="font-extrabold text-blue-600 text-sm">Total: {{ totalOrangComputed }} Orang</span>
                            </div>

                            <!-- Error inline -->
                            <div v-if="stage1Error" class="text-xs text-red-650 bg-red-50 border border-red-200 rounded px-2.5 py-1">
                                ⚠️ {{ stage1Error }}
                            </div>

                            <!-- CTA -->
                            <button @click="checkEligibility" :disabled="!isStage1Valid || isChecking"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-black py-2.5 px-6 rounded-xl text-xs transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap shadow-3xs flex items-center gap-2">
                                <span v-if="isChecking" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                {{ isChecking ? 'Mengecek Kapasitas...' : 'Cari Ruangan →' }}
                            </button>
                        </div>
                    </div>

                    <!-- Tabel bertumpuk: Peserta (Atas) | Panitia (Bawah) -->
                    <div class="flex flex-col gap-6 w-full">

                        <!-- ── Tabel Roster Peserta (Editable & Interactive) ── -->
                        <div class="border border-gray-250 rounded-xl overflow-hidden flex flex-col bg-white shadow-3xs">
                            <div class="bg-blue-50/50 border-b border-blue-100 px-4 py-3 flex items-center justify-between shrink-0">
                                <span class="text-xs font-black text-blue-900 uppercase tracking-wider">📋 Roster Peserta</span>
                                <div class="flex items-center gap-3">
                                    <span class="bg-blue-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-3xs">
                                        {{ participantCount }} Orang
                                    </span>
                                    <button @click="addParticipant"
                                        class="bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black px-3 py-1 rounded-lg transition flex items-center gap-1 shadow-3xs">
                                        + Tambah Peserta
                                    </button>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto flex-1 max-h-[420px]">
                                <table class="w-full text-xs text-left border-collapse min-w-[650px]">
                                    <thead class="bg-gray-50 text-gray-500 font-extrabold uppercase tracking-wider sticky top-0 z-10 border-b border-gray-200/50">
                                        <tr>
                                            <th class="px-4 py-3 w-12 text-center">#</th>
                                            <th class="px-4 py-3">Nama Lengkap<span class="text-red-400">*</span></th>
                                            <th class="px-4 py-3">NRP<span class="text-red-400">*</span></th>
                                            <th class="px-4 py-3">Jabatan<span class="text-red-400">*</span></th>
                                            <th class="px-4 py-3">Site<span class="text-red-400">*</span></th>
                                            <th class="px-4 py-3">No. HP<span class="text-red-400">*</span></th>
                                            <th class="px-4 py-3 w-44 text-center">Gender<span class="text-red-400">*</span></th>
                                            <th class="px-4 py-3 w-12 text-center"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="(p, i) in participants" :key="i" class="hover:bg-blue-50/20 transition-colors">
                                            <td class="px-4 py-2.5 text-center text-gray-400 font-bold">{{ i + 1 }}</td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.nama" type="text" placeholder="Ketik nama lengkap..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.nama.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.nrp" type="text" placeholder="Ketik NRP atau 'N/A'..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.nrp.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.jabatan" type="text" placeholder="Jabatan..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.jabatan.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.site" type="text" placeholder="Site..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.site.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.no_hp" type="text" placeholder="Contoh: 0812..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.no_hp.trim() === '' || !/^[0-9+]+$/.test(p.no_hp) ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <select v-model="p.gender"
                                                    class="text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white min-w-[120px] select-gender appearance-none pr-8 relative bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.4c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E')] bg-[length:0.65em_auto] bg-[right_0.75rem_center] bg-no-repeat">
                                                    <option value="L">♂ Laki-laki</option>
                                                    <option value="P">♀ Perempuan</option>
                                                </select>
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <button @click="removeParticipant(i)"
                                                    class="text-red-400 hover:text-red-650 text-sm font-black transition"
                                                    title="Hapus baris">×</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Footer hint validasi peserta -->
                            <div class="bg-gray-50 border-t border-gray-200 px-4 py-2.5 flex items-center gap-2 shrink-0">
                                <span class="text-[10px] text-gray-500 font-semibold">Semua kolom bertanda asterisk (<span class="text-red-450 font-bold">*</span>) wajib diisi.</span>
                                <span v-if="!participantsValid" class="text-[10px] text-red-500 font-extrabold ml-auto flex items-center gap-1">⚠️ Ada kolom kosong</span>
                                <span v-else class="text-[10px] text-green-600 font-extrabold ml-auto flex items-center gap-1">✓ Peserta siap validasi</span>
                            </div>
                        </div>

                        <!-- ── Tabel Roster Panitia (manual input) ── -->
                        <div class="border border-gray-250 rounded-xl overflow-hidden flex flex-col bg-white shadow-3xs">
                            <div class="bg-amber-50/50 border-b border-amber-100 px-4 py-3 flex items-center justify-between shrink-0">
                                <span class="text-xs font-black text-amber-900 uppercase tracking-wider">👥 Roster Panitia</span>
                                <div class="flex items-center gap-3">
                                    <span class="bg-amber-500 text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-3xs">
                                        {{ panitiaCount }} Orang
                                    </span>
                                    <button @click="addPanitia"
                                        class="bg-amber-500 hover:bg-amber-600 text-white text-[10px] font-black px-3 py-1 rounded-lg transition flex items-center gap-1 shadow-3xs">
                                        + Tambah Panitia
                                    </button>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto flex-1 max-h-[420px]">
                                <table class="w-full text-xs text-left border-collapse min-w-[650px]">
                                    <thead class="bg-gray-50 text-gray-500 font-extrabold uppercase tracking-wider sticky top-0 z-10 border-b border-gray-200/50">
                                        <tr>
                                            <th class="px-4 py-3 w-12 text-center">#</th>
                                            <th class="px-4 py-3">Nama Lengkap<span class="text-red-400">*</span></th>
                                            <th class="px-4 py-3">NRP<span class="text-red-400">*</span></th>
                                            <th class="px-4 py-3">Jabatan</th>
                                            <th class="px-4 py-3">Site</th>
                                            <th class="px-4 py-3">No. HP</th>
                                            <th class="px-4 py-3 w-44 text-center">Gender</th>
                                            <th class="px-4 py-3 w-12 text-center"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="(p, i) in panitiaList" :key="i" class="hover:bg-amber-50/20 transition-colors">
                                            <td class="px-4 py-2.5 text-center text-gray-400 font-bold">{{ i + 1 }}</td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.nama" type="text" placeholder="Nama lengkap..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.nama.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.nrp" type="text" placeholder="Ketik NRP atau 'N/A'..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.nrp.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.jabatan" type="text" placeholder="Jabatan..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.site" type="text" placeholder="Site..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.no_hp" type="text" placeholder="Contoh: 0812..."
                                                    class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.no_hp.trim() !== '' && !/^[0-9+]+$/.test(p.no_hp) ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <select v-model="p.gender"
                                                    class="text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 bg-white min-w-[120px] select-gender appearance-none pr-8 relative bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.4c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E')] bg-[length:0.65em_auto] bg-[right_0.75rem_center] bg-no-repeat">
                                                    <option value="L">♂ Laki-laki</option>
                                                    <option value="P">♀ Perempuan</option>
                                                </select>
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <button @click="removePanitia(i)" :disabled="panitiaList.length === 1"
                                                    class="text-red-400 hover:text-red-655 disabled:opacity-30 disabled:cursor-not-allowed text-sm font-black transition"
                                                    title="Hapus baris">×</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Footer hint validasi panitia -->
                            <div class="bg-gray-50 border-t border-gray-200 px-4 py-2.5 flex items-center gap-2 shrink-0">
                                <span class="text-[10px] text-gray-500 font-semibold">Kolom <strong>Nama*</strong> dan <strong>NRP*</strong> wajib diisi semua baris (NRP boleh diisi "N/A").</span>
                                <span v-if="!panitiaValid" class="text-[10px] text-red-500 font-extrabold ml-auto">⚠️ Ada nama atau NRP yang belum diisi</span>
                                <span v-else class="text-[10px] text-green-600 font-extrabold ml-auto">✓ Roster panitia valid</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- STAGE 2: Kalender Ketersediaan -->
                    <div v-if="currentStage === 2" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-6">
                <div>
                    <h2 class="text-base font-bold text-gray-800 mb-1">Tahap 2: Kalender Ketersediaan</h2>
                    <p class="text-xs text-gray-500 mb-4">Pilih rentang tanggal mulai dan selesai pada kalender di bawah untuk melihat ketersediaan ruangan.</p>
                </div>

                <!-- Top bar Stage 2: status + info + aksi -->
                <div class="flex flex-wrap items-center justify-between gap-4 bg-gray-50 border border-gray-150 rounded-2xl px-5 py-4">
                    <!-- Left side: Capacity indicator & Date Selection status -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs">
                            <span class="w-5 h-5 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-[10px] font-bold">👤</span>
                            <span class="text-xs font-bold text-gray-700">Kapasitas:</span>
                            <span class="text-xs text-blue-600 font-extrabold">{{ totalOrang }} Orang</span>
                            <span class="text-gray-300">|</span>
                            <span class="text-xs text-gray-500 font-semibold">{{ eligibleRooms.length }} Opsi Ruangan</span>
                        </div>

                        <!-- Date selection status -->
                        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs">
                            <span class="w-2.5 h-2.5 rounded-full" :class="isRangeSelected ? 'bg-green-500' : 'bg-yellow-500 animate-pulse'"></span>
                            <span class="text-xs text-gray-700 font-semibold">
                                <template v-if="!startDate">Pilih tanggal mulai di kalender</template>
                                <template v-else-if="!endDate">Mulai: <strong class="text-blue-600">{{ formatDate(startDate) }}</strong> → Pilih tanggal selesai</template>
                                <template v-else>Rentang: <strong class="text-green-600">{{ formatDate(startDate) }}</strong> s/d <strong class="text-green-600">{{ formatDate(endDate) }}</strong></template>
                            </span>
                        </div>

                        <!-- Early Conflict Detection (Auto-fetch status) -->
                        <div v-if="isRangeSelected" class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs transition-all">
                            <span v-if="isAutoFetchingRooms" class="w-3 h-3 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></span>
                            <span v-else-if="availableRooms.length > 0 && availableRooms.some(r => r.is_available)" class="text-[10px] text-green-600 font-black">✅ {{ availableRooms.filter(r => r.is_available).length }} Ruangan Siap</span>
                            <span v-else-if="availableRooms.length > 0" class="text-[10px] text-red-500 font-black">❌ Semua Penuh</span>
                            <span v-else class="text-[10px] text-gray-400 font-semibold">Mengecek ketersediaan...</span>
                        </div>
                    </div>

                    <!-- Right side: Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Legend -->
                        <div class="hidden md:flex items-center gap-2.5 text-[10px] text-gray-400 mr-2 font-medium">
                            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> Tersedia</div>
                            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span> Parsial</div>
                            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> Penuh</div>
                        </div>

                        <div v-if="rangeError" class="text-xs text-red-650 bg-red-50 border border-red-200 rounded px-2.5 py-1">
                            ⚠️ {{ rangeError }}
                        </div>
                        <div v-if="stage3Error" class="text-xs text-red-650 bg-red-50 border border-red-200 rounded px-2.5 py-1">
                            ⚠️ {{ stage3Error }}
                        </div>

                        <button @click="backToStage1"
                            class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 hover:text-gray-800 text-xs font-black py-2.5 px-4 rounded-xl transition shadow-3xs">
                            ← Kembali
                        </button>
                        <button @click="proceedToStage3" :disabled="!isRangeSelected || isLoadingRooms"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-black py-2.5 px-6 rounded-xl text-xs transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap shadow-3xs flex items-center gap-2">
                            <span v-if="isLoadingRooms" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            {{ isLoadingRooms ? 'Memproses Opsi...' : 'Pilih Ruangan →' }}
                        </button>
                    </div>
                </div>

                <div v-if="isLoadingCalendar" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-10 text-center text-gray-400 text-sm">
                    Memuat data ketersediaan kalender...
                </div>

                <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div v-for="(monthName, monthIdx) in MONTH_NAMES" :key="monthIdx"
                            class="group/month relative border border-gray-100 rounded-lg p-2 bg-white transition-all duration-300 ease-in-out hover:scale-130 hover:shadow-2xl hover:z-[60] hover:border-blue-200"
                            :class="getMonthOriginClass(monthIdx)">
                            <div class="text-xs font-bold text-center text-gray-700 mb-1 pb-1 border-b border-gray-100">
                                {{ monthName }} {{ activeYear }}
                            </div>
                            <div class="grid grid-cols-7 text-center mb-1">
                                <span v-for="d in DAY_NAMES" :key="d" class="text-[9px] text-gray-400 font-medium">{{ d }}</span>
                            </div>
                            <div class="grid grid-cols-7 gap-px">
                                <template v-for="(day, cellIdx) in getMonthGrid(activeYear, monthIdx)" :key="cellIdx">
                                    <div v-if="day === null" class="h-6"></div>
                                    <div v-else
                                        class="h-6 w-6 flex flex-col items-center justify-center text-[10px] rounded transition select-none relative"
                                        :class="dayClass(toDateStr(activeYear, monthIdx, day))"
                                        @click="selectDate(activeYear, monthIdx, day)"
                                        :title="getDayTooltip(toDateStr(activeYear, monthIdx, day))">
                                        {{ day }}
                                        <span v-if="dotClass(toDateStr(activeYear, monthIdx, day)) && !isInRange(toDateStr(activeYear, monthIdx, day))"
                                            class="absolute bottom-0.5 w-1 h-1 rounded-full"
                                            :class="dotClass(toDateStr(activeYear, monthIdx, day))"></span>
                                        <span v-if="toDateStr(activeYear, monthIdx, day) === todayStr"
                                            class="absolute top-0 right-0 w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- STAGE 3: Pilih Ruangan -->
            <!-- ======================================================= -->
            <div v-if="currentStage === 3" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-6">
                <div>
                    <h2 class="text-base font-bold text-gray-800 mb-1">Tahap 3: Opsi Ruangan</h2>
                    <p class="text-xs text-gray-500 mb-4">Pilih salah satu opsi ruangan yang tersedia untuk kapasitas peserta dan rentang tanggal yang telah Anda tentukan.</p>
                </div>

                <!-- Top bar Stage 3: status + info + aksi -->
                <div class="flex flex-wrap items-center justify-between gap-4 bg-gray-50 border border-gray-150 rounded-2xl px-5 py-4">
                    <!-- Left side: Range & Selected Room indicator -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs">
                            <span class="w-5 h-5 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-[10px] font-bold">📅</span>
                            <span class="text-xs font-bold text-gray-700">Tanggal:</span>
                            <span class="text-xs text-gray-600 font-semibold">{{ formatDate(startDate) }} s/d {{ formatDate(endDate) }}</span>
                        </div>

                        <!-- Room choice status -->
                        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs">
                            <span class="w-2.5 h-2.5 rounded-full" :class="isRoomSelected ? 'bg-green-500' : 'bg-yellow-500 animate-pulse'"></span>
                            <span class="text-xs font-bold text-gray-755">Ruangan:</span>
                            <span class="text-xs font-semibold" :class="isRoomSelected ? 'text-green-600 font-bold' : 'text-gray-400'">
                                {{ isRoomSelected ? selectedRoom.nama_ruang : 'Pilih salah satu ruangan di bawah' }}
                            </span>
                        </div>
                    </div>

                    <!-- Right side: Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Legend -->
                        <div class="hidden md:flex items-center gap-3 text-[10px] text-gray-400 mr-2 font-medium">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded border-2 border-blue-600 inline-block"></span>
                                <span>Tersedia & Terpilih</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded bg-gray-200 inline-block"></span>
                                <span>Tidak Tersedia</span>
                            </div>
                        </div>

                        <button @click="backToStage2"
                            class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 hover:text-gray-800 text-xs font-black py-2.5 px-4 rounded-xl transition shadow-3xs">
                            ← Kembali
                        </button>
                        <button @click="proceedToStage4" :disabled="!isRoomSelected"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-black py-2.5 px-6 rounded-xl text-xs transition disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap shadow-3xs">
                            Isi Detail Booking →
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="room in availableRooms"
                        :key="room.id"
                        class="bg-white rounded-2xl border-2 transition-all relative overflow-hidden shadow-3xs"
                        :class="[
                            !room.is_available
                                ? 'border-gray-200 opacity-60 cursor-not-allowed'
                                : isRoomSelected_(room)
                                    ? 'border-blue-600 ring-4 ring-blue-50 cursor-pointer shadow-md'
                                    : 'border-gray-100 hover:border-blue-300 hover:shadow-sm cursor-pointer'
                        ]"
                        @click="selectRoom(room)"
                    >
                        <div v-if="isRoomSelected_(room)"
                            class="absolute top-0 right-0 bg-blue-600 text-white text-[10px] font-black px-3 py-1 rounded-bl-xl shadow-3xs">
                            TERPILIH
                        </div>
                        <div v-if="!room.is_available"
                            class="absolute top-0 right-0 bg-red-500 text-white text-[10px] font-black px-3 py-1 rounded-bl-xl shadow-3xs">
                            TIDAK TERSEDIA
                        </div>

                        <div class="p-5">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                                    :class="room.is_combined ? 'bg-purple-100' : 'bg-blue-50'">
                                    <span class="text-lg">{{ room.is_combined ? '🔗' : '🏫' }}</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800 text-sm leading-tight">{{ room.nama_ruang }}</h3>
                                    <p class="text-[11px] text-gray-400 mt-0.5">{{ room.lokasi_gedung }}</p>
                                </div>
                            </div>

                            <p v-if="room.deskripsi" class="text-xs text-gray-500 mb-3 italic">{{ room.deskripsi }}</p>

                            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                <div>
                                    <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Kapasitas Maks</span>
                                    <p class="text-lg font-black text-gray-800 leading-none mt-1">{{ room.kapasitas_max }} <span class="text-xs font-normal text-gray-500">orang</span></p>
                                </div>

                                <div v-if="room.is_available">
                                    <span class="inline-flex items-center gap-1 text-green-700 text-xs font-black bg-green-50 px-2.5 py-1 rounded-lg shadow-3xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                        Tersedia
                                    </span>
                                </div>
                                <div v-else>
                                    <span class="inline-flex items-center gap-1 text-red-500 text-xs font-black bg-red-50 px-2.5 py-1 rounded-lg shadow-3xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block"></span>
                                        Penuh
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <p v-if="isRoomSelected_(room)" class="text-[11px] text-green-700 bg-green-50 border border-green-200 rounded-lg p-2 font-black text-center shadow-3xs">
                                    ✅ Ruangan ini siap dipesan untuk {{ totalOrang }} orang!
                                </p>
                                <p v-else-if="!room.is_available" class="text-[10px] text-red-500 font-semibold text-center bg-red-50 border border-red-100 rounded-lg py-1.5">
                                    Tidak tersedia pada rentang tanggal ini.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="availableRooms.length > 0 && availableRooms.every(r => !r.is_available)"
                    class="bg-orange-50 border border-orange-200 text-orange-700 text-sm rounded-2xl px-4 py-3">
                    ⚠️ Semua ruangan tidak tersedia pada rentang tanggal yang dipilih. Silakan kembali dan pilih tanggal lain.
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- STAGE 4: Detail Booking -->
            <!-- ======================================================= -->
            <div v-if="currentStage === 4" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-6">
                <div>
                    <h2 class="text-base font-bold text-gray-800 mb-1">Tahap 4: Detail Informasi Acara</h2>
                    <p class="text-xs text-gray-500 mb-4">Lengkapi informasi utama acara seperti nama training/acara, nama PIC divisi, kebutuhan layout ruangan, serta logistik tambahan.</p>
                </div>

                <!-- Top bar Stage 4: status + info + aksi -->
                <div class="flex flex-wrap items-center justify-between gap-4 bg-gray-50 border border-gray-150 rounded-2xl px-5 py-4 mb-4">
                    <!-- Left side: Summary of selection -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs">
                            <span class="w-5 h-5 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-[10px] font-bold">🏫</span>
                            <span class="text-xs font-bold text-gray-700">Ruangan Terpilih:</span>
                            <span class="text-xs text-blue-600 font-extrabold">{{ selectedRoom?.nama_ruang }}</span>
                        </div>
                        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs">
                            <span class="text-xs font-bold text-gray-700">Form Detail:</span>
                            <span class="w-2 h-2 rounded-full" :class="isStage4Valid ? 'bg-green-500' : 'bg-yellow-500 animate-pulse'"></span>
                            <span class="text-xs font-semibold" :class="isStage4Valid ? 'text-green-600 font-bold' : 'text-gray-400'">
                                {{ isStage4Valid ? 'Siap dilanjutkan' : 'Lengkapi Nama Acara & PIC' }}
                            </span>
                        </div>
                    </div>

                    <!-- Right side: Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div v-if="stage4Error" class="text-xs text-red-655 bg-red-50 border border-red-200 rounded px-2.5 py-1">
                            ⚠️ {{ stage4Error }}
                        </div>
                        <button @click="currentStage = 3" :disabled="isSavingStage4"
                            class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 hover:text-gray-800 text-xs font-black py-2.5 px-4 rounded-xl transition shadow-3xs">
                            ← Kembali
                        </button>
                        <button @click="proceedToStage5" :disabled="!isStage4Valid || isSavingStage4"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-black py-2.5 px-6 rounded-xl text-xs transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap shadow-3xs flex items-center gap-2">
                            <span v-if="isSavingStage4" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            {{ isSavingStage4 ? 'Menyimpan Detail...' : 'Lanjut ke Review →' }}
                        </button>
                    </div>
                </div>

                <!-- Smart Defaults / Assistant Banner -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-4 flex items-center gap-4 shadow-sm mb-6 transition-all hover:shadow-md">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-3xs border border-blue-100 flex-shrink-0 text-xl">🤖</div>
                    <div>
                        <p class="text-xs font-black text-blue-900 mb-0.5">Halo, {{ auth?.user?.name || 'User' }}!</p>
                        <p class="text-[11px] text-blue-700 leading-tight">Asisten pintar kami telah mengisi profil Anda sebagai penanggung jawab acara. Anda tetap dapat mengeditnya jika mewakilkan pihak lain.</p>
                    </div>
                </div>

                <div>
                    <h2 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider text-blue-900/80">Informasi Utama</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-750 mb-1">Nama Training / Acara <span class="text-red-500">*</span></label>
                            <input v-model="formStage4.nama_training" type="text" placeholder="Masukkan nama acara..."
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-1 focus:ring-blue-100 focus:border-blue-500 bg-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-750 mb-1">Nama PIC Divisi <span class="text-red-500">*</span></label>
                            <input v-model="formStage4.nama_pic" type="text" placeholder="Masukkan nama PIC..."
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-1 focus:ring-blue-100 focus:border-blue-500 bg-white" />
                        </div>
                    </div>
                </div>

                <hr class="border-gray-100" />

                <div>
                    <h2 class="text-base font-bold text-gray-800 mb-1">Pemilihan Layout Ruangan</h2>
                    <p class="text-xs text-gray-500 mb-4">Pilih pengaturan meja dan kursi yang paling sesuai.</p>
                    
                    <div class="flex flex-wrap gap-4">
                        <label v-for="layout in ['classroom', 'u-shape', 'i-shape', 'o-shape', 'custom']" :key="layout" 
                            class="relative flex flex-col items-center justify-center p-4 border-2 rounded-xl cursor-pointer transition w-32 shadow-3xs"
                            :class="formStage4.layout_preferensi === layout ? 'border-blue-500 bg-blue-50/50' : 'border-gray-150 hover:border-gray-300 bg-white'">
                            
                            <input type="radio" v-model="formStage4.layout_preferensi" :value="layout" class="hidden" />
                            
                            <span class="text-2xl mb-2">
                                {{ layout === 'classroom' ? '🏫' : layout === 'u-shape' ? '🧲' : layout === 'i-shape' ? '➖' : layout === 'o-shape' ? '⭕' : '✏️' }}
                            </span>
                            <span class="text-[10px] font-black text-gray-700 uppercase tracking-wider">{{ layout }}</span>
                            
                            <div v-if="formStage4.layout_preferensi === layout" class="absolute top-2 right-2 w-4 h-4 bg-blue-500 rounded-full flex items-center justify-center text-white text-[10px] font-black">✓</div>
                        </label>
                    </div>

                    <div v-if="formStage4.layout_preferensi === 'custom'" class="mt-4 bg-gray-50 border border-gray-200 p-4 rounded-xl">
                        <label class="block text-xs font-bold text-gray-755 mb-2">Unggah Denah/Sketsa Kustom (Maks 2MB) <span class="text-red-500">*</span></label>
                        <input type="file" @change="handleCustomLayoutUpload" accept=".jpg,.png,.pdf" class="text-xs text-gray-600 bg-white border border-gray-200 rounded-lg p-2 file:bg-gray-100 file:border-none file:px-3 file:py-1 file:rounded-md file:mr-3" />
                        <p v-if="uploadedCustomFileName" class="text-xs text-green-600 font-semibold mt-2">✓ Berkas terpilih: {{ uploadedCustomFileName }}</p>
                    </div>
                </div>

                <hr class="border-gray-100" />

                <div>
                    <h2 class="text-base font-bold text-gray-800 mb-1">Kebutuhan Tambahan & Logistik</h2>
                    <div class="flex gap-6 mt-3">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="formStage4.hybrid" class="rounded text-blue-600 focus:ring-blue-100 focus:ring-offset-0 border-gray-200" />
                            <span class="text-xs text-gray-700 font-semibold">📹 Hybrid (Kamera & Mic)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="formStage4.flipchart" class="rounded text-blue-600 focus:ring-blue-100 focus:ring-offset-0 border-gray-200" />
                            <span class="text-xs text-gray-700 font-semibold">📝 Flipchart (Papan Tulis)</span>
                        </label>
                    </div>
                    <div class="mt-4">
                        <label class="block text-xs font-bold text-gray-750 mb-1">Catatan Tambahan (Opsional)</label>
                        <textarea v-model="formStage4.catatan" rows="3" placeholder="Pesan khusus untuk Admin..."
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-1 focus:ring-blue-100 focus:border-blue-500 bg-white"></textarea>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- STAGE 5: Review & Submit -->
            <!-- ======================================================= -->
            <div v-if="currentStage === 5 && !submitSuccess" class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 space-y-6">
                <div>
                    <h2 class="text-base font-bold text-gray-800 mb-1">Tahap 5: Review & Finalisasi</h2>
                    <p class="text-xs text-gray-500 mb-4">Tinjau kembali seluruh data pemesanan ruangan Anda sebelum diajukan ke admin untuk proses persetujuan.</p>
                </div>

                <!-- Top bar Stage 5: status + info + aksi -->
                <div class="flex flex-wrap items-center justify-between gap-4 bg-gray-50 border border-gray-150 rounded-2xl px-5 py-4 mb-4">
                    <!-- Left side: Review status -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-3xs">
                            <span class="w-5 h-5 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-[10px] font-bold">⭐</span>
                            <span class="text-xs font-bold text-gray-700">Review Akhir</span>
                        </div>
                        <div class="text-xs text-gray-500 font-semibold">Periksa kembali detail pemesanan di bawah</div>
                    </div>

                    <!-- Right side: Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div v-if="submitError" class="text-xs text-red-655 bg-red-50 border border-red-200 rounded px-2.5 py-1">
                            ⚠️ {{ submitError }}
                        </div>
                        <button @click="currentStage = 4" :disabled="isSubmitting"
                            class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 hover:text-gray-800 text-xs font-black py-2.5 px-4 rounded-xl transition shadow-3xs">
                            ← Kembali Edit
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-6">
                    <!-- Row 1: Acara & Ruangan -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Card: Informasi Acara -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-blue-50 rounded-bl-full -mr-4 -mt-4"></div>
                            <div class="flex items-center gap-2.5 mb-4">
                                <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm font-black border border-blue-100 shadow-3xs">📝</span>
                                <h3 class="text-sm font-black text-gray-800 uppercase tracking-wider">Informasi Acara</h3>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Nama Training / Acara</p>
                                    <p class="text-base font-black text-gray-900 leading-tight">{{ formStage4.nama_training || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">PIC Divisi</p>
                                    <p class="text-sm font-bold text-gray-800">{{ formStage4.nama_pic || '-' }}</p>
                                </div>
                                <div v-if="formStage4.catatan">
                                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Catatan Tambahan</p>
                                    <p class="text-xs text-gray-700 italic bg-gray-50 border border-gray-100 p-2.5 rounded-lg">{{ formStage4.catatan }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Card: Jadwal & Ruangan -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-emerald-50 rounded-bl-full -mr-4 -mt-4"></div>
                            <div class="flex items-center gap-2.5 mb-4">
                                <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-black border border-emerald-100 shadow-3xs">🏫</span>
                                <h3 class="text-sm font-black text-gray-800 uppercase tracking-wider">Jadwal & Ruangan</h3>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Ruangan Terpilih</p>
                                    <div class="flex items-start gap-3">
                                        <!-- Thumbnail Ruangan Placeholder -->
                                        <div class="w-12 h-12 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center flex-shrink-0">
                                            <span class="text-xl opacity-50">🖼️</span>
                                        </div>
                                        <div>
                                            <p class="text-base font-black text-gray-900 leading-tight">{{ selectedRoom?.nama_ruang || '-' }}</p>
                                            <p class="text-[11px] text-gray-500 mt-0.5">{{ selectedRoom?.lokasi_gedung || '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Tanggal Pelaksanaan</p>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-gray-900 bg-gray-50 px-2.5 py-1 rounded border border-gray-100">{{ formatDate(startDate) }}</span>
                                        <span class="text-xs text-gray-400 font-bold">s/d</span>
                                        <span class="text-sm font-bold text-gray-900 bg-gray-50 px-2.5 py-1 rounded border border-gray-100">{{ formatDate(endDate) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Layout & Kapasitas -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-amber-50 rounded-bl-full -mr-4 -mt-4"></div>
                        <div class="flex items-center gap-2.5 mb-5">
                            <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm font-black border border-amber-100 shadow-3xs">⚙️</span>
                            <h3 class="text-sm font-black text-gray-800 uppercase tracking-wider">Kapasitas & Setup Ruangan</h3>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Kolom 1: Total Peserta -->
                            <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Total Orang</p>
                                <p class="text-base font-black text-blue-600">{{ totalOrang }} Orang</p>
                                <p class="text-[10px] text-gray-500 mt-1">{{ participantCount }} Peserta, {{ panitiaCount }} Panitia</p>
                            </div>
                            
                            <!-- Kolom 2: Layout -->
                            <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Layout Preferensi</p>
                                <p class="text-base font-black text-gray-900 capitalize">{{ formStage4.layout_preferensi }}</p>
                                <p v-if="formStage4.layout_preferensi === 'custom' && uploadedCustomFileName" class="text-[10px] text-blue-600 font-bold mt-1 flex items-center gap-1 truncate" :title="uploadedCustomFileName">
                                    <span class="flex-shrink-0">📎</span> <span class="truncate">{{ uploadedCustomFileName }}</span>
                                </p>
                            </div>
                            
                            <!-- Kolom 3: Hybrid -->
                            <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Opsi Hybrid</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="w-5 h-5 rounded flex items-center justify-center text-[10px]" :class="formStage4.hybrid ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500'">
                                        {{ formStage4.hybrid ? '✓' : '✗' }}
                                    </span>
                                    <p class="text-sm font-bold" :class="formStage4.hybrid ? 'text-gray-900' : 'text-gray-500'">
                                        {{ formStage4.hybrid ? 'Kamera & Mic' : 'Tidak Perlu' }}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Kolom 4: Flipchart -->
                            <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Opsi Flipchart</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="w-5 h-5 rounded flex items-center justify-center text-[10px]" :class="formStage4.flipchart ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500'">
                                        {{ formStage4.flipchart ? '✓' : '✗' }}
                                    </span>
                                    <p class="text-sm font-bold" :class="formStage4.flipchart ? 'text-gray-900' : 'text-gray-500'">
                                        {{ formStage4.flipchart ? 'Papan Tulis' : 'Tidak Perlu' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Roster List -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Roster Peserta -->
                        <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col bg-white">
                            <div class="bg-gray-50 border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                                <span class="text-xs font-black text-gray-800 uppercase tracking-wider flex items-center gap-2">
                                    <span>📋</span> Daftar Peserta
                                </span>
                                <span class="bg-white border border-gray-200 text-gray-700 text-[10px] font-black px-2 py-0.5 rounded-full shadow-3xs">{{ participantCount }}</span>
                            </div>
                            <!-- Menggunakan class khusus custom-scrollbar yang akan didefinisikan di style -->
                            <div class="overflow-y-auto max-h-56 p-0 custom-scrollbar">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-white sticky top-0 border-b border-gray-100 shadow-3xs z-10">
                                        <tr>
                                            <th class="px-4 py-2 font-bold text-gray-500 w-10 uppercase tracking-wider text-[10px]">#</th>
                                            <th class="px-4 py-2 font-bold text-gray-500 uppercase tracking-wider text-[10px]">Nama Lengkap</th>
                                            <th class="px-4 py-2 font-bold text-gray-500 uppercase tracking-wider text-[10px]">NRP</th>
                                            <th class="px-4 py-2 font-bold text-gray-500 uppercase tracking-wider text-[10px]">Site</th>
                                            <th class="px-4 py-2 font-bold text-gray-500 uppercase tracking-wider text-[10px]">No. HP</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="(p, i) in participants" :key="i" class="hover:bg-blue-50/30 transition-colors">
                                            <td class="px-4 py-2.5 text-gray-400 font-bold">{{ i + 1 }}</td>
                                            <td class="px-4 py-2.5 font-bold text-gray-800">{{ p.nama }} <span class="text-[10px] font-medium text-gray-400 block mt-0.5">{{ p.jabatan }}</span></td>
                                            <td class="px-4 py-2.5 font-mono text-gray-700">{{ p.nrp || 'N/A' }}</td>
                                            <td class="px-4 py-2.5 text-gray-500">{{ p.site }}</td>
                                            <td class="px-4 py-2.5 text-gray-500">{{ p.no_hp || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Roster Panitia -->
                        <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col bg-white">
                            <div class="bg-gray-50 border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                                <span class="text-xs font-black text-gray-800 uppercase tracking-wider flex items-center gap-2">
                                    <span>👥</span> Daftar Panitia
                                </span>
                                <span class="bg-white border border-gray-200 text-gray-700 text-[10px] font-black px-2 py-0.5 rounded-full shadow-3xs">{{ panitiaCount }}</span>
                            </div>
                            <div class="overflow-y-auto max-h-56 p-0 custom-scrollbar">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-white sticky top-0 border-b border-gray-100 shadow-3xs z-10">
                                        <tr>
                                            <th class="px-4 py-2 font-bold text-gray-500 w-10 uppercase tracking-wider text-[10px]">#</th>
                                            <th class="px-4 py-2 font-bold text-gray-500 uppercase tracking-wider text-[10px]">Nama Lengkap</th>
                                            <th class="px-4 py-2 font-bold text-gray-500 uppercase tracking-wider text-[10px]">NRP</th>
                                            <th class="px-4 py-2 font-bold text-gray-500 uppercase tracking-wider text-[10px]">Site</th>
                                            <th class="px-4 py-2 font-bold text-gray-500 uppercase tracking-wider text-[10px]">No. HP</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="(p, i) in panitiaList" :key="i" class="hover:bg-amber-50/30 transition-colors">
                                            <td class="px-4 py-2.5 text-gray-400 font-bold">{{ i + 1 }}</td>
                                            <td class="px-4 py-2.5 font-bold text-gray-800">{{ p.nama }} <span class="text-[10px] font-medium text-gray-400 block mt-0.5">{{ p.jabatan }}</span></td>
                                            <td class="px-4 py-2.5 font-mono text-gray-700">{{ p.nrp || 'N/A' }}</td>
                                            <td class="px-4 py-2.5 text-gray-500">{{ p.site || '-' }}</td>
                                            <td class="px-4 py-2.5 text-gray-500">{{ p.no_hp || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Konfirmasi & Submit Box -->
                    <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-sm mt-2">
                        <div class="flex-1">
                            <label class="flex items-start gap-3 select-none group" :class="isSubmitting ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'">
                                <div class="relative flex items-center mt-0.5">
                                    <input type="checkbox" v-model="termsAccepted" :disabled="isSubmitting" class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 peer" :class="isSubmitting ? 'cursor-not-allowed' : 'cursor-pointer'" />
                                </div>
                                <div>
                                    <span class="text-sm font-bold text-gray-800 group-hover:text-blue-700 transition-colors block">
                                        Saya memastikan data pemesanan sudah benar
                                    </span>
                                    <span class="text-xs text-gray-500 block mt-0.5">
                                        Dengan mencentang kotak ini, saya menyetujui syarat penggunaan ruangan dan bertanggung jawab atas kelengkapan data.
                                    </span>
                                </div>
                            </label>
                        </div>
                        <div class="w-full md:w-auto text-right flex flex-col md:items-end">
                            <button @click="submitFinal" :disabled="isSubmitting || !termsAccepted"
                                class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-black py-3 px-8 rounded-xl text-sm transition shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                {{ isSubmitting ? 'Mengamankan Ruangan...' : 'Ajukan Booking Sekarang' }}
                            </button>
                            <p class="text-[10px] text-gray-400 font-semibold mt-2.5 bg-white border border-gray-200 py-1 px-2.5 rounded-lg inline-flex items-center gap-1.5 shadow-3xs mx-auto md:mx-0">
                                <span>⏱️</span> Pengajuan akan direview admin maks. 1x24 jam kerja
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- SUCCESS PAGE -->
            <!-- ======================================================= -->
            <div v-if="submitSuccess" class="bg-white rounded-3xl shadow-xl border border-gray-100 p-12 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-green-400 to-emerald-500"></div>
                <div class="w-24 h-24 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto text-5xl mb-6 shadow-inner animate-[bounce_1s_ease-in-out_1]">
                    <span class="animate-[ping_1.5s_cubic-bezier(0,0,0.2,1)_1_reverse] absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-20"></span>
                    <span class="relative">✨</span>
                </div>
                <h2 class="text-3xl font-black text-gray-900 mb-3 tracking-tight">Booking Berhasil Terkirim!</h2>
                <p class="text-gray-500 text-sm max-w-md mx-auto mb-8 leading-relaxed">Luar biasa! Pengajuan ruangan Anda telah masuk ke sistem dan diamankan. Tim kami akan segera meninjau pesanan Anda.</p>
                
                <div class="bg-gradient-to-br from-gray-50 to-white p-6 rounded-2xl inline-block mx-auto border border-gray-200 shadow-sm mb-8 w-full max-w-xs">
                    <p class="text-[11px] text-gray-400 font-bold uppercase tracking-widest mb-1">ID Referensi Anda</p>
                    <p class="text-3xl font-mono font-black text-blue-700 tracking-tight">#{{ String(bookingId).padStart(5, '0') }}</p>
                </div>
                
                <div>
                    <Link href="/user/booking/history" class="bg-green-600 hover:bg-green-700 text-white font-black py-3 px-8 rounded-xl text-sm transition shadow-lg hover:shadow-xl inline-flex items-center gap-2 transform hover:-translate-y-0.5">
                        Lihat Riwayat Booking →
                    </Link>
                </div>
            </div>

        </div>
    </UserLayout>
</template>

<style scoped>
/* Custom Scrollbar for the tables */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1; /* tailwind slate-300 */
    border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8; /* tailwind slate-400 */
}
</style>
