# Rencana Penyederhanaan Content Studio

| Field | Value |
|---|---|
| Status | Disetujui; implementasi bertahap (Fase 0–4 selesai) |
| Owner | Prasetya Group |
| Last updated | 2026-11-09 |
| Decision | [ADR-0012](decisions/0012-simplified-content-studio-workflow.md) |
| Related | [`content-studio-ai-planning-plan.md`](content-studio-ai-planning-plan.md), [`design.md`](design.md), [`data-dictionary.md`](data-dictionary.md), [`testing/strategy.md`](testing/strategy.md) |

## Ringkasan

Content Studio disederhanakan untuk satu operator yang merencanakan, membuat, dan memposting kontennya sendiri. Approval formal dihapus dari target workflow, tetapi keputusan publikasi tetap milik manusia dan posting tetap dilakukan manual di Instagram.

Alur target:

```text
Rencanakan -> AI membuat Draft -> Edit seperlunya -> Siap posting -> Tandai sudah diposting
                    |                    |
                    +------ Draft <-----+
```

Tujuan perubahan:

- mengurangi perpindahan halaman dan jumlah keputusan administratif;
- memungkinkan konten dimulai dengan tiga input;
- menjaga data rinci tersedia tanpa memenuhi layar utama;
- mempertahankan revision history, AI provenance, research source, audit, dan insight;
- memastikan AI tidak pernah memublikasikan atau menyatakan pekerjaannya sendiri siap diposting.

## Batasan keputusan

- Tidak ada Instagram API, auto-post, atau publikasi berdasarkan jadwal.
- Tidak ada submit, approve, reject, request revision, atau aturan four-eyes pada target workflow.
- AI dan Hermes hanya boleh menghasilkan atau memperbarui Draft.
- Hanya tindakan eksplisit pengguna yang dapat menandai **Siap posting**, **Sudah diposting**, atau **Diarsipkan**.
- Pelanggaran brand yang bersifat error tetap mencegah **Siap posting**; warning hanya memberi informasi.
- Riwayat approval lama tidak dihapus dan tidak ditulis ulang.

## Information architecture target

Navigasi Content Studio:

| Menu | Tanggung jawab |
|---|---|
| Kalender | Perencanaan, daftar mingguan, filter status, campaign, dan pintu masuk editor |
| Insight | Pencatatan serta ringkasan performa posting manual |
| Brand Knowledge | Sumber pengetahuan versi aktif yang dipakai AI dan validator |
| Pengaturan AI | Model, kesehatan provider, penggunaan, evaluasi, dan riwayat run |

Perubahan navigasi:

- **Konten** tidak lagi menjadi menu utama; pencarian dan daftar tersedia sebagai mode daftar/filter Kalender.
- **Siap posting** tidak lagi menjadi menu utama; menjadi filter dan kelompok pengingat di Kalender.
- `/content/items` dan `/content/to-post` dipertahankan sementara sebagai redirect yang membawa filter setara.
- Detail konten tetap dapat memakai route sendiri pada fase pertama. Side panel dapat dievaluasi kemudian tanpa menjadi syarat migrasi domain.

## Kalender sebagai pusat kerja

Header utama berisi:

- minggu sebelumnya dan berikutnya;
- **Minggu ini**;
- filter ringkas: Semua, Draft, Siap posting, Sudah diposting;
- **Filter lainnya** untuk brand, pilar, campaign, dan arsip;
- **Tambah konten**;
- **Rencanakan minggu dengan AI** setelah capability tersebut tersedia.

Kartu kalender menampilkan hanya informasi yang diperlukan untuk memilih pekerjaan:

```text
10.00
Kenapa UMKM butuh website
Draft                         [thumbnail]
```

Setiap status selalu ditulis dengan kata, bukan warna saja. Konten terlambat diberi keterangan relatif, tetapi tanggal lewat tidak mengubah status.

Komposisi pilar menjadi ringkasan satu baris yang dapat dibuka:

```text
Minggu ini: 5 konten · Edukasi 2 · Portofolio 1 · Promosi 1 · Proses 1
```

Grafik target rinci hanya tampil setelah ringkasan dibuka atau di Insight.

## Membuat konten

Form utama mempunyai maksimal tiga input:

| Field | Aturan |
|---|---|
| Ide atau topik | Wajib, bahasa bebas, menjadi dasar judul kerja dan brief |
| Jadwal posting | Wajib, WIB, default pada hari yang dipilih atau pukul 10.00 |
| Campaign | Opsional; hanya ditampilkan jika ada campaign yang relevan |

Perilaku otomatis:

- brand dipilih otomatis bila hanya ada satu;
- judul kerja berasal dari ide dan tetap dapat diubah;
- pilar direkomendasikan berdasarkan ide, Brand Knowledge, dan distribusi minggu itu;
- audience dan objective berasal dari Brand Knowledge/campaign;
- item langsung dibuat sebagai Draft;
- kegagalan AI tidak menggagalkan pembuatan Draft.

Field tambahan tidak dibuang. Pillar, judul, audience, objective, dan metadata campaign dapat diubah melalui **Pengaturan tambahan**.

## Editor konten

Urutan permukaan utama:

1. Ringkasan jadwal dan status.
2. **Brief** sebagai satu textarea instruksi kerja.
3. **Buat dengan AI** atau **Buat ulang**.
4. Caption beserta hashtag.
5. Gambar/media.
6. Temuan aturan brand yang relevan.
7. Aksi **Siap posting**.

Field yang tetap disimpan tetapi ditempatkan dalam disclosure **Pengaturan tambahan**:

- pilar;
- target audiens;
- objective;
- visual direction;
- alt text;
- sumber riset;
- riwayat versi dan AI run.

Aturan data:

- brief tetap menjadi field tersimpan dan konteks AI;
- hashtag tetap tersimpan terpisah agar dapat divalidasi, walaupun tampil menyatu dengan caption;
- alt text dihasilkan sebagai saran dan dapat diedit;
- caption, hashtag, alt text, visual direction, dan gambar tetap mempunyai revision history;
- edit atas konten Ready membuat revisi baru dan mengembalikan status menjadi Draft.

## Lifecycle target

| Status | Makna | Aksi utama |
|---|---|---|
| Draft | Sedang direncanakan, dibuat AI, atau diedit | Edit, buat dengan AI, tandai Siap posting, arsipkan |
| Siap posting | Operator menyatakan materi selesai | Buka paket posting, kembali ke Draft, arsipkan |
| Sudah diposting | Operator mencatat posting Instagram yang benar-benar terjadi | Catat insight, lihat riwayat |
| Diarsipkan | Rencana dibatalkan tanpa menghapus bukti | Lihat riwayat |

Transisi:

```text
DRAFT <-> READY -> PUBLISHED
  |         |
  +---------+-> ARCHIVED
```

AI, queue, scheduler, dan Hermes hanya boleh menghasilkan atau mengubah revision pada `DRAFT`. Tidak ada transisi berbasis waktu.

## Pemeriksaan brand tanpa approval

- Validator berjalan saat menyimpan dan sebelum Ready.
- Error tampil dekat field terkait dan mencegah Ready.
- Warning tidak mencegah Ready.
- Tidak ada panel Tinjauan atau reviewer.
- Tombol Ready menjelaskan kekurangan yang masih harus dilengkapi.
- Minimum Ready: caption, alt text, dan gambar valid. Brief diperlukan untuk AI, tetapi pengguna dapat menulis caption manual tanpa AI.

## Paket posting

Paket hanya muncul untuk Ready dan Published:

- preview gambar;
- **Bagikan ke Instagram** bila Web Share tersedia;
- **Unduh gambar**;
- **Salin caption**;
- **Salin hashtag** bila diperlukan terpisah;
- **Tandai sudah diposting**.

