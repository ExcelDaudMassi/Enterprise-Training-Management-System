# Dokumentasi Sistem: Enterprise Training Management System

## 1. Tujuan Sistem
Sistem ini dirancang untuk memfasilitasi, mengelola, dan mengotomatisasi proses pemesanan (booking) ruangan untuk keperluan *training* di perusahaan. Tujuan utamanya adalah mencegah bentrok jadwal (double-booking), mempermudah alur persetujuan secara berjenjang, memberikan transparansi status kepada pemohon, dan mengotomatisasi notifikasi ke pihak terkait (seperti Frontdesk) saat jadwal telah dikonfirmasi.

## 2. Permasalahan yang Diselesaikan
- **Bentrok Jadwal Ruangan:** Sistem secara otomatis memvalidasi ketersediaan ruangan sebelum pemesanan dibuat.
- **Birokrasi Persetujuan Manual:** Menggantikan proses manual dengan alur kerja (workflow) digital berjenjang (Plotting -> ACC 1 -> ACC 2/Final).
- **Komunikasi yang Kurang Cepat:** Otomatisasi notifikasi melalui WhatsApp (integrasi Fonnte) memastikan Frontdesk segera mengetahui saat jadwal telah final.
- **Pengelolaan Data Peserta:** Pemohon dapat mengunggah atau memasukkan daftar peserta, dan admin dapat mengekspor data tersebut ke dalam file Excel terstruktur.
- **Keteraturan Waktu Booking:** Diterapkannya sistem *Booking Window* untuk membatasi rentang waktu kapan pemohon diizinkan mengajukan jadwal.

## 3. Teknologi yang Digunakan
- **Backend:** PHP 8.3 dengan Framework Laravel 11.x.
- **Frontend:** Vue.js 3 (Composition API) terintegrasi via Inertia.js.
- **Styling:** Tailwind CSS v4.
- **Database:** SQLite (Default, dapat bermigrasi ke MySQL/PostgreSQL).
- **Excel Generator:** PhpSpreadsheet.
- **Notification API:** Fonnte (WhatsApp Gateway).
- **Calendar:** FullCalendar Vue 3.

## 4. Arsitektur Sistem
Sistem menggunakan arsitektur **Monolith Modern** berbasis *Model-View-Controller (MVC)* Laravel di sisi backend, digabungkan dengan **Single Page Application (SPA)** Vue.js di sisi frontend melalui Inertia.js.
- **Routing & Controller:** Laravel menangani rute web dan API, serta logika bisnis di layer Controller.
- **State Management & UI:** Vue.js + Inertia.js menangani perenderan dinamis di client tanpa *full-page reload*.
- **Asynchronous Task:** Queue Worker digunakan untuk memproses pengiriman pesan WhatsApp agar tidak memblokir respon HTTP pengguna.

## 5. Struktur Folder dan Penjelasannya
- `app/Http/Controllers`: Menyimpan logika bisnis yang menjembatani permintaan (request) dan model data. Terbagi menjadi sub-folder `Admin` dan `User`.
- `app/Models`: Representasi struktur tabel database dan relasi ORM (Booking, User, Ruangan, dll).
- `app/Services`: Berisi class spesifik untuk layanan eksternal atau fungsionalitas kompleks (contoh: `FonnteService.php`, `BookingExcelExportService.php`).
- `app/Jobs`: Menyimpan class antrean asinkronus (contoh: `SendWhatsAppNotification.php`).
- `database/migrations`: Menyimpan skema pembentukan tabel database.
- `resources/js/Pages`: Direktori komponen Vue.js yang bertindak sebagai halaman utama (views) frontend.
- `routes/web.php`: Berisi definisi rute aplikasi, grup middleware, dan signed-route.

## 6. Database dan Relasi Tabel
### Tabel Utama
1. **users**: Menyimpan data akun pengguna (Admin & User biasa). Memiliki kolom `role`, `divisi`, `site`.
2. **ruangan**: Master data ruangan, kapasitas, dan relasi "ruang gabungan" (bisa digabung/pasangan).
3. **bookings**: Menyimpan data inti pemesanan jadwal (tgl mulai, selesai, pic, status, is_hybrid). Berelasi `BelongsTo` ke `users` dan `ruangan`.
4. **booking_participants**: Relasi `One-to-Many` dari tabel `bookings`. Menyimpan detail nama, NRP, no HP peserta/panitia.
5. **booking_windows**: Mengontrol periode dibukanya keran pengajuan jadwal oleh admin.
6. **settings**: Menyimpan konfigurasi global (contoh: mode H-14).

### Relasi ERD Ringkas
- `User` (1) memiliki banyak (M) `Booking`.
- `Ruangan` (1) memiliki banyak (M) `Booking`.
- `Booking` (1) memiliki banyak (M) `BookingParticipant`.
- `Ruangan` memiliki relasi rekursif ke dirinya sendiri (untuk *ruang gabungan*).

