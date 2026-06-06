<script setup>
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted, watch, watchEffect } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
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
onUnmounted(() => _stopNav())

const currentUrl = computed(() => page.url)

const bookingWindow = computed(() => page.props.bookingWindow)
const notifications = computed(() => page.props.notifications ?? [])

function isActive(path) {
    return currentUrl.value.startsWith(path)
}

const logoutForm  = useForm({})

function logout() { logoutForm.post('/logout') }

// ── Notification dropdown ────────────────────────────────────────
const showNotif = ref(false)
function toggleNotif() { showNotif.value = !showNotif.value }
function closeNotif()  { showNotif.value = false }

function goToBooking(filter) {
    closeNotif()
    router.get('/admin/bookings', { filter })
}

const notifCount = computed(() => notifications.value.length)

const notifTypeStyle = {
    new:     { dot: 'bg-blue-500',   text: 'text-blue-700',  bg: 'hover:bg-blue-50' },
    urgent:  { dot: 'bg-amber-500',  text: 'text-amber-700', bg: 'hover:bg-amber-50' },
    overdue: { dot: 'bg-red-500',    text: 'text-red-700',   bg: 'hover:bg-red-50' },
}

// ── Booking Window modal ─────────────────────────────────────────
const showWindowModal = ref(false)
const windowForm = useForm({ 
    tahun: new Date().getFullYear(),
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '' 
})

function openWindowModal() { showWindowModal.value = true }
function closeWindowModal() { showWindowModal.value = false; windowForm.reset() }

function submitOpenWindow() {
    windowForm.post('/admin/booking-window/open', {
        onSuccess: () => closeWindowModal(),
    })
}

const closeForm = useForm({})

function closeWindow() {
    if (!confirm('Apakah Anda yakin ingin menutup window booking? User tidak akan bisa melakukan pemesanan baru setelah ini.')) return
    closeForm.post('/admin/booking-window/close')
}

// ── Responsive Sidebar Toggle ────────────────────────────────────
const isSidebarOpen = ref(true)

function handleResize() {
    if (window.innerWidth < 1024 && isSidebarOpen.value) {
        isSidebarOpen.value = false
    } else if (window.innerWidth >= 1024 && !isSidebarOpen.value && localStorage.getItem('admin_sidebar_open') === null) {
        isSidebarOpen.value = true
    }
}

onMounted(() => {
    const saved = localStorage.getItem('admin_sidebar_open')
    if (saved !== null) {
        isSidebarOpen.value = saved === 'true'
    } else {
        isSidebarOpen.value = window.innerWidth >= 1024
    }
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    _stopNav()
})

function toggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value
    if (window.innerWidth >= 1024) {
        localStorage.setItem('admin_sidebar_open', isSidebarOpen.value)
    }
}

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
</script>

