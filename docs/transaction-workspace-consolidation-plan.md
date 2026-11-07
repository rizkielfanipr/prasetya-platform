# Rencana Konsolidasi Workspace Transaksi

| Field | Value |
|---|---|
| Status | Draft untuk persetujuan |
| Owner | Prasetya Group |
| Last updated | 2026-11-07 |
| Decision | [ADR-0010](decisions/0010-consolidated-transaction-workspace.md) |
| Dampak utama | Navigasi back office, transaksi, payment event, rekonsiliasi, audit |

## Ringkasan

Back office saat ini menempatkan **Transaksi**, **Event Pembayaran**, **Rekonsiliasi**, dan **Audit Log** sebagai empat menu utama. Ketiganya saling berkaitan erat dan sebagian besar data event serta audit transaksi sudah tersedia pada detail transaksi. Untuk tim kecil, navigasi tersebut terasa lebih besar daripada jumlah tindakan yang benar-benar dilakukan.

Rencana ini menjadikan **Transaksi** sebagai satu workspace operasional. Event pembayaran dan rekonsiliasi menjadi view di dalam workspace tersebut. Audit ditempatkan pada detail entitas yang relevan, bukan menjadi satu menu global yang harus dibuka terpisah.

Konsolidasi hanya mengubah information architecture dan presentation. State machine, payment evidence, data append-only, callback, rekonsiliasi, serta aturan keamanan tidak berubah.

## Tujuan

- Mengurangi jumlah menu utama tanpa mengurangi kemampuan investigasi.
- Membuat invoice/order menjadi pusat navigasi operasional.
- Menampilkan masalah pembayaran dan tindakan koreksi aman dekat dengan transaksi terkait.
- Mempertahankan event tanpa transaksi cocok dan audit non-transaksi agar tetap dapat ditemukan.
- Menghindari duplikasi tabel, filter, presenter, serta pola navigasi.

## Non-tujuan

- Menghapus webhook event, payment event, audit log, atau reconciliation query.
- Mengizinkan admin mengubah payment menjadi `PAID` secara manual.
- Menggabungkan payment status dan order status.
- Mengubah state machine, callback verification, idempotency, atau transaction check Duitku.
- Menghapus route lama sebelum masa kompatibilitas selesai.
- Membuat dashboard observability umum atau log viewer teknis.

## Kondisi saat ini

### Sidebar

1. Dashboard
2. Transaksi
3. Event Pembayaran
4. Rekonsiliasi
5. Audit Log
6. Pengguna
7. Pengaturan

### Detail transaksi

Detail transaksi sudah memuat:

- ringkasan brand, jumlah tagihan, payment status, dan order status;
- pelanggan dan paket;
- detail Webworks/Satamu;
- seluruh payment attempt;
- payment event timeline;
- webhook event terkait payment;
- riwayat status order;
- admin notes;
- audit order dan payment.

Karena itu, halaman Event Pembayaran dan Audit Log sering menjadi pintu masuk kedua menuju data yang sebenarnya lebih mudah dipahami dalam konteks invoice.

### Kemampuan yang tidak boleh hilang

- Pencarian event lintas invoice berdasarkan status dan tanggal.
- Event dengan invoice tidak dikenal atau tanpa transaksi lokal.
- Sembilan reconciliation group.
- Count masalah terbuka dan badge navigasi.
- Tindakan **Cek status** terhadap gateway, termasuk alasan dan audit.
- Audit login, logout, pengguna, dan pengaturan yang tidak mempunyai transaksi.
- Deep link untuk runbook/incident response dan bookmark lama.

## Information architecture yang diusulkan

### Sidebar

1. Dashboard
2. **Transaksi** — memiliki badge jumlah masalah yang perlu ditinjau
3. Pengguna
4. Pengaturan

Menu **Event Pembayaran**, **Rekonsiliasi**, dan **Audit Log** dihapus dari sidebar setelah view pengganti tersedia dan tervalidasi.

### Workspace Transaksi

Route utama tetap `/transactions`. Gunakan query `view` yang dapat dibagikan/bookmark:

```text
/transactions?view=all
/transactions?view=attention
/transactions?view=events
```

Tab/view:

#### 1. Semua

Daftar transaksi yang sudah ada, termasuk filter:

- pencarian invoice/pelanggan;
- brand;
- payment status;
- order status;
- metode pembayaran;
- paket;
- tema dan jenis/tanggal acara;
- rentang tanggal;
- sorting.

#### 2. Perlu Ditinjau

Menggantikan halaman Rekonsiliasi sebagai workflow operasional. Tampilkan:

- total masalah yang membutuhkan tindakan;
- sembilan kelompok reconciliation sebagai chips/filter;
- penjelasan singkat kelompok dan threshold;
- invoice/order/payment terkait;
- severity/petunjuk tindakan;
- waktu kejadian terakhir;
- tombol **Buka transaksi** jika relasi tersedia;
- tombol **Cek status** hanya untuk payment yang memenuhi kontrak tindakan;
- detail evidence aman untuk issue tanpa transaksi terkait.

`DuplicateWebhooks` tetap terlihat sebagai informasi tetapi tidak dihitung sebagai badge masalah yang membutuhkan tindakan.

#### 3. Event Pembayaran

Mempertahankan fungsi halaman Event Pembayaran:

- pencarian invoice;
- filter processing status;
- rentang tanggal;
- pagination;
- status, result, rejection, payment method, dan waktu;
- link ke transaksi terkait;
- detail tersanitasi untuk event tanpa transaksi.

Event tidak boleh hilang hanya karena payment/order tidak ditemukan.

### Detail transaksi

Route tetap `/transactions/{order}`. Susun detail menjadi tiga tab atau anchor section agar halaman panjang tetap mudah dipindai:

#### Ringkasan

- status payment dan order tetap terpisah;
- brand dan jumlah tagihan;
- pelanggan;
- package snapshot;
- detail Webworks/Satamu;
- batas pembayaran;
- admin notes.

#### Pembayaran

- seluruh payment attempt;
- payment summary;
- payment event timeline;
- webhook events;
- issue rekonsiliasi aktif untuk transaksi ini;
- **Cek status gateway** pada payment terkait jika tersedia;
- hasil pemeriksaan terakhir dan auditnya.

#### Aktivitas

Timeline kronologis gabungan untuk konteks transaksi:

- order status history;
- payment events;
- webhook events yang aman ditampilkan;
- admin notes;
- audit order/payment.

Sumber data tetap terpisah di backend. Timeline gabungan adalah read model/presenter, bukan tabel baru dan bukan penghapusan data asli.

## Penempatan audit non-transaksi

Audit tidak boleh dipaksa masuk ke detail transaksi jika tidak mempunyai hubungan transaksi.

| Jenis audit | Lokasi baru |
|---|---|
| Order/payment/reconciliation | Detail Transaksi → Aktivitas |
| User created/updated/activated/password reset | Detail Pengguna → Aktivitas |
| Login/logout/settings/system | Pengaturan → Aktivitas Sistem |
| Unknown/unsupported entity | Pengaturan → Aktivitas Sistem |

`Pengaturan → Aktivitas Sistem` mempertahankan pencarian action/actor/entity dan rentang tanggal. Halaman ini tidak perlu menjadi menu sidebar utama; tautannya tersedia dari halaman Pengaturan dan incident runbook.

## Desain backend

### Prinsip

- Tidak ada migration database untuk fase konsolidasi awal.
- Model dan tabel payment event, webhook event, audit log, dan reconciliation tetap append-only sesuai aturan sekarang.
- Query domain yang ada tetap menjadi source of truth.
- Controller tidak memuat business rule baru.
- Presenter/read model menggabungkan data hanya untuk tampilan.

### Controller dan query

Rekomendasi implementasi bertahap:

1. `TransactionController::index` menerima `view=all|attention|events` dan mendelegasikan ke query/presenter yang sudah ada.
2. `TransactionController::show` menambahkan issue rekonsiliasi untuk order/payment tersebut dan activity timeline terurut.
3. `ReconciliationReport` tetap dipakai tanpa menyalin decision logic ke controller atau Vue.
4. Query WebhookEvent tetap mendukung event tanpa payment/order.
5. Audit query memperoleh scope berdasarkan entity agar dapat dipakai ulang pada detail transaksi, detail pengguna, dan Aktivitas Sistem.
6. Action `CheckPaymentWithGateway` dan seluruh invariant-nya tidak berubah.

Jangan membuat satu query SQL union besar yang sulit diuji hanya demi menyatukan UI. Gunakan response shape yang eksplisit per view.