## 7. Daftar Fitur
- Authentication (Login/Logout).
- Role-based Access Control (Admin & User).
- **User Dashboard**: Statistik jadwal dan kalender interaktif.
- **Booking Wizard (User)**: Formulir pemesanan multi-tahap (ketersediaan, detail acara, daftar peserta, ulasan).
- **Manajemen Booking (User)**: Membatalkan jadwal, mengajukan perubahan tanggal (reschedule), dan mengupdate peserta.
- **Admin Dashboard**: Statistik global dan pantauan jadwal.
- **Booking Approval (Admin)**: Alur ACC (ACC 1, ACC Final, ACC Terlambat/H-14).
- **Booking Window (Admin)**: Buka/Tutup periode pengajuan.
- **Master Ruangan (Admin)**: Tambah/Edit ketersediaan ruangan fisik.
- **WhatsApp Notification (Sistem)**: Terkirim otomatis ke Frontdesk saat booking mencapai status FINAL.
- **Public Shareable Link (Sistem)**: Tautan valid 7 hari via *Signed URL* untuk melihat ringkasan booking & download Excel tanpa login.
- **Export to Excel / PDF**: Detail list peserta & panitia.

## 8. Penjelasan Detail Setiap Fitur
- **Alur Persetujuan (Approval Flow):** Jadwal baru berstatus `plotting`. Jika diajukan, status naik ke `waiting_confirmation` -> di ACC Admin menjadi `confirmed` -> di ACC Final menjadi `final`. Jika lewat H-14, memerlukan catatan khusus (ACC Terlambat).
- **Validasi Ketersediaan Ruangan:** Sebelum *form* disubmit, API akan mengecek jika ada jadwal beririsan pada ruangan yang sama.
- **Notifikasi WA:** Menggunakan `FonnteService` yang dijalankan di belakang layar (`queue`). Pesan otomatis memperpendek URL panjang (*Signed URL*) menggunakan TinyURL.
- **Signed URL:** Fitur keamanan bawaan Laravel yang menciptakan tautan berekstensi *hash*. Mencegah akses publik terhadap data yang belum tervalidasi.

## 9. Alur Kerja Sistem (Workflow)
1. Admin membuka *Booking Window*.
2. Pemohon (User) mengajukan pesanan lewat *Booking Wizard*.
3. Admin meninjau data di menu persetujuan dan menekan "ACC 1" (Confirmed).
4. Menjelang hari H, Admin menekan "ACC Final" (Final).
5. Sistem meng-generate tautan publik, mengirim pesan WhatsApp ke Frontdesk.
6. Frontdesk mengklik tautan, melihat *layout* ruangan, jumlah peserta, dan mendownload Excel peserta jika perlu.
7. Selesai. Acara berlangsung.

## 10. Hak Akses Pengguna (Role & Permission)
- **Admin**: Akses penuh ke dashboard admin, menu persetujuan, manajemen master ruang, pembukaan *booking window*, dan *settings*. Tidak bisa mengajukan form booking biasa (meski terdapat fitur *switch role* untuk *developer*).
- **User**: Akses ke dashboard user, kalender jadwal pribadi, riwayat booking, dan manajemen *reschedule*. Tidak bisa menyetujui (ACC) pemesanan.

## 11. Desain UI/UX
- Pendekatan **Minimalis & Profesional**: Menggunakan warna solid dan palet netral.
- **Interaktif**: Validasi formulir secara *real-time*. Halaman *booking wizard* yang dibagi menjadi *step-by-step* untuk mengurangi *cognitive load* pengguna.
- **Responsif**: Dapat diakses di desktop, tablet, maupun layar ponsel pintar dengan layout yang menyesuaikan.

## 12. Penjelasan Halaman per Halaman
- `/login`: Halaman otentikasi.
- `/user/dashboard`: *Landing page* bagi user. Memuat widget statistik (Pending, Disetujui) dan kalender.
- `/user/booking/create`: 4 tahap *wizard* pemesanan.
- `/user/booking/history`: Tabel riwayat pengajuan milik user.
- `/admin/dashboard`: Ringkasan okupansi ruangan.
- `/admin/bookings`: Daftar panjang seluruh permohonan yang menunggu di-ACC.
- `/admin/rooms`: Manajemen entitas ruangan fisik.
- `/booking/{id}/share`: Halaman publik elegan bagi pihak eksternal/Frontdesk untuk melihat ringkasan pesanan.

## 13. API dan Endpoint Internal
Sistem lebih condong ke SPA internal. Namun beberapa *endpoint* AJAX disediakan:
- `POST /api/booking/get-available-rooms`: Menerima rentang tanggal, mengembalikan daftar ID ruang kosong.
- `POST /api/booking/submit`: Memproses data lengkap wizard tahap 4 ke database.
- `GET /api/booking-window/status`: *Polling* status keran pemesanan.

