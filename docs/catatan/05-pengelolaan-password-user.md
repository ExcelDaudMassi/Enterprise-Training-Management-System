# Catatan Implementasi: Fitur Pengelolaan Password Mandiri (User)

> Tanggal implementasi: 27 Mei 2026  
> Branch pengerjaan: `feature/password-management`

---

## 1. Pendahuluan & Tujuan
Sebelumnya, sistem pembuatan akun memproduksi **496 akun departemen** dengan password bawaan yang mudah diprediksi per site (`NAMASITE.123`). Untuk menjamin keamanan data pengajuan pemesanan antar departemen/site dan mencegah akses tidak sah, kami menambahkan fitur **Pengelolaan Password Mandiri bagi akun bertipe User**.

Fitur ini didesain terisolasi dan aman:
* **Privasi Penuh:** Password dienkripsi menggunakan algoritma `bcrypt` (hashing bawaan Laravel). Bahkan administrator pun tidak dapat melihat password baru milik user tersebut.
* **Pembatasan Hak Akses:** Rute penulisan dan perubahan password dilindungi secara ketat oleh middleware `EnsureUser`. Administrator yang mencoba mengakses alamat pengaturan password user secara langsung akan ditolak otomatis.

---

## 2. Rincian Perubahan Kode

### A. Rute Backend (Routing)
* **File:** [web.php](file:///c:/Users/Excel%20Daud/Herd/booking/routes/web.php)
* **Perubahan:** Menambahkan rute GET dan POST di bawah middleware `auth` dan `user`:
  * `/user/settings/password` (GET - merender halaman form)
  * `/user/settings/password` (POST - memproses perubahan)

### B. Controller (Logika Bisnis & Validasi)
* **File Baru:** [SettingsController.php](file:///c:/Users/Excel%20Daud/Herd/booking/app/Http/Controllers/User/SettingsController.php)
* **Fungsi Utama:**
  * `editPassword()`: Mengambil data otentikasi user saat ini dan merender halaman form `User/PasswordSettings`.
  * `updatePassword()`: Menangani validasi ketat:
    1. Memastikan **Password Saat Ini** wajib diisi dan cocok dengan database (`Hash::check`).
    2. Memastikan **Password Baru** minimal 8 karakter dan wajib dikonfirmasi dengan benar.
    3. Memastikan **Password Baru tidak boleh sama** dengan password lama untuk meningkatkan integritas keamanan.
    4. Setelah sukses, database diupdate dan user diarahkan kembali ke dashboard dengan pop-up SweetAlert2 hijau.

### C. Sidebar & Navigasi (User Interface Layout)
* **File:** [UserLayout.vue](file:///c:/Users/Excel%20Daud/Herd/booking/resources/js/Layouts/UserLayout.vue)
* **Perubahan:**
  * Menambahkan tombol **⚙️ Pengaturan Password** di bagian sidebar navigasi di bawah "Riwayat Booking".
  * Menambahkan deteksi kecocokan rute aktif (`isActive('/user/settings/password')`) untuk indikator warna biru menyala pada sidebar.
  * Memperbarui breadcrumb dinamis pada top header agar menampilkan label `"User / Pengaturan Password"` ketika halaman diakses.

### D. Formulir Ubah Password (Page Component)
* **File Baru:** [PasswordSettings.vue](file:///c:/Users/Excel%20Daud/Herd/booking/resources/js/Pages/User/PasswordSettings.vue)
* **Fitur & Detail Desain:**
  * **Desain Bersih & Profesional:** Sesuai permintaan Anda, desain dibuat bersih, formal, dan tidak terlalu mencolok/flashy, tetapi tetap rapi menggunakan bayangan tipis, layout terpusat, dan kartu putih yang solid.
  * **Password Toggle (Show/Hide):** Tombol emoji 👁️/🙈 disematkan pada setiap kolom password untuk memberikan opsi menampilkan isi password.
  * **Validasi Real-time Frontend:** 
    * Indikator centang hijau otomatis menyala jika password baru telah memenuhi minimal 8 karakter.
    * Indikator dinamis bertuliskan `✅ Password baru cocok` atau `❌ Password baru tidak cocok` otomatis tampil saat menginput ulang konfirmasi password.
  * **Anti-Layar-Beku:** Tombol "Simpan Password" otomatis menampilkan loading spinner mikro dan tidak bisa diklik dua kali ketika proses penyimpanan sedang berlangsung di backend.

---

## 3. Cara Pengujian Fungsionalitas
1. Pastikan Anda berada di branch `feature/password-management`.
2. Login menggunakan salah satu akun user departemen (misal: `hcld@bbso.com` dengan password bawaan `BBSO.123`).
3. Klik tombol **Pengaturan Password** di sidebar kiri.
4. Coba masukkan password lama yang salah, atau password baru kurang dari 8 karakter untuk melihat peringatan validasi bekerja secara otomatis.
5. Masukkan password lama yang benar, dan password baru yang kuat, lalu tekan **Simpan Password**.
6. Sistem akan otomatis memperbarui database, me-redirect ke dashboard utama, dan memunculkan toast SweetAlert2 yang menawan.
7. Silakan lakukan logout dan login kembali menggunakan password baru untuk memverifikasi.