Dialog publikasi meminta:

- waktu posting aktual, wajib dan default sekarang;
- URL Instagram, opsional;
- catatan, opsional.

Checklist empat langkah tidak lagi menjadi syarat. Audit actor, waktu, revision, dan detail publikasi tetap menjadi bukti.

## Campaign minimal

Form utama campaign:

- nama;
- tanggal mulai dan selesai;
- tujuan singkat.

Aturan:

- status aktif diturunkan dari periode;
- campaign di luar periode tidak ditawarkan untuk konten baru;
- priority dan `is_active` lama tetap dapat dibaca selama masa kompatibilitas;
- data tambahan, jika nanti dibutuhkan, masuk ke disclosure lanjutan dan bukan form utama.

## Rencanakan minggu dengan AI

Alur yang direncanakan:

1. Pengguna memilih minggu dan membuka **Rencanakan minggu dengan AI**.
2. Sistem memberi AI Brand Knowledge aktif, campaign berjalan, histori topik, insight, komposisi pilar, serta sumber riset tepercaya.
3. AI mengembalikan kandidat ide, alasan, pilar rekomendasi, dan jadwal rekomendasi.
4. Pengguna memilih kandidat dan boleh mengubah judul/jadwal.
5. Hanya kandidat terpilih yang dibuat sebagai Draft.

Pemilihan ide bukan approval konten. Proposal yang tidak dipilih tidak menjadi item, dan AI tidak dapat menandai kandidat Ready.

## Kompatibilitas dan migrasi

Pemetaan data yang diusulkan:

| Status lama | Status target | Catatan |
|---|---|---|
| `DRAFT` | `DRAFT` | Tidak berubah |
| `READY_FOR_REVIEW` | `DRAFT` | Revision tetap tersimpan |
| `REVISION_REQUESTED` | `DRAFT` | Catatan keputusan tetap menjadi history |
| `APPROVED` | `READY` | Approval lama tetap read-only |
| `PUBLISHED` | `PUBLISHED` | Tidak berubah |
| `REJECTED` | `ARCHIVED` | Alasan lama tetap tersedia |

Strategi delivery:

1. Tambahkan pembacaan kompatibel dan status label baru sebelum migrasi data.
2. Tambahkan action `MarkContentReady`, `ReturnContentToDraft`, dan `ArchiveContent`.
3. Hentikan penulisan approval baru, tetapi pertahankan relasi dan presenter history.
4. Migrasikan status dalam transaksi yang dapat diverifikasi dan dibatalkan.
5. Ubah UI dan redirect route lama.
6. Setelah satu siklus rilis stabil, evaluasi penghapusan kolom `approved_revision_id`; tabel approval tidak dihapus.

Rollback mengembalikan status target ke status kompatibel (`READY -> APPROVED`, `ARCHIVED -> REJECTED`) tanpa membuat approval palsu. Karena itu kode lama tidak boleh dijalankan kembali tanpa compatibility release yang memahami absence of a real approval row.

## Tahapan implementasi

### Fase 0 — Kontrak dan baseline

- Terima ADR-0012.
- Bekukan fixture status lama dan hitung record per status.
- Catat baseline query, responsif, accessibility, dan E2E.
- Finalisasi istilah UI: Draft, Siap posting, Sudah diposting, Diarsipkan.

**Exit:** pemetaan migrasi dan rollback telah diuji pada salinan database.

**Status (2026-11-08): selesai** di branch `feat/simplify-content-studio`.

