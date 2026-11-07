# Prompt Implementasi Konsolidasi Workspace Transaksi

Salin prompt berikut ke coding agent dari repository `/Users/air/Projects/prasetya-group/prasetya-platform`. Prompt ini mengharuskan delivery bertahap agar menu lama baru dihapus setelah seluruh capability terbukti tersedia di lokasi baru.

---

## Prompt

Anda bekerja di repository:

```text
/Users/air/Projects/prasetya-group/prasetya-platform
```

Tujuan: sederhanakan sidebar Prasetya Platform dengan menggabungkan **Event Pembayaran** dan **Rekonsiliasi** ke dalam workspace **Transaksi**, menampilkan seluruh data operasional terkait pada detail transaksi, dan memindahkan audit ke detail entitas yang relevan. Jangan menghapus data, evidence, query rekonsiliasi, atau kemampuan investigasi.

Hasil navigasi akhir yang dituju:

1. Dashboard
2. Transaksi
3. Pengguna
4. Pengaturan

Workspace **Transaksi** mempunyai view **Semua**, **Perlu Ditinjau**, dan **Event Pembayaran**.

### 1. Instruksi awal wajib

1. Baca `AGENTS.md` secara penuh.
2. Baca source of truth berikut:
   - `docs/transaction-workspace-consolidation-plan.md`
   - `docs/decisions/0010-consolidated-transaction-workspace.md`
   - `docs/decisions/0002-mvp-payment-monitoring-scope.md`
   - `docs/decisions/0007-duitku-gateway.md`
   - `docs/decisions/0009-single-access-users-username-login.md`
   - `docs/design.md`
   - `docs/architecture.md`
   - `docs/data-dictionary.md`
   - `docs/testing/strategy.md`
   - `docs/security/threat-model.md`
   - `docs/runbooks/payment-reconciliation.md`
   - `docs/runbooks/incident-response.md`
3. Jalankan `git status --short`, `git branch --show-current`, dan `git log -5 --oneline`.
4. Semua perubahan yang sudah ada adalah milik pengguna. Jangan reset, checkout, stash, menghapus, atau menimpa perubahan tersebut.
5. Jika worktree tidak bersih dan file target bertumpang tindih dengan perubahan pengguna, buat worktree/branch terisolasi atau berhenti dan laporkan konflik.
6. Audit kode aktual sebelum mengubahnya. Jangan mengandalkan nama file dalam prompt jika repository sudah berkembang.
7. Buat baseline test dan screenshot sebelum perubahan UI agar regresi dapat dibedakan dari defect lama.
8. Kerjakan bertahap. Jangan menghapus menu/route lama sebelum parity feature dan compatibility test lulus.

### 2. Invariant yang tidak boleh berubah

- Hanya `ApplyPaymentEvidence` yang dapat membuat payment berstatus `PAID`.
- Semua perubahan status tetap melalui `TransitionPaymentStatus` dan `TransitionOrderStatus`.
- Tindakan **Cek status** harus tetap memakai `CheckPaymentWithGateway`.
- Tidak ada direct model update untuk memperbaiki payment/order dari UI.
- Payment status dan order status tetap dua konsep terpisah.
- Payment events, webhook events, audit logs, dan status history tetap append-only.
- Seluruh sembilan `ReconciliationGroup` tetap tersedia.
- `DuplicateWebhooks` tetap dapat diperiksa tetapi tidak dihitung sebagai issue yang membutuhkan tindakan.
- Unknown/unmatched webhook atau payment tetap dapat ditemukan walaupun tidak punya transaksi lokal.
- Raw callback body, signature, API key, credential, dan data pribadi berlebih tidak boleh dikirim ke frontend atau log.
- Network call tidak boleh berada dalam database transaction.
- Controller tetap tipis; query/action/presenter memegang tanggung jawabnya masing-masing.
- Jangan membuat migration destruktif atau menghapus tabel/record sebagai bagian konsolidasi UI.
- Satu access level dari ADR-0009 tetap berlaku kecuali ada keputusan baru di luar scope ini.

### 3. Audit capability sebelum implementasi

Buat matriks aktual yang memetakan:

| Capability | Lokasi sekarang | Lokasi target | Query/action source | Test existing |
|---|---|---|---|---|
| Daftar transaksi |  | Transaksi → Semua |  |  |
| Payment events |  | Transaksi → Event Pembayaran |  |  |
| Rekonsiliasi |  | Transaksi → Perlu Ditinjau |  |  |
| Cek status gateway |  | Detail Transaksi → Pembayaran |  |  |
| Audit order/payment |  | Detail Transaksi → Aktivitas |  |  |
| Audit pengguna |  | Detail Pengguna → Aktivitas |  |  |
| Login/settings/system audit |  | Pengaturan → Aktivitas Sistem |  |  |

