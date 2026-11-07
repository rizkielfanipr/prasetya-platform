# Rencana Content Studio dan AI Content Strategist

| Field | Value |
|---|---|
| Status | Draft untuk persetujuan |
| Owner | Prasetya Group |
| Last updated | 2026-11-07 |
| Decision | ADR penggunaan Hermes dan batas kewenangan agent belum dibuat |
| Related | [`architecture.md`](architecture.md), [`design.md`](design.md), [`data-dictionary.md`](data-dictionary.md), [`security/threat-model.md`](security/threat-model.md), [`testing/strategy.md`](testing/strategy.md) |

## Tujuan

Membangun modul **Content Studio** untuk merencanakan konten Instagram mingguan, melakukan riset tren, menyusun strategi dan brief, menghasilkan caption serta gambar, menjalankan approval manusia, dan menyiapkan paket posting manual.

Sistem harus mengurangi pekerjaan operasional tanpa menyerahkan keputusan publikasi kepada AI. Laravel tetap menjadi sumber data, pemilik workflow, dan audit trail. Hermes digunakan sebagai **AI Content Strategist** untuk riset dan perencanaan setelah fondasi manual serta generator OpenAI stabil.

## Keputusan produk saat ini

| Area | Keputusan |
|---|---|
| Kanal awal | Instagram feed, gambar tunggal |
| Perencanaan | Mingguan, dengan item konten per hari |
| Strategi dan riset | Hermes Agent setelah research tools tersedia |
| Caption dan structured output | OpenAI API |
| Gambar | OpenAI Image API |
| Approval | Wajib dilakukan pengguna Content Studio |
| Publikasi | Manual: download gambar, salin caption, lalu tandai sudah diposting |
| Instagram API | Tidak digunakan pada scope awal |
| n8n | Tidak digunakan pada scope awal |
| Sumber data utama | Prasetya Platform / MySQL |
| Scheduler dan pekerjaan latar | Laravel Scheduler dan Queue |
| Agent runtime | Hermes self-hosted pada service terpisah |
| Runtime lokal Hermes | Docker Desktop sebagai engine; dijalankan melalui Docker CLI dan Compose |
| Runtime VPS Hermes | Docker Engine, Docker CLI, dan Compose plugin; tanpa Docker Desktop |

## Prinsip utama

1. **Human approval wajib.** Output AI selalu menjadi draft dan tidak dapat memublikasikan konten.
2. **Laravel authoritative.** Status, brand guideline, campaign, approval, versi, dan audit hanya sah jika tersimpan melalui domain action Laravel.
3. **AI tidak menjadi sumber fakta produk.** Harga, nama produk, promo, periode, dan klaim bisnis selalu berasal dari data yang disetujui.
4. **Research dapat ditelusuri.** Setiap tren menyimpan sumber, URL, waktu akses, ringkasan, relevansi, dan content item yang menggunakannya.
5. **Agent memiliki least privilege.** Hermes hanya mendapat tool baca dan pengiriman draft; tidak mendapat tool approval, publish, delete, atau credential management.
6. **Setiap generation dapat diulang dengan aman.** Queue retry dan idempotency mencegah duplikasi draft dan asset.
7. **Biaya dibatasi.** Setiap run mempunyai batas token, tool call, pencarian, gambar, durasi, dan estimasi biaya.
8. **Hasil lama tidak ditimpa.** Caption, gambar, research snapshot, dan brief yang direvisi disimpan sebagai versi.

## Scope

### Content Studio manual

- Kalender mingguan dan daftar konten.
- Content item dengan brand, tanggal rencana, content pillar, objective, target audience, brief, caption, hashtag, visual direction, alt text, dan catatan.
- Upload gambar manual.
- Approval, rejection, dan revision request.
- Riwayat versi dan audit.
- Download gambar dan salin caption, hashtag, serta alt text.
- Tandai konten sebagai sudah diposting.
- Simpan waktu publikasi aktual dan URL Instagram secara opsional.

### Brand knowledge

- Profil brand dan target audience.
- Tone of voice.
- Kata yang dianjurkan dan dilarang.
- CTA yang diizinkan.
- Aturan caption dan hashtag.
- Identitas serta arahan visual.
- Contoh konten yang disetujui dan ditolak.
- Klaim produk yang diperbolehkan.
- Content pillar dan komposisi mingguan.
- Jarak minimum pengulangan topik.

