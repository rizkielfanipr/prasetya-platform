# Prompt Implementasi Penyederhanaan Content Studio

Salin prompt berikut ke coding agent dari repository `/Users/air/Projects/prasetya-group/prasetya-platform`. Prompt ini mengimplementasikan [ADR-0012](decisions/0012-simplified-content-studio-workflow.md) secara bertahap tanpa menghapus revision, approval lama, publication, research, AI run, atau audit.

---

## Prompt

Anda bekerja di repository:

```text
/Users/air/Projects/prasetya-group/prasetya-platform
```

Sederhanakan **Content Studio** dari workflow editorial four-eyes menjadi workflow creator-owned untuk satu operator:

```text
Rencanakan -> AI membuat Draft -> Edit -> Siap posting -> Tandai sudah diposting
                    |              |
                    +-- Draft <----+
```

Posting Instagram tetap manual. Tidak ada Instagram API, n8n, atau image generator. AI dan Hermes hanya boleh menghasilkan Draft dan tidak boleh menandai konten Siap posting, Sudah diposting, atau Diarsipkan.

### 1. Instruksi awal wajib

1. Baca `AGENTS.md` dan `docs/README.md` secara penuh.
2. Baca source of truth berikut secara penuh:
   - `docs/content-studio-simplification-plan.md`;
   - `docs/decisions/0012-simplified-content-studio-workflow.md`;
   - `docs/decisions/0011-content-studio-authority-boundary.md`;
   - `docs/content-studio-ai-planning-plan.md`;
   - `docs/architecture.md`;
   - `docs/design.md` bagian Content Studio;
   - `docs/data-dictionary.md` bagian Content Studio;
   - `docs/testing/strategy.md`;
   - `docs/security/threat-model.md`;
   - `docs/runbooks/content-studio-ai.md`;
   - ADR lain yang dirujuk dokumen tersebut.
3. Periksa keadaan repository:

   ```sh
   pwd
   git status --short
   git branch --show-current
   git log --oneline -8
   git diff --stat
   ```

4. Semua perubahan yang sudah ada adalah milik pengguna. Jangan reset, checkout, stash, clean, hapus, atau menimpa perubahan tersebut.
5. Audit apakah perubahan Content Studio/Brand Knowledge sebelumnya belum di-commit. Jika file yang dibutuhkan tumpang tindih, lanjutkan dengan hati-hati di atas perubahan itu; berhenti hanya bila ada konflik maksud yang benar-benar tidak dapat diputuskan dari source of truth.
6. Kerjakan pada branch feature khusus dari `main`, misalnya `feat/simplify-content-studio`. Jangan push langsung ke `main`.
7. Audit codebase aktual sebelum menentukan migration, enum, route, action, presenter, atau component. Prompt menjelaskan behaviour target, bukan izin untuk mengarang struktur yang tidak cocok dengan repository.
8. Catat baseline sebelum mengubah kode:
   - jumlah record per status Content Studio;
   - relasi `active_revision_id` dan `approved_revision_id`;
   - approval yang terhubung ke revision;
   - publication yang terhubung ke revision;
   - query count Kalender dan detail;
   - test yang sedang hijau;
   - screenshot 360, 768, dan 1280 px.
9. Kerjakan satu fase pada satu waktu. Jangan melompati migration/compatibility langsung ke perubahan UI.
10. Setelah setiap fase: jalankan test proporsional, perbarui dokumentasi, tunjukkan diff/status, dan buat commit Conventional Commit hanya jika pengguna meminta atau workflow eksplisit mengharuskannya.
11. Jangan push, membuat PR, menjalankan migration pada staging/production, mengubah VPS, atau menginstal dependency global tanpa permintaan eksplisit pengguna.

### 2. Keputusan produk final

Jangan menanyakan ulang keputusan berikut:

| Area | Keputusan |
|---|---|
| Operator | Satu pengguna dapat merencanakan, mengedit, menandai Ready, dan mencatat Published |
| Approval | Tidak ada submit, approve, reject, request revision, atau four-eyes pada target workflow |
| Status target | `DRAFT`, `READY`, `PUBLISHED`, `ARCHIVED` |
| AI/Hermes | Hanya boleh membuat atau memperbarui Draft |
| Posting | Manual ke Instagram |
| Published | Hanya melalui aksi eksplisit pengguna |
| Instagram API | Tidak digunakan |
| n8n | Tidak digunakan |
| Image generator | Tidak digunakan; gambar diunggah manual |
| Workspace utama | Kalender |
| Navigasi | Kalender, Insight, Brand Knowledge, Pengaturan AI |
| Create form | Ide/topik, jadwal, campaign opsional |
| Editor utama | Brief, caption/hashtag, gambar, temuan brand, aksi Ready |
| Field teknis | Tetap disimpan, ditaruh dalam Pengaturan tambahan |
| Campaign utama | Nama, periode, tujuan |
| Posting checklist | Tidak wajib dan tidak memblokir publication |
| History | Revision, approval lama, publication, source, AI run, dan audit tetap disimpan |

### 3. Target hasil akhir

```text
Content Studio / Kalender
  -> membuat Draft dari ide + jadwal + campaign opsional
  -> AI/manual mengisi brief, caption, hashtag, alt text, visual direction
  -> manusia memperbaiki Draft
  -> manusia menandai Siap posting setelah brand gate lulus
  -> manusia mengunduh/membagikan gambar dan menyalin caption
  -> manusia posting manual ke Instagram
  -> manusia mencatat waktu posting dan URL opsional
  -> Insight menggunakan publication yang tercatat
```

Tidak ada perubahan status berbasis waktu. Tanggal rencana yang lewat hanya menghasilkan pengingat.

### 4. Invariant yang tidak boleh dilanggar

#### Platform

- Jangan mengubah invariant order, payment, package catalogue, Duitku, atau capability transaksi.
- Controller tetap tipis; business rule berada di `App\Domain\Content`.
- Network call tidak boleh terjadi di dalam database transaction.
- Semua route Content Studio tetap berada di balik session auth dan active-user protection.
- Public route memakai ULID/public id, bukan numeric id.
- Vue memakai semantic tokens, tanpa raw colour dan tanpa `any`.
- Route, status label, formatter, dan type mempunyai satu source of truth.

#### Content

- AI output selalu Draft.
- AI, queue, scheduler, dan Hermes tidak boleh menjalankan transition Ready, Published, atau Archived.
- `READY` adalah completion marker manusia, bukan approval.
- `PUBLISHED` hanya berasal dari tindakan manusia yang mencatat posting aktual.
- Edit konten Ready membuat revision baru dan otomatis kembali ke Draft.
- Error brand rule mencegah Ready; warning tidak.
- Tanggal rencana yang lewat tidak mengubah status.
- Published dan Archived terminal dalam UI normal.
- Publication, revision, approval lama, source, generation run, dan audit tidak dihapus sebagai side effect.
- Data unresolved dari bagian `Informasi yang Perlu Dikonfirmasi` Brand Knowledge tidak boleh menjadi fakta prompt.

#### Data dan compatibility

- Jangan drop `content_approvals`, `approved_revision_id`, old decision rows, atau checklist evidence pada migration pertama.
- Jangan membuat approval palsu untuk item Ready baru.
- Jangan mengubah historical actor, timestamp, note, revision, atau publication.
- Migration harus dapat diuji pada database berisi seluruh status lama.
- Backfill harus idempotent atau mempunyai guard yang jelas.
- Rollback tidak boleh mengarang evidence yang sebelumnya tidak ada.

#### Secret dan privacy

- Jangan mengirim customer, order, payment, API key, credential, atau data pribadi ke prompt Content Studio.
- Jangan menampilkan atau mencatat Authorization header, `.env`, raw provider response, atau secret.
- Gunakan fake provider pada seluruh automated test.

### 5. Fase 0 — Audit, ADR acceptance, dan baseline

Sebelum coding:

1. Petakan kode aktual:

   | Capability | File/action/model/route saat ini | Perubahan target | Risiko compatibility |
   |---|---|---|---|
   | Content status enum | | | |
   | Availability/policy | | | |
   | Create/edit action | | | |
   | Revision immutability | | | |
   | Approval decisions | | | |
   | Publication | | | |
   | Calendar/list/to-post | | | |
   | Campaign | | | |
   | AI generation | | | |
   | Hermes/internal tools | | | |
   | Audit/presenter | | | |
   | Tests/fixtures | | | |