Catat seluruh filter, pagination, empty state, loading/error state, deep link, flash message, badge, dan permission/access behaviour yang harus dipertahankan.

### 4. Information architecture target

#### Sidebar

Setelah seluruh fase tervalidasi, sidebar hanya berisi:

- Dashboard
- Transaksi
- Pengguna
- Pengaturan

Item Transaksi menampilkan badge jumlah issue terbuka yang `needsAction() === true`. Badge tidak boleh menghitung duplicate webhook yang informasional.

#### Workspace Transaksi

Pertahankan route utama `/transactions` dan gunakan query yang dapat dibookmark:

```text
/transactions?view=all
/transactions?view=attention
/transactions?view=events
```

Gunakan union type frontend:

```ts
type TransactionWorkspaceView = 'all' | 'attention' | 'events';
```

Nilai `view` yang tidak dikenal harus kembali secara aman ke `all`, bukan menghasilkan error atau memilih view secara tidak deterministik.

##### View Semua

Pertahankan daftar transaksi, pagination, sorting, dan seluruh filter yang sudah tersedia:

- invoice/customer search;
- brand;
- payment status;
- order status;
- payment method;
- package;
- theme code;
- event type/date;
- date range.

##### View Perlu Ditinjau

Reuse `ReconciliationReport` dan `ReconciliationPresenter`. Jangan duplikasi decision logic di controller/Vue.

View harus memuat:

- count per reconciliation group;
- total yang membutuhkan tindakan;
- chips/filter seluruh sembilan group;
- deskripsi dan threshold group;
- issue rows terpaginated;
- invoice/order/payment jika tersedia;
- waktu dan sanitized reason/detail;
- **Buka transaksi** jika relasi valid;
- representasi aman jika relasi tidak tersedia;
- **Cek status** hanya bila payment/action contract mengizinkan.

Default sorting harus membantu operator menemukan issue paling relevan/terbaru tanpa mengubah semantik query domain.

##### View Event Pembayaran

Reuse query dan presenter event yang sudah ada. Pertahankan:

- invoice search;
- webhook processing status;
- date range;
- pagination;
- event status/result/rejection/time;
- link menuju transaction detail jika tersedia;
- unmatched/unknown invoice evidence tanpa broken link.

Filter setiap view harus mempunyai namespace/contract yang jelas dan tidak diterapkan diam-diam pada view lain. State yang harus dibagikan berada di URL.

### 5. Detail transaksi target

Pertahankan route `/transactions/{order}` dan data yang sudah ada. Susun tampilan menjadi tiga tab yang accessible atau anchor navigation bila audit UX membuktikan anchor lebih baik:

#### Ringkasan

- brand;
- amount;
- payment status;
- order status;
- customer;
- package snapshot;
- Webworks/Satamu detail;
- created/expiry time;
- admin notes.

#### Pembayaran

- seluruh payment attempt;
- payment summary;
- payment event timeline;
- related webhook events;
- active reconciliation issues milik order/payment tersebut;
- action **Cek status gateway** pada payment attempt terkait;
- reason dialog, loading state, outcome flash, dan last-check evidence;
- empty state jika belum pernah checkout.

#### Aktivitas

Buat read model/presenter timeline kronologis dari sumber yang tetap terpisah:

- order status history;
- payment events;
- sanitized webhook events;
- admin notes;
- order/payment/reconciliation audit entries.

Jangan membuat tabel universal baru. Timeline adalah representasi read-only dengan stable id, type/source, occurred time, title, sanitized detail, actor label, dan severity/information state.

Scope data wajib ketat: detail satu transaksi tidak boleh memuat audit/event milik transaksi lain.

### 6. Audit berdasarkan entitas

Audit global tidak boleh sekadar dihilangkan.

#### Transaksi

Order, payment, reconciliation check, dan admin-note audit berada di Detail Transaksi → Aktivitas.

#### Pengguna

Tambahkan tab/section Aktivitas pada detail pengguna untuk:

- `user.created`;
- `user.updated`;
- `user.activated`;
- `user.deactivated`;
- `user.password_reset`;
- action lain yang entity-nya user tersebut.

Jangan pernah tampilkan password, hash, token sesi, atau remember token.

#### Pengaturan → Aktivitas Sistem