### AI production

- Generate caption, hashtag, CTA, alt text, dan visual direction dari brief.
- Generate gambar dari visual prompt.
- Regenerate caption atau gambar secara terpisah.
- Revisi berdasarkan feedback pengguna.
- Structured output tervalidasi sebelum disimpan.
- Catat provider, model, prompt version, token, biaya, durasi, dan error.

### Trend research

- Data internal: campaign, produk, histori konten, topik terdahulu, dan performa manual.
- Data eksternal: web search dan extraction melalui provider yang disetujui.
- Domain allowlist/blocklist.
- Snapshot sumber agar hasil perencanaan dapat direproduksi dan diaudit.
- Deduplication dan pemeriksaan kemiripan topik.

### Hermes strategist

- Menentukan query riset dalam batas yang diberikan.
- Memilih research tool yang diizinkan.
- Menilai kecukupan sumber.
- Membandingkan tren, campaign, histori, dan performa.
- Menyusun weekly objective dan komposisi content pillar.
- Membuat ide harian dan brief terstruktur.
- Meminta generator caption dan gambar.
- Mengirim weekly plan dan draft ke Content Studio.
- Merevisi draft berdasarkan feedback terstruktur.

### Manual publishing

- Checklist sebelum posting.
- Preview gambar final.
- Download asset.
- Salin caption, hashtag, dan alt text.
- Aksi eksplisit **Tandai sudah diposting**.
- Tanggal/jam aktual dan URL posting opsional.
- Sistem tidak mengubah status menjadi published hanya karena tanggal rencana telah lewat.

## Di luar scope awal

- Publikasi otomatis dengan Instagram API.
- Instagram direct message, comment moderation, atau social listening.
- Carousel, Reel, Story, dan video generation.
- Iklan berbayar dan pengaturan budget iklan.
- n8n dan approval melalui WhatsApp, Slack, email, atau Notion.
- Agent mengubah brand guideline secara otomatis.
- Agent mengaktifkan campaign atau mengubah data produk.
- Agent menghapus draft atau asset.
- Agent menggunakan browser profile pribadi.
- Automatic learning yang langsung mengubah prompt produksi tanpa review.

## Arsitektur target

```text
Pengguna back office
        |
        v
Prasetya Platform / Content Studio
  - kalender, draft, versi, approval
  - brand knowledge dan campaign
  - audit, budget, scheduler, queue
        |
        +------------------------------+
        |                              |
        v                              v
OpenAI API                      Hermes Agent
  - structured copy              - research
  - image generation             - weekly strategy
                                 - content briefs
        |                              |
        +--------------+---------------+
                       |
                       v
               Draft Content Studio
                       |
                       v
             Approval dan posting manual
```

### Batas service

| Service | Tanggung jawab |
|---|---|
| Laravel web | Interface, validasi transport, dan presentasi |
| Domain Content | State transition, approval, versioning, dan business rules |
| Laravel Queue | Generation, agent run, asset processing, retry, dan timeout |
| Laravel Scheduler | Memulai weekly planning run dan reminder |
| Hermes | Riset, analisis, strategi, brief, dan koordinasi generation |
| OpenAI | Model teks/reasoning dan image generation |
| MySQL | Source of truth untuk data Content Studio |
| Redis | Queue, lock, rate limit, dan cache non-authoritative |
| Storage | Asset gambar original dan final |

Hermes harus berjalan sebagai service terpisah dari proses PHP web. Kegagalan Hermes tidak boleh mengganggu halaman Content Studio atau pekerjaan manual.

## Standar runtime Hermes

### Development lokal macOS

Hermes dijalankan dalam container. Mac menggunakan **Docker Desktop** karena Docker CLI saja tidak menyediakan Linux VM/daemon yang diperlukan untuk menjalankan container pada macOS. Operasional sehari-hari tetap melalui terminal, bukan bergantung pada GUI Docker Desktop.

Komponen lokal:

```text
Docker Desktop
  - Docker Engine / Linux VM
  - Docker CLI
  - Docker Compose
        |
        v
Hermes container
  - OpenAI provider
  - web research
  - image generation
  - Content Studio tools (setelah tersedia)
```

Command operasional yang diharapkan:

```sh
docker --version
docker compose version
docker compose up -d
docker compose ps
docker compose logs -f hermes
docker compose restart hermes
docker compose down
```

Data Hermes disimpan pada bind mount/volume khusus di luar repository, misalnya `~/.hermes`, dan tidak ikut di-commit. API key dimasukkan melalui setup interaktif atau secret file dengan permission minimum; jangan menaruh key pada command, `compose.yaml`, dokumentasi, atau `.env` Laravel.

Untuk tahap belajar, Hermes dijalankan interaktif terlebih dahulu. Scheduler dan gateway permanen belum diaktifkan sampai tool boundary, biaya, dan output research tervalidasi.

### Staging dan production VPS

VPS Linux tidak menggunakan Docker Desktop. Instal hanya:

- Docker Engine;
- Docker CLI;
- Docker Compose plugin.

Hermes dijalankan dengan image versi yang dipin atau digest yang telah diuji, bukan mengikuti `latest` tanpa review. Konfigurasi dan memory persisten disimpan pada volume khusus, sedangkan secret berada pada secret file/secret manager terpisah dari Laravel.

Container Hermes:

- tidak mendapat Docker socket;
- tidak mendapat SSH key;
- tidak mendapat database credential;
- tidak me-mount source, `.env`, log, release, atau backup Laravel;
- hanya me-mount direktori data Hermes yang diperlukan;
- berjalan sebagai user non-root dan memakai `no-new-privileges` bila image mendukung;
- memiliki limit CPU, memory, restart policy, health check, dan log rotation;
- tidak membuka dashboard/gateway ke internet publik;
- hanya dapat mengakses internal Content Studio API dan provider eksternal yang disetujui.

Laravel yang sudah berjalan melalui Nginx/systemd tidak harus dipindahkan ke Docker. Docker digunakan khusus untuk mengisolasi Hermes dan membuat runtime lokal, staging, serta production lebih konsisten.

### Alasan keputusan Docker

| Alasan | Dampak |
|---|---|
| Isolasi | Mengurangi file dan service VPS yang dapat dijangkau agent |
| Dependency | Python, browser, dan plugin Hermes tidak mencemari dependency host/Laravel |
| Reproduksi | Versi runtime lokal, staging, dan production dapat disamakan |
| Upgrade | Image baru dapat diuji sebelum menggantikan container lama |
| Rollback | Kembali ke tag/digest sebelumnya tanpa membongkar dependency host |
| Resource control | CPU dan memory Hermes dapat dibatasi agar tidak mengganggu aplikasi lain |
| Secret separation | Credential Hermes tidak bercampur dengan `.env` Laravel |
| Cleanup | Container dapat diganti tanpa menghapus volume data yang persisten |

Docker mengurangi area dampak, tetapi bukan pengganti least privilege, tool allowlist, network policy, authentication, audit, dan approval manusia.

## Model data awal

Nama tabel final ditetapkan saat design review, tetapi capability berikut harus memiliki penyimpanan eksplisit.

| Entitas | Tujuan dan field utama |
|---|---|
| `content_brands` | Identitas brand, audience, voice, visual direction, status |
| `content_pillars` | Pilar per brand, description, target share, status |
| `content_campaigns` | Nama, objective, periode, priority, approved facts |
| `content_items` | Brand, planned date, pillar, objective, brief, status, active revision |
| `content_revisions` | Versi brief, caption, hashtags, visual prompt, alt text, feedback |
| `content_assets` | File, mime type, dimensions, checksum, generation source, revision |
| `content_approvals` | Decision, actor, timestamp, note, revision yang disetujui |
| `content_publications` | Waktu aktual, URL opsional, actor, note |
| `content_research_runs` | Scope, query, status, budget, start/end, error |
| `content_research_sources` | URL, domain, title, fetched time, summary, relevance, trust status |
| `content_weekly_plans` | Minggu, objective, strategy, status, agent run |
| `ai_generation_runs` | Provider, model, prompt version, usage, cost, latency, status |
| `agent_runs` | Hermes session/run id, tool trace summary, limits, outcome |

