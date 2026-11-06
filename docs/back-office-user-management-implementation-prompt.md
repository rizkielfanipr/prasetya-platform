# Prompt Implementasi Modul Pengguna dan Login Username

Salin prompt di bawah ke coding agent dari workspace `/Users/air/Projects/prasetya-group/prasetya-platform`. Prompt ini meminta audit dulu, lalu implementasi bertahap per PR, dan berhenti untuk review setelah setiap PR.

## Prompt

Anda bekerja di repository `/Users/air/Projects/prasetya-group/prasetya-platform` (Laravel 13, PHP 8.4, Inertia + Vue 3 + TypeScript, Pest, Vitest, Playwright).

Tujuan akhir:

- Back office punya modul **Pengguna** untuk melihat, menambah, mengubah nama, mengaktifkan atau menonaktifkan, dan mengatur password user lain.
- Semua user aktif punya akses penuh ke semua menu, tanpa RBAC.
- Login memakai **username dan password**, bukan email.

### 1. Instruksi awal wajib

1. Baca penuh `AGENTS.md`, lalu `docs/README.md`.
2. Baca dokumen berikut sebagai source of truth:
   - `docs/back-office-user-management-plan.md` (rencana lengkap, termasuk keputusan pemilik di bagian akhir);
   - `docs/decisions/0009-single-access-users-username-login.md`;
   - `docs/decisions/0002-mvp-payment-monitoring-scope.md` (baris Roles yang digantikan);
   - `docs/design.md`;
   - `docs/data-dictionary.md`;
   - `docs/security/threat-model.md`;
   - `docs/testing/strategy.md`;
   - `docs/runbooks/deployment.md`;
   - `docs/runbooks/incident-response.md`.
3. Jalankan `git status --short` dan `git log --oneline -5`. Anggap semua perubahan yang sudah ada sebagai milik pengguna. Jangan reset, checkout, stash, hapus, atau timpa. Kalau ada perubahan aktif di file yang perlu diedit, berhenti dan laporkan.
4. Kerjakan di branch baru dari `main`, misalnya `feat/user-management`. **Jangan push ke `main`.** Push ke `main` langsung deploy ke production lewat CI dan menjalankan migration.
5. Sebelum mengubah kode, audit dan petakan semua pemakaian role, permission, email login, dan password reset:
   - `app/Domain/Auth/Enums/{Role,Permission}.php` dan `User::hasPermission`;
   - `app/Policies/*` dan gate `view-reconciliation` serta `manage-settings` di `AppServiceProvider`;
   - `HandleInertiaRequests` (prop `auth.permissions`);
   - `SettingsController`, `TransactionController`, `GatewayCheckRequest`, `StoreAdminNoteRequest`;
   - `LoginRequest`, `LoginController`, `PasswordResetController`, dan `ResetPassword::createUrlUsing`;
   - `resources/js`: `AppSidebar.vue`, `AppHeader.vue`, `IssueTable.vue`, `AdminNotesPanel.vue`, `AdminPanels.vue`, `ProfileForm.vue`, halaman `auth/*`, `lib/status.ts` (`roleLabels`), `types/backoffice.ts`;
   - `routes/console.php` (`platform:create-admin --role`), `AdminUserSeeder`, `DemoDataSeeder`, `UserFactory`;
   - test: `tests/Feature/BackOffice/{AuthTest,AuthorizationTest}.php`, `tests/Support/helpers.php` (`admin(Role)`), `tests/e2e/{auth,permissions,support}.ts`.

   Laporkan hasil audit secara singkat sebelum mulai.

### 2. Keputusan pemilik (final, jangan diubah)

