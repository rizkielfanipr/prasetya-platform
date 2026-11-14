# Rencana Penyederhanaan Workspace Detail Konten

| Field | Value |
|---|---|
| Status | Disetujui (ADR-0013, 2026-11-14); Fase 0–5 selesai, menunggu review |
| Owner | Prasetya Group |
| Last updated | 2026-11-14 |
| Decision | [ADR-0013](decisions/0013-status-driven-content-detail-workspace.md) |
| Related | [`content-studio-simplification-plan.md`](content-studio-simplification-plan.md), [`design.md`](design.md), [`data-dictionary.md`](data-dictionary.md), [`testing/strategy.md`](testing/strategy.md) |
| Dampak utama | Detail konten Vue/Inertia, information hierarchy, lazy props, accessibility, E2E Content Studio |

## Ringkasan

Lifecycle Content Studio sudah disederhanakan menjadi **Draft → Siap posting → Sudah diposting**, dengan **Diarsipkan** sebagai akhir rencana yang dibatalkan. Halaman detail masih menampilkan hampir semua kemampuan Content Studio dalam satu workspace panjang. Akibatnya, workflow sederhana terlihat seperti dashboard teknis.

Rencana ini menjadikan detail konten sebagai workspace yang mengikuti status:

```text
DRAFT       -> Buat dan lengkapi konten
READY       -> Posting konten secara manual
PUBLISHED   -> Catat dan lihat performa
ARCHIVED    -> Lihat ringkasan read-only
```

Perubahan berfokus pada information architecture, presentation, dan strategi pemuatan data. State machine, domain action, revision history, AI authority, publication evidence, research provenance, brand validation, dan audit tidak berubah.

## Tujuan

- Membuat langkah berikutnya dapat dipahami tanpa membaca dokumentasi.
- Menampilkan maksimal satu pekerjaan utama dan satu primary action pada satu waktu.
- Mengurangi panjang dan jumlah kontrol pada initial detail screen.
- Menempatkan data wajib dekat dengan field dan action yang membutuhkannya.
- Membuat Ready benar-benar terasa sebagai workspace posting, bukan editor yang kebetulan berstatus Ready.
- Membuat Published berfokus pada bukti posting dan insight.
- Mempertahankan seluruh evidence dan kemampuan lanjutan melalui surface yang bernama jelas.
- Mengurangi initial payload dan query yang tidak relevan dengan status saat ini.

## Non-tujuan

- Mengubah status atau transisi Content Studio.
- Menghapus revision, generation run, research source, publication, insight, approval lama, atau audit.
- Mengizinkan AI menandai Ready, Published, atau Archived.
- Mengubah posting manual menjadi Instagram API atau auto-post.
- Mengubah requirement Ready: caption, alt text, gambar valid, saved revision, dan tidak ada blocking brand error.
- Mengizinkan edit in-place terhadap revisi Ready atau Published.
- Mendesain ulang Kalender, Insight, Brand Knowledge, atau Pengaturan AI di luar integrasi navigasi yang diperlukan.
- Mengganti Vue, Inertia, Tailwind, semantic token, atau komponen UI dasar proyek.

## Kondisi saat ini

Route `/content/items/{id}` merender `ContentDetailPage.vue` dengan beberapa card besar:

1. Jadwal dan status, termasuk dialog Ubah rencana.
2. Insight bila tersedia.
3. Paket posting untuk Ready dan Published.
4. Editor revisi aktif.

Editor masih memuat:

- brief;
- action dan opsi AI;
- caption;
- hashtag;
- gambar;
- temuan aturan brand;
- save action;
- status action;
- disclosure Pengaturan tambahan.

`Pengaturan tambahan` memuat:

- alt text;
- tujuan;
- audiens;
- arahan visual;
- rencana;
- riset dan sumber;
- riwayat draft AI;
- riwayat revisi serta perbandingan;
- audit aktivitas.

### Masalah utama

1. **Status tidak mengubah pekerjaan utama.** Ready masih menampilkan editor penuh walaupun tindakan berikutnya adalah posting.
2. **Data wajib tersembunyi.** Alt text diwajibkan sebelum Ready tetapi diletakkan di Pengaturan tambahan, terpisah dari gambar.
3. **Terlalu banyak container dan heading.** Jadwal/status, paket posting, editor, status panel, dan advanced disclosure saling bersaing.
4. **AI terlihat teknis.** Field selection, instruction, provider/run context, dan editor berada dekat satu sama lain walaupun sebagian jarang dipakai.
5. **Brand check mengambil ruang walau bersih.** Keadaan tanpa masalah tetap mempunyai section lengkap.
6. **Secondary evidence dimuat sebelum diminta.** Research, AI runs, semua revision, comparison, dan audit ikut membentuk detail awal.
7. **Satu disclosure mempunyai terlalu banyak arti.** Pengaturan tambahan adalah editor lanjutan, research workspace, revision browser, dan audit viewer sekaligus.
8. **Action hierarchy tidak tunggal.** Simpan, Ready, archive, posting, AI, dan edit plan dapat terlihat dalam satu perjalanan panjang.

