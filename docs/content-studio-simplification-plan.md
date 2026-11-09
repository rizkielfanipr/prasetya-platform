# Rencana Penyederhanaan Content Studio

| Field | Value |
|---|---|
| Status | Draft untuk persetujuan |
| Owner | Prasetya Group |
| Last updated | 2026-11-08 |
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

### Fase 1 — Domain compatibility

- Tambahkan status/action target.
- Ubah availability dan brand gate.
- Pertahankan history revision/approval/publication.
- Larang AI dan Hermes melakukan transisi manusia.

**Exit:** feature test state machine dan migration test lulus.

### Fase 2 — Kalender terpadu

- Ubah navigasi menjadi empat menu.
- Gabungkan daftar dan ready queue sebagai filter Kalender.
- Tambahkan redirect route lama.
- Ringkaskan kartu dan komposisi pilar.

**Exit:** seluruh pekerjaan rutin dapat dimulai dari Kalender pada 360, 768, dan 1280 px.

### Fase 3 — Form dan editor minimal

- Batasi create form pada ide, jadwal, campaign opsional.
- Jadikan brief satu input utama.
- Pindahkan metadata ke Pengaturan tambahan.
- Pertahankan validasi, revision, dan AI provenance.

**Exit:** pengguna dapat membuat Draft dalam satu dialog dan menyelesaikannya tanpa melihat field teknis.

### Fase 4 — Posting dan campaign

- Hapus checklist wajib.
- Sederhanakan dialog publication.
- Sederhanakan campaign dan turunkan active state dari periode.

**Exit:** Ready dapat diposting manual dan dicatat dengan langkah minimum.

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