| Topik | Keputusan |
|---|---|
| Akses | Satu level akses. Semua user aktif yang login bisa memakai semua menu dan aksi. Hapus role, permission, policy, dan gate berbasis role |
| Login | Username + password. Username unik, lower-case, 3–32 karakter, pola `^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$`, dicocokkan case-insensitive setelah trim |
| Username | **Tidak bisa diubah setelah dibuat**, oleh siapa pun |
| Email | **Disembunyikan dulu**: kolom menjadi nullable, tidak tampil, tidak bisa diedit, tidak dipakai. Belum dihapus dari database |
| Password awal | **Diketik oleh pembuat akun**, dengan konfirmasi, mengikuti `Password::defaults()` (12 karakter, huruf dan angka) |
| Ganti password di login pertama | **Belum** di rilis ini |
| Lupa password | Tidak ada reset lewat email. Minta user lain mengatur password baru di Pengguna, atau jalankan `php artisan platform:reset-password <username>` di server |
| Hapus user | Tidak ada. Hanya nonaktifkan |

### 3. Invariant yang tidak boleh dilanggar

- Hanya `ApplyPaymentEvidence` yang boleh mengubah payment menjadi `PAID`. Tidak ada route atau layar yang mengubah status pembayaran.
- Semua route back office tetap di balik middleware `auth` dan `EnsureUserIsActive`.
- User tidak bisa menonaktifkan dirinya sendiri, dan user aktif terakhir tidak bisa dinonaktifkan.
- Menonaktifkan user atau mengatur password user lain langsung mengakhiri sesinya: hapus baris `sessions` milik user itu dan kosongkan `remember_token`.
- Setiap perubahan user diaudit: `user.created`, `user.updated`, `user.activated`, `user.deactivated`, `user.password_reset`. Audit **tidak pernah** berisi password atau hash.
- Pesan error login selalu generik: "Username atau kata sandi salah."
- Public identifier user memakai `public_id` (ULID), bukan ID numerik.
- Aturan bisnis ada di `app/Domain/Auth/Actions`. Controller tetap tipis. `tests/Architecture` harus tetap lulus.
- Vue: hanya semantic token, tanpa warna mentah dan tanpa `any`. Label, status, route, dan formatter ditaruh di `resources/js/lib`.
- Jangan simpan atau log password, secret, atau data pribadi yang tidak perlu.

### 4. Yang harus dibangun

Ikuti `docs/back-office-user-management-plan.md` bagian **Scope**. Ringkasan:

**A. Data.** Satu migration baru:

1. Tambahkan `users.username` (string 32, nullable dulu).
2. Isi username untuk user yang sudah ada dari bagian email sebelum `@`: lower-case, karakter di luar `[a-z0-9._-]` diganti `-`, dipotong 32 karakter, dan diberi akhiran `-2`, `-3`, … kalau bentrok.
3. Jadikan `username` not null dan unique.
4. Jadikan `email` nullable.
5. Hapus kolom `role` beserta index-nya, lalu hapus tabel `password_reset_tokens`.

`down()` mengembalikan `role` (semua user menjadi `SUPER_ADMIN`) dan tabel `password_reset_tokens`. Buat value object atau helper `App\Domain\Auth\Username` sebagai satu-satunya tempat aturan normalisasi dan validasi username.

**B. Login.**

- `LoginRequest` memakai `username`, `password`, dan `remember`. Throttle 5 percobaan per menit per username+IP, ditambah 20 per menit per IP.
- `LoginPage.vue` memakai label **Username** dengan `autocomplete="username"`. Ganti link "Lupa kata sandi?" dengan petunjuk "Lupa kata sandi? Hubungi admin lain."
- Hapus route `forgot-password` dan `reset-password`, `PasswordResetController`, halaman `ForgotPasswordPage.vue` dan `ResetPasswordPage.vue`, rate limiter `password-reset`, dan `ResetPassword::createUrlUsing`.

**C. Hapus RBAC.**

- Hapus `Role`, `Permission`, `hasPermission`, keempat policy, gate berbasis role, dan prop `auth.permissions`.
- `authorize()` di form request mengembalikan `true`.
- Evidence dan audit di detail transaksi, serta panel gateway dan aplikasi di Pengaturan, selalu tampil.
- Navigasi menampilkan semua menu.
- Tambahkan architecture test bahwa semua route web kecuali login dan dev fake checkout memakai `auth`.