## Prinsip desain

1. **Status menentukan workspace.** UI mengikuti pekerjaan nyata, bukan menampilkan seluruh model data.
2. **Satu primary action.** Action lain harus secondary, tertiary, atau berada dalam overflow sesuai risikonya.
3. **Required stays visible.** Field wajib untuk langkah berikutnya tidak boleh tersembunyi.
4. **Evidence on demand.** Riwayat dan provenance selalu dapat ditemukan, tetapi tidak mendominasi pekerjaan rutin.
5. **Words before colour.** Status, warning, error, dan readiness selalu ditulis dengan kata.
6. **Server remains authoritative.** Checklist UI hanya mempresentasikan availability/reason dari server; tidak membuat state machine kedua di client.
7. **Stable canonical URL.** Link item tidak berubah ketika status berubah.
8. **Progressive disclosure with names.** Gunakan action `Riset & sumber` atau `Riwayat versi`, bukan catch-all `Pengaturan tambahan`.

## Information architecture target

### Header bersama

Semua status menggunakan satu header ringkas:

```text
<- Kalender

Judul konten                                      [Status]
Brand · Rabu, 18 November 2026, 10.00 WIB         [...]
```

Header memuat:

- kembali ke minggu Kalender yang memuat item;
- judul kerja;
- status dalam kata;
- brand dan jadwal;
- warning terlambat/hari ini bila relevan;
- overflow untuk action jarang atau destruktif.

Header tidak memuat revision number sebagai informasi utama. Revision number tetap tersedia di Riwayat versi dan evidence posting.

Menu overflow sesuai status:

| Action | Draft | Ready | Published | Archived |
|---|---:|---:|---:|---:|
| Ubah rencana | Ya | Ya, bila domain mengizinkan | Tidak | Tidak |
| Riset & sumber | Ya | Read-only | Read-only | Read-only |
| Riwayat versi | Ya | Ya | Ya | Ya |
| Aktivitas | Ya | Ya | Ya | Ya |
| Arsipkan | Ya | Ya | Tidak | Tidak |

## Workspace Draft

### Target layout desktop

```text
+--------------------------------------+-----------------------+
| Buat konten                          | Kesiapan posting      |
|                                      |                       |
| Brief                                | [x] Caption           |
| [textarea]                           | [x] Gambar            |
| [Buat dengan AI] [Opsi]              | [ ] Alt text          |
|                                      | [x] Aturan brand      |
| Caption Instagram                    | [ ] Semua tersimpan   |
| [caption]                            |                       |
| [hashtags]                           | 3 dari 5 selesai      |
|                                      |                       |
| Media                Alt text        | [Simpan Draft]        |
| [preview/upload]      [textarea]      | [Tandai Siap Posting] |
|                                      |                       |
| Pemeriksaan brand ringkas            |                       |
+--------------------------------------+-----------------------+

[Riset & sumber] [Riwayat versi] [Aktivitas]
```

Pada mobile, readiness menjadi action bar/card setelah editor. Action bar tidak boleh menutupi field, error, keyboard virtual, atau toast.

### Brief

- Brief tetap field pertama dan konteks utama AI.
- Helper copy singkat menjelaskan bahwa brief adalah instruksi kerja.
- Counter tetap tersedia tetapi visually secondary.
- Brief tidak ditampilkan sebagai field teknis atau metadata.

### AI assistant

Default surface:

- satu tombol **Buat dengan AI** atau **Buat ulang dengan AI**;
- satu kalimat yang menjelaskan output;
- loading/progress dan hasil terakhir dalam bahasa manusia.

Action **Opsi AI** membuka popover/dialog berisi:

- bagian yang ditulis AI: caption, hashtag, alt text, arahan visual;
- instruksi tambahan opsional;
- penjelasan bahwa AI hanya membuat Draft.

Provider, model, prompt version, cost, dan seluruh run history tidak muncul pada primary editor. Data tersebut tetap tersedia di Riwayat versi/AI.

### Instagram composition

Caption dan hashtag tetap field terpisah pada request dan database, tetapi tampil sebagai satu section **Caption Instagram**:

- caption textarea utama;
- hashtag textarea/input visual di bawah caption;
- combined preview/counter yang mudah dipahami;
- validasi dan focus target tetap terpisah;
- copy tidak menyiratkan bahwa hashtag menjadi bagian kolom caption di server.

### Media dan alt text

Media dan alt text menjadi satu section:

- preview 4:5;
- upload/ganti gambar;
- requirement format, dimensi, dan warning;
- alt text wajib di sisi preview pada desktop dan tepat di bawah preview pada mobile;
- AI-generated alt text dapat diedit;
- error image dan alt text terlihat tanpa membuka disclosure.

Objective, audience, dan visual direction tidak diwajibkan untuk Ready. Ketiganya dapat dibuka lewat action **Detail kreatif** di dalam Draft atau sheet Ubah rencana, tetapi tidak berada pada initial flow.

### Brand check summary

State presentasi:

| Kondisi | Default |
|---|---|
| Tidak ada finding | Satu baris success: `Konten sesuai aturan brand` |
| Warning saja | Summary jumlah warning dan action `Lihat saran` |
| Blocking error | Error expanded, jumlah dan pesan, serta link Brand Knowledge bila relevan |
| Aturan belum ada | Neutral message, bukan success |
| Versi berubah setelah Ready lama | Evidence dijelaskan di history, bukan memenuhi editor Draft |

### Readiness panel

Readiness adalah presenter dari server `actions` dan data revisi tersimpan. Minimum row:

- Caption sudah diisi.
- Gambar valid tersedia.
- Alt text sudah diisi.
- Tidak ada blocking brand error.
- Perubahan saat ini sudah disimpan.

Setiap row menggunakan icon, kata, dan penjelasan singkat bila belum terpenuhi. Primary action berubah secara deterministik:

| Kondisi | Primary action |
|---|---|
| Form dirty | Simpan perubahan |
| Saved tetapi belum lengkap | Tombol Ready disabled; row yang kurang terlihat |
| Saved dan lengkap | Tandai Siap Posting |
| Saving/AI active | Loading state; mutation lain disabled |

Archive tidak menjadi primary/secondary action di panel; letakkan di overflow header.

## Workspace Ready

Ready membuka posting package sebagai permukaan utama:

```text
Judul konten                               [Siap posting]
Jadwal hari ini · 10.00 WIB

+------------------+--------------------------------------+
| Preview gambar   | Caption dan hashtag                  |
|                  |                                      |
|                  | [Bagikan ke Instagram]               |
|                  | [Unduh gambar] [Salin caption] [...]  |
+------------------+--------------------------------------+

[Tandai Sudah Diposting]
Edit konten · Kembali ke Draft
```

Aturan:

- action utama adalah **Tandai Sudah Diposting** setelah operator melakukan posting manual;
- share tersedia hanya bila browser/device mendukung;
- download dan copy tetap mempunyai fallback;
- `Salin caption` sudah termasuk hashtag;
- `Salin hashtag` dan `Salin alt text` masuk menu **Salin lainnya** agar action row tidak ramai;
- due/overdue message terlihat dekat jadwal;
- publication dialog tetap meminta waktu aktual, URL opsional, dan catatan opsional;
- editor tidak dirender secara default;
- **Edit konten** meminta konfirmasi bahwa save berikutnya membuat revision baru dan mengembalikan item ke Draft, lalu membuka Draft workspace secara eksplisit.

## Workspace Published

Published berfokus pada evidence dan performa:

1. publication summary: waktu aktual, actor, waktu pencatatan, URL Instagram, dan catatan;
2. read-only snapshot gambar, caption, hashtag, dan alt text dari revision yang diposting;
3. action **Catat insight**;
4. daftar insight yang sudah dicatat;
5. secondary actions Riwayat versi dan Aktivitas.

Editor, AI action, readiness checklist, dan Mark as posted tidak dirender.

Insight tetap manual. Tidak ada asumsi bahwa URL Instagram selalu tersedia.

## Workspace Archived

Archived bersifat read-only:

- status dan jadwal terakhir;
- alasan archive bila evidence audit tersedia secara aman;
- snapshot revision terakhir;
- link Riwayat versi;
- link Aktivitas.

AI, editor, readiness, research mutation, posting action, dan insight mutation tidak dirender.

## Secondary surfaces

### Ubah rencana

Dialog/sheet memuat:

- judul kerja;
- jadwal posting;
- pilar;
- campaign;
- topik.

Gunakan action dan validation yang ada. Copy lama tentang `persetujuan` diganti agar sesuai lifecycle ADR-0012.

### Detail kreatif

Surface Draft-only untuk field non-wajib:

- tujuan;
- audiens;
- arahan visual.

Surface ini tidak memuat plan, research, revision, atau audit.

### Riset & sumber

Sheet/focused view memuat:

- internal context;
- sumber yang dipakai;
- tambah sumber manual;
- web research;
- search runs dan hasil;
- konten serupa.

Mutation hanya tersedia ketika domain mengizinkan. Ready, Published, dan Archived memperoleh read-only state yang jelas.

### Riwayat versi

Sheet/focused view memuat:

- revision timeline;
- Ready revision marker;
- legacy approval evidence;
- AI provenance;
- perbandingan dua revision;
- generation run history yang relevan.

### Aktivitas

Sheet/focused view hanya memuat audit item, terbaru di atas, dengan existing redaction dan limit. Jangan gabungkan audit ke revision comparison.

## Loading dan data contract

### Initial props per status

| Prop/capability | Draft | Ready | Published | Archived |
|---|---:|---:|---:|---:|
| Item + active revision | Ya | Ya | Ya | Ya |
| Availability/actions | Ya | Ya | Minimal | Minimal |
| Current brand findings | Ya | Evidence ringkas | Tidak | Tidak |
| AI availability/current active run | Ya | Tidak | Tidak | Tidak |
| Posting package | Tidak | Ya | Evidence | Tidak |
| Insights | Tidak | Tidak | Ya | Tidak |
| Full revisions | Lazy | Lazy | Lazy | Lazy |
| Research | Lazy | Lazy read-only | Lazy read-only | Lazy read-only |
| AI run history | Lazy | Lazy | Lazy | Lazy |
| Activity | Lazy | Lazy | Lazy | Lazy |

### Strategi Inertia

- Pertahankan `/content/items/{item}` sebagai canonical page.
- Gunakan deferred/lazy props atau focused authorized route untuk secondary surfaces.
- Partial reload harus menyebut prop yang diperlukan dan tidak menimpa form dirty state.
- Setiap surface mempunyai loading, empty, failed, dan retry state.
- Authorization dijalankan server-side pada setiap request; client visibility bukan security control.
- Polling AI/research hanya hidup ketika capability terkait aktif dan surface yang relevan terbuka.

## Struktur komponen target

```text
resources/js/pages/content/ContentDetailPage.vue
resources/js/features/content/detail/
  ContentDetailHeader.vue
  DraftContentWorkspace.vue
  ContentBriefEditor.vue
  ContentAiAssistant.vue
  InstagramCompositionEditor.vue
  ContentMediaEditor.vue
  BrandCheckSummary.vue
  ContentReadinessPanel.vue
  ReadyPostingWorkspace.vue
  PublishedContentWorkspace.vue
  ArchivedContentWorkspace.vue
  ContentPlanDialog.vue
  CreativeDetailsSheet.vue
  ContentResearchSheet.vue
  ContentHistorySheet.vue
  ContentActivitySheet.vue
```

Nama final boleh mengikuti convention repository, tetapi boundary harus mengikuti user job. Komponen existing digunakan atau dipecah; jangan menduplikasi mutation logic, status label, route, formatter, atau semantic token.

### Komponen existing yang dipertahankan/refactor

| Existing | Target |
|---|---|
| `ContentEditor.vue` | Orchestrator Draft yang lebih kecil atau diganti `DraftContentWorkspace` |
| `ContentAiAction.vue` | `ContentAiAssistant` dengan opsi on demand |
| `ContentImageField.vue` | Menjadi bagian Media + Alt text |
| `ContentRuleFindings.vue` | `BrandCheckSummary` progressive disclosure |
| `ContentStatusPanel.vue` | `ContentReadinessPanel` berbasis requirement |
| `ContentPostingPackage.vue` | Primary `ReadyPostingWorkspace` dan read-only Published snapshot |
| `ContentResearchPanel.vue` | Isi `ContentResearchSheet` |
| `ContentRevisionHistory.vue` | Isi `ContentHistorySheet` |
| `AuditTimeline.vue` | Isi `ContentActivitySheet` |
| `ContentDetailsDialog.vue` | `ContentPlanDialog` dengan copy lifecycle baru |

## Invariant yang tidak boleh rusak

1. AI dan queue hanya menulis Draft.
2. Ready, Published, dan Archived selalu memerlukan action manusia terautentikasi.
3. Ready menunjuk revision tersimpan dan beku.
4. Edit Ready menghasilkan revision baru dan kembali ke Draft.
5. Published hanya berasal dari current Ready revision yang valid.
6. Waktu publication tidak boleh mendahului Ready evidence sesuai domain rule existing.
7. Blocking brand error mencegah Ready; warning tidak.
8. Asset validation membaca bytes server-side; client preview bukan validator final.
9. Caption, hashtag, alt text, visual direction, dan image tetap mempunyai revision history.
10. Research source dan AI generation provenance tetap queryable.
11. Legacy approval evidence tetap read-only dan tidak ditulis ulang.
12. Audit tidak memuat secret, raw provider payload, atau personal data yang tidak perlu.

