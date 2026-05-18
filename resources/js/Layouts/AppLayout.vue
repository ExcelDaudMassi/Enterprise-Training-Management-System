<script setup>
import { useForm } from '@inertiajs/vue3'

defineProps({
    auth: Object,
})

const logoutForm = useForm({})

function logout() {
    logoutForm.post('/logout')
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Navbar -->
        <nav class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
            <div class="font-bold text-gray-800">BBSO Booking</div>
            <div class="flex items-center gap-4 text-sm text-gray-600">
                <span>{{ auth.user.name }}</span>
                <span class="text-gray-400">|</span>
                <span>{{ auth.user.divisi ?? 'Admin' }}</span>
                <button
                    @click="logout"
                    class="ml-2 text-red-500 hover:text-red-700 text-sm"
                >
                    Logout
                </button>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="p-6">
            <slot />
        </main>
    </div>
</template>