**D. Modul Pengguna.**

- Menu **Pengguna** di antara Audit Log dan Pengaturan.
- Route:
  - `GET /users` (daftar, pencarian nama/username, filter status);
  - `POST /users`;
  - `PUT /users/{user:public_id}` (nama saja; `username` di request diabaikan);
  - `POST /users/{user:public_id}/deactivate` dan `.../activate`;
  - `PUT /users/{user:public_id}/password`.
- Domain actions: `CreateUser`, `UpdateUser`, `SetUserActive`, `ResetUserPassword`.
- UI mengikuti `docs/design.md` dan pola daftar transaksi:
  - tabel (kartu di ponsel) berisi nama, username, badge Aktif/Nonaktif, login terakhir, dan tanggal dibuat, dengan tanda "(Anda)" untuk user yang sedang login;
  - dialog **Tambah** berisi nama, username, password, dan konfirmasi. Hint username menjelaskan karakter yang boleh dan bahwa username tidak bisa diubah;
  - dialog **Ubah** hanya untuk nama, dengan username read-only;
  - dialog konfirmasi **Nonaktifkan** menjelaskan bahwa user langsung keluar. Tombolnya nonaktif untuk diri sendiri dan untuk user aktif terakhir, dengan alasannya ditampilkan;
  - dialog **Atur password**.
- Halaman Pengaturan: hapus tabel user. Form profil hanya untuk nama, dengan username read-only. Form ganti password tetap.

**E. CLI dan seeder.**

- `platform:create-admin {username} {name}` tanpa `--role`, password lewat prompt.
- Perintah baru `platform:reset-password {username}`: password lewat prompt, mengakhiri sesi user tersebut, diaudit sebagai `Sistem`.
- Perintah baru `platform:list-users`: menampilkan username, nama, status, dan login terakhir.
- `AdminUserSeeder` membaca `ADMIN_USERNAME`; perbarui `.env.example`.
- User demo menjadi `demo` dan `demo2`.

### 5. Test wajib

Ikuti bagian **Required tests** di rencana. Minimal:

- **Login:**
  - username valid berhasil, case-insensitive dan dengan spasi di sekitarnya;
  - password salah dan username tidak dikenal menghasilkan pesan yang sama;
  - user nonaktif ditolak;
  - kedua rate limit berlaku;
  - field `email` tidak bisa dipakai login;
  - `forgot-password` dan `reset-password` membalas 404.
- **Akses:**
  - user aktif mana pun bisa membuka rekonsiliasi, Cek status, audit log, event pembayaran, panel Pengaturan, dan evidence transaksi;
  - guest diarahkan ke login;
  - user yang dinonaktifkan keluar di request berikutnya;
  - architecture test route `auth`.
- **Pengguna:**
  - tambah user dengan semua validasi (unique case-insensitive, format username, aturan password, konfirmasi);
  - ubah nama, dengan `username` yang dikirim diabaikan;
  - menonaktifkan diri sendiri ditolak, dan menonaktifkan user aktif terakhir ditolak;
  - nonaktifkan dan atur password menghapus sesi dan `remember_token`;
  - ID numerik membalas 404;
  - semua audit tercatat tanpa password;
  - tidak ada response atau halaman yang menampilkan email user.
- **Migration:** backfill menghasilkan username valid dan unik untuk email yang bentrok atau tidak biasa.
- **CLI:** ketiga perintah berfungsi, menolak input tidak valid, dan menulis audit.
- **Komponen (Vitest):** pesan validasi form user.
- **Browser (Playwright):**
  - login dengan username;
  - tambah user, logout, lalu login sebagai user baru;
  - nonaktifkan user yang sedang login di context browser lain, dan request berikutnya ke halaman login;
  - atur password lalu login dengannya;
  - axe dan tanpa horizontal scroll di 360, 768, dan 1280 px untuk `/users`;
  - ganti `permissions.spec.ts`.