## Accessibility dan responsive

### Accessibility

- Visible label untuk seluruh input.
- Readiness tidak bergantung pada warna atau icon saja.
- Error summary dan inline error mengarah ke field yang sama.
- Sheet/dialog mengunci fokus, mempunyai accessible name, Escape behaviour, dan mengembalikan fokus ke trigger.
- Perubahan AI, save, dan readiness diumumkan lewat `aria-live` yang tidak berisik.
- Primary action mempunyai loading dan `aria-busy`.
- Keyboard dapat membuka semua secondary surfaces dan overflow action.
- Zoom 200% tidak menyembunyikan save/Ready action.
- Reduced motion menonaktifkan animasi non-esensial.

### Responsive

| Width | Layout |
|---|---|
| 320–639 | Satu kolom; media lalu alt text; readiness setelah editor; action tidak menutupi keyboard |
| 640–1023 | Satu/dua kolom adaptif; secondary surfaces full-height sheet |
| ≥1024 | Editor + sticky readiness rail; media/alt text berdampingan bila ruang cukup |
| ≥1280 | Lebar baca caption dibatasi; tidak membuat textarea terlalu lebar |

Target minimum: 320×568, 360×780, 768×1024, 1280×900, dan 1440×900.

## Tahapan implementasi

### Fase 0 — Kontrak, baseline, dan ADR

- Review dan terima ADR-0013.
- Bekukan screenshot Draft, Ready, Published, dan Archived pada target viewport.
- Catat initial response size, query count, response time lokal, jumlah heading, jumlah visible controls, dan page height.
- Catat interaction count untuk Draft → Ready dan Ready → Published.
- Petakan test selector dan deep link existing.

**Exit:** status-workspace matrix, evidence inventory, dan rollback UI disetujui.

**Status (2026-11-14): selesai** di branch `feat/simplify-content-studio`.

- ADR-0013 diterima.
- Baseline pada database e2e (`ContentDemoSeeder`), satu item per status, median 6 request lokal setelah warm-up:

  | Status | Query | Props awal (byte JSON) | Waktu lokal | Kontrol terlihat | Heading | Tinggi halaman 1440×900 |
  |---|---:|---:|---:|---:|---:|---:|
  | Draft | 28 | 5.021 | 18,7 ms | 13 | 4 | 1.504 px |
  | Ready | 27 | 5.464 | 17,2 ms | 17 | 5 | 2.010 px |
  | Published | 29 | 6.389 | 16,4 ms | 13 | 6 | 2.067 px |
  | Archived | 23 | 4.850 | 12,9 ms | 8 | 4 | 1.427 px |

  Semua status memuat `revisions`, `activity`, `research`, dan `ai.runs` penuh pada request awal. Tidak ada horizontal overflow pada 320, 360, 768, 1280, dan 1440 px.
- Interaksi: Draft lengkap → Ready = 2 (tombol, konfirmasi). Ready → dialog posting = 1 klik, tetapi tombolnya berada di bawah paket posting dan di atas editor penuh.
- Selector yang dipakai test dan berubah: `more-settings`, `content-more`, `status-summary`, `edit-blocked`, `caption-area`, `#reason-markReady`, heading `Paket posting`, `Jadwal dan status`. Dipertahankan: `ai-panel`, `ai-draft-note`, `rule-findings`, `posting-image`, `due-banner`, `publication`, `insight-panel`, `research-panel`, `cited-sources`, `research-run-*`, `ai-run-*`, `revision-N`, `revision-diff`, id field `content-*`. Deep link `#paket-posting` dari pengingat Kalender tetap mendarat di workspace Ready.
- Rollback presentasi: kembalikan `ContentDetailPage.vue`, komponen editor lama, dan props `show()` dari commit sebelumnya, lalu build ulang asset. Tidak ada migration.

### Fase 1 — Shell dan status-driven routing

- Buat `ContentDetailHeader`.
- Hapus card Jadwal dan status dari initial composition; pindahkan informasi ringkas ke header.
- Pilih workspace berdasarkan status server.
- Pertahankan canonical route dan calendar back link.
- Tambahkan overflow action dengan authorization/availability existing.
- Published/Archived tidak merender editor mutation.