- ADR-0012 diterima. Runtime tetap mengikuti ADR-0011 sampai Fase 1 ship.
- `App\Domain\Content\Migration\LegacyStatusMap` memegang pemetaan maju dan mundur; `ContentStatusAudit` dan `php artisan content:status-audit` (read-only, tanpa isi konten) menghitung status, relasi revision, bukti approval dan publikasi, serta pelanggaran invariant. Jalankan pada salinan database production sebelum migrasi; perintah gagal bila ada status tak dikenal atau pelanggaran.
- Fixture `legacyContentFixture()` membangun keenam status lama lewat action asli; `StatusMigrationRehearsalTest` menguji pemetaan, rollback tanpa approval palsu, deteksi data rusak, dan bahwa audit tidak mengubah data.
- Data saat audit: database lokal 1 item (`DRAFT`), database e2e 7 item (`DRAFT` 2, `READY_FOR_REVIEW` 1, `REVISION_REQUESTED` 1, `APPROVED` 1, `PUBLISHED` 2), 5 approval, 2 publikasi, tanpa pelanggaran. Item `PUBLISHED` menyimpan `approved_revision_id` sebagai bukti revisi yang diposting; ini sah dan dipertahankan.
- Baseline: Pest 534, Vitest 96, e2e 67 hijau. Query (e2e, median): Kalender 13 query 9,9 ms; Konten 12 query 10,2 ms; Siap posting 9 query 5,1 ms; detail item disetujui 28 query 15,9 ms. Screenshot 360×780, 768×1024, 1280×900 dengan reduced motion, tanpa overflow.
- Strategi kolom untuk Fase 1: tambah `content_items.ready_revision_id` (nullable), backfill dari `approved_revision_id` untuk `APPROVED` → `READY`; `approved_revision_id` dan `content_approvals` tetap sebagai bukti lama dan tidak diisi untuk Ready baru. Arsip memakai audit `content.archived` (actor, waktu, alasan opsional) tanpa kolom baru kecuali kebutuhan tampilan membuktikan sebaliknya.

### Fase 1 — Domain compatibility

- Tambahkan status/action target.
- Ubah availability dan brand gate.
- Pertahankan history revision/approval/publication.
- Larang AI dan Hermes melakukan transisi manusia.

**Exit:** feature test state machine dan migration test lulus.

**Status (2026-11-09): selesai** di branch `feat/simplify-content-studio`. Runtime kini mengikuti ADR-0012.

- `ContentStatus`: `DRAFT`, `READY`, `PUBLISHED`, `ARCHIVED`. Action baru `MarkContentReady`, `ReturnContentToDraft`, `ArchiveContent` (alasan opsional, audit `content.archived`); `SubmitContentForReview`, `ReviewContent`, dan `RevisionRequestLookup` dihapus. `ContentAvailability` menjawab `edit`, `editDetails`, `markReady`, `returnToDraft`, `archive`, `markPublished` dengan alasan dalam kata.
- Ready membekukan revisi aktif (memakai imutabilitas `submitted_at`) dan mencatat versi aturan brand; error aturan brand mencegah Ready, warning tidak. Brief tidak lagi wajib; caption, alt text, dan gambar wajib. Revisi yang sudah beku tidak dibekukan ulang saat ditandai Ready lagi.
- Edit konten Ready membuat revisi baru dan mengembalikan item ke Draft (`ready_revision_id` dikosongkan). Published hanya dari orang yang mencatat posting pada revisi Ready saat ini; waktu posting tidak boleh sebelum revisi itu ditandai Ready. Checklist posting tetap wajib sampai Fase 4.
- AI hanya menulis item Draft; run yang selesai setelah item menjadi Ready dibuang (`DISCARDED`). Catatan peninjau tidak lagi dikirim ke prompt (`copy-v4` tidak berubah).
- Route `submit-review`, `request-revision`, `approve`, `reject` tetap ada selama compatibility window, tetap di balik auth dan active-user, dan hanya menolak dengan pesan "Alur pengajuan dan persetujuan sudah tidak dipakai. Gunakan Siap posting." tanpa mengubah data. Filter status lama di URL (`?status=APPROVED`) dibaca sebagai status barunya.
- Migration `2026_11_09_000001_simplify_content_lifecycle`: tambah `content_items.ready_revision_id`; data dipetakan oleh `LegacyStatusBackfill` (idempotent). `APPROVED` menjadi Ready pada revisi aktif bila revisi itu beku dan tidak bertentangan dengan approval revisi lain; item yang disetujui pada revisi lama tetap tanpa `ready_revision_id`, tidak bisa diposting, dan dilaporkan audit. `approved_revision_id`, `content_approvals`, area keputusan, revisi, publikasi, dan checklist tidak disentuh. Rollback hanya mengembalikan status (`READY`→`APPROVED`, `ARCHIVED`→`REJECTED`) lalu menghapus kolom; tidak pernah membuat approval, sehingga audit sesudah rollback melaporkan item Ready baru tanpa approval apa adanya.
- Rehearsal pada database e2e: maju → audit bersih; rollback → audit melaporkan 1 item `APPROVED` tanpa approval (item Ready baru), sesuai desain; maju lagi → audit bersih, dan menjalankan ulang migrate tidak mengubah apa pun.
- UI minimal: panel **Status** menggantikan Tinjauan (Tandai siap posting, Kembali ke Draft, Arsipkan; tombol yang tidak tersedia tetap tampil dengan alasan), label status dalam kata, riwayat menandai revisi *siap posting* dan *disetujui (alur lama)*. Penataan Kalender dan editor menyusul di Fase 2 dan 3.
- Hasil: Pest 547, Vitest 96, e2e 68 hijau; PHPStan dan lint bersih. Query (e2e, median): Kalender 13 query 11,1 ms; Konten 12 query 9,7 ms; Siap posting 9 query 5,9 ms; detail item Ready 27 query 18,6 ms (baseline 13/12/9/28 query; selisih waktu dalam rentang derau lokal). Screenshot 360×780, 768×1024, 1280×900 dengan reduced motion, tanpa overflow.