2. Verifikasi data lokal tanpa menampilkan caption atau data sensitif:
   - count per status;
   - count item dengan/ tanpa active revision;
   - count approved item dengan approval evidence;
   - count publication per revision;
   - orphan/invalid relation jika ada.
3. Jadikan ADR-0012 `Accepted` hanya karena prompt ini membawa keputusan implementasi final; tambahkan tanggal acceptance bila format repository membutuhkannya.
4. Perbarui `docs/design.md`, `docs/data-dictionary.md`, `docs/testing/strategy.md`, dan runbook menjadi kontrak target sebelum perubahan behaviour.
5. Buat migration rehearsal/test fixture untuk semua status lama.

**Gate Fase 0:** audit, rencana file, baseline, mapping data, rollback, dan dokumen target selesai. Jangan mengubah status produksi pada fase ini.

### 6. Fase 1 — Domain lifecycle dan compatibility

Implementasikan target lifecycle dengan intention-revealing actions:

```text
DRAFT <-> READY -> PUBLISHED
  |         |
  +---------+-> ARCHIVED
```

Action minimum, sesuaikan nama dengan convention repository:

- `MarkContentReady`;
- `ReturnContentToDraft`;
- `ArchiveContent`;
- `MarkContentPublished` yang disesuaikan agar menerima Ready revision;
- action edit/save yang mengembalikan Ready ke Draft dengan revision baru.

Aturan:

- hindari generic `setStatus(string)`;
- action mengunci row yang relevan dan memvalidasi expected current revision/status;
- Ready membutuhkan active revision yang sama dengan revision yang diperiksa;
- Ready membutuhkan caption, alt text, dan gambar valid;
- brief wajib untuk AI generation, tetapi tidak memblokir caption manual menjadi Ready;
- blocking brand findings mencegah Ready;
- Return to Draft tidak menghapus revision Ready;
- edit Ready membuat revision baru, mengaktifkannya, dan menyimpan audit;
- Published menyimpan revision aktual, actor, actual posting time, URL/note opsional;
- Archived menyimpan actor, waktu, dan alasan opsional tanpa menghapus data.

#### Migration status

Pemetaan minimum:

| Lama | Target |
|---|---|
| `DRAFT` | `DRAFT` |
| `READY_FOR_REVIEW` | `DRAFT` |
| `REVISION_REQUESTED` | `DRAFT` |
| `APPROVED` | `READY` |
| `PUBLISHED` | `PUBLISHED` |
| `REJECTED` | `ARCHIVED` |

Audit schema aktual sebelum memilih strategi kolom. Preferensi aman:

- tambahkan `ready_revision_id` bila mengganti makna `approved_revision_id` secara in-place akan mengaburkan evidence;
- backfill `ready_revision_id` dari current/approved revision untuk item lama yang dipetakan Ready;
- pertahankan `approved_revision_id` sebagai legacy evidence selama compatibility window;
- jangan membuat row approval baru untuk Ready;
- tambahkan metadata archive hanya bila dibutuhkan dan tidak dapat direpresentasikan dengan audit yang ada.

Jika strategi yang lebih sederhana terbukti aman melalui kode dan fixture aktual, dokumentasikan alasan sebelum menerapkannya.

#### Endpoint target

Gunakan route naming convention proyek. Capability minimum:

```text
POST /content/items/{contentItem}/ready
POST /content/items/{contentItem}/return-to-draft
POST /content/items/{contentItem}/archive
POST /content/items/{contentItem}/publication
```

Route mutation approval lama tidak boleh dipakai oleh UI baru. Pertahankan hanya selama compatibility window dengan penolakan domain yang aman atau removal yang disertai bukti tidak ada caller. Jangan biarkan endpoint lama melewati invariant baru.

**Gate Fase 1:** seluruh transition valid/invalid, migration mapping, legacy evidence, audit, authorization, dan rollback rehearsal lulus. Behaviour payment/order tidak berubah.

### 7. Fase 2 — Kalender terpadu dan navigasi

Ubah secondary navigation menjadi:

- Kalender;
- Insight;
- Brand Knowledge;
- Pengaturan AI.

Kalender menjadi workspace utama:

- navigasi minggu sebelumnya/berikutnya dan Minggu ini;
- filter utama: Semua, Draft, Siap posting, Sudah diposting;
- Filter lainnya: brand, pilar, campaign, arsip;
- tombol Tambah konten;
- slot/tombol Rencanakan minggu dengan AI bila capability sudah ada; jangan membuat provider baru pada fase ini;
- card: waktu, judul, status dalam kata, thumbnail/indikator gambar;
- overdue adalah label/pengingat, bukan transition;
- komposisi pilar menjadi ringkasan yang dapat dibuka.

Capability dari halaman lama:

- pencarian/list masuk ke mode daftar atau filter Kalender;
- Ready queue menjadi filter/kelompok Kalender;
- `/content/items` dan `/content/to-post` redirect ke Kalender dengan query setara;
- bookmark detail item tetap bekerja;
- jangan menghasilkan redirect loop atau kehilangan filter.

Semua status harus dibaca dengan teks dan tidak mengandalkan warna. Gunakan komponen serta semantic token proyek.

**Gate Fase 2:** seluruh pekerjaan rutin dapat dimulai dari Kalender; route lama kompatibel; tidak ada overflow pada 360, 768, dan 1280 px; keyboard dan axe WCAG A/AA lulus.

### 8. Fase 3 — Form create dan editor minimal

#### Form tambah konten

Primary fields maksimum:

1. `Ide atau topik` — wajib;
2. `Jadwal posting` — wajib, WIB;
3. `Campaign` — opsional dan hanya tampil bila ada campaign relevan.

Perilaku:

- brand otomatis jika hanya satu;
- default jadwal berasal dari hari yang dipilih, pukul 10.00 bila waktu belum ditentukan;
- title dibuat deterministik dari ide; AI enhancement boleh terjadi kemudian dan tidak boleh menggagalkan create;
- pillar direkomendasikan/dipilih otomatis hanya jika rule deterministik cukup; jika tidak, nullable;
- campaign tidak pernah wajib;
- create selalu menghasilkan Draft.

#### Editor utama

Urutan UI:

1. jadwal dan status;
2. satu textarea Brief;
3. Buat dengan AI/Buat ulang;
4. Caption dengan hashtag pada satu area visual;
5. image upload/preview;
6. temuan brand;
7. Siap posting.

Disclosure **Pengaturan tambahan** memuat:

- title bila perlu;
- pilar;
- audience;
- objective;
- visual direction;
- alt text;
- source/research;
- revision dan AI run history.

Ketentuan:

- data tetap menggunakan field relational/typed yang sudah ada; jangan gabungkan semuanya menjadi JSON atau satu kolom baru;
- hashtag tetap disimpan terpisah untuk validator meskipun tampil menyatu;
- field dengan error otomatis dibuka dan fokus diarahkan dengan benar;
- AI provider down tidak menghalangi edit manual;
- AI generation hanya tersedia pada Draft;
- hilangkan panel Tinjauan dan seluruh copy approval dari UI target.

**Gate Fase 3:** pengguna dapat membuat dan menyelesaikan Draft tanpa melihat field teknis, tetapi seluruh metadata lama tetap dapat dibaca dan diubah lewat progressive disclosure.

### 9. Fase 4 — Posting package dan campaign minimal

#### Posting package

Untuk Ready:

- preview image;
- Bagikan ke Instagram bila Web Share tersedia;
- Unduh gambar;
- Salin caption;
- Salin hashtag bila dibutuhkan;
- Tandai sudah diposting.

Publication dialog:

- actual posting time wajib, default waktu sekarang dalam WIB;
- URL Instagram opsional;
- note opsional;
- tidak ada checklist wajib;
- publication tetap immutable dan auditable.

#### Campaign

Primary fields:

- nama;
- tanggal mulai;
- tanggal selesai;
- tujuan.

Aturan:

- aktif untuk pilihan konten diturunkan dari periode dalam business timezone WIB;
- campaign di luar periode tidak ditawarkan untuk konten baru;
- priority dan manual `is_active` lama tetap readable selama compatibility window;
- request baru tidak membutuhkan priority/manual active bila schema dapat memberi compatibility default yang aman;
- campaign management tetap berada di Kalender.

**Gate Fase 4:** operator dapat menyelesaikan Ready sampai publication dengan langkah minimum; campaign baru dapat dibuat tanpa field administratif lama.

### 10. Fase 5 — Weekly AI planner

Kerjakan hanya setelah Fase 1–4 stabil dan research/provider infrastructure yang ada sudah diaudit.

Alur:

1. Pengguna memilih minggu.
2. Laravel menyiapkan Brand Knowledge aktif, campaign berjalan, histori topik, insight, komposisi pilar, dan sumber riset yang sudah tersimpan.
3. AI/Hermes mengembalikan kandidat ide dengan alasan, pillar recommendation, dan schedule recommendation.
4. Pengguna memilih kandidat dan dapat mengubah judul/jadwal.
5. Hanya kandidat terpilih dibuat sebagai Draft.

Larangan:

- proposal tidak otomatis menjadi ContentItem;
- proposal terpilih tidak otomatis menjadi Ready;
- agent tidak mendapat tool Ready, Published, Archive, credential, DB, shell, atau unrestricted HTTP;
- web content tetap untrusted data;
- semua bounded-write memakai run id, idempotency key, expected version, timeout, dan audit.

**Gate Fase 5:** satu minggu kandidat dapat dibuat dalam budget, dipilih manual, dan menjadi Draft tanpa forbidden capability.

### 11. Presenter, status, dan copy

Perbarui satu source of truth backend/frontend untuk:

- label Draft, Siap posting, Sudah diposting, Diarsipkan;
- urutan status;
- badge semantic;
- action availability dan disabled reason;
- audit action labels;
- filters/query serialization;
- insight denominator/labels bila sebelumnya bergantung pada Approved;
- evaluasi AI yang sebelumnya menghitung later-approved menjadi later-ready atau later-published.

Hapus copy user-facing berikut dari workflow target:

- Ajukan untuk ditinjau;
- Menunggu tinjauan;
- Setujui;
- Tolak;
- Minta revisi;
- Pengguna lain harus menyetujui.

Jangan menghapus copy tersebut dari historical decision timeline bila diperlukan untuk menjelaskan approval lama.

### 12. Audit events

Gunakan convention repository dan jangan mencatat isi caption lengkap. Capability minimum:

```text
content.created
content.updated
content.marked_ready
content.returned_to_draft
content.archived
content.published
```

Simpan actor, entity public id, previous/next state yang aman, revision reference, dan request id bila tersedia. Event approval lama tetap dapat dirender.

### 13. Required tests

#### Domain dan migration

1. Seluruh transition valid dan invalid.
2. AI/system actor tidak dapat Ready, Publish, atau Archive.
3. Ready menolak missing caption, alt text, image, atau blocking brand errors.
4. Warning tidak memblokir Ready.
5. Edit Ready membuat revision baru dan kembali Draft.
6. Published/Archived terminal.
7. Mapping seluruh status lama benar.
8. Approval lama dan publication tetap queryable.
9. Tidak ada approval palsu untuk Ready baru.
10. Migration rehearsal dan rollback compatibility.
11. Public id route; numeric id tidak bocor.
12. Audit actor/action/revision/status benar.

#### AI dan research

1. Generate hanya pada Draft.
2. Queue completion tidak mengubah status manusia.
3. Provider timeout/failure tidak merusak Draft.
4. Retry idempotent dan tidak menggandakan revision.
5. Brand Knowledge unresolved section tidak masuk prompt.
6. Existing research source/citation tetap terhubung.
7. Seluruh HTTP difake; automated test tidak memakai jaringan nyata.

#### Frontend/component

1. Create form hanya menampilkan field yang relevan.
2. Campaign tersembunyi bila tidak ada pilihan.
3. Advanced fields dapat dibuka, diedit, dan otomatis terbuka saat error.
4. Calendar status filter mempertahankan URL state.
5. Route lama redirect dengan query yang benar.
6. Ready/Draft actions mempunyai disabled reason yang accessible.
7. Copy/share/download memberi live-region feedback.
8. Tidak ada approval action/copy pada UI baru.
9. Historical approval timeline tetap terbaca.

#### E2E

1. Buat Draft dari ide dan jadwal.
2. Generate AI atau isi manual.
3. Mark Ready.
4. Edit Ready dan kembali Draft.
5. Ready -> posting package -> Published.
6. Draft/Ready -> Archived.
7. Filter Kalender dan overdue reminder.
8. Legacy `/content/items` dan `/content/to-post` redirect.
9. Campaign minimal.
10. Provider down dengan manual fallback.
11. 360×780, 768×1024, dan 1280×900 tanpa horizontal overflow.
12. Reduced motion, keyboard, focus restoration, dan axe WCAG A/AA.