**Exit:** keempat status membuka workspace yang berbeda tanpa mengubah domain action.

**Status (2026-11-14): selesai.** `ContentDetailHeader` (Kembali ke Kalender, status dan keterlambatan dengan kata, menu **Lainnya** yang hanya berisi action yang diizinkan server). `ContentDetailPage` memilih `DraftContentWorkspace`, `ReadyPostingWorkspace`, `PublishedContentWorkspace`, atau `ArchivedContentWorkspace` dari status server pada URL yang sama. `ContentItemController::show` mengirim props per status; yang tidak relevan bernilai `null`. Domain action tidak berubah.

### Fase 2 — Draft workspace

- Refactor brief dan AI assistant.
- Satukan caption + hashtag secara visual tanpa menggabungkan data contract.
- Satukan media + alt text.
- Tambahkan brand summary progressive disclosure.
- Ganti status panel dengan readiness presenter.
- Satukan save/Ready action hierarchy.
- Pindahkan archive ke overflow.

**Exit:** alur normal Draft → AI/manual → media → save → Ready selesai tanpa membuka secondary surface.

**Status (2026-11-14): selesai.** Brief, `ContentAiAssistant` (hasil terakhir dengan kata, Opsi AI on demand), `InstagramCompositionEditor`, `ContentMediaEditor` (alt text di samping/bawah pratinjau, input file native di balik label 44 px), `BrandCheckSummary`, dan `ContentReadinessPanel` (lima syarat dari revisi tersimpan dan temuan server, satu primary action, ringkasan error yang memindahkan fokus). Archive pindah ke Lainnya. Tujuan, audiens, dan arahan visual pindah ke sheet **Detail kreatif** (Lainnya); arahan visual tersimpan tetap terlihat sebagai rujukan di bagian media.

### Fase 3 — Ready, Published, dan Archived

- Jadikan posting package sebagai Ready workspace.
- Ringkaskan copy/download/share actions dan tambahkan overflow `Salin lainnya`.
- Tambahkan explicit Edit content confirmation untuk Ready.
- Jadikan publication evidence + insight sebagai Published workspace.
- Buat Archived read-only summary.

**Exit:** Ready → Published selesai dari satu primary workspace dan terminal status tidak menunjukkan action yang mustahil.

**Status (2026-11-14): selesai.** Ready membuka paket posting; **Tandai sudah diposting** satu klik dari dialog. **Bagikan ke Instagram** menjadi secondary agar primary tetap satu. **Salin lainnya** memuat hashtag dan alt text. **Edit konten** meminta konfirmasi lalu menampilkan editor Draft dengan peringatan; simpan memakai `SaveContentRevision` yang sudah ada (revisi baru, kembali ke Draft). **Kembali ke Draft** tanpa edit tetap ada di Lainnya agar capability lama tidak hilang. Published membaca `postedRevision` dari `publication.revision`; sebelumnya paket posting Published menampilkan revisi aktif. Archived menampilkan alasan dari audit `content.archived` terakhir.

### Fase 4 — Secondary surfaces dan lazy loading

- Pisahkan Ubah rencana, Detail kreatif, Riset, Riwayat, dan Aktivitas.
- Implementasikan deferred/lazy props atau focused endpoint dengan policy yang sama.
- Pastikan dirty Draft tidak hilang ketika secondary surface dibuka/ditutup.
- Poll AI/research hanya saat diperlukan.
- Tambahkan loading, retry, empty, dan unavailable states.

**Exit:** initial detail tidak membutuhkan full research, revision history, AI history, atau audit payload.

**Status (2026-11-14): selesai.** Tiga endpoint GET read-only di `ContentItemEvidenceController`: `/content/items/{id}/research`, `/history`, `/activity`, di balik `auth` dan `EnsureUserIsActive` yang sama, item di-resolve dengan public id. Dipilih endpoint JSON, bukan Inertia deferred/partial reload, karena respons 500 di aplikasi ini dirender sebagai `ErrorPage`: partial reload yang gagal akan mengganti halaman dan menghapus edit Draft yang belum disimpan. `useItemEvidence` memuat saat sheet dibuka, membatalkan saat ditutup, dan me-refresh diam-diam setelah visit Inertia yang berhasil selama sheet terbuka. Polling riset hanya hidup saat sheet terbuka dan pencarian berjalan; polling AI hanya pada Draft dengan run aktif dan hanya me-reload `ai`, `item`, `revision`, `findings`, `actions`. Riset hanya dapat diubah di UI selama Draft; server tetap menerima perubahan riset untuk Ready seperti sebelumnya (tidak ada perubahan domain).

### Fase 5 — Quality, rollout, dan observability