### Status content item

```text
DRAFT
  -> READY_FOR_REVIEW
  -> REVISION_REQUESTED
  -> READY_FOR_REVIEW
  -> APPROVED
  -> PUBLISHED

DRAFT / READY_FOR_REVIEW -> REJECTED
```

Aturan:

- Hanya revision aktif yang dapat diajukan untuk review.
- Approval selalu menunjuk revision tertentu.
- Perubahan pada content setelah approval membuat revision baru dan mengembalikan status ke `READY_FOR_REVIEW`.
- `PUBLISHED` hanya berasal dari aksi manusia dengan revision yang masih approved.
- Konten rejected tetap dipertahankan sebagai evidence dan sumber evaluasi.

### Status pekerjaan AI

```text
QUEUED
  -> RESEARCHING
  -> PLANNING
  -> GENERATING_COPY
  -> GENERATING_ASSETS
  -> READY_FOR_REVIEW
  -> COMPLETED

Any running state -> PARTIALLY_FAILED / FAILED / CANCELLED
```

## Kontrak tool Hermes

Hermes tidak mengakses database secara langsung. Laravel menyediakan internal tool/API dengan input dan output terstruktur.

### Tool baca yang diizinkan

- `get_brand_profile`
- `get_content_rules`
- `get_active_campaigns`
- `get_approved_product_facts`
- `get_recent_content`
- `get_content_performance`
- `get_upcoming_business_events`
- `find_similar_topics`
- `get_weekly_planning_constraints`

### Tool tulis terbatas

- `submit_research_source`
- `submit_research_summary`
- `submit_weekly_plan_draft`
- `submit_content_brief_draft`
- `request_copy_generation`
- `request_image_generation`
- `submit_revision_draft`
- `complete_agent_run`

### Tool yang dilarang

- `approve_content`
- `mark_published`
- `delete_content`
- `delete_asset`
- `change_brand_guideline`
- `change_campaign`
- `change_product_fact`
- `manage_credentials`
- arbitrary SQL, shell, atau unrestricted HTTP terhadap jaringan internal

Setiap tool tulis memerlukan idempotency key, agent run id, dan expected revision/version agar retry tidak menimpa perubahan pengguna.

## Strategi model dan credential

### OpenAI

- Gunakan satu model teks/reasoning yang mendukung tool use dan structured output.
- Gunakan OpenAI Image API untuk generation dan editing gambar.
- Model identifier harus berupa konfigurasi, bukan hard-coded di domain logic.
- Prompt disimpan sebagai versi; perubahan prompt tidak berlaku retroaktif pada run lama.
- Output model dianggap untrusted dan selalu divalidasi.

### Credential

- API key tidak disimpan dalam tabel Content Studio atau dikirim ke browser.
- Gunakan credential service account/project terpisah untuk agent reasoning dan image generation bila tersedia.
- Hermes menerima key melalui environment atau secret injection pada service-nya.
- Laravel hanya memegang credential yang benar-benar diperlukan olehnya.
- Log dan audit tidak boleh memuat API key, Authorization header, atau raw response sensitif.

### Budget awal

Nilai final ditentukan setelah spike, tetapi sistem harus mendukung:

- maksimum agent turn per weekly run;
- maksimum search dan extract call;
- maksimum sumber per content item;
- maksimum generation attempt per revision;
- maksimum gambar per content item;
- token dan biaya maksimum per run dan per minggu;
- timeout per tool dan total run;
- circuit breaker ketika provider berulang kali gagal.

## Research policy

- Gunakan sumber internal terlebih dahulu.
- Web research hanya melalui tool/provider yang dikonfigurasi.
- Sumber dari user-generated content diberi trust level yang lebih rendah.
- Agent harus memisahkan fakta sumber dari rekomendasi atau inferensinya.
- Klaim harga, promo, produk, tanggal bisnis, dan kebijakan hanya boleh berasal dari approved internal facts.
- Simpan URL canonical dan waktu akses.
- Jangan menyimpan isi artikel penuh jika tidak diperlukan; simpan metadata, ringkasan, dan excerpt terbatas.
- Konten halaman web adalah data tidak tepercaya dan tidak boleh dianggap sebagai instruksi agent.
- Blokir private network, localhost, metadata endpoints, dan URL scheme non-HTTP(S) untuk mencegah SSRF.

