<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted, provide, watch } from 'vue'

defineProps({
    auth: Object,
})

const page = usePage()
const currentUrl = computed(() => page.url)

function isActive(path) {
    return currentUrl.value.startsWith(path)
}

const logoutForm = useForm({})
const switchForm = useForm({})

function logout() {
    logoutForm.post('/logout')
}

function switchRole() {
    switchForm.post('/dev/switch-role')
}

// ── Realtime Booking Window Polling ──────────────────────────────────
const isWindowActive = ref(page.props.bookingWindow?.is_active ?? true)
let pollingInterval = null

watch(() => page.props.bookingWindow, (newVal) => {
    if (newVal) {
        isWindowActive.value = newVal.is_active
    }
}, { deep: true })

async function checkWindowStatus() {
    try {
        const res = await fetch('/api/booking-window/status', {
            headers: { 'Accept': 'application/json' }
        })
        if (res.ok) {
            const data = await res.json()
            isWindowActive.value = data.is_active
        }
    } catch (e) {
        // Abaikan error jaringan sementara
    }
}

// ── Responsive Sidebar Toggle ────────────────────────────────────
const isSidebarOpen = ref(true)

function handleResize() {
    if (window.innerWidth < 1024 && isSidebarOpen.value) {
        isSidebarOpen.value = false
    } else if (window.innerWidth >= 1024 && !isSidebarOpen.value && localStorage.getItem('user_sidebar_open') === null) {
        isSidebarOpen.value = true
    }
}

onMounted(() => {
    const saved = localStorage.getItem('user_sidebar_open')
    if (saved !== null) {
        isSidebarOpen.value = saved === 'true'
    } else {
        isSidebarOpen.value = window.innerWidth >= 1024
    }
    window.addEventListener('resize', handleResize)
    checkWindowStatus() // Cek saat pertama kali load
    pollingInterval = setInterval(checkWindowStatus, 10000) // Polling setiap 10 detik
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (pollingInterval) clearInterval(pollingInterval)
})

function toggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value
    if (window.innerWidth >= 1024) {
        localStorage.setItem('user_sidebar_open', isSidebarOpen.value)
    }
}

// Sediakan state ini untuk halaman-halaman anak
provide('isWindowActive', isWindowActive)
</script>

<template>
    <div class="h-screen bg-gray-100 flex overflow-hidden">

        <!-- Sidebar Backdrop (Mobile only) -->
        <div 
            v-if="isSidebarOpen" 
            @click="isSidebarOpen = false" 
            class="fixed inset-0 bg-black/40 z-30 lg:hidden transition-opacity duration-300"
        ></div>

        <!-- ======================================================= -->
        <!-- Sidebar -->
        <!-- ======================================================= -->
        <aside 
            class="fixed inset-y-0 left-0 z-40 w-56 bg-white border-r border-gray-200 flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out lg:static lg:translate-x-0 h-screen"
            :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:-ml-56'"
        >

            <!-- Logo / Brand -->
            <div class="px-4 py-4 border-b border-gray-100">
                <span class="font-bold text-gray-800 text-sm">📋 BBSO Booking</span>
                <p class="text-[10px] text-gray-400 mt-0.5">User Panel</p>
            </div>

            <!-- User Info -->
            <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-xs font-semibold text-gray-800 truncate">{{ auth.user.name }}</p>
                <p class="text-[10px] text-gray-500 truncate">{{ auth.user.divisi ?? '-' }}</p>
                <span class="inline-block mt-1 px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-full">USER</span>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 px-2 py-3 space-y-0.5">
                <Link
                    href="/user/dashboard"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/user/dashboard') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <span class="text-base">🏠</span> Dashboard
                </Link>

                <div v-if="!isWindowActive"
                     class="flex items-center justify-between px-3 py-2 text-sm text-gray-400 bg-gray-50 rounded cursor-not-allowed border border-gray-100"
                     title="Window Booking sedang ditutup oleh Admin.">
                    <div class="flex items-center gap-2.5">
                        <span class="text-base opacity-50">➕</span> Buat Booking
                    </div>
                    <span class="text-[10px] font-bold text-red-500">TUTUP</span>
                </div>
                <Link v-else
                    href="/user/booking/create"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/user/booking/create') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <span class="text-base">➕</span> Buat Booking
                </Link>

                <Link
                    href="/user/booking/history"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/user/booking/history') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <span class="text-base">🕒</span> Riwayat
                </Link>
            </nav>

            <!-- Bottom Actions -->
            <div class="px-2 py-3 border-t border-gray-100 space-y-1">
                <!-- Switch Role (Dev Only) -->
                <button
                    @click="switchRole"
                    :disabled="switchForm.processing"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded text-sm text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors font-medium"
                    title="Switch ke Admin (Dev Only)"
                >
                    <span class="text-base">🔄</span>
                    <span class="truncate">{{ switchForm.processing ? 'Switching...' : 'Switch ke Admin' }}</span>
                </button>

                <!-- Logout -->
                <button
                    @click="logout"
                    :disabled="logoutForm.processing"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded text-sm text-red-600 hover:bg-red-50 transition-colors"
                >
                    <span class="text-base">🚪</span> Logout
                </button>
            </div>
        </aside>

        <!-- ======================================================= -->
        <!-- Main Content Area -->
        <!-- ======================================================= -->
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden transition-all duration-300 ease-in-out">
            <!-- Top bar -->
            <header class="bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3">
                    <button 
                        @click="toggleSidebar" 
                        class="p-1.5 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-200"
                        title="Toggle Sidebar"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <h1 class="text-sm font-semibold text-gray-700">
                        <span class="text-gray-400">User /</span>
                        <span class="ml-1 text-gray-800">
                            {{ isActive('/user/booking') ? 'Booking Ruangan' : 'Dashboard' }}
                        </span>
                    </h1>
                </div>
                <span class="text-xs text-gray-400 hidden sm:inline">{{ auth.user.email }}</span>
            </header>

            <!-- Page Slot -->
            <main class="flex-1 p-6 overflow-auto">
                <slot />
            </main>
        </div>

    </div>
</template>
