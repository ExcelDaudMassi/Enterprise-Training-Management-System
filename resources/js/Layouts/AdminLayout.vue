<script setup>
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted, watch } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
    auth: Object,
})

const page = usePage()
const currentUrl = computed(() => page.url)

const bookingWindow = computed(() => page.props.bookingWindow)
const notifications = computed(() => page.props.notifications ?? [])

function isActive(path) {
    return currentUrl.value.startsWith(path)
}

const logoutForm  = useForm({})
const switchForm  = useForm({})

function logout() { logoutForm.post('/logout') }
function switchRole() { switchForm.post('/dev/switch-role') }

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
                position: 'bottom-end',
                text: newVal.success,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: false,
                icon: false,
                background: '#18181b', // Zinc 900
                color: '#fafafa', // Zinc 50
                showClass: { popup: 'swal2-noanimation' },
                hideClass: { popup: 'swal2-noanimation' },
                customClass: { 
                    popup: '!rounded-lg !shadow-2xl !border !border-gray-800 !py-2 !px-4 !mb-4 !mr-4',
                    title: '!text-sm !font-medium !m-0 !p-0'
                }
            })
        }
        if (newVal.error) {
            Swal.fire({
                toast: true,
                position: 'bottom-end',
                text: newVal.error,
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: false,
                icon: false,
                background: '#7f1d1d', // Red 900
                color: '#fef2f2', // Red 50
                showClass: { popup: 'swal2-noanimation' },
                hideClass: { popup: 'swal2-noanimation' },
                customClass: { 
                    popup: '!rounded-lg !shadow-2xl !border !border-red-800 !py-2 !px-4 !mb-4 !mr-4',
                    title: '!text-sm !font-medium !m-0 !p-0'
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
            class="fixed inset-y-0 left-0 z-40 w-56 bg-gray-900 text-gray-100 flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out lg:static lg:translate-x-0 h-screen"
            :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:-ml-56'"
        >

            <!-- Logo / Brand -->
            <div class="px-4 py-4 border-b border-gray-700">
                <span class="font-bold text-white text-sm">📋 BBSO Booking</span>
                <p class="text-[10px] text-gray-500 mt-0.5">Admin Panel</p>
            </div>

            <!-- Admin Info -->
            <div class="px-4 py-3 border-b border-gray-700">
                <p class="text-xs font-semibold text-white truncate">{{ auth.user.name }}</p>
                <p class="text-[10px] text-gray-400 truncate">{{ auth.user.email }}</p>
                <span class="inline-block mt-1 px-2 py-0.5 bg-red-900 text-red-300 text-[10px] font-bold rounded-full">ADMIN</span>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 px-2 py-3 space-y-0.5">
                <Link
                    href="/admin/dashboard"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/admin/dashboard') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100'"
                >
                    <span class="text-base">🏠</span> Dashboard
                </Link>

                <div class="px-3 py-2 text-[10px] text-gray-600 uppercase tracking-wider mt-2">Manajemen</div>

                <Link
                    href="/admin/bookings"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/admin/bookings') && !isActive('/admin/booking-recap') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100'"
                >
                    <span class="text-base">⚙️</span> Manajemen Booking
                </Link>

                <Link
                    href="/admin/booking-recap"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/admin/booking-recap') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100'"
                >
                    <span class="text-base">📋</span> Rekap Booking
                </Link>

                <Link
                    href="/admin/booking-windows"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/admin/booking-windows') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100'"
                >
                    <span class="text-base">📅</span> Riwayat Window
                </Link>

                <div class="px-3 py-2 text-[10px] text-gray-600 uppercase tracking-wider mt-2">Master Data</div>

                <Link
                    href="/admin/rooms"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/admin/rooms') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100'"
                >
                    <span class="text-base">🏢</span> Master Ruangan
                </Link>

                <div class="px-3 py-2 text-[10px] text-gray-600 uppercase tracking-wider mt-2">Sistem</div>

                <Link
                    href="/admin/settings"
                    class="flex items-center gap-2.5 px-3 py-2 rounded text-sm transition-colors"
                    :class="isActive('/admin/settings') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-100'"
                >
                    <span class="text-base">⚙️</span> Pengaturan
                </Link>
            </nav>

            <!-- Bottom Actions -->
            <div class="px-2 py-3 border-t border-gray-700 space-y-1">
                <!-- Switch Role (Dev Only) -->
                <button
                    @click="switchRole"
                    :disabled="switchForm.processing"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded text-sm text-amber-400 hover:bg-gray-800 transition-colors font-medium"
                    title="Switch ke User (Dev Only)"
                >
                    <span class="text-base">🔄</span>
                    <span class="truncate">{{ switchForm.processing ? 'Switching...' : 'Switch ke User' }}</span>
                </button>

                <!-- Logout -->
                <button
                    @click="logout"
                    :disabled="logoutForm.processing"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded text-sm text-red-400 hover:bg-gray-800 transition-colors"
                >
                    <span class="text-base">🚪</span> Logout
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
                                  class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center leading-none">
                                {{ notifCount > 9 ? '9+' : notifCount }}
                            </span>
                        </button>

                        <!-- Notification Dropdown -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 scale-95 translate-y-1"
                            enter-to-class="opacity-100 scale-100 translate-y-0"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 scale-100 translate-y-0"
                            leave-to-class="opacity-0 scale-95 translate-y-1"
                        >
                            <div v-if="showNotif"
                                 class="absolute right-0 top-11 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden"
                                  @click.stop
                            >
                                <!-- Header -->
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-800">Notifikasi</span>
                                    <span v-if="notifCount > 0"
                                          class="text-xs bg-red-100 text-red-700 font-bold px-2 py-0.5 rounded-full">
                                        {{ notifCount }} baru
                                    </span>
                                </div>

                                <!-- Notification List -->
                                <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                                    <p v-if="notifCount === 0"
                                       class="px-4 py-6 text-center text-sm text-gray-400">
                                        Tidak ada notifikasi.
                                    </p>

                                    <button
                                        v-for="n in notifications"
                                        :key="n.booking_id + n.type"
                                        @click="goToBooking(n.filter)"
                                        class="w-full text-left px-4 py-3 transition-colors flex items-start gap-3"
                                        :class="notifTypeStyle[n.type]?.bg ?? 'hover:bg-gray-50'"
                                    >
                                        <!-- Type dot -->
                                        <span class="mt-1.5 w-2 h-2 rounded-full flex-shrink-0"
                                              :class="notifTypeStyle[n.type]?.dot ?? 'bg-gray-400'"></span>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-gray-800 truncate">{{ n.label }}</p>
                                            <p class="text-[11px] text-gray-500 truncate">{{ n.sub }}</p>
                                            <p class="text-[10px] text-gray-400 mt-0.5">{{ n.created_at }}</p>
                                        </div>
                                        <svg class="w-3.5 h-3.5 text-gray-300 flex-shrink-0 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Footer -->
                                <div class="px-4 py-2 border-t border-gray-100 bg-gray-50">
                                    <Link href="/admin/bookings"
                                          class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                                          @click="closeNotif">
                                        Lihat semua booking →
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Admin name -->
                    <span class="text-sm text-gray-600 font-medium">{{ auth.user.name }}</span>
                </div>
            </header>

            <!-- Page Slot -->
            <main class="flex-1 p-6 overflow-auto">
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