## Interface

### Kalender

- Navigasi minggu sebelumnya/berikutnya dan kembali ke minggu ini.
- Setiap hari menampilkan content item, pillar, brand, preview, dan status.
- Filter brand, pillar, campaign, dan status.
- Empty state yang menawarkan tambah manual atau generate weekly plan.
- Responsive tanpa horizontal page overflow; kalender boleh memakai view list pada mobile.

### Detail content item

- Objective, audience, brief, pillar, dan campaign.
- Caption, hashtags, alt text, visual direction, dan gambar.
- Alasan pemilihan topik dan research sources.
- Riwayat revision, generation, approval, dan publication.
- Aksi generate ulang per bagian.
- Feedback revisi dengan pilihan bagian yang dipertahankan.
- Tombol approve/reject hanya pada revision yang siap review.

### Paket posting manual

- Preview asset final.
- Download gambar.
- Salin caption.
- Salin hashtags.
- Salin alt text.
- Checklist bahwa caption dan gambar telah diperiksa.
- Tandai sudah diposting dengan waktu aktual dan URL opsional.

### Pengaturan AI

- Status provider tersedia/tidak, tanpa menampilkan secret.
- Model aktif.
- Budget dan batas run.
- Search provider.
- Hermes health/status.
- Tidak menyediakan form untuk menempel API key melalui browser pada scope awal.

## Urutan delivery

Setiap fase harus dapat digunakan dan lulus quality gate sebelum fase berikutnya dimulai.

### Fase 0 — Decision dan kontrak

- Setujui plan ini.
- Buat ADR penggunaan Hermes dan pembagian kewenangan Laravel/Hermes.
- Finalisasi brand awal, content pillar, jumlah konten mingguan, dan approval policy.
- Finalisasi data dictionary dan threat model sebelum migration.
- Definisikan evaluasi kualitas caption, gambar, dan weekly plan.
- Pasang Docker Desktop pada macOS dan verifikasi Docker CLI/Compose sebelum spike Hermes; deployment VPS belum dilakukan pada fase ini.

**Exit:** scope, authority boundary, data contract, biaya, dan risiko disetujui.

### Fase 1 — Content Studio manual

- Migration dan domain actions.
- Kalender, daftar, create/edit, detail, upload asset, dan versioning.
- Approval, rejection, revision request, publication manual, dan audit.
- Download asset dan copy helpers.
- Belum ada OpenAI atau Hermes.

**Exit:** satu minggu konten dapat dikelola, disetujui, dan ditandai published sepenuhnya secara manual.

### Fase 2 — Brand knowledge dan content rules

- Brand profile, tone, visual guidance, examples, dan forbidden claims.
- Content pillar serta target composition.
- Campaign dan approved product facts.
- Validator aturan deterministik.
- Seed/template awal untuk brand pertama.

**Exit:** brief yang sama dapat dinilai terhadap aturan brand tanpa bergantung pada prompt tersembunyi.

### Fase 3 — OpenAI production

- Provider abstraction untuk copy dan image.
- Structured copy generation.
- Image generation/editing.
- Queue, retry, timeout, idempotency, usage, cost, dan prompt versioning.
- Partial regeneration dan revision history.

**Exit:** pengguna dapat membuat serta merevisi caption dan gambar dari brief, lalu meng-approve hasilnya.

### Fase 4 — Research tools

- Internal research services.
- Web search dan extraction provider.
- Source snapshot, trust status, citation, domain policy, dan SSRF protection.
- Similarity/deduplication terhadap histori content.
- Research dapat dijalankan secara deterministik tanpa Hermes.

**Exit:** research run menghasilkan kumpulan sumber yang dapat diaudit dan digunakan oleh planner.

### Fase 5 — Hermes Content Strategist