- Update tests, docs, data contract notes, dan runbook bila operasional berubah.
- Jalankan full quality gate.
- Rehearse rollback presentation.
- Uji production-like data dengan revision dan legacy approval yang panjang.
- Catat regression query/payload dan interaction count.

**Exit:** seluruh acceptance criteria, test matrix, accessibility, responsive, dan rollback lulus.

**Status (2026-11-14): selesai, menunggu review.**

| Status | Query | Props awal (byte) | Waktu lokal | Kontrol terlihat 1440 | Tinggi 1440×900 |
|---|---:|---:|---:|---:|---:|
| Draft | 28 → 23 | 5.021 → 2.807 | 18,7 → 14,7 ms | 13 → 10 | 1.504 → 1.131 px |
| Ready | 27 → 21 | 5.464 → 2.770 | 17,2 → 9,6 ms | 17 → 7 | 2.010 → 900 px |
| Published | 29 → 24 | 6.389 → 4.118 | 16,4 → 8,9 ms | 13 → 3 | 2.067 → 931 px |
| Archived | 23 → 14 | 4.850 → 2.216 | 12,9 → 5,2 ms | 8 → 4 | 1.427 → 900 px |

- Target yang belum tercapai penuh: kontrol Draft turun 23% (target 30%) dan tinggi Draft desktop turun 24,8% (target 25%; 18,7% pada 1280×900). Alt text wajib kini terlihat (sebelumnya tersembunyi); tanpa menghitungnya kontrol Draft turun 31%. Tidak ada informasi wajib yang disembunyikan untuk mengejar angka.
- Interaksi: Draft lengkap → Ready tetap 2 (tombol, konfirmasi). Ready → dialog posting 1 klik.
- Tidak ada horizontal overflow pada sweep 320–1440 px tiap 8 px untuk keempat status; zoom 200% (640×450) menjaga primary action terjangkau.
- Perbaikan bersama yang ikut: `vue-sonner/style.css` dimuat (toast sebelumnya tampil tanpa gaya di bawah halaman sehingga konfirmasi simpan dan salin tidak terlihat) dan warnanya memakai token status (warna bawaan sonner 4,26:1, token 4,51–5,49:1); judul halaman membungkus dua baris; tombol tutup Sheet berlabel *Tutup* dengan target 44 px; `expectNoAxeViolations` menunggu animasi selesai (sebelumnya axe kadang menilai dialog atau toast yang masih fade-in).
- Hasil test: Pest 571, Vitest 127, e2e 80 hijau; PHPStan, ESLint, vue-tsc, Pint, Prettier bersih.
- Rollback presentasi diverifikasi secara struktur: perubahan hanya di Vue, `ContentItemController::show`, presenter, route GET baru, dan test; tidak ada migration atau perubahan status.

## Test strategy

### Feature/Pest

- Props awal berbeda sesuai status.
- Secondary props/endpoint memerlukan authenticated active user dan content permission.
- Cross-item/public-id guessing ditolak.
- Draft availability dan readiness reason berasal dari server.
- Ready edit tetap membuat revision baru dan kembali ke Draft.
- Published/Archived mutation tetap ditolak server walaupun request dibuat manual.
- Lazy research/history/activity tidak mengubah record.
- Legacy approval evidence tetap dapat dibaca.

### Vitest

- Workspace selector untuk empat status.
- Readiness rows untuk complete, incomplete, dirty, finding error, saving, dan AI active.
- AI options disclosure dan accessible announcement.
- Brand summary clean/warning/error.
- Media + alt text error focus.
- Ready action hierarchy dan copy overflow.
- Sheet loading/error/empty/focus return.

### Playwright

1. Idea → Draft → AI → image → alt text → save → Ready tanpa secondary surface.
2. Manual caption tanpa AI → Ready.
3. Blocking brand error fokus ke masalah dan mencegah Ready.
4. Ready default membuka posting workspace.
5. Edit Ready memberi warning, membuat revision baru, kembali ke Draft.
6. Share-capable dan fallback download/copy path.
7. Ready → Mark as posted → Published workspace.
8. Published → record insight.
9. Archive Draft/Ready → Archived read-only.
10. Open/close Riset, Riwayat, dan Aktivitas dengan keyboard.
11. Dirty form bertahan ketika secondary surface digunakan.
12. Axe pada semua status dan semua sheet/dialog.

### Quality gate repository

```sh
vendor/bin/pint && npm run format
vendor/bin/phpstan analyse --memory-limit=1G
npm run lint && npm run typecheck && npm test
vendor/bin/pest
npm run build && npm run test:e2e
```

## Acceptance criteria