Sediakan route yang jelas, misalnya `/settings/activity`, untuk:

- login/logout;
- settings changes;
- system/unknown entities;
- audit yang tidak dapat ditempatkan pada transaksi atau pengguna.

Pertahankan search action/actor/entity, date range, pagination, dan immutable presentation. Tautan tersedia dari halaman Pengaturan dan runbook; tidak perlu menjadi item sidebar utama.

### 7. Backend design

1. Validasi `view` dengan enum/allow-list server-side.
2. Hindari satu controller method monolitik. Delegasikan payload per view ke query/presenter/service yang sesuai.
3. Pertahankan `TransactionQuery`, `ReconciliationReport`, WebhookEvent query, dan AuditLog query sebagai source masing-masing.
4. Tambahkan scope/query audit berdasarkan `entity_type` dan `entity_id` bila diperlukan.
5. Tambahkan query issue untuk satu order/payment tanpa menjalankan sembilan query tak terbatas per row.
6. Hindari N+1; eager-load relasi yang dibutuhkan dan ukur jumlah query pada detail/list.
7. Pagination setiap view tidak boleh mengganggu pagination view lain.
8. Cache count issue dapat dipakai seperti sekarang, tetapi invalidasi harus tetap dilakukan setelah callback, expiry, checkout, atau gateway check yang mengubah hasil.
9. Read model activity harus deterministic pada timestamp yang sama, misalnya tie-break dengan source + id.
10. Jangan mengubah domain action atau payment gateway contract kecuali test membuktikan defect; pisahkan perbaikan domain dalam commit tersendiri.

### 8. Frontend design

1. Ikuti design tokens dan component conventions di `docs/design.md`.
2. Reuse `FilterBar`, `SearchInput`, `SelectFilter`, `DateRangeFilter`, `DataTable`, `TablePagination`, badges, timelines, dialog, empty/loading/error states.
3. Jangan menyalin halaman lama menjadi tiga komponen besar yang menduplikasi filter/table logic. Ekstrak komponen reusable dengan props yang jelas.
4. Tab workspace adalah navigasi sekunder, bukan menu sidebar bertingkat.
5. Gunakan Inertia partial reload hanya untuk props view aktif dan pertahankan state URL/history dengan benar.
6. Tab harus keyboard accessible, memiliki focus visible, `aria-current` atau semantics tab yang benar, dan tidak bergantung pada warna saja.
7. Pada mobile, tab boleh horizontal scroll tetapi label tidak terpotong dan halaman tidak mengalami horizontal overflow global.
8. Evidence teknis menggunakan progressive disclosure; operator melihat ringkasan terlebih dahulu.
9. Entity tanpa transaction link menampilkan label **Transaksi tidak ditemukan/tidak tersedia**, bukan anchor kosong.
10. Semua label/status/route/type tetap punya satu source of truth di `resources/js/lib`/types yang sesuai.

### 9. Compatibility routing

Pertahankan route lama minimal satu release cycle:

```text
/payment-events  -> /transactions?view=events
/reconciliation  -> /transactions?view=attention
/audit-log       -> /settings/activity
```

Requirements:

- preserve filter yang kompatibel (`search`, `status`, `date_from`, `date_to`, `group`);
- gunakan redirect server-side yang dapat diuji;
- bookmark lama tidak boleh 404;
- route POST check-status tetap tersedia selama transition window;
- bila route action baru dibuat di bawah transaction, route lama menjadi compatibility alias menuju action domain yang sama;
- jangan membuat dua implementasi action.

Setelah satu release cycle, evaluasi penggunaan route lama sebelum cleanup. Jangan otomatis menghapusnya hanya karena UI baru sudah ada.

### 10. Tahap implementasi wajib

#### Fase 1 — Enrich detail transaksi

- Tambahkan scoped reconciliation issues.
- Tambahkan activity read model.
- Pindahkan/duplikasi sementara gateway check action ke konteks payment attempt.
- Pertahankan seluruh sidebar dan halaman lama.

Gate: satu kasus rekonsiliasi dapat diselidiki dan dicek dari detail transaksi tanpa kehilangan audit/evidence.

#### Fase 2 — Workspace tabs

- Tambahkan `view=all|attention|events`.
- Reuse query/filter/table/pagination lama.
- Tambahkan issue badge dan deep links.
- Pertahankan menu lama selama parity QA.

Gate: seluruh filter, group, event unmatched, pagination, dan action lama mempunyai padanan baru.

#### Fase 3 — Entity-scoped audit