### Fase 2 — Kalender terpadu

- Ubah navigasi menjadi empat menu.
- Gabungkan daftar dan ready queue sebagai filter Kalender.
- Tambahkan redirect route lama.
- Ringkaskan kartu dan komposisi pilar.

**Exit:** seluruh pekerjaan rutin dapat dimulai dari Kalender pada 360, 768, dan 1280 px.

**Status (2026-11-09): selesai** di branch `feat/simplify-content-studio`.

- Navigasi Content Studio: Kalender (dengan jumlah posting yang jatuh tempo), Insight, Brand Knowledge, Pengaturan AI. Halaman Konten dan Siap posting dihapus.
- Kalender punya tampilan **Minggu** dan **Daftar** (`view=list`: pencarian judul, urutan, halaman). Filter utama *Semua / Draft / Siap posting / Sudah diposting*; *Semua* menyembunyikan arsip. **Filter lainnya**: brand, pilar, campaign, arsip. Semua state ada di URL.
- Panel **Perlu diposting** di atas Kalender: posting Ready yang jadwalnya hari ini atau lebih awal (maksimal 10 baris, total tetap dihitung), hanya pengingat. Kartu minggu menulis *Terlambat diposting* / *Jadwal hari ini* dan *Tanpa gambar* dengan kata.
- Komposisi pilar menjadi ringkasan yang dapat dibuka. Tombol **Tambah konten** menggantikan *Buat konten*; form-nya berubah di Fase 3.
- Redirect: `/content/items?…` → `/content/calendar?view=list&…` (hanya kunci yang dikenal; status lama tetap dipetakan), `/content/to-post` → `/content/calendar?view=list&status=READY&sort=planned_at&direction=asc`. Kalender tidak pernah redirect, jadi tidak ada loop. Link detail `/content/items/{id}` tidak berubah.
- *Rencanakan minggu dengan AI* belum ditampilkan karena capability-nya baru ada di Fase 5.
- Hasil: Pest 551, Vitest 98, e2e 69 hijau (termasuk keyboard dan axe pada Minggu, Daftar, dan Arsip). Query (e2e, median): Kalender minggu 14 query 13,6 ms (+1 query untuk pengingat); Daftar 13 query 13,1 ms; daftar Siap posting 13 query 11,9 ms; detail 27 query. Screenshot 360×780, 768×1024, 1280×900 dengan reduced motion, termasuk filter dan komposisi terbuka, tanpa overflow.