- Semua test lama yang bergantung pada role atau email login diperbarui, bukan dihapus begitu saja. Tidak boleh ada test yang di-skip.

### 6. Quality gates

Jalankan semua gate dari `AGENTS.md` dan laporkan hasil aktualnya:

```sh
vendor/bin/pint && npm run format
vendor/bin/phpstan analyse --memory-limit=1G
npm run lint && npm run typecheck && npm test
vendor/bin/pest
npm run build && npm run test:e2e
```

Lakukan visual QA halaman Login, Pengguna (daftar, dialog tambah, dialog nonaktifkan), dan Pengaturan di 360, 768, dan 1280 px. Kalau ada gate yang tidak bisa dijalankan, sebutkan secara eksplisit yang mana dan alasannya. Jangan menyatakan semua lulus.

### 7. Dokumentasi

Sinkronkan di perubahan yang sama, sesuai bagian **Documents to update** di rencana:

- `data-dictionary.md`;
- `design.md` (form login, urutan navigasi, hapus kalimat "Items a role cannot use…", pola halaman Pengguna);
- `security/threat-model.md` (password guessing per username dan IP, privilege escalation, risiko akun bocor dengan akses penuh);
- `testing/strategy.md`;
- `runbooks/deployment.md` (`create-admin <username>`, `list-users` setelah rilis, `reset-password`);
- `runbooks/incident-response.md` (akun bocor ditangani lewat Pengguna);
- `implementation-plan.md`;
- `.env.example`.

Setelah implementasi diterima, ubah status ADR-0009 menjadi Accepted dan tandai baris Roles di ADR-0002 sebagai digantikan oleh ADR-0009 di indeks ADR.

### 8. Urutan delivery dan commit

Satu PR per fase. Berhenti dan minta review setelah setiap PR.

1. `feat(platform): sign in with username`: migration `username` dan `email` nullable, `Username`, login, hapus password reset, CLI, seeder, test, dan docs terkait.
2. `refactor(platform): remove roles and permissions`: drop `role`, hapus enum, policy, gate, prop, dan filter navigasi; ganti test role matrix; architecture test route.
3. `feat(platform): manage users in the back office`: modul Pengguna lengkap dengan test.
4. `docs: user management and username sign-in`: sinkronisasi dokumen yang tersisa dan status ADR.

Commit memakai Conventional Commits. Jangan push ke `main` dan jangan deploy. Pemilik yang akan merge.

### 9. Rollout (dikerjakan pemilik setelah merge)

1. Backup database production.
2. Merge ke `main`. CI menjalankan test, deploy, dan migration (username diisi otomatis).
3. Di server, jalankan `/opt/cpanel/ea-php84/root/usr/bin/php artisan platform:list-users`, lalu bagikan username ke masing-masing user secara pribadi.
4. Login dengan username, cek menu Pengguna, lalu tambah dan nonaktifkan satu user uji.

Rollback: deploy ulang rilis sebelumnya dan restore backup. Hindari `migrate:rollback`, karena role asli dan email yang sudah dikosongkan tidak bisa kembali.

### 10. Definition of done

- Seorang user bisa menambah rekan, dan rekan itu bisa login dengan username dan password lalu membuka semua menu.
- Tidak ada lagi role, permission, atau login email di kode, UI, maupun test.
- Username tidak bisa diubah, email tidak tampil di mana pun, dan password awal diketik oleh pembuat akun.
- Semua safeguard dan audit di bagian 3 terbukti oleh test.
- Semua quality gate lulus, dan visual QA selesai.
- Dokumen di bagian 7 sudah sinkron.

### 11. Format laporan akhir

Laporkan:

1. hasil yang dirasakan pengguna;
2. file yang berubah per fase;
3. dampak migration, termasuk contoh hasil backfill username tanpa data asli;
4. daftar test beserta hasil aktual setiap gate;
5. hasil visual QA;
6. risiko yang tersisa;
7. langkah rollout dan rollback;
8. branch dan commit per fase.