1. Draft initial screen hanya menunjukkan flow membuat konten dan readiness.
2. Alt text terlihat bersama media dan tidak bergantung pada Pengaturan tambahan.
3. Ready initial screen adalah posting package, bukan editor.
4. Published initial screen adalah publication evidence + insight.
5. Archived tidak merender mutation control.
6. Tidak ada catch-all `Pengaturan tambahan` yang memuat editor, plan, research, history, dan audit sekaligus.
7. Riset, riwayat, AI provenance, dan audit tetap dapat ditemukan dari action bernama jelas.
8. Initial detail tidak memuat full secondary evidence bila surface belum dibuka.
9. Maksimal satu primary action ditonjolkan pada satu waktu.
10. Server availability tetap menjadi sumber kebenaran readiness dan mutation.
11. Semua domain invariant ADR-0011/0012 tetap lulus.
12. Tidak ada accessibility violation pada target E2E.
13. Tidak ada horizontal overflow pada target viewport.
14. URL `/content/items/{id}` dan calendar back link tetap stabil.
15. Presentation rollback tidak memerlukan database rollback.

## Success metrics

Bandingkan baseline dan hasil pada fixture yang sama:

| Metric | Target |
|---|---|
| Visible controls pada Draft initial screen | Turun sekurangnya 30% |
| Initial page height Draft desktop | Turun sekurangnya 25% pada fixture normal |
| Interaction Draft lengkap → Ready | Tidak bertambah; secondary surface tidak wajib |
| Interaction Ready → Mark as posted | Maksimal dua sebelum dialog konfirmasi |
| Initial query/payload secondary evidence | Tidak dimuat sebelum diminta |
| User-facing primary action per status | Tepat satu yang dominan |
| Axe serious/critical | 0 |
| Domain regression | 0 |

Metric ini mengevaluasi arah, bukan alasan untuk menghapus evidence atau menyembunyikan error penting.

## Rollout

1. Implementasikan per fase pada satu feature branch setelah ADR diterima.
2. Gunakan fixture Draft, Ready, Published, Archived, legacy approval, multi-revision, AI failure, research failure, dan missing asset.
3. Review screenshot mobile/tablet/desktop sebelum merge.
4. Jalankan production-like query/payload comparison.
5. Deploy bersama frontend asset build; tidak ada migration untuk refactor presentation ini kecuali kebutuhan baru terbukti dan didokumentasikan lebih dahulu.
6. Setelah rilis, pantau frontend error, failed lazy requests, AI polling, save failure, Ready failure, dan publication failure.

## Rollback

- Kembalikan `ContentDetailPage.vue` ke composition sebelumnya dan build ulang asset.
- Pertahankan route, action, presenter, model, dan seluruh evidence.
- Jika focused lazy routes sudah ditambahkan, route tersebut boleh tetap tersedia tetapi tidak dipanggil UI lama; hapus hanya pada cleanup terpisah setelah stabil.
- Tidak ada status reverse mapping atau database rollback karena refactor ini tidak mengubah data lifecycle.

## Risiko dan mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Secondary capability sulit ditemukan | Research/history terasa hilang | Action bernama jelas di header/footer, bukan icon-only |
| Lazy request gagal | Evidence tidak terlihat sementara | Error + retry per surface; primary editor tetap bekerja |
| Dirty state hilang saat sheet dibuka | Edit user hilang | Secondary load tidak melakukan full navigation; E2E dirty-state |
| Client checklist berbeda dari server | Ready salah aktif | Render server reason; mutation tetap divalidasi server |
| Ready editor terlalu tersembunyi | User tidak tahu cara revisi | Secondary `Edit konten` dengan konsekuensi eksplisit |
| Published snapshot memakai revision salah | Bukti posting salah | Selalu gunakan `publication.revision`, bukan asumsi active revision |
| Mobile sticky action menutupi field | Form sulit digunakan | Safe-area, non-overlay fallback, keyboard viewport test |
| Component split menduplikasi logic | Status drift | Central label, route, formatter, action availability, dan form schema |

## Keputusan review yang diminta

Sebelum implementasi, product perlu menyetujui:

1. Workspace status-driven menggantikan satu halaman panjang.
2. Alt text dipindahkan ke primary Media section.
3. Ready membuka posting package secara default.
4. Published membuka publication evidence dan Insight secara default.
5. Research, Riwayat, dan Aktivitas menjadi secondary named surfaces dan lazy-loaded.
6. Edit Ready tetap eksplisit kembali ke Draft sesuai domain existing.

Tidak ada keputusan baru tentang state machine, AI authority, posting automation, atau deletion evidence dalam review ini.
