<script setup>
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted, provide, watch, watchEffect } from 'vue'
import Swal from 'sweetalert2'

defineProps({
    // Auth is accessed via usePage() — shared globally by HandleInertiaRequests
})

const page = usePage()

// Persistent cache — once auth is set it never goes null during navigation
const _authCache = ref(page.props.auth ?? null)
const auth = computed(() => _authCache.value)

watchEffect(() => {
    const a = page.props.auth
    if (a?.user) _authCache.value = a
})

// Also refresh on every Inertia navigate event (belt-and-suspenders)
const _stopNav = router.on('navigate', () => {
    const a = page.props.auth
    if (a?.user) _authCache.value = a
})

const currentUrl = computed(() => page.url)

const isDetailActive = ref(currentUrl.value.includes('/detail'))

watch(() => currentUrl.value, (newUrl) => {
    isDetailActive.value = newUrl.includes('/detail')
})

let unregisterStartListener = null

function isActive(path) {
    return currentUrl.value.startsWith(path)
}

const logoutForm = useForm({})

function logout() {
    logoutForm.post('/logout')
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

    unregisterStartListener = router.on('start', (event) => {
        const url = event.detail.visit.url
        const pathname = url.pathname || url.toString()
        if (!pathname.includes('/detail')) {
            isDetailActive.value = false
        }
    })
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
    if (pollingInterval) clearInterval(pollingInterval)
    if (unregisterStartListener) unregisterStartListener()
    _stopNav()
})

function toggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value
    if (window.innerWidth >= 1024) {
        localStorage.setItem('user_sidebar_open', isSidebarOpen.value)
    }
}

function handleNav(url, e) {
    if (currentUrl.value === url) return
    if (isDetailActive.value && !url.includes('/detail')) {
        e.preventDefault()
        e.stopPropagation()
        isDetailActive.value = false
        setTimeout(() => {
            router.visit(url)
        }, 450)
    }
}

// Sediakan state ini untuk halaman-halaman anak
provide('isWindowActive', isWindowActive)
provide('collapseDetailMenu', () => {
    isDetailActive.value = false
})
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
            <div class="px-4 py-4 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span class="font-bold text-gray-800 text-sm">BBSO Booking <span class="text-[10px] text-gray-400 font-normal ml-1">User</span></span>
            </div>

            <!-- User Info -->
            <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-xs font-semibold text-gray-800 truncate">{{ auth?.user?.name }}</p>
                <p class="text-[10px] text-gray-500 truncate">{{ auth?.user?.email }}</p>
                <span class="inline-block mt-1 px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-full">USER</span>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">

                <!-- Dashboard -->
                <Link
                    href="/user/dashboard"
                    @click="handleNav('/user/dashboard', $event)"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/user/dashboard') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </Link>

                <!-- ── MANAJEMEN ─────────────────────────────────────── -->
                <div class="px-3 pt-3 pb-1 text-[10px] text-gray-400 font-bold uppercase tracking-wider">Manajemen</div>

                <!-- Buat Booking -->
                <div v-if="!isWindowActive"
                     class="flex items-center justify-between px-3 py-2.5 text-sm text-gray-400 bg-gray-50 rounded-lg cursor-not-allowed border border-gray-100"
                     title="Window Booking sedang ditutup oleh Admin.">
                    <div class="flex items-center gap-3">
                        <svg class="w-[18px] h-[18px] shrink-0 opacity-50" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Buat Booking
                    </div>
                    <span class="text-[10px] font-bold text-red-500">TUTUP</span>
                </div>
                <Link v-else
                    href="/user/booking/create"
                    @click="handleNav('/user/booking/create', $event)"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/user/booking/create') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Buat Booking Baru
                </Link>

                <!-- Booking Aktif -->
                <Link
                    href="/user/booking/active"
                    @click="handleNav('/user/booking/active', $event)"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/user/booking/active') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Booking Aktif
                </Link>

                <!-- Detail Booking sub-menu (animasi slide down) -->
                <Transition name="menu-slide" appear>
                    <div v-if="isDetailActive" class="pl-6 pr-1 shrink-0 overflow-hidden">
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs bg-blue-50/75 text-blue-700 font-bold border-l-2 border-blue-500 shadow-2xs select-none">
                            <svg class="w-3.5 h-3.5 shrink-0 text-blue-500/70" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Detail Booking
                        </div>
                    </div>
                </Transition>

                <!-- Riwayat Booking -->
                <Link
                    href="/user/booking/history"
                    @click="handleNav('/user/booking/history', $event)"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/user/booking/history') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
                    Riwayat Booking
                </Link>

                <!-- ── SISTEM ────────────────────────────────────────── -->
                <div class="px-3 pt-3 pb-1 text-[10px] text-gray-400 font-bold uppercase tracking-wider">Sistem</div>

                <!-- Pengaturan -->
                <Link
                    href="/user/settings/password"
                    @click="handleNav('/user/settings/password', $event)"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/user/settings/password') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Pengaturan
                </Link>

            </nav>

            <div class="px-2 py-3 border-t border-gray-100 space-y-1">

                <!-- Logout -->
                <button
                    @click="logout"
                    :disabled="logoutForm.processing"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded text-sm text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg> Logout
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
                            {{ isActive('/user/booking') ? 'Booking Ruangan' : (isActive('/user/settings') ? 'Pengaturan Password' : 'Dashboard') }}
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
                                class="absolute top-1 right-1 min-w-[16px] h-4 px-0.5 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center leading-none"
                            >{{ userNotifications.length > 9 ? '9+' : userNotifications.length }}</span>
                        </button>

                        <!-- Notification Dropdown -->
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

                    <span class="text-xs text-gray-400 hidden sm:inline">{{ auth?.user?.email }}</span>
                </div>
            </header>

            <!-- Page Slot -->
            <main class="flex-1 p-4 md:p-6 overflow-auto">
                <slot />
            </main>
        </div>
    </div>

</template>

<style scoped>
.menu-slide-enter-active {
  transition: all 0.65s cubic-bezier(0.16, 1, 0.3, 1);
}
.menu-slide-leave-active {
  transition: all 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.menu-slide-enter-from,
.menu-slide-leave-to {
  max-height: 0;
  opacity: 0;
  transform: translateY(-10px);
  padding-top: 0;
  padding-bottom: 0;
  margin-top: 0;
  margin-bottom: 0;
}
.menu-slide-enter-to,
.menu-slide-leave-from {
  max-height: 48px;
  opacity: 1;
  transform: translateY(0);
}
</style>