- Jalankan spike lokal dalam container melalui Docker Desktop dan Docker CLI/Compose.
- Deploy Hermes staging/production dengan Docker Engine dan Compose plugin sebagai service terpisah.
- Pin image version/digest dan verifikasi upgrade serta rollback container.
- Daftarkan tool contract terbatas.
- Weekly agent run, budget, timeout, cancellation, dan trace summary.
- Research, weekly strategy, content briefs, dan generation orchestration.
- Semua output masuk sebagai draft.

**Exit:** Hermes menghasilkan satu minggu draft lengkap dengan sumber dan alasan, tanpa akses approval atau publication.

### Fase 6 — Revision loop

- Feedback terstruktur dan partial revision.
- Compare revision.
- Pertahankan bagian caption/gambar tertentu saat regenerate.
- Catat acceptance/rejection reason untuk evaluasi, bukan automatic prompt mutation.

**Exit:** pengguna dapat meminta revisi granular tanpa kehilangan versi sebelumnya.

### Fase 7 — Manual posting workflow

- Final posting package.
- Checklist dan ergonomi copy/download.
- Published action, actual timestamp, URL, dan note.
- Reminder untuk approved content yang belum ditandai posted.

**Exit:** pengguna dapat berpindah dari approved draft ke posting manual dengan langkah minimum dan evidence yang lengkap.

### Fase 8 — Insight feedback

- Input metrik Instagram secara manual.
- Ringkasan performa per pillar, topic, campaign, dan visual direction.
- Hermes menggunakan snapshot performa untuk rekomendasi minggu berikutnya.
- Rekomendasi tetap memerlukan approval dan tidak mengubah brand rules otomatis.

**Exit:** weekly strategy dapat menjelaskan bagaimana performa masa lalu memengaruhi rekomendasi baru.

## Required tests

### Domain dan feature

- State transition content item hanya mengikuti diagram yang disetujui.
- Approval menunjuk revision tertentu.
- Perubahan setelah approval membatalkan approval revision lama.
- Hanya pengguna yang melakukan aksi eksplisit dapat menandai published.
- Publication manual menyimpan waktu, URL opsional, actor, dan audit.
- Upload menolak format, ukuran, dan dimensi yang tidak diizinkan.
- Checksum mencegah duplikasi asset yang tidak disengaja.
- Semua route berada di belakang auth.

### AI generation

- Structured output yang invalid ditolak atau diperbaiki dengan retry terbatas.
- Provider timeout tidak mengubah content item menjadi approved.
- Retry dengan idempotency key tidak membuat revision atau asset ganda.
- Prompt, credential, dan raw sensitive response tidak muncul di log atau UI.
- Token dan biaya tercatat per run.
- Budget menghentikan run sebelum call berikutnya.
- Caption dan image dapat diregenerate secara independen.

### Research

- Domain blocklist dan private network tidak dapat diakses.
- Research source menyimpan URL dan waktu akses.
- Untrusted page content tidak dapat memanggil tool atau mengubah system instruction.
- Approved product facts mengalahkan klaim eksternal yang bertentangan.
- Topik mirip dalam lookback window ditandai.

### Hermes

- Hermes hanya melihat tool allowlist.
- Tool approval, publication, delete, dan credential tidak tersedia.
- Setiap write tool memerlukan agent run id dan idempotency key.
- Agent run berhenti pada token, tool-call, cost, atau duration limit.
- Cancellation mencegah write berikutnya.
- Partial failure menyimpan output sukses dan menjelaskan bagian gagal.
- Weekly plan selalu masuk sebagai draft.

### Component dan browser

- Kalender dan list dapat digunakan dengan keyboard.
- Status tidak disampaikan hanya dengan warna.
- Dialog approval/rejection memulihkan fokus.
- Copy button memberi feedback yang dapat dibaca screen reader.
- Detail, kalender, dan posting package lulus axe.
- Tidak ada horizontal page overflow pada 360, 768, dan 1280 px.
- Reduced motion dihormati.

## Security dan privacy gates