### Kontrak data yang disarankan

Workspace menerima salah satu payload sesuai `view`:

```ts
type TransactionWorkspaceView = 'all' | 'attention' | 'events';
```

Detail transaksi menerima tambahan:

```ts
interface TransactionDetailPageProps {
    transaction: TransactionDetail;
    reconciliationIssues: ReconciliationIssue[];
    activity: TransactionActivityEntry[];
}
```

Setiap `TransactionActivityEntry` minimal memiliki:

- stable id;
- type/source;
- occurred at;
- title dan sanitized detail;
- actor label jika tersedia;
- severity/informational state;
- reference ke sumber tanpa raw secret/payload.

## URL dan kompatibilitas

Route lama dipertahankan setidaknya satu release cycle:

| Route lama | Tujuan baru |
|---|---|
| `/payment-events` | redirect ke `/transactions?view=events` dengan filter yang kompatibel |
| `/reconciliation` | redirect ke `/transactions?view=attention` dengan mapping `group` |
| `/audit-log` | redirect ke `/settings/activity` |

POST `/reconciliation/payments/{payment}/check-status` dapat dipertahankan sementara agar form/action lama tidak rusak. Setelah UI baru stabil, route action boleh dipindahkan ke namespace transaksi dengan compatibility alias dan test yang memastikan invariant sama.

Redirect harus mempertahankan query filter yang relevan. Jangan mengganti route lama langsung dengan 404.

## UX dan visual

- Tab workspace terlihat seperti navigasi sekunder, bukan tambahan menu sidebar.
- Badge pada tab **Perlu Ditinjau** hanya menghitung `needsAction()`.
- Masalah tanpa transaksi terkait harus memiliki empty relationship state yang jelas, bukan link rusak.
- Detail transaksi menampilkan CTA tindakan dekat payment attempt yang bermasalah.
- Evidence teknis tetap progressive disclosure; ringkasan operasional muncul lebih dahulu.
- Mobile menggunakan tab horizontal scroll yang memiliki label utuh dan focus state.
- Filter setiap view tersimpan di URL dan tidak bocor ke view lain.
- Perubahan view tidak menghapus filter view sebelumnya dari browser history secara membingungkan.

## Keamanan dan auditability

- Hanya `ApplyPaymentEvidence` yang dapat menghasilkan `PAID`.
- `Cek status` tetap memakai `CheckPaymentWithGateway`, bukan direct model update.
- Setiap tindakan cek status tetap mencatat audit actor, reason, outcome, dan waktu.
- Raw callback body, signature, secret, dan data pribadi tambahan tidak boleh masuk response UI.
- Penghapusan menu tidak berarti penghapusan retention atau kemampuan investigasi.
- Event/audit append-only tidak boleh memperoleh edit/delete action.

## Tahap implementasi

### Fase 1 — Read model detail transaksi

- Tambahkan reconciliation issue per transaksi.
- Buat activity timeline gabungan.
- Pindahkan **Cek status** ke payment attempt pada detail.
- Pertahankan seluruh menu lama.

Exit: investigator dapat menyelesaikan satu kasus dari detail transaksi tanpa membuka halaman lain.

### Fase 2 — Workspace bertab

- Tambahkan `view=all|attention|events`.
- Reuse filter, table, pagination, dan query yang sudah ada.
- Tambahkan badge issue count dan deep link.
- Pertahankan route/menu lama selama QA.

Exit: parity fitur Event Pembayaran dan Rekonsiliasi tercapai di workspace baru.

### Fase 3 — Audit berdasarkan entitas

- Tambahkan aktivitas pada detail Pengguna.
- Tambahkan Pengaturan → Aktivitas Sistem.
- Pastikan audit transaksi sudah lengkap pada detail transaksi.
- Perbarui incident response links.

Exit: setiap jenis audit memiliki lokasi yang jelas dan searchable.

### Fase 4 — Sederhanakan navigasi

- Hapus tiga item sidebar lama.
- Aktifkan compatibility redirect.
- Perbarui design docs, ADR-0002 status/supersession, runbook, test, dan screenshot.
- Pantau penggunaan dan error route.

Exit: sidebar berisi Dashboard, Transaksi, Pengguna, dan Pengaturan tanpa kehilangan capability.

### Fase 5 — Cleanup setelah masa kompatibilitas