### Fase 3 — Form dan editor minimal

- Batasi create form pada ide, jadwal, campaign opsional.
- Jadikan brief satu input utama.
- Pindahkan metadata ke Pengaturan tambahan.
- Pertahankan validasi, revision, dan AI provenance.

**Exit:** pengguna dapat membuat Draft dalam satu dialog dan menyelesaikannya tanpa melihat field teknis.

**Status (2026-11-09): selesai** di branch `feat/simplify-content-studio`.

- **Tambah konten**: *Ide atau topik* (wajib), *Jadwal posting (WIB)* (default hari yang dipilih pukul 10.00), dan *Campaign* hanya bila ada campaign aktif brand itu yang berjalan pada tanggal tersebut. Brand otomatis bila hanya satu. `IdeaPlan` membuat judul kerja secara deterministik dari baris pertama ide (dipotong di batas kata sekitar 80 karakter) dan memilih pilar hanya bila tepat satu pilar aktif disebut dalam ide; selain itu null. Seluruh ide menjadi brief revisi 1. Tanpa AI, jadi create tidak pernah gagal karena provider. `title`, `pillar`, dan `topic` eksplisit tetap diterima.
- **Editor** berurutan: Brief → Buat dengan AI / Buat ulang → Caption dan hashtag (satu area, tetap dua field) → gambar → Aturan brand → Simpan konten → Siap posting. Perubahan yang belum disimpan disimpan dulu sebelum AI menulis, dan menahan *Tandai siap posting*. Field read-only selama AI bekerja.
- **Pengaturan tambahan** (tertutup): alt text, tujuan, audiens, arahan visual, rencana (judul, pilar, topik), riset & sumber, riwayat draft AI, riwayat revisi, aktivitas. Terbuka sendiri dan memindahkan fokus bila field di dalamnya error, serta pada Draft yang hanya kurang alt text. AI menulis alt text, sehingga alur normal tidak perlu membukanya.
- Panel Tinjauan dan copy approval tidak ada lagi di UI. Data tetap di kolom relasional yang ada; tidak ada kolom atau JSON baru. Audit `content.created` mencatat `from_idea`.
- Hasil: Pest 556, Vitest 102, e2e 70 hijau, termasuk alur ide → AI → gambar → Siap posting → diposting tanpa membuka Pengaturan tambahan, error yang membuka disclosure dan memindahkan fokus, serta axe pada dialog create dan editor dengan disclosure terbuka. Query (e2e, median): detail Ready 27 query 17,4 ms; detail Draft 28 query 15,9 ms (sama dengan sebelumnya). Screenshot 360×780, 768×1024, 1280×900 dengan reduced motion, tanpa overflow.

### Fase 4 — Posting dan campaign

- Hapus checklist wajib.
- Sederhanakan dialog publication.
- Sederhanakan campaign dan turunkan active state dari periode.

**Exit:** Ready dapat diposting manual dan dicatat dengan langkah minimum.

**Status (2026-11-09): selesai** di branch `feat/simplify-content-studio`.

