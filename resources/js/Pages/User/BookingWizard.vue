<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import UserLayout from '@/Layouts/UserLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'

defineOptions({ layout: UserLayout })

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
    no_hp_pic: '',
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
    if (!formStage4.value.no_hp_pic.trim()) return false
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
        blockedDates: blockedDates.value,
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
            no_hp_pic: formStage4.value.no_hp_pic,
            layout_preferensi: formStage4.value.layout_preferensi,
            hybrid: formStage4.value.hybrid,
            flipchart: formStage4.value.flipchart,
            catatan: formStage4.value.catatan,
        }
    }
})

const storageKey = computed(() => 'booking_wizard_state_' + (props.auth?.user?.id || 'guest'))

// Langkah A: Auto-save form ke localStorage setiap kali ada perubahan
watch(stateToSave, (newState) => {
    // Jangan simpan jika form telah berhasil diajukan
    if (submitSuccess.value) return
    
    if (typeof window !== 'undefined') {
        localStorage.setItem(storageKey.value, JSON.stringify(newState))
    }
}, { deep: true })

// Langkah B: Restore form dari localStorage saat pertama kali dimuat
onMounted(() => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem(storageKey.value)
        if (saved) {
            let infoHtml = ''
            try {
                const data = JSON.parse(saved)
                const stageNames = {
                    1: 'Kapasitas & Peserta',
                    2: 'Pilih Tanggal',
                    3: 'Pilih Ruangan',
                    4: 'Detail Acara',
                    5: 'Review & Ajukan'
                }
                const stageName = stageNames[data.currentStage] || 'Formulir'
                
                // Format tanggal jika ada
                let dateRangeStr = 'Belum dipilih'
                if (data.startDate && data.endDate) {
                    const formatDateLocal = (dateStr) => {
                        const d = new Date(dateStr)
                        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
                    }
                    dateRangeStr = `${formatDateLocal(data.startDate)} s/d ${formatDateLocal(data.endDate)}`
                }
                
                // Hitung total peserta & panitia
                const pCount = data.participants ? data.participants.length : 0
                const panCount = data.panitiaList ? data.panitiaList.length : 0
                const totalOrang = pCount + panCount
                
                infoHtml = `
                    <div class="mt-4 p-3.5 bg-gray-50 border border-gray-100 rounded-xl text-left font-sans shadow-inner">
                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-1.5 select-none">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 inline-block animate-pulse"></span>
                            <span>Resume Progres Tersimpan:</span>
                        </div>
                        <div class="space-y-2 text-[11px] leading-relaxed">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400 font-medium">Langkah Terakhir:</span>
                                <span class="bg-blue-50 text-blue-700 font-bold px-2 py-0.5 rounded-[4px] text-[10px] border border-blue-100/50">
                                    Tahap ${data.currentStage}: ${stageName}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400 font-medium">Rentang Tanggal:</span>
                                <span class="text-gray-700 font-semibold">${dateRangeStr}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400 font-medium">Total Kapasitas:</span>
                                <span class="text-gray-700 font-semibold">${totalOrang > 0 ? `${totalOrang} Orang` : 'Belum diisi'}</span>
                            </div>
                `
                if (data.selectedRoom) {
                    infoHtml += `
                            <div class="flex items-center justify-between border-t border-gray-200/50 pt-2 mt-2">
                                <span class="text-gray-400 font-medium">Ruangan Terpilih:</span>
                                <span class="text-blue-600 font-bold">${data.selectedRoom.nama_ruang}</span>
                            </div>
                    `
                }
                infoHtml += `
                        </div>
                    </div>
                `
            } catch (e) {
                console.error('Gagal mem-parse info modal resume:', e)
            }

            Swal.fire({
                title: 'Lanjutkan Isian?',
                html: `
                    <div class="text-center font-sans">
                        <div class="w-14 h-14 bg-blue-50 border border-blue-100 rounded-full flex items-center justify-center mx-auto mb-3 text-blue-600 relative shadow-sm">
                            <span class="animate-[ping_2s_cubic-bezier(0,0,0.2,1)_infinite] absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-20"></span>
                            <svg class="w-7 h-7 relative" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div class="text-[11.5px] text-gray-500 leading-relaxed px-2">
                            Anda memiliki data isian form booking yang belum selesai. Apakah Anda ingin melanjutkannya?
                        </div>
                        ${infoHtml}
                    </div>
                `,
                width: '22rem', // Ukuran box ramping dan elegan (352px)
                showCancelButton: true,
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Buat Baru',
                allowOutsideClick: false,
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-2xl border border-gray-100 shadow-xl p-5 font-sans',
                    title: 'text-base font-black text-gray-800 tracking-tight !mt-1 !mb-1.5',
                    htmlContainer: '!m-0 !mb-4',
                    confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2.5 px-5 rounded-md transition shadow-sm mr-2 focus:outline-none select-none cursor-pointer',
                    cancelButton: 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 font-bold text-xs py-2.5 px-5 rounded-md transition shadow-sm focus:outline-none select-none cursor-pointer',
                    actions: '!mt-1.5',
                }
            }).then((result) => {
                if (result.isConfirmed) {
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
                        if (data.blockedDates !== undefined) blockedDates.value = data.blockedDates
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
                            formStage4.value.no_hp_pic = data.formStage4.no_hp_pic || ''
                            formStage4.value.layout_preferensi = data.formStage4.layout_preferensi || 'classroom'
                            formStage4.value.hybrid = data.formStage4.hybrid || false
                            formStage4.value.flipchart = data.formStage4.flipchart || false
                            formStage4.value.catatan = data.formStage4.catatan || ''
                        }
                    } catch (e) {
                        console.error('Gagal memulihkan state formulir:', e)
                    }
                } else {
                    clearSavedState()
                }
            })
        }
    }
    
    // Smart Defaults: Nama PIC tidak otomatis diisi karena harus diinput manual oleh user
    // Departemen & Site dikunci dan diambil dari data akun yang login
})