- Tambahkan aktivitas pengguna.
- Tambahkan Settings Activity.
- Perbarui links dari incident response/runbook.
- Verifikasi audit tidak bocor antar entitas.

Gate: seluruh jenis audit existing dapat ditemukan dari satu lokasi target yang terdokumentasi.

#### Fase 4 — Simplify navigation

- Hapus tiga item sidebar lama.
- Aktifkan compatibility redirects.
- Update design, ADR-0002 supersession/reference, architecture, runbooks, testing strategy, dan screenshots.

Gate: sidebar empat item, feature parity green, UAT selesai.

#### Fase 5 — Deferred cleanup

- Analisis usage route lama.
- Hapus page/controller/component yang benar-benar tidak lagi dipakai.
- Pertahankan domain queries/models/evidence.
- Cleanup harus menjadi commit/PR terpisah agar mudah direview dan rollback.

### 11. Test wajib

#### Backend feature tests

Tambahkan/perbarui test untuk:

1. `view=all`, `attention`, dan `events` menghasilkan response/props yang tepat.
2. Invalid `view` aman kembali ke `all`.
3. Filter per view tervalidasi dan tidak bocor.
4. Pagination dan sorting setiap view.
5. Seluruh sembilan reconciliation group tersedia.
6. Count badge mengecualikan duplicate webhook informational.
7. Unmatched payment/webhook tetap tampil tanpa transaksi.
8. Detail transaksi hanya memuat issue/event/audit miliknya.
9. Gateway check success, pending/no record, rejected evidence, gateway exception, flash message, cache invalidation, dan audit.
10. Audit pengguna scoped ke target user.
11. Settings Activity memuat login/settings/system audit dan tidak menampilkan secret.
12. Legacy redirects mempertahankan filter/group.
13. Route auth dan satu-access-level behaviour tidak berubah.
14. Tidak ada query N+1 pada list/detail kritis bila project memiliki assertion/tool untuk query count.

#### Frontend tests

1. Active workspace view dari URL.
2. Perubahan tab memperbarui URL/history.
3. Filter aktif, clear, remove, loading, error, retry, empty state.
4. Badge count dan label.
5. Issue/event link menuju transaksi benar.
6. Missing transaction tidak menghasilkan broken link.
7. Detail tabs/anchors dan focus management.
8. Gateway check dialog reason validation, pending state, success/error feedback.
9. Activity ordering dan presentation per source.
10. Audit detail tidak menampilkan secret/sensitive fields.

#### E2E

1. Cari invoice di Semua dan buka detail.
2. Lihat payment attempt, events, webhook, active issue, notes, dan audit.
3. Jalankan **Cek status** lalu pastikan activity/audit berubah.
4. Buka Perlu Ditinjau dan pindah antar seluruh group.
5. Buka issue tanpa payment/order link.
6. Cari rejected/duplicate/processed event pada Event Pembayaran.
7. Buka audit pengguna dari detail user.
8. Buka Aktivitas Sistem dari Pengaturan.
9. Buka setiap legacy URL dan verifikasi redirect/filter.
10. Verifikasi sidebar akhir hanya empat item setelah feature flag/fase akhir aktif.
11. Jalankan responsive test pada mobile, tablet, desktop tanpa horizontal overflow.

### 12. Performance dan observability

- Catat baseline response time dan query count untuk empat halaman lama dan detail transaksi.
- Bandingkan dengan workspace baru menggunakan fixture realistis.
- Jangan memuat event/audit tanpa limit.
- Pagination wajib server-side.
- Count badge boleh di-cache singkat dengan invalidation yang benar.
- Tambahkan log/monitoring hanya jika memakai route pattern dan sanitized metadata; jangan log public reference/query/customer data secara berlebihan.
- Laporkan perbedaan performance sebelum/akhir.

### 13. Documentation updates

Setelah implementasi, sinkronkan:

- `docs/transaction-workspace-consolidation-plan.md` status dan implementation report;
- `docs/decisions/0010-consolidated-transaction-workspace.md` setelah keputusan disetujui;
- `docs/decisions/0002-mvp-payment-monitoring-scope.md` dengan supersession/reference yang tepat, jangan mengedit sejarah tanpa penjelasan;
- `docs/design.md` navigation dan page patterns;
- `docs/architecture.md` frontend feature structure;
- `docs/testing/strategy.md`;
- `docs/runbooks/payment-reconciliation.md`;
- `docs/runbooks/incident-response.md`;
- `docs/README.md`.

Dokumentasikan compatibility window, route mapping, rollout, rollback, dan lokasi baru setiap capability.

