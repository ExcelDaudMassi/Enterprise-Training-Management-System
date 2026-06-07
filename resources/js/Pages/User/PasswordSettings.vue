<script setup>
import UserLayout from '@/Layouts/UserLayout.vue'
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'

defineOptions({ layout: UserLayout })

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
    <div class="max-w-xl mx-auto space-y-6">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Password Settings</h1>
                    <p class="text-xs text-gray-500 mt-1">Manage your department's account security by updating the password independently.</p>
                </div>
            </div>

            <!-- Card Utama -->
            <div class="bg-white rounded-xl border border-gray-150 shadow-xs p-6">
                
                <!-- Info Banner -->
                <div class="mb-5 p-3.5 bg-blue-50 border border-blue-100 rounded-lg flex items-start gap-3">
                    <div class="text-blue-600 mt-0.5 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </div>
                    <div class="text-xs text-blue-700 leading-snug">
                        <p class="font-bold">Security Information:</p>
                        <p class="mt-1">Neither the admin nor the system can view your new password. All passwords are encrypted with industry-standard hashing methods to ensure full confidentiality.</p>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    
                    <!-- Password Saat Ini -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-600">Current Password</label>
                        <div class="relative">
                            <input 
                                :type="showCurrent ? 'text' : 'password'" 
                                v-model="form.current_password"
                                class="w-full border border-gray-300 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pr-10"
                                placeholder="Enter your current password"
                                required
                            />
                            <button 
                                type="button" 
                                @click="showCurrent = !showCurrent"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                            >
                                <svg v-if="showCurrent" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.current_password" class="text-xs text-red-500 font-medium">{{ form.errors.current_password }}</p>
                    </div>

                    <hr class="border-gray-100 my-2">

                    <!-- Password Baru -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-600">New Password</label>
                        <div class="relative">
                            <input 
                                :type="showNew ? 'text' : 'password'" 
                                v-model="form.password"
                                class="w-full border border-gray-300 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pr-10"
                                placeholder="Minimum 8 characters"
                                required
                            />
                            <button 
                                type="button" 
                                @click="showNew = !showNew"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                            >
                                <svg v-if="showNew" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                        <!-- Petunjuk Kekuatan/Panjang -->
                        <div class="flex items-center gap-1.5 mt-1 select-none">
                            <span :class="isMinLength ? 'text-emerald-600 font-semibold' : 'text-gray-400'" class="text-[10px] flex items-center gap-1">
                                <svg v-if="isMinLength" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <svg v-else class="w-3.5 h-3.5 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <circle cx="12" cy="12" r="9" />
                                </svg>
                                Minimum 8 characters
                            </span>
                        </div>
                        <p v-if="form.errors.password" class="text-xs text-red-500 font-medium">{{ form.errors.password }}</p>
                    </div>

                    <!-- Konfirmasi Password Baru -->
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-gray-600">Confirm New Password</label>
                        <div class="relative">
                            <input 
                                :type="showConfirm ? 'text' : 'password'" 
                                v-model="form.password_confirmation"
                                class="w-full border border-gray-300 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 pr-10"
                                placeholder="Repeat your new password"
                                required
                            />
                            <button 
                                type="button" 
                                @click="showConfirm = !showConfirm"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                            >
                                <svg v-if="showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                        <!-- Petunjuk Kecocokan -->
                        <div v-if="form.password_confirmation.length > 0" class="flex items-center gap-1.5 mt-1 select-none">
                            <span :class="isMatched ? 'text-emerald-600 font-semibold' : 'text-red-500 font-semibold'" class="text-[10px] flex items-center gap-1">
                                <svg v-if="isMatched" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                {{ isMatched ? 'New passwords match' : 'New passwords do not match' }}
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
                            Cancel
                        </Link>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <span v-if="form.processing" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            {{ form.processing ? 'Saving...' : 'Save Password' }}
                        </button>
                    </div>

                </form>

            </div>

        </div>
</template>
