# Catatan Implementasi: Peningkatan UX Tahap 2

## 1. Global Toast Notifications (SweetAlert2)
Lokasi: UserLayout.vue & AdminLayout.vue`n- Menggantikan flash message standar dengan SweetAlert2 toast.
- **Desain Khusus**: Menggunakan animasi kustom CSS (\slideInRight\) agar notifikasi meluncur mulus dari sisi kanan layar, bukan memantul (bounce).
- **Ikon Kustom**: Ikon animasi bawaan diganti dengan ikon SVG murni yang statis dan tegas (ceklis hijau dan silang merah) tanpa efek melingkar.
- **Tampilan**: Elegan dengan kotak putih, shadow halus, dan progress bar otomatis 3-4 detik.

## 2. Skeleton Loading UI
Lokasi: BookingApproval.vue`n- Menggantikan efek spinner (lingkaran berputar) standar saat Admin melihat detail booking.
- **Cara Kerja**: Menampilkan blok-blok kerangka (skeleton) yang beranimasi kedip (pulse/shimmer) selama proses fetch API berjalan, memberikan kesan mulus (seamless transition) pada pergantian data UI.

## 3. Donut Chart Interaktif (ApexCharts)
Lokasi: Admin/Dashboard.vue`n- Mengintegrasikan pustaka ue3-apexcharts untuk memvisualisasikan proporsi data booking secara langsung.
- **Data yang Ditampilkan**: Menunggu ACC, Disetujui, Plotting, dan Dibatalkan.
- **Tata Letak**: Diposisikan pada sistem Grid bersandingan dengan Notifikasi Terbaru agar memanfaatkan ruang lebar layar (Dashboard Admin) secara optimal.