## 14. Validasi dan Keamanan Sistem
- **CSRF Token:** Diamankan bawaan Laravel (via Header di Axios/Inertia).
- **SQL Injection Prevention:** Penggunaan ORM Eloquent.
- **XSS Protection:** Blade/Vue otomatis melakukan *escaping* pada input.
- **Signed URL:** URL *share* tidak bisa dipalsukan (memiliki *signature* rahasia).
- **Validasi Input:** *Form Request Validation* di sisi server untuk tanggal (tidak boleh terbalik), limit peserta maksimal sesuai ruang, dan validasi duplikat file.

## 15. Diagram Use Case (Konseptual)
- **User** -> Login, Mengajukan Jadwal, Reschedule, Batalkan Jadwal.
- **Admin** -> Kelola Ruangan, Buka/Tutup Periode, ACC Jadwal, Tolak Jadwal, Tarik Laporan Excel.
- **Sistem** -> Cek Ketersediaan, Kirim WhatsApp.

## 16. Diagram Activity (Konseptual - Pengajuan Booking)
`User Isi Form` -> `Sistem Validasi Tanggal & Ruang` -> `Sistem Simpan (Status: waiting)` -> `Admin Periksa` -> `Admin Klik ACC` -> `Status = Confirmed` -> `Admin Klik Final` -> `Kirim WA ke Frontdesk`.

## 17. Diagram Sequence (Konseptual - Notifikasi WA)
1. `Admin` -> `BookingApprovalController`: Click Finalize
2. `Controller` -> `Database`: Update status 'final'
3. `Controller` -> `SignedURL`: Generate Public Link
4. `Controller` -> `TinyURL API`: Shorten URL
5. `Controller` -> `Queue (SendWhatsAppNotification)`: Dispatch
6. `Queue Worker` -> `FonnteService`: Send Message
7. `Fonnte API` -> `WhatsApp Client`: Deliver Text

## 18. Diagram ERD (Konseptual)
- **User (1)** ----- **(M) Booking**
- **Ruangan (1)** ----- **(M) Booking**
- **Booking (1)** ----- **(M) BookingParticipant**

## 19. Proses Pengujian Sistem
- **Unit Testing**: Pengujian logika model Laravel (status change logic).
- **Feature Testing**: Simulasi alur pengajuan dan *approval* (menggunakan PHPUnit / Pest).
- **Manual Testing**: Pengujian form UI, batas kapasitas ruangan, upload denah custom, dan penerimaan WA di *device* fisik.

## 20. Skenario Testing Utama
- *Test 1:* User mencoba membooking ruang yang sudah di-ACC orang lain di tanggal yang sama. -> *Ekspektasi: Sistem menolak.*
- *Test 2:* Admin meng-ACC Final sebuah jadwal. -> *Ekspektasi: Pesan WA terkirim ke nomor Frontdesk beserta link.*
- *Test 3:* Orang asing mengakses link Shareable yang sudah kadaluarsa (lebih 7 hari). -> *Ekspektasi: Tampil halaman Error 403 Invalid Signature.*

## 21. Hasil Testing
- Validasi tumpang tindih waktu telah berjalan 100% akurat.
- Sistem Queue Laravel terbukti efektif mengirimkan notifikasi tanpa membuat browser admin *loading* lambat.
- Fonnte API dapat meng-handle pesan teks, namun tidak mendukung kirim file secara langsung (sehingga diganti metode Signed URL yang justru lebih canggih & aman).

## 22. Kelebihan Sistem
- Sangat meminimalisir bentrokan (human error) berkat filter algoritma ruang.
- Tidak mengharuskan Frontdesk untuk memiliki akun (meningkatkan adopsi pengguna berkat link publik).
- Arsitektur SPA yang cepat berkat Inertia.js dan Vue 3.

## 23. Kekurangan Sistem
- Tergantung pada layanan API pihak ketiga (Fonnte/TinyURL) untuk fungsi komunikasi.
- Mengandalkan proses latar belakang (`queue:work`), yang membutuhkan *daemon* (seperti Supervisor) pada mesin server produksi agar tidak terputus.

## 24. Rencana Pengembangan Selanjutnya
- Integrasi ke Google Calendar / Outlook Calendar API untuk notifikasi undangan peserta.
- Implementasi autentikasi *Single Sign-On* (SSO) agar pegawai tidak perlu mengingat *password* terpisah.
- Fitur *Dashboard Analytics* tingkat lanjut untuk mengetahui ruang mana yang paling sering di-*booking*.

## 25. Kesimpulan
Enterprise Training Management System ini berhasil mentransformasi proses penjadwalan manual menjadi alur kerja otomasi yang efisien, transparan, dan interaktif. Dengan fondasi Laravel dan Vue.js, sistem tidak hanya menjawab tantangan kebutuhan operasional harian tetapi juga memiliki skalabilitas tinggi untuk menunjang pengembangan fitur korporat di masa depan.