- Evaluasi analytics/log penggunaan route lama.
- Hapus page component/controller lama hanya jika tidak lagi dipakai dan test route compatibility sudah disesuaikan.
- Jangan menghapus query domain atau data model yang masih menjadi source workspace.

## Test plan

### Feature

- Setiap `view` mengembalikan payload dan pagination yang benar.
- Filter antar-view tervalidasi dan tidak saling tercampur.
- Legacy route redirect mempertahankan filter.
- Semua reconciliation group tetap tersedia.
- Issue tanpa order/payment tetap tampil.
- `Cek status` tetap menghasilkan outcome, flash message, cache invalidation, dan audit yang sama.
- Detail transaksi hanya memuat audit milik order/payment tersebut.
- Aktivitas Sistem memuat audit non-transaksi.
- No raw evidence atau secret muncul pada response.

### Frontend/unit

- Active tab mengikuti URL.
- Badge hanya menghitung issue yang membutuhkan tindakan.
- Empty/loading/error/retry state setiap view.
- Deep link dari event/issue menuju transaksi yang benar.
- Entity tanpa transaksi tidak merender link palsu.
- Keyboard navigation dan focus pada tabs/dialog.

### E2E

1. Cari transaksi dan buka detail.
2. Investigasi payment attempt, webhook, reconciliation issue, dan audit tanpa pindah menu utama.
3. Jalankan **Cek status** dan verifikasi activity/audit bertambah.
4. Temukan unknown webhook melalui Event Pembayaran.
5. Temukan orders without attempt melalui Perlu Ditinjau.
6. Buka audit pengguna dan aktivitas sistem dari lokasi baru.
7. Buka URL lama dan verifikasi redirect.
8. Uji desktop dan mobile tanpa horizontal overflow.

## Acceptance criteria

- Sidebar memiliki maksimal empat menu utama: Dashboard, Transaksi, Pengguna, Pengaturan.
- Semua fungsi Event Pembayaran dan Rekonsiliasi tersedia di workspace Transaksi.
- Detail transaksi menampilkan payment attempts, events, webhooks, active issues, order history, notes, dan audit terkait.
- Event tanpa transaksi tetap dapat dicari dan diperiksa.
- Audit non-transaksi tetap dapat dicari dari lokasi entitas/sistem yang sesuai.
- Tidak ada perubahan pada payment/order state machine atau authority `PAID`.
- Tidak ada kehilangan data, migration destruktif, atau pengurangan retention.
- Route lama tidak langsung rusak.
- Test dan runbook membuktikan parity capability sebelum sidebar disederhanakan.

## Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Data global tersembunyi karena semua dipaksa ke transaksi | Sediakan Event view untuk unmatched evidence dan Aktivitas Sistem untuk audit non-transaksi |
| Workspace menjadi terlalu padat | Gunakan tiga view dan tab detail, progressive disclosure, serta pagination terpisah |
| Filter/query menjadi monolitik | Pertahankan query domain per view dan gabungkan hanya pada page contract |
| Bookmark/runbook lama rusak | Compatibility redirect minimal satu release cycle |
| Operator melewatkan masalah | Badge `needsAction`, Dashboard link, dan default sort berdasarkan urgensi/waktu |
| Action cek status kehilangan konteks | Letakkan pada payment attempt dan tampilkan reason/outcome di activity timeline |

## Rollout dan rollback

### Rollout

1. Deploy detail transaksi baru dengan sidebar lama.
2. Deploy workspace tabs dengan menu lama masih aktif.
3. Jalankan UAT parity dan incident drill.
4. Sederhanakan sidebar dan aktifkan redirects.
5. Pantau error, latency query, dan feedback operator.

### Rollback

Karena tidak ada migration destruktif, rollback dilakukan dengan mengembalikan sidebar dan route GET lama. Domain data, event, audit, dan reconciliation query tetap utuh. Route POST cek status tidak boleh dihapus selama rollback window.

## Keputusan yang perlu disetujui

1. Apakah nama tab terbaik **Perlu Ditinjau** atau **Rekonsiliasi**.
2. Apakah Aktivitas Sistem ditempatkan di Pengaturan atau tetap punya route tersembunyi khusus.
3. Apakah detail transaksi memakai tiga tab atau satu halaman dengan anchor navigation.
4. Berapa lama compatibility route dipertahankan; rekomendasi minimal satu release cycle.