### 14. Quality gates

Jalankan seluruh gate repository yang diwajibkan `AGENTS.md`:

```sh
vendor/bin/pint
npm run format
vendor/bin/phpstan analyse --memory-limit=1G
npm run lint
npm run typecheck
npm test
vendor/bin/pest
npm run build
npm run test:e2e
```

Jika suite terlalu panjang, jalankan targeted tests selama iterasi lalu seluruh gate sebelum handoff. Jangan menyatakan semua lulus jika ada command yang tidak dijalankan, skipped secara tidak terduga, atau gagal.

Lakukan visual QA pada:

- desktop sidebar dan workspace;
- mobile drawer;
- tiga workspace view;
- transaction detail dengan dan tanpa payment attempt;
- issue unmatched;
- audit pengguna;
- settings activity;
- loading, empty, error, long text, dan pagination.

Periksa minimal 360 px, 768 px, 1280 px, serta reduced motion. Tidak boleh ada horizontal overflow global, clipped action, inaccessible dialog, atau status yang hanya dibedakan warna.

### 15. Commit dan PR strategy

Gunakan branch feature khusus dan commit kecil, misalnya:

1. `feat: enrich transaction investigation detail`
2. `feat: add transaction workspace views`
3. `feat: scope audit activity by entity`
4. `refactor: simplify back-office navigation`
5. `test: cover consolidated transaction workflows`
6. `docs: document transaction workspace rollout`

Jangan mencampur cleanup deferred dengan implementasi inti jika membuat review sulit. Jangan force push atau rewrite perubahan pengguna.

### 16. Rollout

1. Deploy detail transaksi baru dengan sidebar lama.
2. Uji satu incident/payment reconciliation nyata di staging/fake gateway.
3. Deploy workspace tabs tetapi pertahankan menu lama.
4. Jalankan parity/UAT checklist dengan operator.
5. Deploy entity-scoped audit.
6. Baru sederhanakan sidebar dan aktifkan redirects.
7. Pantau 404, redirect loop, query latency, failed jobs, gateway checks, dan feedback operator.

Jangan melakukan production deployment tanpa persetujuan eksplisit pengguna.

### 17. Rollback

Rollback harus dapat dilakukan tanpa data migration:

- tampilkan kembali menu lama;
- arahkan GET route ke page component lama;
- pertahankan POST gateway check;
- jangan menghapus evidence, event, audit, notes, atau history;
- jangan membatalkan payment/order state yang sudah sah;
- invalidasi cache issue count jika diperlukan.

Pastikan rollback dibuktikan pada staging atau melalui automated routing test.

### 18. Definition of done

Implementasi selesai hanya jika:

- sidebar akhir mempunyai Dashboard, Transaksi, Pengguna, Pengaturan;
- Semua, Perlu Ditinjau, dan Event Pembayaran mencapai feature parity;
- seluruh sembilan reconciliation group dan unmatched evidence tetap dapat ditemukan;
- transaction detail cukup untuk investigasi dan gateway check satu transaksi;
- audit transaksi, pengguna, dan sistem mempunyai lokasi baru yang jelas;
- legacy route tidak langsung rusak;
- payment/order invariant dan auditability tidak berubah;
- tidak ada migration/data deletion destruktif;
- seluruh quality gate dan visual QA lulus;
- docs/runbooks/route mapping/rollout/rollback sinkron;
- tidak ada secret atau data production dalam code, fixture, test output, screenshot, atau laporan;
- pengguna menerima ringkasan bukti implementasi, bukan hanya pernyataan “selesai”.

### 19. Format laporan akhir

Laporkan:

1. Outcome UX dan operasional.
2. Matriks capability lama → baru.
3. File dan modul utama yang berubah.
4. Route mapping dan compatibility window.
5. Data/migration impact.
6. Payment/security invariants yang diverifikasi.
7. Hasil test aktual per command.
8. Hasil visual QA dan viewport.
9. Performance/query comparison.
10. Risiko atau keputusan yang masih terbuka.
11. Rollout dan rollback steps.
12. Branch, commit, dan PR.

Berhenti dan minta keputusan pengguna jika implementasi membutuhkan perubahan state machine, penghapusan capability, migration destruktif, atau penempatan audit yang berbeda dari plan.

---

## Catatan penggunaan

Jalankan prompt ini per fase. Minta agent berhenti setelah Fase 1–3 siap direview sebelum menghapus item sidebar. Strategi tersebut memberi bukti parity dan jalur rollback yang aman sebelum information architecture lama dilepas.