- **Paket posting**: pratinjau gambar, Bagikan ke Instagram (bila perangkat mendukung berbagi file), Unduh gambar, Salin caption (termasuk hashtag), Salin hashtag, Salin alt text, dan Tandai sudah diposting. Tidak ada checklist.
- **Dialog posting**: waktu posting aktual wajib (default sekarang, WIB), URL Instagram dan catatan opsional. `checklist` di request menjadi opsional; bila dikirim tetap divalidasi dan disimpan sebagai bukti; publikasi lama dengan checklist tetap ditampilkan. Publikasi tetap immutable dan diaudit; audit hanya memuat `checklist` bila ada.
- **Campaign**: form hanya Nama, Tanggal mulai, Tanggal selesai, Tujuan. `priority` dan `is_active` opsional di request (default `NORMAL` dan aktif untuk campaign baru, tidak berubah bila tidak dikirim). Status *Berjalan / Akan datang / Selesai* diturunkan dari periode WIB; campaign yang dinonaktifkan manual di alur lama tampil *Nonaktif (manual)* dengan opsi *Aktifkan lagi*. Konten baru menolak campaign yang tidak berjalan pada tanggalnya; konten lama tetap hanya mendapat warning. Istilah UI diseragamkan menjadi *campaign*.
- Hasil: Pest 559, Vitest 104, e2e 71 hijau, termasuk posting tanpa checklist, form campaign minimal dengan axe, dan batas hari WIB. Query (e2e, median): Kalender 14 query; Kelola campaign 14 query; detail Ready 28 query 18,6 ms; detail Published 29 query 15,6 ms. Screenshot 360×780, 768×1024, 1280×900 tanpa overflow.

### Fase 5 — Weekly AI planner

- Tambahkan proposal mingguan dan pilihan kandidat.
- Gunakan tool research yang sudah ada melalui Laravel.
- Simpan hanya kandidat yang dipilih sebagai Draft.

**Exit:** AI dapat mengusulkan satu minggu tanpa membuat Ready atau Published.

## Acceptance criteria

- Konten baru dapat dibuat dengan ide dan jadwal; campaign selalu opsional.
- Tidak ada kata atau aksi Ajukan, Setujui, Tolak, atau Minta revisi pada workflow target.
- Pengguna yang sama dapat membuat, mengedit, menandai Ready, dan mencatat Published.
- AI tidak dapat mengubah status keluar dari Draft.
- Edit Ready selalu menghasilkan revision baru dan kembali ke Draft.
- Error brand mencegah Ready; warning tidak.
- Kalender menyediakan seluruh filter dan pengingat yang sebelumnya ada pada Konten dan Siap posting.
- Riwayat approval lama, revision, source, generation, publication, dan audit tetap dapat dibaca.
- Route lama tidak menghasilkan 404 selama masa transisi.
- Semua alur dapat digunakan dengan keyboard, tidak overflow pada 360 px, dan lulus axe WCAG A/AA.

## Pengujian wajib

- Unit: status transition, field inference/default, campaign active period, validator Ready.
- Feature: actions, authorization, AI restrictions, migration mapping, redirects, audit, publication, legacy evidence.
- Frontend: minimal create form, progressive disclosure, filters, labels, disabled reasons, error recovery.
- E2E: create -> AI/manual edit -> Ready -> Published; Ready -> edit -> Draft; archive; legacy redirects; responsive and accessibility.
- Performance: query count Kalender dan detail tidak boleh meningkat tanpa penjelasan dan batas yang disetujui.

## Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Status lama kehilangan makna | Pemetaan eksplisit, fixture produksi anonim, history tidak dihapus |
| AI dianggap menyetujui konten | AI hanya dapat menulis Draft; Ready selalu audit actor manusia |
| Field tersembunyi membuat data tidak dapat diperbaiki | Sediakan Pengaturan tambahan dan tampilkan field ketika ada error |
| Redirect memutus filter/bookmark | Uji query mapping dan pertahankan redirect minimal satu siklus rilis |
| Rollback menghasilkan approval palsu | Jangan membuat approval; gunakan compatibility release sebelum rollback kode |
| Campaign otomatis aktif pada tanggal salah | Semua evaluasi periode memakai business timezone WIB dan boundary test |

## Di luar scope

- Instagram API dan auto-post.
- Multi-level role atau workflow agensi/client approval.
- Penghapusan fisik tabel approval atau revision.
- AI image generation.
- Perubahan otomatis Brand Knowledge berdasarkan performa konten.