function clearSavedState() {
    if (typeof window !== 'undefined') {
        localStorage.removeItem(storageKey.value)
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
    formData.append('no_hp_pic', formStage4.value.no_hp_pic)
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
    // Reset state stage 3 dan 4 (TETAP pertahankan availableRooms agar indikator "Ruangan Tersedia" akurat)
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

const getStageTitle = (stage) => {
    switch (stage) {
        case 1: return 'Daftar Peserta & Panitia'
        case 2: return 'Kalender Ketersediaan'
        case 3: return 'Pilih Ruangan'
        case 4: return 'Detail Informasi Acara'
        case 5: return 'Review & Finalisasi'
        default: return ''
    }
}

const getStageSubtitle = (stage) => {
    switch (stage) {
        case 1: return 'Lengkapi daftar peserta training dan panitia penyelenggara untuk mencari ruangan yang sesuai.'
        case 2: return 'Pilih rentang tanggal mulai dan selesai pada kalender untuk melihat ketersediaan ruangan.'
        case 3: return 'Pilih salah satu ruangan yang tersedia untuk kapasitas dan rentang tanggal yang telah ditentukan.'
        case 4: return 'Lengkapi informasi acara, PIC, layout ruangan, dan kebutuhan tambahan.'
        case 5: return 'Tinjau kembali seluruh data sebelum diajukan ke admin.'
        default: return ''
    }
}

const STAGE_LABELS = ['Kapasitas', 'Tanggal', 'Ruangan', 'Detail', 'Review']

const getLayoutLabel = (layout) => {
    switch (layout) {
        case 'classroom': return 'Kelas'
        case 'u-shape': return 'Bentuk U'
        case 'i-shape': return 'Boardroom'
        case 'o-shape': return 'Melingkar'
        case 'custom': return 'Kustom'
        default: return layout
    }
}

const getLayoutDesc = (layout) => {
    switch (layout) {
        case 'classroom': return 'Meja kelas paralel'
        case 'u-shape': return 'Kolaborasi aktif'
        case 'i-shape': return 'Meja rapat panjang'
        case 'o-shape': return 'Hollow square/bulat'
        case 'custom': return 'Upload denah sendiri'
        default: return ''
    }
}
</script>

<template>
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
                                <svg v-if="currentStage > idx + 1" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
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
            <!-- STAGE CARD MAIN WRAPPER -->
            <!-- ======================================================= -->
            <div v-if="!submitSuccess" class="bg-white rounded-md border border-gray-100 shadow-sm p-6 flex flex-col min-h-[500px]">
                
                <!-- Dynamic Stage Header (Always Pinned at Top) -->
                <div class="mb-5 flex items-start gap-3 min-h-[52px]">
                    <div class="bg-gray-50 border border-gray-100 rounded-md p-2 shrink-0">
                        <svg v-if="currentStage === 1" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.386 11.386 0 0 1 10.089 21c-2.243 0-4.32-.647-6.07-1.758A5.986 5.986 0 0 1 4 17.25m6-5.625a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6.5-2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <svg v-else-if="currentStage === 2" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <svg v-else-if="currentStage === 3" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                        <svg v-else-if="currentStage === 4" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                        <svg v-else-if="currentStage === 5" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="flex-grow">
                        <h2 class="text-sm font-bold text-gray-800 leading-none mb-1">{{ getStageTitle(currentStage) }}</h2>
                        <p class="text-xs text-gray-400 leading-relaxed">{{ getStageSubtitle(currentStage) }}</p>
                    </div>
                </div>

                <!-- Unified Action Bar (Persistent at the Top, under Header - Hidden on Stage 5) -->
                <div v-if="currentStage !== 5" class="bg-gray-50 border border-gray-100 rounded-md px-4 py-3 flex flex-wrap items-center justify-between gap-3 mb-6 shrink-0 min-h-[54px]">
                    <!-- Left: dynamic information based on current stage -->
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                        <!-- Stage 1 -->
                        <template v-if="currentStage === 1">
                            <div class="flex items-center gap-2">
                                <template v-if="uploadedFileName === 'Input Manual'">
                                    <input type="file" id="excel-upload-bar" class="hidden" accept=".xlsx"
                                        @change="handleExcelUpload" :disabled="isUploadingExcel" />
                                    <label for="excel-upload-bar"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2 px-3.5 rounded-md cursor-pointer transition flex items-center gap-2 shadow-sm select-none"
                                        :class="{ 'opacity-60 cursor-not-allowed': isUploadingExcel }">
                                        <span v-if="isUploadingExcel" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin shrink-0"></span>
                                        <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        <span>{{ isUploadingExcel ? 'Memproses...' : 'Impor Excel' }}</span>
                                    </label>
                                    <a :href="`/user/booking/download-template?v=${Date.now()}`"
                                        class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold py-2 px-3.5 rounded-md transition inline-flex items-center gap-2 shadow-sm select-none">
                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        Template Excel
                                    </a>
                                </template>
                                <template v-else>
                                    <button @click="resetExcel"
                                        class="bg-white border border-red-200 text-red-500 hover:bg-red-50 text-xs font-semibold py-2 px-3.5 rounded-md transition flex items-center gap-2 shadow-sm max-w-xs">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9 9m9.24-3.5v-.75A2.25 2.25 0 0 0 16.25 2.25h-4.5A2.25 2.25 0 0 0 9.5 4.5v.75m3 3h4.5M5.625 6h12.75L17.25 18a2.25 2.25 0 0 1-2.25 2.25h-6A2.25 2.25 0 0 1 6.75 18L5.625 6Z" />
                                        </svg>
                                        <span class="truncate">Hapus "{{ uploadedFileName }}"</span>
                                    </button>
                                </template>
                            </div>
                            <span class="text-gray-300 mx-0.5">|</span>
                            <div class="flex items-center gap-1.5 font-medium text-xs">
                                Peserta: <strong class="text-gray-800">{{ participantCount }}</strong>
                                <span class="text-gray-300 mx-0.5">|</span>
                                Panitia: <strong class="text-gray-800">{{ panitiaCount }}</strong>
                                <span class="text-gray-300 mx-0.5">|</span>
                                <span class="text-blue-600 font-bold">Total: {{ totalOrangComputed }}</span>
                            </div>
                        </template>

                        <!-- Stage 2 -->
                        <template v-else-if="currentStage === 2">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                                <span>Kapasitas: <strong class="text-gray-800">{{ totalOrang }} Orang</strong></span>
                                <span class="text-gray-300 mx-0.5">|</span>
                                <span><strong class="text-gray-800">{{ eligibleRooms.length }}</strong> Opsi Ruangan</span>
                            </div>
                            <span class="text-gray-300 mx-0.5">|</span>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full shrink-0" :class="isRangeSelected ? 'bg-green-500' : 'bg-yellow-400 animate-pulse'"></span>
                                <span>
                                    <template v-if="!startDate">Pilih tanggal mulai di kalender</template>
                                    <template v-else-if="!endDate">Mulai: <strong class="text-blue-600">{{ formatDate(startDate) }}</strong> — pilih selesai</template>
                                    <template v-else><strong class="text-green-600">{{ formatDate(startDate) }}</strong> s/d <strong class="text-green-600">{{ formatDate(endDate) }}</strong></template>
                                </span>
                            </div>
                            <template v-if="isRangeSelected">
                                <span class="text-gray-300 mx-0.5">|</span>
                                <div class="flex items-center gap-1.5">
                                    <span v-if="isAutoFetchingRooms" class="w-3 h-3 border-2 border-blue-400 border-t-transparent rounded-full animate-spin"></span>
                                    <template v-else>
                                        <span v-if="availableRooms.filter(r => r.is_available).length > 0" class="bg-green-50 text-green-700 px-2 py-0.5 rounded-full font-semibold border border-green-100 flex items-center gap-1">
                                            <span>✓</span>
                                            <span>{{ availableRooms.filter(r => r.is_available).length }} Ruangan Tersedia</span>
                                        </span>
                                        <span v-else class="bg-red-50 text-red-700 px-2 py-0.5 rounded-full font-semibold border border-red-100 flex items-center gap-1 animate-pulse">
                                            <span>❌</span>
                                            <span>Semua Penuh</span>
                                        </span>
                                    </template>
                                </div>
                            </template>
                        </template>

                        <!-- Stage 3 -->
                        <template v-else-if="currentStage === 3">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                                <span>{{ formatDate(startDate) }} <span class="text-gray-300">s/d</span> {{ formatDate(endDate) }}</span>
                            </div>
                            <span class="text-gray-300 mx-0.5">|</span>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full shrink-0" :class="isRoomSelected ? 'bg-green-500' : 'bg-yellow-400 animate-pulse'"></span>
                                <span :class="isRoomSelected ? 'text-green-600 font-semibold' : ''">
                                    {{ isRoomSelected ? `${selectedRoom.nama_ruang} Terpilih` : 'Pilih ruangan di bawah' }}
                                </span>
                            </div>
                        </template>

                        <!-- Stage 4 -->
                        <template v-else-if="currentStage === 4">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                </svg>
                                <span class="font-bold text-gray-700">{{ selectedRoom?.nama_ruang }}</span>
                            </div>
                            <span class="text-gray-300 mx-0.5">|</span>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full shrink-0" :class="isStage4Valid ? 'bg-green-500' : 'bg-yellow-400 animate-pulse'"></span>
                                <span :class="isStage4Valid ? 'text-green-600 font-semibold' : ''">
                                    {{ isStage4Valid ? 'Siap dilanjutkan' : 'Lengkapi Nama Acara & PIC' }}
                                </span>
                            </div>
                        </template>

                        <!-- Stage 5 -->
                        <template v-else-if="currentStage === 5">
                            <label class="flex items-start gap-3 select-none cursor-pointer flex-1" :class="isSubmitting ? 'opacity-60 cursor-not-allowed' : ''">
                                <input type="checkbox" v-model="termsAccepted" :disabled="isSubmitting"
                                    class="w-4 h-4 mt-0.5 rounded-sm border-gray-300 text-blue-600 focus:ring-blue-100 shrink-0" />
                                <div>
                                    <span class="text-xs font-semibold text-gray-700 block leading-tight">Saya memastikan data pemesanan sudah benar</span>
                                    <span class="text-[10px] text-gray-450 block mt-0.5 leading-tight">Saya bertanggung jawab penuh atas kelengkapan data & menyetujui syarat penggunaan ruangan.</span>
                                </div>
                            </label>
                        </template>
                    </div>

                    <!-- Right: dynamic action buttons -->
                    <div class="flex items-center gap-2">
                        <div v-if="currentStage === 1 && stage1Error" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-md px-3 py-1.5 flex items-center gap-1.5 shrink-0">
                            <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span>{{ stage1Error }}</span>
                        </div>

                        <div v-if="currentStage === 2 && rangeError" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-md px-2.5 py-1.5 flex items-center gap-1 shrink-0">
                            <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                            {{ rangeError }}
                        </div>
                        <div v-if="currentStage === 2 && stage3Error" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-md px-2.5 py-1.5 flex items-center gap-1 shrink-0">
                            <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                            {{ stage3Error }}
                        </div>

                        <div v-if="currentStage === 3 && stage3Error" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-md px-2.5 py-1.5 flex items-center gap-1 shrink-0">
                            <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                            {{ stage3Error }}
                        </div>

                        <div v-if="currentStage === 4 && stage4Error" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-md px-2.5 py-1.5 flex items-center gap-1 shrink-0">
                            <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                            {{ stage4Error }}
                        </div>

                        <div v-if="currentStage === 5 && submitError" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-md px-2.5 py-1.5 flex items-center gap-1 shrink-0">
                            <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                            {{ submitError }}
                        </div>

                        <div v-if="currentStage === 2" class="hidden md:flex items-center gap-2 text-[10px] text-gray-400 font-medium mr-2 shrink-0">
                            <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-400 inline-block"></span> Tersedia</div>
                            <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-yellow-400 inline-block"></span> Parsial</div>
                            <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block"></span> Penuh</div>
                        </div>

                        <!-- CTAs -->
                        <button v-if="currentStage === 1" @click="checkEligibility" :disabled="!isStage1Valid || isChecking"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md text-xs transition disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap shadow-sm flex items-center gap-2 select-none">
                            <span v-if="isChecking" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin shrink-0"></span>
                            <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.637 10.637z" />
                            </svg>
                            <span>{{ isChecking ? 'Mengecek...' : 'Cari Ruangan' }}</span>
                            <svg v-if="!isChecking" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </button>

                        <template v-else-if="currentStage === 2">
                            <button @click="backToStage1"
                                class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold py-2 px-3.5 rounded-md transition shadow-sm flex items-center gap-1.5 select-none">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                                Kembali
                            </button>
                            <button @click="proceedToStage3" :disabled="!isRangeSelected || isLoadingRooms || (availableRooms.length > 0 && !availableRooms.some(r => r.is_available))"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md text-xs transition disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap shadow-sm flex items-center gap-2 select-none">
                                <span v-if="isLoadingRooms" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span>{{ isLoadingRooms ? 'Memproses...' : 'Pilih Ruangan' }}</span>
                                <svg v-if="!isLoadingRooms" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </button>
                        </template>

                        <template v-else-if="currentStage === 3">
                            <button @click="backToStage2"
                                class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold py-2 px-3.5 rounded-md transition shadow-sm flex items-center gap-1.5 select-none">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                                Kembali
                            </button>
                            <button @click="proceedToStage4" :disabled="!isRoomSelected"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md text-xs transition disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap shadow-sm flex items-center gap-2 select-none">
                                <span>Isi Detail</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </button>
                        </template>

                        <template v-else-if="currentStage === 4">
                            <button @click="currentStage = 3" :disabled="isSavingStage4"
                                class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold py-2 px-3.5 rounded-md transition shadow-sm flex items-center gap-1.5 select-none">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                                Kembali
                            </button>
                            <button @click="proceedToStage5" :disabled="!isStage4Valid || isSavingStage4"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md text-xs transition disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap shadow-sm flex items-center gap-2 select-none">
                                <span v-if="isSavingStage4" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span>{{ isSavingStage4 ? 'Menyimpan...' : 'Lanjut ke Review' }}</span>
                                <svg v-if="!isSavingStage4" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </button>
                        </template>

                        <template v-else-if="currentStage === 5">
                            <button @click="currentStage = 4" :disabled="isSubmitting"
                                class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold py-2 px-3.5 rounded-md transition shadow-sm flex items-center gap-1.5 select-none">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                                Kembali Edit
                            </button>
                            <div class="flex flex-col items-end gap-0.5 shrink-0">
                                <button @click="submitFinal" :disabled="isSubmitting || !termsAccepted"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-md text-xs transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2 whitespace-nowrap select-none">
                                    <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                                    {{ isSubmitting ? 'Mengajukan...' : 'Ajukan Booking' }}
                                </button>
                                <span class="text-[9px] text-gray-405 select-none">Direview admin maks. 1×24 jam</span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Dynamic Stage Content Area -->
                <div class="flex-grow">

                    <!-- STAGE 1 -->
                    <div v-if="currentStage === 1" class="space-y-6">

                    <!-- Tables -->
                    <div class="flex flex-col gap-4 w-full">

                        <!-- List Peserta -->
                        <div class="rounded-sm overflow-hidden flex flex-col bg-white shadow-sm">
                            <div class="bg-gray-50 border-b border-gray-100 px-4 py-3 flex items-center justify-between shrink-0">
                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">List Peserta</span>
                                    <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded border border-blue-100">
                                        {{ participantCount }} Orang
                                    </span>
                                </div>
                                <button @click="addParticipant"
                                    class="bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-semibold py-1.5 px-3 rounded-md transition flex items-center gap-1.5 shadow-sm select-none">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Tambah Peserta
                                </button>
                            </div>
                            
                            <div class="overflow-x-auto flex-1 max-h-[420px]">
                                <table class="w-full text-xs text-left border-collapse min-w-[650px]">
                                    <thead class="bg-gray-50 text-gray-500 font-bold uppercase tracking-wider sticky top-0 z-10 border-b border-gray-100">
                                        <tr>
                                            <th class="px-4 py-3 w-12 text-center font-bold">#</th>
                                            <th class="px-4 py-3 font-bold">Nama Lengkap<span class="text-red-500">*</span></th>
                                            <th class="px-4 py-3 font-bold">NRP<span class="text-red-500">*</span></th>
                                            <th class="px-4 py-3 font-bold">Jabatan<span class="text-red-500">*</span></th>
                                            <th class="px-4 py-3 font-bold">Site<span class="text-red-500">*</span></th>
                                            <th class="px-4 py-3 font-bold">No. HP<span class="text-red-500">*</span></th>
                                            <th class="px-4 py-3 w-40 text-center font-bold">Gender<span class="text-red-500">*</span></th>
                                            <th class="px-4 py-3 w-12 text-center font-bold"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="(p, i) in participants" :key="i" class="hover:bg-blue-50/20 transition-colors">
                                            <td class="px-4 py-2.5 text-center text-gray-400 font-bold">{{ i + 1 }}</td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.nama" type="text" placeholder="Ketik nama lengkap..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.nama.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.nrp" type="text" placeholder="Ketik NRP atau 'N/A'..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.nrp.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.jabatan" type="text" placeholder="Jabatan..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.jabatan.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.site" type="text" placeholder="Site..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.site.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.no_hp" type="text" placeholder="Contoh: 0812..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.no_hp.trim() === '' || !/^[0-9+]+$/.test(p.no_hp) ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <select v-model="p.gender"
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white min-w-[110px] appearance-none pr-8 relative bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.4c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E')] bg-[length:0.6em_auto] bg-[right_0.65rem_center] bg-no-repeat">
                                                    <option value="L">Laki-laki</option>
                                                    <option value="P">Perempuan</option>
                                                </select>
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <button @click="removeParticipant(i)"
                                                    class="text-gray-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition"
                                                    title="Hapus baris">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9 9m6.24-3.5v-.75A2.25 2.25 0 0 0 14.25 2.25h-4.5A2.25 2.25 0 0 0 7.5 4.5v.75m3 3h4.5M5.625 6h12.75L17.25 18a2.25 2.25 0 0 1-2.25 2.25h-6A2.25 2.25 0 0 1 6.75 18L5.625 6Z" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Footer hint validasi peserta -->
                            <div class="bg-gray-50/70 px-4 py-2.5 flex items-center justify-between shrink-0">
                                <span class="text-[10px] text-gray-500 font-medium">
                                    Semua kolom bertanda asterisk (<span class="text-red-500 font-bold">*</span>) wajib diisi.
                                </span>
                                <div class="flex items-center gap-1 ml-auto">
                                    <template v-if="!participantsValid">
                                        <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                        </svg>
                                        <span class="text-[10px] text-red-650 font-bold">Ada kolom kosong</span>
                                    </template>
                                    <template v-else>
                                        <svg class="w-3.5 h-3.5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <span class="text-[10px] text-green-655 font-bold">Peserta siap validasi</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- List Panitia -->
                        <div class="rounded-sm overflow-hidden flex flex-col bg-white shadow-sm">
                            <div class="bg-gray-50 border-b border-gray-100 px-4 py-3 flex items-center justify-between shrink-0">
                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                    </svg>
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">List Panitia</span>
                                    <span class="bg-amber-50 text-amber-700 text-[10px] font-bold px-2 py-0.5 rounded border border-amber-100">
                                        {{ panitiaCount }} Orang
                                    </span>
                                </div>
                                <button @click="addPanitia"
                                    class="bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-semibold py-1.5 px-3 rounded-md transition flex items-center gap-1.5 shadow-sm select-none">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Tambah Panitia
                                </button>
                            </div>
                            
                            <div class="overflow-x-auto flex-1 max-h-[420px]">
                                <table class="w-full text-xs text-left border-collapse min-w-[650px]">
                                    <thead class="bg-gray-50 text-gray-500 font-bold uppercase tracking-wider sticky top-0 z-10 border-b border-gray-100">
                                        <tr>
                                            <th class="px-4 py-3 w-12 text-center font-bold">#</th>
                                            <th class="px-4 py-3 font-bold">Nama Lengkap<span class="text-red-500">*</span></th>
                                            <th class="px-4 py-3 font-bold">NRP<span class="text-red-500">*</span></th>
                                            <th class="px-4 py-3 font-bold">Jabatan</th>
                                            <th class="px-4 py-3 font-bold">Site</th>
                                            <th class="px-4 py-3 font-bold">No. HP</th>
                                            <th class="px-4 py-3 w-40 text-center font-bold">Gender</th>
                                            <th class="px-4 py-3 w-12 text-center font-bold"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="(p, i) in panitiaList" :key="i" class="hover:bg-amber-50/20 transition-colors">
                                            <td class="px-4 py-2.5 text-center text-gray-400 font-bold">{{ i + 1 }}</td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.nama" type="text" placeholder="Nama lengkap..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.nama.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.nrp" type="text" placeholder="Ketik NRP atau 'N/A'..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.nrp.trim() === '' ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.jabatan" type="text" placeholder="Jabatan..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.site" type="text" placeholder="Site..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white" />
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input v-model="p.no_hp" type="text" placeholder="Contoh: 0812..."
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white"
                                                    :class="p.no_hp.trim() !== '' && !/^[0-9+]+$/.test(p.no_hp) ? 'border-red-300 bg-red-50/20' : ''" />
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <select v-model="p.gender"
                                                    class="w-full text-xs border border-gray-200 rounded-sm px-2.5 py-1.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-100 bg-white min-w-[110px] appearance-none pr-8 relative bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.4c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E')] bg-[length:0.6em_auto] bg-[right_0.65rem_center] bg-no-repeat">
                                                    <option value="L">Laki-laki</option>
                                                    <option value="P">Perempuan</option>
                                                </select>
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <button @click="removePanitia(i)" :disabled="panitiaList.length === 1"
                                                    class="text-gray-400 hover:text-red-655 disabled:opacity-30 disabled:cursor-not-allowed p-1.5 hover:bg-red-50 rounded-lg transition"
                                                    title="Hapus baris">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9 9m6.24-3.5v-.75A2.25 2.25 0 0 0 14.25 2.25h-4.5A2.25 2.25 0 0 0 7.5 4.5v.75m3 3h4.5M5.625 6h12.75L17.25 18a2.25 2.25 0 0 1-2.25 2.25h-6A2.25 2.25 0 0 1 6.75 18L5.625 6Z" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Footer hint validasi panitia -->
                            <div class="bg-gray-50/70 px-4 py-2.5 flex items-center justify-between shrink-0">
                                <span class="text-[10px] text-gray-500 font-medium">
                                    Kolom <strong>Nama*</strong> dan <strong>NRP*</strong> wajib diisi semua baris (NRP boleh diisi "N/A").
                                </span>
                                <div class="flex items-center gap-1 ml-auto">
                                    <template v-if="!panitiaValid">
                                        <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                        </svg>
                                        <span class="text-[10px] text-red-650 font-bold">Ada nama atau NRP belum diisi</span>
                                    </template>
                                    <template v-else>
                                        <svg class="w-3.5 h-3.5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <span class="text-[10px] text-green-600 font-semibold">List panitia valid</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            <!-- STAGE 2 -->
            <div v-if="currentStage === 2" class="space-y-6">

                <div v-if="isLoadingCalendar" class="bg-white rounded-md border border-gray-100 shadow-sm p-10 text-center text-gray-400 text-sm">
                    Memuat data ketersediaan kalender...
                </div>

                <div v-else class="bg-white rounded-md border border-gray-100 shadow-sm p-6">
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

            <!-- STAGE 3 -->
            <div v-if="currentStage === 3" class="space-y-4">

                <!-- Room Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    <div
                        v-for="room in availableRooms"
                        :key="room.id"
                        class="relative bg-white rounded-md border transition-all cursor-pointer overflow-hidden"
                        :class="[
                            !room.is_available
                                ? 'border-gray-100 opacity-50 cursor-not-allowed'
                                : isRoomSelected_(room)
                                    ? 'border-blue-500 ring-2 ring-blue-100 shadow-sm'
                                    : 'border-gray-100 hover:border-blue-300 hover:shadow-sm'
                        ]"
                        @click="selectRoom(room)"
                    >
                        <!-- Selected badge -->
                        <div v-if="isRoomSelected_(room)"
                            class="absolute top-0 right-0 bg-blue-600 text-white text-[9px] font-bold px-2 py-0.5 rounded-bl-md">
                            TERPILIH
                        </div>
                        <div v-if="!room.is_available"
                            class="absolute top-0 right-0 bg-red-400 text-white text-[9px] font-bold px-2 py-0.5 rounded-bl-md">
                            PENUH
                        </div>

                        <div class="p-4">
                            <!-- Icon + Nama -->
                            <div class="flex items-center gap-2.5 mb-2">
                                <div class="w-8 h-8 rounded-md flex items-center justify-center flex-shrink-0"
                                    :class="room.is_combined ? 'bg-purple-50' : 'bg-blue-50'">
                                    <svg class="w-4 h-4" :class="room.is_combined ? 'text-purple-500' : 'text-blue-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path v-if="room.is_combined" stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-gray-800 text-xs leading-tight truncate">{{ room.nama_ruang }}</h3>
                                    <p class="text-[10px] text-gray-400 truncate">{{ room.lokasi_gedung }}</p>
                                </div>
                            </div>

                            <!-- Kapasitas -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1 text-xs text-gray-500">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                    </svg>
                                    <span>Maks <strong class="text-gray-700">{{ room.kapasitas_max }}</strong> orang</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Semua penuh warning -->
                <div v-if="availableRooms.length > 0 && availableRooms.every(r => !r.is_available)"
                    class="bg-orange-50 border border-orange-200 text-orange-700 text-xs rounded-md px-4 py-3 flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    Semua ruangan tidak tersedia pada tanggal ini. Silakan kembali dan pilih tanggal lain.
                </div>
            </div>

            <!-- STAGE 4 -->
            <div v-if="currentStage === 4" class="space-y-5">

                <!-- Info Banner: Dept & Site terkunci otomatis -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-4 shadow-sm mb-6">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-sm border border-blue-100 flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-black text-blue-900 mb-0.5">Identitas Pemesan Terkunci Otomatis</p>
                        <p class="text-[11px] text-blue-700 leading-tight">Departemen dan Site diambil dari akun Anda secara otomatis dan tidak dapat diubah. Harap isi <strong>Nama PIC</strong> dan <strong>No. HP PIC</strong> — yaitu nama manusia nyata yang dapat dihubungi saat hari-H.</p>
                    </div>
                </div>

                <!-- Locked: Departemen & Site -->
                <div class="mb-4">
                    <h2 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider text-blue-900/80">Identitas Pemesan (Terkunci)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-750 mb-1">Departemen</label>
                            <div class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs bg-gray-50 text-gray-600 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.33m-16.5 0V21m6-12h9" />
                                </svg>
                                <span class="font-semibold">{{ auth?.user?.divisi || '—' }}</span>
                                <span class="ml-auto text-[10px] text-gray-400 bg-gray-200 px-2 py-0.5 rounded-full">Terkunci</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-750 mb-1">Site / Lokasi</label>
                            <div class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs bg-gray-50 text-gray-600 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                <span class="font-semibold">{{ auth?.user?.site || '—' }}</span>
                                <span class="ml-auto text-[10px] text-gray-400 bg-gray-200 px-2 py-0.5 rounded-full">Terkunci</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informasi Utama -->
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Informasi Utama</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nama Training / Acara <span class="text-red-500">*</span></label>
                            <input v-model="formStage4.nama_training" type="text" placeholder="Masukkan nama acara..."
                                class="w-full border border-gray-200 rounded-sm px-3 py-2 text-xs focus:ring-1 focus:ring-blue-100 focus:border-blue-400 focus:outline-none bg-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nama PIC <span class="text-red-500">*</span></label>
                            <input v-model="formStage4.nama_pic" type="text" placeholder="Nama PIC..."
                                class="w-full border border-gray-200 rounded-sm px-3 py-2 text-xs focus:ring-1 focus:ring-blue-100 focus:border-blue-400 focus:outline-none bg-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">No. HP PIC <span class="text-red-500">*</span></label>
                            <input v-model="formStage4.no_hp_pic" type="tel" placeholder="Contoh: 08123456789"
                                class="w-full border border-gray-200 rounded-sm px-3 py-2 text-xs focus:ring-1 focus:ring-blue-100 focus:border-blue-400 focus:outline-none bg-white" />
                            <p class="text-[10px] text-gray-400 mt-1">Dihubungi Admin Gedung saat hari-H.</p>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-100" />

                <!-- Layout Ruangan -->
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-3">Layout Ruangan (Pilih Salah Satu)</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                        <label v-for="layout in ['classroom', 'u-shape', 'i-shape', 'o-shape', 'custom']" :key="layout"
                            class="relative flex flex-col items-center justify-between p-3 border rounded-lg cursor-pointer transition select-none h-40"
                            :class="formStage4.layout_preferensi === layout ? 'border-blue-600 bg-blue-50/40 text-blue-700 ring-2 ring-blue-100 shadow-sm' : 'border-gray-200 hover:border-blue-300 hover:bg-gray-50/40 text-gray-600 bg-white'">
                            <input type="radio" v-model="formStage4.layout_preferensi" :value="layout" class="hidden" />

                            <!-- Mini Layout Schematic (CSS Visual Diagram) -->
                            <div class="w-full h-20 bg-gray-50 border border-gray-100 rounded-md flex items-center justify-center relative overflow-hidden shrink-0">
                                <!-- Classroom Layout -->
                                <template v-if="layout === 'classroom'">
                                    <div class="absolute top-1.5 w-10 h-0.5 bg-gray-400 rounded-full"></div>
                                    <div class="flex flex-col gap-2 mt-4 items-center w-full px-2">
                                        <div v-for="row in 2" :key="row" class="flex gap-2 justify-center w-full">
                                            <div v-for="col in 2" :key="col" class="flex flex-col items-center gap-0.5">
                                                <div class="w-5 h-1.5 bg-blue-300/80 rounded-[1px] shadow-sm"></div>
                                                <div class="flex gap-0.5">
                                                    <div class="w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                                    <div class="w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- U-Shape Layout -->
                                <template v-if="layout === 'u-shape'">
                                    <div class="absolute top-1.5 w-10 h-0.5 bg-gray-400 rounded-full"></div>
                                    <div class="relative w-12 h-10 mt-3 flex items-center justify-center">
                                        <div class="absolute left-2.5 top-0 bottom-0 w-1.5 bg-blue-300/80 rounded-[1px] shadow-sm"></div>
                                        <div class="absolute right-2.5 top-0 bottom-0 w-1.5 bg-blue-300/80 rounded-[1px] shadow-sm"></div>
                                        <div class="absolute top-0 left-2.5 right-2.5 h-1.5 bg-blue-300/80 rounded-[1px] shadow-sm"></div>

                                        <!-- Outer side chairs -->
                                        <div class="absolute -top-1 left-4 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute -top-1 right-4 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute left-1 top-2.5 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute left-1 top-6 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute right-1 top-2.5 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute right-1 top-6 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                    </div>
                                </template>

                                <!-- I-Shape Layout -->
                                <template v-if="layout === 'i-shape'">
                                    <div class="relative w-12 h-10 flex items-center justify-center">
                                        <div class="w-8 h-3 bg-blue-300/80 rounded-[1px] shadow-sm flex items-center justify-center"></div>
                                        <!-- Chairs -->
                                        <div class="absolute top-1.5 flex gap-1 justify-center w-full">
                                            <div class="w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                            <div class="w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                            <div class="w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        </div>
                                        <div class="absolute bottom-1.5 flex gap-1 justify-center w-full">
                                            <div class="w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                            <div class="w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                            <div class="w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        </div>
                                        <div class="absolute left-1 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute right-1 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                    </div>
                                </template>

                                <!-- O-Shape Layout -->
                                <template v-if="layout === 'o-shape'">
                                    <div class="relative w-12 h-10 flex items-center justify-center">
                                        <div class="w-8 h-8 border-2 border-blue-300/85 rounded-full flex items-center justify-center"></div>
                                        <!-- Chairs outer sides -->
                                        <div class="absolute top-0.5 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute bottom-0.5 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute left-1 top-4.5 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                        <div class="absolute right-1 top-4.5 w-1 h-1 rounded-full bg-blue-500/90 shadow-sm"></div>
                                    </div>
                                </template>

                                <!-- Custom Layout -->
                                <template v-if="layout === 'custom'">
                                    <div class="w-10 h-8 border border-dashed border-gray-300 rounded flex flex-col items-center justify-center gap-0.5">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                        </svg>
                                        <span class="text-[7px] text-gray-400 font-bold">Sketsa</span>
                                    </div>
                                </template>
                            </div>

                            <!-- Layout Text -->
                            <div class="text-center mt-2 w-full">
                                <span class="text-[10px] font-bold block leading-tight capitalize">{{ getLayoutLabel(layout) }}</span>
                                <span class="text-[8px] text-gray-400 block mt-0.5 leading-none">{{ getLayoutDesc(layout) }}</span>
                            </div>
                        </label>
                    </div>

                    <!-- Custom layout file upload section -->
                    <div v-if="formStage4.layout_preferensi === 'custom'" class="mt-4 bg-gray-50 border border-gray-100 rounded-lg p-4 transition-all duration-350">
                        <label class="block text-xs font-bold text-gray-700 mb-2">Unggah Denah/Sketsa Kustom (Maks 2MB) <span class="text-red-500">*</span></label>
                        <input type="file" @change="handleCustomLayoutUpload" accept=".jpg,.png,.pdf" class="text-xs text-gray-600 bg-white border border-gray-200 rounded-sm p-2 file:bg-gray-100 file:border-none file:px-2 file:py-1 file:rounded-sm file:mr-2 file:text-xs" />
                        <p v-if="uploadedCustomFileName" class="text-xs text-green-600 font-medium mt-1.5">✓ {{ uploadedCustomFileName }}</p>
                    </div>
                </div>

                <hr class="border-gray-100" />

                <!-- Kebutuhan Tambahan -->
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Kebutuhan Tambahan</p>
                    <div class="flex gap-4 flex-wrap">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="formStage4.hybrid" class="rounded-sm text-blue-600 focus:ring-blue-100 focus:ring-offset-0 border-gray-200" />
                            <span class="text-xs text-gray-600 font-medium">Hybrid (Kamera &amp; Mic)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="formStage4.flipchart" class="rounded-sm text-blue-600 focus:ring-blue-100 focus:ring-offset-0 border-gray-200" />
                            <span class="text-xs text-gray-600 font-medium">Flipchart (Papan Tulis)</span>
                        </label>
                    </div>
                    <div class="mt-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Catatan Tambahan (Opsional)</label>
                        <textarea v-model="formStage4.catatan" rows="3" placeholder="Pesan khusus untuk Admin..."
                            class="w-full border border-gray-200 rounded-sm px-3 py-2 text-xs focus:ring-1 focus:ring-blue-100 focus:border-blue-400 focus:outline-none bg-white"></textarea>
                    </div>
                </div>
            </div>

            <!-- STAGE 5 -->
            <div v-if="currentStage === 5" class="space-y-5">

                <!-- Review: single column flow -->
                <div class="flex flex-col gap-4">

                    <!-- Summary 3 kolom -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="bg-gray-50 border border-gray-100 rounded-md p-4">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Informasi Acara</p>
                            <p class="text-sm font-semibold text-gray-800 leading-tight">{{ formStage4.nama_training || '-' }}</p>
                            <p class="text-xs text-gray-500 mt-1">PIC: <span class="text-gray-700 font-medium">{{ formStage4.nama_pic || '-' }}</span></p>
                            <p class="text-xs text-gray-500">HP: <span class="text-gray-700 font-medium">{{ formStage4.no_hp_pic || '-' }}</span></p>
                            <p v-if="formStage4.catatan" class="text-[11px] text-gray-500 italic mt-2 border-t border-gray-100 pt-2">{{ formStage4.catatan }}</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-md p-4">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Ruangan & Jadwal</p>
                            <p class="text-sm font-semibold text-gray-800 leading-tight">{{ selectedRoom?.nama_ruang || '-' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ selectedRoom?.lokasi_gedung || '-' }}</p>
                            <div class="flex items-center gap-1.5 mt-2 text-xs text-gray-600">
                                <span class="font-medium">{{ formatDate(startDate) }}</span>
                                <span class="text-gray-300">s/d</span>
                                <span class="font-medium">{{ formatDate(endDate) }}</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-md p-4">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Setup</p>
                            <div class="space-y-1.5 text-xs">
                                <div class="flex justify-between"><span class="text-gray-500">Total</span><span class="font-medium text-gray-800">{{ totalOrang }} ({{ participantCount }}P + {{ panitiaCount }}Pan)</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Layout</span><span class="font-medium text-gray-800 capitalize">{{ formStage4.layout_preferensi }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Hybrid</span><span :class="formStage4.hybrid ? 'text-green-600 font-medium' : 'text-gray-400'">{{ formStage4.hybrid ? 'Ya' : 'Tidak' }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Flipchart</span><span :class="formStage4.flipchart ? 'text-green-600 font-medium' : 'text-gray-400'">{{ formStage4.flipchart ? 'Ya' : 'Tidak' }}</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Peserta — full width -->
                    <div class="rounded-sm overflow-hidden border border-gray-100 bg-white">
                        <div class="bg-gray-50 px-4 py-2.5 flex items-center gap-2 border-b border-gray-100">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Daftar Peserta</span>
                            <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded border border-blue-100">{{ participantCount }} orang</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-gray-50 text-gray-500 font-semibold uppercase tracking-wider text-[10px] border-b border-gray-100">
                                    <tr>
                                        <th class="px-4 py-2.5 w-10 text-center">#</th>
                                        <th class="px-4 py-2.5">Nama Lengkap</th>
                                        <th class="px-4 py-2.5">NRP</th>
                                        <th class="px-4 py-2.5">Jabatan</th>
                                        <th class="px-4 py-2.5">Site</th>
                                        <th class="px-4 py-2.5">No. HP</th>
                                        <th class="px-4 py-2.5">Gender</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(p, i) in participants" :key="i" class="hover:bg-blue-50/20 transition-colors">
                                        <td class="px-4 py-2.5 text-center text-gray-400">{{ i + 1 }}</td>
                                        <td class="px-4 py-2.5 font-medium text-gray-800">{{ p.nama }}</td>
                                        <td class="px-4 py-2.5 font-mono text-gray-600">{{ p.nrp || 'N/A' }}</td>
                                        <td class="px-4 py-2.5 text-gray-500">{{ p.jabatan || '-' }}</td>
                                        <td class="px-4 py-2.5 text-gray-500">{{ p.site || '-' }}</td>
                                        <td class="px-4 py-2.5 text-gray-500">{{ p.no_hp || '-' }}</td>
                                        <td class="px-4 py-2.5 text-gray-500 capitalize">{{ p.gender || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Daftar Panitia — full width -->
                    <div class="rounded-sm overflow-hidden border border-gray-100 bg-white">
                        <div class="bg-gray-50 px-4 py-2.5 flex items-center gap-2 border-b border-gray-100">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Daftar Panitia</span>
                            <span class="bg-amber-50 text-amber-700 text-[10px] font-bold px-2 py-0.5 rounded border border-amber-100">{{ panitiaCount }} orang</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-gray-50 text-gray-500 font-semibold uppercase tracking-wider text-[10px] border-b border-gray-100">
                                    <tr>
                                        <th class="px-4 py-2.5 w-10 text-center">#</th>
                                        <th class="px-4 py-2.5">Nama Lengkap</th>
                                        <th class="px-4 py-2.5">NRP</th>
                                        <th class="px-4 py-2.5">Jabatan</th>
                                        <th class="px-4 py-2.5">Site</th>
                                        <th class="px-4 py-2.5">No. HP</th>
                                        <th class="px-4 py-2.5">Gender</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(p, i) in panitiaList" :key="i" class="hover:bg-amber-50/20 transition-colors">
                                        <td class="px-4 py-2.5 text-center text-gray-400">{{ i + 1 }}</td>
                                        <td class="px-4 py-2.5 font-medium text-gray-800">{{ p.nama }}</td>
                                        <td class="px-4 py-2.5 font-mono text-gray-600">{{ p.nrp || 'N/A' }}</td>
                                        <td class="px-4 py-2.5 text-gray-500">{{ p.jabatan || '-' }}</td>
                                        <td class="px-4 py-2.5 text-gray-500">{{ p.site || '-' }}</td>
                                        <td class="px-4 py-2.5 text-gray-500">{{ p.no_hp || '-' }}</td>
                                        <td class="px-4 py-2.5 text-gray-500 capitalize">{{ p.gender || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Stage 5 Action Bar at the Bottom -->
                <div class="bg-gray-50 border border-gray-100 rounded-md px-4 py-3 flex flex-wrap items-center justify-between gap-3 mt-6 shrink-0 min-h-[54px]">
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 flex-1">
                        <label class="flex items-start gap-3 select-none cursor-pointer flex-1" :class="isSubmitting ? 'opacity-60 cursor-not-allowed' : ''">
                            <input type="checkbox" v-model="termsAccepted" :disabled="isSubmitting"
                                class="w-4 h-4 mt-0.5 rounded-sm border-gray-300 text-blue-600 focus:ring-blue-100 shrink-0" />
                            <div>
                                <span class="text-xs font-semibold text-gray-700 block leading-tight">Saya memastikan data pemesanan sudah benar</span>
                                <span class="text-[10px] text-gray-405 block mt-0.5 leading-tight">Saya bertanggung jawab penuh atas kelengkapan data & menyetujui syarat penggunaan ruangan.</span>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center gap-2">
                        <div v-if="submitError" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-md px-2.5 py-1.5 flex items-center gap-1 shrink-0">
                            <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                            {{ submitError }}
                        </div>

                        <div class="flex items-center gap-2">
                            <button @click="currentStage = 4" :disabled="isSubmitting"
                                class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold py-2 px-3.5 rounded-md transition shadow-sm flex items-center gap-1.5 select-none h-[38px]">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                                Kembali Edit
                            </button>
                            <button @click="submitFinal" :disabled="isSubmitting || !termsAccepted"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-md text-xs transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2 whitespace-nowrap select-none h-[38px]">
                                <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                                {{ isSubmitting ? 'Mengajukan...' : 'Ajukan Booking' }}
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Sub-teks panduan admin diletakkan di luar box bar agar vertikal centering di dalam box bar 100% presisi -->
                <div class="text-right mt-1.5 mr-1">
                    <span class="text-[9px] text-gray-400 select-none">Direview admin maks. 1×24 jam</span>
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
</template>

<style>
/* Sembunyikan indikator scrollbar secara global untuk mencegah pergeseran layout */
::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}
* {
    -ms-overflow-style: none !important;  /* IE dan Edge */
    scrollbar-width: none !important;  /* Firefox */
}
</style>
