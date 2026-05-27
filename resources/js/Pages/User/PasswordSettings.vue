<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'

defineProps({
    auth: Object,
})

// State form menggunakan useForm dari Inertia
const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

// Toggle visibility password
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

// Validasi lokal di frontend
const isMinLength = computed(() => form.password.length >= 8)
const isMatched = computed(() => form.password.length > 0 && form.password === form.password_confirmation)

function submit() {
    form.post('/user/settings/password', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
        },
        onError: () => {
            // Error ditangani secara otomatis oleh Inertia share errors
        }
    })
}
</script>

<template>
    <UserLayout :auth="auth">
        <div class="max-w-xl mx-auto space-y-6">

            <!-- Header -->
            <div>
                <h1 class="text-xl font-bold text-gray-800">🔐 Pengaturan Password</h1>
                <p class="text-xs text-gray-500 mt-1">Kelola keamanan akun departemen Anda dengan memperbarui password secara mandiri.</p>
            </div>

            <!-- Card Utama -->
            <div class="bg-white rounded-xl border border-gray-150 shadow-xs p-6">
                
                <!-- Info Banner -->
                <div class="mb-5 p-3.5 bg-blue-50 border border-blue-100 rounded-lg flex items-start gap-3">
                    <span class="text-base mt-0.5">ℹ️</span>
                    <div class="text-xs text-blue-700 leading-snug">
                        <p class="font-bold">Informasi Keamanan:</p>
                        <p class="mt-1">Admin maupun sistem tidak dapat melihat password baru Anda. Seluruh password dienkripsi dengan metode hash berstandar industri demi menjamin kerahasiaan penuh.</p>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    
                    <!-- Password Saat Ini -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-600">Password Saat Ini</label>
                        <div class="relative">
                            <input 
                                :type="showCurrent ? 'text' : 'password'" 
                                v-model="form.current_password"
                                class="w-full border border-gray-300 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pr-10"
                                placeholder="Masukkan password Anda sekarang"
                                required
                            />
                            <button 
                                type="button" 
                                @click="showCurrent = !showCurrent"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                            >
                                <span v-if="showCurrent" class="text-sm select-none" title="Sembunyikan">👁️</span>
                                <span v-else class="text-sm select-none" title="Tampilkan">🙈</span>
                            </button>
                        </div>
                        <p v-if="form.errors.current_password" class="text-xs text-red-500 font-medium">{{ form.errors.current_password }}</p>
                    </div>

                    <hr class="border-gray-100 my-2">

                    <!-- Password Baru -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-600">Password Baru</label>
                        <div class="relative">
                            <input 
                                :type="showNew ? 'text' : 'password'" 
                                v-model="form.password"
                                class="w-full border border-gray-300 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pr-10"
                                placeholder="Minimal 8 karakter"
                                required
                            />
                            <button 
                                type="button" 
                                @click="showNew = !showNew"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                            >
                                <span v-if="showNew" class="text-sm select-none" title="Sembunyikan">👁️</span>
                                <span v-else class="text-sm select-none" title="Tampilkan">🙈</span>
                            </button>
                        </div>
                        <!-- Petunjuk Kekuatan/Panjang -->
                        <div class="flex items-center gap-1.5 mt-1 select-none">
                            <span :class="isMinLength ? 'text-emerald-500' : 'text-gray-350'" class="text-[10px]">
                                {{ isMinLength ? '✅' : '⚪' }} Minimal 8 karakter
                            </span>
                        </div>
                        <p v-if="form.errors.password" class="text-xs text-red-500 font-medium">{{ form.errors.password }}</p>
                    </div>

                    <!-- Konfirmasi Password Baru -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-600">Konfirmasi Password Baru</label>
                        <div class="relative">
                            <input 
                                :type="showConfirm ? 'text' : 'password'" 
                                v-model="form.password_confirmation"
                                class="w-full border border-gray-300 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pr-10"
                                placeholder="Ulangi password baru Anda"
                                required
                            />
                            <button 
                                type="button" 
                                @click="showConfirm = !showConfirm"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                            >
                                <span v-if="showConfirm" class="text-sm select-none" title="Sembunyikan">👁️</span>
                                <span v-else class="text-sm select-none" title="Tampilkan">🙈</span>
                            </button>
                        </div>
                        <!-- Petunjuk Kecocokan -->
                        <div v-if="form.password_confirmation.length > 0" class="flex items-center gap-1.5 mt-1 select-none">
                            <span :class="isMatched ? 'text-emerald-500' : 'text-red-500'" class="text-[10px] font-medium">
                                {{ isMatched ? '✅ Password baru cocok' : '❌ Password baru tidak cocok' }}
                            </span>
                        </div>
                        <p v-if="form.errors.password_confirmation" class="text-xs text-red-500 font-medium">{{ form.errors.password_confirmation }}</p>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 mt-6">
                        <Link 
                            href="/user/dashboard" 
                            class="px-4 py-2 border border-gray-250 rounded-lg text-xs font-semibold text-gray-600 bg-white hover:bg-gray-50 transition"
                        >
                            Batal
                        </Link>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <span v-if="form.processing" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Password' }}
                        </button>
                    </div>

                </form>

            </div>

        </div>
    </UserLayout>
</template>
