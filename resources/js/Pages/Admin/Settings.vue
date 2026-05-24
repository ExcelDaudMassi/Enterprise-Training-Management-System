<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'

defineOptions({ layout: AdminLayout })

const mode = ref('manual')
const isSaving = ref(false)
const isLoading = ref(true)
const successMessage = ref('')
const errorMessage = ref('')

onMounted(async () => {
    try {
        const response = await axios.get('/admin/api/settings/h14-mode')
        mode.value = response.data.h14_mode
    } catch (error) {
        console.error('Failed to fetch settings:', error)
        errorMessage.value = 'Gagal memuat pengaturan.'
    } finally {
        isLoading.value = false
    }
})

const saveSettings = async () => {
    isSaving.value = true
    successMessage.value = ''
    errorMessage.value = ''
    
    try {
        const response = await axios.post('/admin/api/settings/h14-mode', {
            h14_mode: mode.value
        })
        successMessage.value = response.data.message
    } catch (error) {
        console.error('Failed to save settings:', error)
        errorMessage.value = error.response?.data?.message || 'Gagal menyimpan pengaturan.'
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <Head title="Pengaturan Sistem" />

    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Pengaturan Sistem</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola konfigurasi dan mode otomatisasi aplikasi.</p>
        </div>

        <!-- Feedback Messages -->
        <div v-if="successMessage" class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800 flex items-start justify-between">
            <span>{{ successMessage }}</span>
            <button @click="successMessage = ''" class="text-emerald-500 hover:text-emerald-700 font-semibold ml-2">✕</button>
        </div>
        <div v-if="errorMessage" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800 flex items-start justify-between">
            <span>{{ errorMessage }}</span>
            <button @click="errorMessage = ''" class="text-red-500 hover:text-red-700 font-semibold ml-2">✕</button>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Mode Pemrosesan H-14</h2>
                <p class="text-sm text-gray-500 mt-1">Pilih bagaimana sistem menangani booking yang sudah melewati batas H-14 dari tanggal mulai namun belum di-ACC final.</p>
            </div>

            <div class="p-6">
                <div v-if="isLoading" class="flex justify-center py-8">
                    <span class="text-gray-400 animate-pulse">Memuat pengaturan...</span>
                </div>
                
                <form v-else @submit.prevent="saveSettings" class="space-y-6">
                    
                    <div class="space-y-4">
                        <!-- Option: Manual -->
                        <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-colors"
                               :class="mode === 'manual' ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500' : 'border-gray-200 hover:bg-gray-50'">
                            <div class="flex items-center h-5">
                                <input type="radio" v-model="mode" value="manual" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            </div>
                            <div class="flex-1">
                                <span class="block text-sm font-semibold text-gray-900">Manual (Bawaan)</span>
                                <span class="block text-xs text-gray-500 mt-1">Sistem tidak akan melakukan apa-apa. Admin harus secara manual meninjau dan mengklik ACC Final atau Batal. Memiliki risiko human-error jika Admin lupa.</span>
                            </div>
                        </label>

                        <!-- Option: Otomatis ACC -->
                        <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-colors"
                               :class="mode === 'auto_acc' ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500' : 'border-gray-200 hover:bg-gray-50'">
                            <div class="flex items-center h-5">
                                <input type="radio" v-model="mode" value="auto_acc" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            </div>
                            <div class="flex-1">
                                <span class="block text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    Otomatis ACC
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-700 rounded">Disarankan</span>
                                </span>
                                <span class="block text-xs text-gray-500 mt-1">Booking yang sudah masuk batas H-14 akan secara otomatis diubah menjadi <b>Final (ACC 2)</b>. Ini memastikan Tim Lapangan tetap mendapatkan instruksi persiapan.</span>
                            </div>
                        </label>

                        <!-- Option: Otomatis Batal -->
                        <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-colors"
                               :class="mode === 'auto_cancel' ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500' : 'border-gray-200 hover:bg-gray-50'">
                            <div class="flex items-center h-5">
                                <input type="radio" v-model="mode" value="auto_cancel" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            </div>
                            <div class="flex-1">
                                <span class="block text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    Otomatis Batal
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-red-100 text-red-700 rounded">Ketat</span>
                                </span>
                                <span class="block text-xs text-gray-500 mt-1">Booking yang sudah masuk batas H-14 namun belum ada tindak lanjut akan secara otomatis <b>Dibatalkan</b>. Ini mencegah ruangan digantung (mengurangi booking fiktif).</span>
                            </div>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex justify-end">
                        <button type="submit" 
                                :disabled="isSaving"
                                class="px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50 flex items-center gap-2">
                            <svg v-if="isSaving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isSaving ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
