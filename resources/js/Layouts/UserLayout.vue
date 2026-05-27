<script setup>
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted, provide, watch } from 'vue'
import Swal from 'sweetalert2'

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

// ── User Notifications & Flash Messages ──────────────────────────────
const isNotificationDropdownOpen = ref(false)
const notificationRef = ref(null)
const userNotifications = computed(() => page.props.userNotifications ?? [])

function toggleNotification() {
    isNotificationDropdownOpen.value = !isNotificationDropdownOpen.value
}

function markAsRead(notificationId) {
    router.post(`/api/notifications/${notificationId}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            // Re-evaluasi otomatis dilakukan oleh Inertia
        }
    })
}

function handleClickOutside(e) {
    if (notificationRef.value && !notificationRef.value.contains(e.target)) {
        isNotificationDropdownOpen.value = false
    }
}

const flashSuccess = ref('')
const flashError = ref('')

watch(() => page.props.flash, (newVal) => {
    if (newVal) {
        if (newVal.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                iconHtml: '<svg class="w-7 h-7 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>',
                title: newVal.success,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                showClass: { popup: 'animate-slide-in-right' },
                hideClass: { popup: 'animate-slide-out-right' },
                customClass: { 
                    popup: 'colored-toast shadow-lg border border-gray-100',
                    icon: '!border-0 !m-0 !mr-3'
                }
            })
        }
        if (newVal.error) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                iconHtml: '<svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>',
                title: newVal.error,
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                showClass: { popup: 'animate-slide-in-right' },
                hideClass: { popup: 'animate-slide-out-right' },
                customClass: { 
                    popup: 'colored-toast shadow-lg border border-gray-100',
                    icon: '!border-0 !m-0 !mr-3'
                }
            })
        }
    }
}, { immediate: true, deep: true })

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
    document.addEventListener('click', handleClickOutside)
    checkWindowStatus() // Cek saat pertama kali load
    pollingInterval = setInterval(checkWindowStatus, 10000) // Polling setiap 10 detik
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
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

                <!-- Dashboard -->
                <Link
                    href="/user/dashboard"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/user/dashboard') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <span class="text-base">🏠</span> Dashboard
                </Link>

                <!-- Divider Label -->
                <div class="pt-2 pb-1 px-3">
                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Booking</p>
                </div>

                <!-- Buat Booking -->
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
                    <span class="text-base">➕</span> Buat Booking Baru
                </Link>

                <!-- Booking Aktif -->
                <Link
                    href="/user/booking/active"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/user/booking/active') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <span class="text-base">📋</span> Booking Aktif
                </Link>

                <!-- Riwayat Booking -->
                <Link
                    href="/user/booking/history"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/user/booking/history') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <span class="text-base">🕒</span> Riwayat Booking
                </Link>

                <!-- Detail Booking (hanya muncul saat user sedang di halaman detail) -->
                <div v-if="currentUrl.includes('/detail')"
                     class="flex items-center gap-2.5 px-3 py-2 rounded text-sm bg-blue-50 text-blue-700 font-semibold border-l-2 border-blue-500 ml-2">
                    <span class="text-base">📄</span> Detail Booking
                </div>

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
                
                <div class="flex items-center gap-4">
                    <!-- Bell Button / Dropdown -->
                    <div class="relative" ref="notificationRef" @click.stop>
                        <button 
                            @click="toggleNotification" 
                            class="relative p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition focus:outline-none"
                            title="Notifikasi"
                        >
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <!-- Badge -->
                            <span 
                                v-if="userNotifications.length > 0" 
                                class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"
                            ></span>
                        </button>

                        <!-- Simple Dropdown Menu -->
                        <div 
                            v-if="isNotificationDropdownOpen" 
                            class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-50 overflow-hidden"
                        >
                            <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-700">Notifikasi Baru</span>
                                <span class="text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded font-bold">{{ userNotifications.length }} Baru</span>
                            </div>
                            
                            <div class="max-h-60 overflow-y-auto divide-y divide-gray-100">
                                <div v-if="userNotifications.length === 0" class="px-4 py-6 text-center text-xs text-gray-400">
                                    Tidak ada notifikasi baru.
                                </div>
                                <div 
                                    v-for="n in userNotifications" 
                                    :key="n.id" 
                                    class="p-3 hover:bg-gray-50 transition text-xs flex gap-2.5 items-start justify-between"
                                >
                                    <div class="space-y-0.5 min-w-0 flex-1">
                                        <div class="font-bold text-gray-800 truncate">{{ n.title }}</div>
                                        <div class="text-gray-600 leading-snug break-words">{{ n.message }}</div>
                                        <div class="text-[9px] text-gray-400 font-semibold">{{ n.created_at }}</div>
                                    </div>
                                    <button 
                                        @click="markAsRead(n.id)" 
                                        class="text-[10px] text-blue-600 hover:text-blue-800 font-semibold shrink-0"
                                        title="Tandai terbaca"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <span class="text-xs text-gray-400 hidden sm:inline">{{ auth.user.email }}</span>
                </div>
            </header>

            <!-- Page Slot -->
            <main class="flex-1 p-6 overflow-auto">
                <slot />
            </main>
        </div>

    </div>
</template>