- Buat threat-model entry untuk prompt injection, SSRF, secret leakage, excessive agency, runaway cost, malicious file, dan supply-chain agent.
- Hermes berjalan sebagai user/container non-root dengan filesystem dan network access minimum.
- Jangan memberi Hermes SSH key, database credential, deployment credential, atau browser profile pengguna.
- Internal tool endpoint memakai service authentication, short-lived credential bila tersedia, rate limit, dan audit.
- Egress agent dibatasi ke provider yang diperlukan.
- Asset diperiksa MIME, extension, size, dimensions, dan decompression risk.
- Data pelanggan atau pembayaran tidak masuk ke prompt Content Studio.
- Research dan generation log memiliki retention policy.
- API key hanya berada di environment/secret store dan dapat dirotasi terpisah.
- Dependency dan container Hermes dipin ke versi yang direview; update tidak otomatis masuk production.

## Observability

Pantau minimal:

- jumlah weekly run dan success rate;
- durasi per tahap;
- token dan biaya per brand/minggu;
- tool call dan retry count;
- research provider failure;
- generation failure;
- approval, rejection, dan revision rate;
- rasio draft yang akhirnya published;
- reminder yang belum diselesaikan;
- queue depth dan failed jobs;
- Hermes health serta last successful run.

Log menggunakan run id dan content public id, bukan caption penuh atau secret.

## Rollout

1. Instal Docker Desktop lokal dan pelajari operasional Hermes melalui Docker CLI/Compose.
2. Jalankan setup Hermes interaktif dengan volume lokal dan credential development.
3. Deploy Fase 1 tanpa credential AI.
4. Uji workflow manual dengan satu brand dan satu minggu konten.
5. Aktifkan OpenAI hanya untuk pengguna internal dan budget kecil.
6. Jalankan generation secara manual sebelum scheduler digunakan.
7. Aktifkan research tool satu provider pada staging.
8. Jalankan Hermes lokal dalam shadow mode: hasil dibandingkan tetapi belum menjadi draft resmi.
9. Siapkan container Hermes pada VPS dengan image pin, volume, secret, resource limit, dan private connectivity.
10. Setelah evaluasi, izinkan Hermes mengirim draft untuk satu brand.
11. Pertahankan approval manusia dan posting manual pada seluruh rollout.
12. Tambahkan brand lain hanya setelah biaya dan kualitas stabil.

## Rollback

- Nonaktifkan scheduler weekly agent run.
- Nonaktifkan tool credential Hermes dan OpenAI tanpa menghapus data Content Studio.
- Hentikan queue khusus generation/agent, bukan queue pembayaran platform.
- Kembali ke pembuatan konten manual.
- Pertahankan research, generation, revision, approval, dan audit history.
- Jangan menghapus asset approved/published saat rollback.
- Jika Hermes bermasalah, Content Studio manual dan OpenAI direct generation harus tetap dapat digunakan secara independen.

## Exit criteria keseluruhan

- Content Studio manual dapat digunakan tanpa AI.
- Brand knowledge dan approved facts menjadi konteks authoritative.
- OpenAI menghasilkan caption dan gambar dengan structured, versioned, dan auditable output.
- Research memiliki sumber yang dapat ditelusuri serta perlindungan prompt injection dan SSRF.
- Hermes dapat menyusun weekly plan dalam budget dan tool boundary yang ditentukan.
- Tidak ada jalur AI untuk approve, publish, delete, atau mengubah fakta bisnis.
- Pengguna dapat review, meminta revisi, approve, download, copy, dan menandai published.
- Seluruh required tests dan quality gate proyek lulus.
- Runbook deploy, incident, credential rotation, provider outage, dan cost runaway tersedia sebelum production.

## Pertanyaan yang harus diputuskan sebelum Fase 1

1. Brand pertama: Satamu, Prasetya Webworks, atau brand lain?
2. Berapa content item per minggu dan hari apa saja?
3. Siapa yang boleh membuat, merevisi, dan approve konten jika saat ini semua user memiliki satu access level?
4. Apakah gambar boleh sepenuhnya generatif, harus memakai asset brand, atau kombinasi keduanya?
5. Ukuran/aspect ratio final untuk posting gambar tunggal?
6. Berapa lama asset, research snapshot, dan agent trace disimpan?
7. Batas biaya OpenAI per minggu dan per brand?
8. Search provider production: SearXNG self-hosted, Firecrawl, Tavily, atau pilihan lain?
9. Apakah URL Instagram wajib saat menandai published?
10. Apakah input insight manual masuk scope release awal atau release optimasi?