#### Regression

- auth/user management;
- transaction workspace;
- package catalogue;
- order/payment/Duitku;
- Brand Knowledge versioning/retrieval;
- Content research security;
- Insight summaries;
- architecture boundary tests.

### 14. Quality gates setiap fase

Ikuti `AGENTS.md`. Sebelum fase dinyatakan selesai, jalankan:

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

Tambahkan pemeriksaan:

```sh
git diff --check
```

Bandingkan query count dan median response Kalender/detail dengan baseline. Kenaikan harus dijelaskan dan diperbaiki bila berasal dari N+1 atau eager loading yang hilang.

### 15. Visual QA wajib

Ambil screenshot dan periksa secara nyata pada:

- 360×780;
- 768×1024;
- 1280×900;
- reduced motion.

Periksa:

- Calendar week/list mode;
- create dialog dengan/tanpa campaign;
- Draft detail;
- advanced disclosure;
- blocking brand error;
- Ready posting package;
- Published detail;
- Archived historical detail;
- mobile navigation/drawer;
- long Indonesian copy dan long title;
- empty, loading, error, retry, dan no-result.

Jangan menyatakan visual QA selesai hanya berdasarkan test DOM. Inspeksi screenshot dan catat overflow, clipping, hierarchy, focus, contrast, dan tap target.

### 16. Dokumentasi yang harus selesai bersama implementasi

Perbarui sesuai behaviour yang benar-benar sudah ship:

- `docs/decisions/0012-simplified-content-studio-workflow.md`;
- `docs/content-studio-simplification-plan.md` status per fase;
- `docs/design.md`;
- `docs/data-dictionary.md`;
- `docs/testing/strategy.md`;
- `docs/runbooks/content-studio-ai.md`;
- `docs/security/threat-model.md` bila authority/tool berubah;
- prompt Hermes/internal tools bila tool schema berubah;
- `docs/README.md` bila status dokumen berubah.

Jangan menandai fase selesai sebelum code, migration, test, screenshot, dan dokumentasi selaras.

### 17. Strategi commit dan PR

Pisahkan agar mudah direview dan rollback:

1. `docs: accept simplified content workflow`
2. `refactor(content): add creator-owned lifecycle compatibility`
3. `refactor(content): make calendar the primary workspace`
4. `refactor(content): simplify content creation and editing`
5. `refactor(content): simplify posting and campaigns`
6. `feat(content): add AI weekly planning` — hanya bila Fase 5 dikerjakan

Jangan memasukkan seluruh fase dalam satu commit besar. Jangan force push. Jangan merge atau deploy tanpa instruksi pengguna.

### 18. Format laporan setiap fase

Laporkan:

```text
Fase:
Outcome:
File utama:
Migration/data mapping:
Compatibility:
Test dan hasil:
Performance sebelum/sesudah:
Visual QA:
Dokumentasi:
Risiko tersisa:
Commit:
Next gate:
```

Jika menemukan data lama yang tidak sesuai invariant, jangan menghapus atau memperbaikinya diam-diam. Laporkan jumlah dan bentuk inkonsistensinya tanpa menampilkan konten sensitif, tambahkan guard/test, lalu minta keputusan bila perbaikannya mengubah bukti historis.

### 19. Definition of done

Pekerjaan selesai hanya jika:

- workflow user-facing hanya Draft, Siap posting, Sudah diposting, dan Diarsipkan;
- tidak ada approval formal pada UI atau action target;
- AI/Hermes terbukti hanya dapat menghasilkan Draft;
- Kalender menampung planning, list/filter, dan ready reminders;
- create form hanya meminta ide, jadwal, dan campaign opsional;
- editor utama ringkas dan field teknis tetap tersedia secara progresif;
- edit Ready kembali ke Draft dengan revision baru;
- publication tetap manual, immutable, dan audited;
- approval/revision/publication lama tetap dapat dibaca;
- migration serta rollback rehearsal lulus;
- regression, accessibility, responsive, performance, dan security gates hijau;
- dokumentasi menggambarkan behaviour akhir secara akurat;
- tidak ada secret, data pribadi, raw provider response, atau perubahan payment/order yang bocor.

Mulai dari Fase 0. Jangan langsung mengubah enum/status sebelum audit data dan compatibility plan selesai.
