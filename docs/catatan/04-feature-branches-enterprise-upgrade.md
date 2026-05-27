# Catatan Implementasi: 4 Feature Branches (Enterprise Upgrade)

> Tanggal implementasi: 27 Mei 2026
> Dibuat sebagai bagian dari penyesuaian sistem untuk mendukung **496 akun departemen** (16 dept × 31 site).

---

## Branch 1: `feature/department-autofill`
**Tujuan:** Otomasi pengisian identitas pemesan dari data akun yang login.

### Perubahan:
- **Migration baru**: `add_site_to_users_table.php` — menambah kolom `site` (VARCHAR 20) ke tabel `users`.
- **Model `User.php`**: Tambah `site` ke `$fillable`.
- **`HandleInertiaRequests.php`**: Sertakan `site` di dalam shared auth props agar tersedia global di seluruh Vue component.
- **`CompanyUsersSeeder.php`**: Seeder diperbarui untuk mengisi kolom `site` secara otomatis saat pembuatan akun.
- **`BookingWizard.vue` (Stage 4)**:
  - Kotak **Departemen** dan **Site** ditampilkan sebagai field read-only (Terkunci) yang diisi otomatis dari data akun.
  - Ditambahkan field **Nama PIC (Person In Charge)** — field input manual wajib isi.
  - Ditambahkan field **No. HP PIC** — field input manual wajib isi. Disertai keterangan penggunaan untuk Admin/Security.

---

## Branch 2: `feature/department-data-isolation`
**Tujuan:** Mencegah kebocoran informasi antar departemen/site.

### Perubahan:
- **`DashboardController.php` (User)**:
  - Kalender/Gantt: Booking milik departemen lain ditampilkan sebagai `[Sudah Dipesan]` (nama acara, divisi, PIC disembunyikan). Hanya tanggal dan ruangan yang terlihat — cukup untuk menghindari double booking.
  - `myBookings`: Filter diubah dari berdasarkan `divisi` menjadi `user_id` yang ketat, sehingga hanya booking dari akun yang login persis yang tampil.

---

## Branch 3: `feature/admin-pagination`
**Tujuan:** Mencegah halaman Admin hang saat ratusan booking masuk.

### Perubahan:
- **`BookingApprovalController.php`**:
  - Query diganti dari `->get()` menjadi `->paginate(20)` (20 data per halaman).
  - Ditambahkan parameter `?search=` untuk mencari berdasarkan nama acara, nama pemohon, atau divisi.
- **`BookingApproval.vue`**:
  - Props `bookings` diubah dari `Array` menjadi `Object` (Laravel paginator).
  - Ditambahkan **Search Bar** dengan tombol Cari dan Reset di atas tabel.
  - Ditambahkan **Pagination Controls** (Prev / nomor halaman / Next) di bawah tabel.
  - Tampilkan info "X hasil ditemukan" dan "Menampilkan X-Y dari Z booking".

---

## Branch 4: `feature/booking-rate-limit`
**Tujuan:** Mencegah spam pengajuan booking dari akun departemen yang mewakili banyak orang.

### Perubahan:
- **`BookingWizardController.php` (method `submitBooking`)**:
  - Ditambahkan cek sebelum proses DB transaction: hitung jumlah booking **aktif** (`waiting_confirmation` + `confirmed` + `final`) milik akun yang login.
  - Jika jumlahnya **≥ 5**, sistem mengembalikan error HTTP `429 Too Many Requests` dengan pesan yang jelas.
- **`BookingWizard.vue`**:
  - Ditambahkan penanganan error `status === 429` yang menampilkan pesan batas pengajuan tercapai.

> **Batas:** 5 booking aktif per akun pada satu waktu. Akun dapat mengajukan lagi setelah booking aktif selesai (final) atau dibatalkan.