<template>
    <div class="h-screen bg-gray-100 flex overflow-hidden" @click="closeNotif">

        <!-- Sidebar Backdrop (Mobile only) -->
        <div 
            v-if="isSidebarOpen" 
            @click="isSidebarOpen = false" 
            class="fixed inset-0 bg-black/40 z-30 lg:hidden transition-opacity duration-300"
        ></div>

        <!-- ======================================================= -->
        <!-- Sidebar Admin -->
        <!-- ======================================================= -->
        <aside 
            class="fixed inset-y-0 left-0 z-40 w-56 bg-white border-r border-gray-200 flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out lg:static lg:translate-x-0 h-screen"
            :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:-ml-56'"
        >

            <!-- Logo / Brand -->
            <div class="px-4 py-4 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span class="font-bold text-gray-800 text-sm">BBSO Booking <span class="text-[10px] text-gray-400 font-normal ml-1">Admin</span></span>
            </div>

            <!-- Admin Info -->
            <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-xs font-semibold text-gray-800 truncate">{{ auth?.user?.name }}</p>
                <p class="text-[10px] text-gray-500 truncate">{{ auth?.user?.email }}</p>
                <span class="inline-block mt-1 px-2 py-0.5 bg-red-50 text-red-700 text-[10px] font-bold rounded-full">ADMIN</span>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                <Link
                    href="/admin/dashboard"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/admin/dashboard') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> Dashboard
                </Link>

                <div class="px-3 pt-3 pb-1 text-[10px] text-gray-400 font-bold uppercase tracking-wider">Manajemen</div>

                <Link
                    href="/admin/bookings"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/admin/bookings') && !isActive('/admin/booking-recap') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> Manajemen Booking
                </Link>

                <Link
                    href="/admin/booking-recap"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/admin/booking-recap') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg> Rekap Booking
                </Link>

                <Link
                    href="/admin/booking-windows"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/admin/booking-windows') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0a9 9 0 0118 0z"/></svg> Riwayat Window
                </Link>

                <div class="px-3 pt-3 pb-1 text-[10px] text-gray-400 font-bold uppercase tracking-wider">Master Data</div>

                <Link
                    href="/admin/rooms"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/admin/rooms') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> Master Ruangan
                </Link>

                <div class="px-3 pt-3 pb-1 text-[10px] text-gray-400 font-bold uppercase tracking-wider">Sistem</div>

                <Link
                    href="/admin/settings"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
                    :class="isActive('/admin/settings') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                >
                    <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> Pengaturan
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

            <!-- ── Top Header Bar ─────────────────────────────────── -->
            <header class="bg-white border-b border-gray-200 px-4 lg:px-6 py-0 flex items-center justify-between flex-shrink-0 h-14" @click.stop>

                <!-- Left: Burger Button + Page title -->
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

                    <h1 class="text-sm font-semibold text-gray-700 flex items-center">
                        <span class="text-gray-400">Admin /</span>
                        <span class="ml-1 text-gray-800">Dashboard</span>
                    </h1>
                </div>

                <!-- Right: Window Indicator + Notification Bell + Admin Name -->
                <div class="flex items-center gap-3">

                    <!-- ── Booking Window Indicator ── -->
                    <template v-if="bookingWindow && bookingWindow.is_active">
                        <!-- ACTIVE window -->
                        <div class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-semibold text-emerald-700">Window Booking Aktif</span>
                            <button
                                @click="closeWindow"
                                class="ml-1 text-[10px] text-emerald-700 hover:text-red-600 font-medium transition-colors"
                                title="Tutup window booking"
                            >✕</button>
                        </div>
                    </template>
                    <template v-else>
                        <!-- INACTIVE window (including null) -->
                        <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 border border-gray-200 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                            <span class="text-xs font-semibold text-gray-500">Window Booking Tutup</span>
                            <button
                                @click.stop="openWindowModal"
                                class="ml-1 text-[10px] font-semibold text-blue-600 hover:text-blue-800 transition-colors underline"
                            >Buka</button>
                        </div>
                    </template>

                    <!-- ── Notification Bell ── -->
                    <div class="relative">
                        <button
                            @click.stop="toggleNotif"
                            class="relative w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors"
                            title="Notifikasi"
                        >
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <!-- Badge -->
                            <span v-if="notifCount > 0"
                                  class="absolute top-1 right-1 min-w-[16px] h-4 px-0.5 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center leading-none">
                                {{ notifCount > 9 ? '9+' : notifCount }}
                            </span>
                        </button>
                    </div>

                    <!-- Admin name -->
                    <span class="text-sm text-gray-600 font-medium">{{ auth?.user?.name }}</span>
                </div>
            </header>

            <!-- Page Slot -->
            <main class="flex-1 p-4 md:p-6 overflow-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                <slot />
            </main>
        </div>

    </div>

    <!-- ── Modal: Buka Booking Window ─────────────────────────────── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showWindowModal"
                 class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
                 @click.self="closeWindowModal">

                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                >
                    <div v-if="showWindowModal"
                         class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                        <!-- Modal Header -->
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Buka Window Booking</h2>
                                <p class="text-xs text-gray-500 mt-0.5">User akan bisa mengajukan booking baru setelah window dibuka.</p>
                            </div>
                            <button @click="closeWindowModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <form @submit.prevent="submitOpenWindow" class="px-6 py-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Tahun Target Booking <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    v-model="windowForm.tahun"
                                    required
                                    min="2024"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p v-if="windowForm.errors.tahun" class="text-xs text-red-500 mt-1">{{ windowForm.errors.tahun }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                        Tanggal Mulai <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        v-model="windowForm.start_date"
                                        required
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <p v-if="windowForm.errors.start_date" class="text-xs text-red-500 mt-1">{{ windowForm.errors.start_date }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                        Tanggal Penutupan <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        v-model="windowForm.end_date"
                                        :min="windowForm.start_date || new Date().toISOString().slice(0,10)"
                                        required
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <p v-if="windowForm.errors.end_date" class="text-xs text-red-500 mt-1">{{ windowForm.errors.end_date }}</p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mt-1 pt-1">User dapat mengajukan booking selama window ini aktif.</p>

                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="closeWindowModal"
                                    class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="windowForm.processing || !windowForm.end_date"
                                    class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors"
                                >
                                    {{ windowForm.processing ? 'Membuka...' : '✓ Buka Window' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
