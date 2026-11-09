# Prompt Implementasi Content Studio dan AI Content Strategist

Salin prompt berikut ke coding agent dari repository `/Users/air/Projects/prasetya-group/prasetya-platform`. Prompt ini mengharuskan delivery bertahap: Content Studio manual harus stabil sebelum KelontongAI, research tools, dan Hermes diaktifkan. Posting Instagram tetap manual dan tidak ada AI yang boleh menyetujui atau memublikasikan konten.

---

## Prompt

Anda bekerja di repository:

```text
/Users/air/Projects/prasetya-group/prasetya-platform
```

Bangun modul **Content Studio** untuk perencanaan konten Instagram mingguan, research tren, weekly strategy, brief, caption, gambar, approval manusia, revision history, dan paket posting manual.

Target akhir:

```text
Laravel Content Studio
  -> menyimpan brand knowledge, campaign, kalender, revision, approval, dan audit
  -> memanggil KelontongAI untuk structured copy; gambar tetap diunggah manual
  -> menyediakan research tools yang aman
  -> memulai Hermes sebagai AI Content Strategist
  -> menerima weekly plan dan content draft dari Hermes
  -> meminta manusia review dan approve
  -> menyediakan download gambar dan copy caption
  -> manusia posting manual ke Instagram
  -> manusia menandai konten sebagai published
```

Tidak ada Instagram API dan tidak ada n8n dalam scope ini. Hermes berjalan lokal melalui Docker Desktop saat development dan melalui Docker Engine + Compose plugin pada VPS staging/production.

### 1. Instruksi awal wajib

1. Baca `AGENTS.md` dan `docs/README.md` secara penuh.
2. Baca source of truth berikut:
   - `docs/content-studio-ai-planning-plan.md`;
   - ADR Content Studio/Hermes jika sudah tersedia;
   - `docs/architecture.md`;
   - `docs/design.md`;
   - `docs/data-dictionary.md`;
   - `docs/security/threat-model.md`;
   - `docs/testing/strategy.md`;
   - `docs/runbooks/deployment.md`;
   - `docs/runbooks/incident-response.md`;
   - ADR modular monolith, accessible tokens, user access, dan keputusan lain yang relevan.
3. Jalankan:

   ```sh
   pwd
   git status --short
   git branch --show-current
   git log --oneline -8
   ```

4. Semua perubahan yang sudah ada adalah milik pengguna. Jangan reset, checkout, stash, hapus, atau menimpa perubahan tersebut.
5. Kerjakan pada branch feature khusus dari `main`, misalnya `feat/content-studio`. Jangan push langsung ke `main` karena `main` dapat memicu deployment.
6. Jika plan/prompt masih belum di-commit atau file target bertumpang tindih dengan perubahan pengguna, berhenti dan laporkan sebelum coding.
7. Audit codebase aktual sebelum menentukan nama file, namespace, tabel, route, atau component. Jangan menganggap prompt lebih mutakhir daripada repository.
8. Buat baseline test, query count, dan screenshot untuk area yang akan disentuh.
9. Implementasikan satu fase pada satu waktu. Setiap fase harus mempunyai commit, test, dan laporan sendiri.
10. Jangan menginstal Docker Desktop, Hermes, dependency global, membuat OpenAI key, atau mengubah VPS tanpa persetujuan eksplisit pengguna. Dokumentasi dan manifest boleh disiapkan lebih dahulu.

### 2. Keputusan yang sudah final

| Topik | Keputusan |
|---|---|
| Modul | Content Studio berada di Prasetya Platform |
| Kanal awal | Instagram feed, gambar tunggal |
| Perencanaan | Mingguan dengan content item per hari |
| Caption | KelontongAI API dengan structured output |
| Gambar | Upload manual; tidak ada image generator dalam scope aktif |
| Research dan strategy | Hermes setelah manual workflow dan research tools stabil |
| Approval | Wajib manusia |
| Posting | Manual: download gambar dan salin caption |
| Published | Hanya melalui aksi eksplisit manusia |
| Instagram API | Tidak digunakan |
| n8n | Tidak digunakan |
| Source of truth | Laravel/MySQL |
| Scheduler | Laravel Scheduler |
| Background work | Laravel Queue |
| Hermes lokal | Container melalui Docker Desktop, dioperasikan dengan Docker CLI/Compose |
| Hermes VPS | Container melalui Docker Engine, Docker CLI, dan Compose plugin |
| Laravel VPS | Tidak wajib dipindahkan ke Docker |

### 3. Keputusan pemilik yang wajib dijawab sebelum Fase 1

Audit apakah jawaban sudah ditambahkan ke plan atau ADR. Jika belum, berhenti setelah Fase 0 dan minta keputusan pengguna untuk:

1. Brand pertama.
2. Jumlah content item per minggu dan hari publikasi.
3. Siapa yang boleh membuat, merevisi, dan approve bila semua user saat ini memiliki satu access level.
4. Gambar sepenuhnya generatif, memakai asset brand, atau kombinasi.
5. Aspect ratio dan resolusi gambar awal.
6. Retention asset, research snapshot, generation log, dan agent trace.
7. Budget OpenAI per run, per minggu, dan per brand.
8. Search/extract provider untuk staging dan production.
9. Apakah URL Instagram wajib saat menandai published.
10. Apakah insight manual masuk release pertama atau release optimasi.

Jangan mengarang jawaban yang memengaruhi otorisasi, biaya, retensi, atau identitas brand. Keputusan dapat dibuat sebagai konfigurasi hanya setelah default-nya disetujui pemilik.

### 4. Invariant yang tidak boleh dilanggar

#### Platform

- Content Studio tidak boleh mengubah payment, order, package price, gateway, atau capability transaksi.
- Jangan memindahkan atau memperlemah invariant payment yang sudah ada.
- Controller tetap tipis; aturan bisnis berada di domain actions/services.
- Network call tidak boleh dilakukan di dalam database transaction.
- Semua route Content Studio berada di balik `auth` dan active-user protection.
- Public route identifier menggunakan ULID/public id, bukan numeric id.
- Vue menggunakan semantic tokens, tanpa raw colour dan tanpa `any`.
- Label, status, route, formatter, dan type memiliki satu source of truth.

#### Content

- AI output selalu draft.
- Hanya revision aktif yang dapat diajukan untuk review.
- Approval menunjuk revision tertentu.
- Perubahan setelah approval membuat revision baru dan membatalkan approval revision lama.
- `PUBLISHED` hanya berasal dari aksi manusia atas revision yang masih approved.
- Rejected content, revision lama, research source, dan audit tidak dihapus sebagai side effect workflow.
- Fakta harga, produk, promo, periode, dan klaim bisnis hanya berasal dari approved internal facts.
- Sistem tidak menganggap konten published hanya karena planned date sudah lewat.

#### Agent

- Hermes tidak boleh approve, publish, delete, mengubah brand guideline, mengubah campaign, mengubah product facts, atau mengelola credential.
- Hermes tidak mendapat database credential, `.env` Laravel, SSH key, deployment key, Docker socket, backup, atau browser profile pengguna.
- Hermes tidak menjalankan arbitrary SQL, unrestricted shell, atau unrestricted internal HTTP.
- Setiap tool tulis membutuhkan agent run id, idempotency key, dan expected revision/version.
- Web content adalah untrusted data dan tidak boleh dianggap sebagai system/tool instruction.
- Budget, timeout, cancellation, dan tool allowlist diberlakukan oleh Laravel/runtime, bukan hanya system prompt.

#### Secret dan privacy

- API key tidak pernah dikirim ke browser, disimpan di database Content Studio, ditulis di prompt, fixture, log, screenshot, atau dokumentasi.
- Jangan memasukkan data pelanggan, order, payment, atau data pribadi yang tidak relevan ke prompt Content Studio.
- Log memakai run id dan content public id, bukan caption lengkap atau secret.
- Raw provider request/response hanya disimpan bila aman, disanitasi, diperlukan, dan sesuai retention decision.

### 5. Audit capability sebelum implementasi

Petakan keadaan aktual:

| Area | Capability existing | Reusable source | Gap untuk Content Studio |
|---|---|---|---|
| Auth/access |  |  |  |
| Audit |  |  |  |
| File upload/storage |  |  |  |
| Queue/scheduler |  |  |  |
| Public IDs |  |  |  |
| Pagination/filtering |  |  |  |
| Dialog/form patterns |  |  |  |
| Status badges/timeline |  |  |  |
| Notifications/flash |  |  |  |
| Structured logging |  |  |  |
| HTTP/provider adapters |  |  |  |
| Docker/deployment |  |  |  |

Audit khusus:

- storage disk dan URL policy;
- image validation capability;
- audit action conventions;
- scheduler/queue worker production setup;
- user access model setelah ADR-0009;
- existing internal API authentication patterns;
- test database, factories, demo seeder, and Playwright setup;
- CSP, SSRF, outbound HTTP, and logging controls.

Laporkan audit singkat dan rencana file sebelum mulai mengubah kode.

### 6. Information architecture target

Tambahkan satu menu utama **Content Studio**. Di dalamnya gunakan secondary navigation, bukan menambah banyak item sidebar:

- Kalender;
- Konten;
- Brand Knowledge;
- Pengaturan AI.

Route final boleh disesuaikan dengan audit, tetapi target awal:

```text
GET  /content
GET  /content/calendar
GET  /content/items
POST /content/items
GET  /content/items/{contentItem:public_id}
PUT  /content/items/{contentItem:public_id}
POST /content/items/{contentItem:public_id}/submit-review
POST /content/items/{contentItem:public_id}/request-revision
POST /content/items/{contentItem:public_id}/approve
POST /content/items/{contentItem:public_id}/reject
POST /content/items/{contentItem:public_id}/mark-published
GET  /content/brands
GET  /content/settings
```

Mutation route final harus menggunakan Form Request, domain action, CSRF/session auth, audit, dan redirect/response pattern proyek.

### 7. Model domain target

Finalisasi lewat migration design dan `docs/data-dictionary.md`. Capability minimal:

| Entitas | Tanggung jawab |
|---|---|
| ContentBrand | Brand knowledge dan status |
| ContentPillar | Pilar dan target composition |
| ContentCampaign | Objective, periode, priority, approved facts |
| ContentItem | Kalender, status, active revision, brand/campaign/pillar |
| ContentRevision | Brief, caption, hashtags, visual prompt, alt text, feedback |
| ContentAsset | File metadata, checksum, dimensions, revision, generation source |
| ContentApproval | Decision, actor, timestamp, revision, note |
| ContentPublication | Waktu aktual, URL opsional, actor, note |
| ResearchRun | Scope, query, status, budget, duration, error |
| ResearchSource | URL, domain, title, fetched time, summary, trust/relevance |
| WeeklyPlan | Week, objective, strategy, status, agent run |
| AiGenerationRun | Provider, model, prompt version, usage, cost, latency, status |
| AgentRun | Hermes run id, limits, trace summary, outcome |

Gunakan nama Laravel/table yang konsisten dengan codebase. Jangan membuat JSON blob besar sebagai pengganti relational fields yang perlu difilter, divalidasi, atau diaudit. JSON boleh untuk structured metadata yang bounded dan memiliki schema/version.

### 8. State machine content

Implementasikan transisi eksplisit melalui domain action, bukan update status bebas:

```text
DRAFT
  -> READY_FOR_REVIEW
  -> REVISION_REQUESTED
  -> READY_FOR_REVIEW
  -> APPROVED
  -> PUBLISHED

DRAFT / READY_FOR_REVIEW -> REJECTED
```

Aturan minimal:

- draft belum dapat dipublikasikan;
- submit review membutuhkan brief dan output yang diwajibkan keputusan produk;
- revision request membuat revision baru atau workflow edit yang tetap mempertahankan versi lama;
- approve menyimpan actor, revision, note, dan timestamp;
- perubahan approved revision menghasilkan revision baru dan status review ulang;
- publish manual memerlukan approved revision;
- published record immutable kecuali correction flow disetujui dalam ADR;
- reject dan revision feedback tidak mengandung secret/data sensitif.

Hindari generic `setStatus(string)` action. Buat intention-revealing actions seperti `SubmitContentForReview`, `ApproveContentRevision`, dan `MarkContentPublished`.

### 9. UI target

#### Kalender

- week navigation dan kembali ke minggu ini;
- filter brand, pillar, campaign, status;
- content item per hari dengan preview dan status;
- empty state untuk tambah manual atau generate weekly plan;
- list layout pada mobile bila grid kalender tidak usable;
- planned time dan actual publication time dibedakan jelas.

#### Daftar konten

- server-side search, filter, sorting, pagination;
- status, brand, pillar, campaign, planned date, dan preview;
- empty, no-result, loading, error, retry;
- link menuju detail dengan public id.

#### Detail

- objective, audience, brief, campaign, pillar;
- caption, hashtag, alt text, visual direction, image;
- research reason dan citations;
- generation metadata yang relevan tanpa secret;
- revision compare/history;
- approval/publication/audit timeline;
- aksi edit, generate, regenerate, request revision, approve, reject, download, copy, dan mark published sesuai status.

#### Brand Knowledge

- tampilkan satu textarea besar tempat pemilik menulis bebas tentang brand, audience, tone, visual, layanan, harga, proses, kebijakan, FAQ, larangan, dan informasi lain yang disetujui;
- simpan source text sebagai versi immutable; menekan simpan pada teks identik harus ditolak;
- chunk secara deterministik (paragraf/sekitar 1.200 karakter, maksimum 100) dan simpan urutannya;
- retrieval tahap awal menggunakan keyword ranking dan mengirim maksimum enam chunk relevan ke `brand_knowledge` pada prompt;
- ini RAG, bukan training; jangan memanggil model saat menyimpan atau memecah dokumen;
- tampilkan versi aktif, jumlah chunk, riwayat versi, dan audit di disclosure; jangan tampilkan tabs rules/pillars/facts;
- pindahkan pengelolaan campaign ke Kalender/Perencanaan dan pertahankan redirect URL lama;
- jangan pernah memasukkan customer, order, payment, secret, API key, atau credential ke dokumen atau prompt;
- content pillar dan target composition;
- semua perubahan versioned/audited bila digunakan oleh AI.

#### Pengaturan AI

- configured/not configured, model, budget, provider health, Hermes health;
- jangan menampilkan API key;
- jangan menyediakan input secret dari browser pada scope awal;
- jelaskan jika fitur manual tetap tersedia saat provider down.

#### Paket posting manual

- preview gambar final;
- download asset;
- copy caption, hashtag, alt text;
- accessible success feedback untuk copy;
- checklist review;
- actual publish time dan URL Instagram opsional/wajib sesuai keputusan pemilik;
- `Tandai sudah diposting` memakai confirmation yang jelas.

### 10. KelontongAI copy provider design

1. Buat interface/contract provider untuk copy agar domain tidak tergantung SDK tertentu.
2. Implementasi KelontongAI berada di adapter/integration layer, bukan domain model/controller.
3. Model name, timeout, retry, dan budget berasal dari config.
4. Gunakan structured output/schema untuk:
   - caption;
   - hashtags;
   - CTA;
   - alt text;
   - visual direction;
   - weekly plan dan content brief bila dipanggil langsung.
5. Validasi output seperti untrusted request data.
6. Retry hanya untuk kegagalan transient atau bounded repair; jangan infinite self-correction.
7. Catat token, cost estimate, latency, provider request id yang aman, prompt version, dan outcome.
8. Jangan log Authorization header atau raw secret.

### 11. Research design

Research source internal didahulukan:

- brand profile;
- active campaign;
- approved product facts;
- recent content;
- similar topics;
- manual performance snapshot;
- business calendar.

External search/extraction:

- menggunakan provider abstraction;
- domain allowlist/blocklist;
- hanya HTTP(S);
- blok localhost, loopback, private/link-local ranges, metadata endpoints, redirect ke private IP, dan DNS rebinding sebisa mungkin;
- batasi response size, content type, timeout, redirect, dan extraction length;
- simpan canonical URL, title, domain, fetched time, summary, trust, dan relevance;
- jangan menyimpan artikel penuh tanpa kebutuhan dan review copyright/retention;
- tandai inference terpisah dari fakta sumber;
- hostile page text tidak boleh mengubah system prompt atau memanggil tool.

Fase research harus dapat dijalankan secara deterministik dari Laravel tanpa Hermes agar adapter, policy, dan test tidak bergantung pada agent.

### 12. Kontrak Hermes

Hermes tidak mengakses database. Expose internal tools/API dengan authentication service-to-service dan schema eksplisit.

#### Read tools

```text
get_brand_profile
get_content_rules
get_active_campaigns
get_approved_product_facts
get_recent_content
get_content_performance
get_upcoming_business_events
find_similar_topics
get_weekly_planning_constraints
```

#### Bounded write tools

```text
submit_research_source
submit_research_summary
submit_weekly_plan_draft
submit_content_brief_draft
request_copy_generation
submit_revision_draft
complete_agent_run
```

#### Forbidden tools

```text
approve_content
mark_published
delete_content
delete_asset
change_brand_guideline
change_campaign
change_product_fact
manage_credentials
arbitrary_sql
unrestricted_shell
unrestricted_http
```

Setiap call:

- memiliki JSON schema;
- memvalidasi agent identity dan active run;
- memakai idempotency key;
- memakai expected version untuk optimistic concurrency;
- mempunyai rate limit dan timeout;
- mencatat sanitized audit/trace;
- ditolak jika run cancelled, expired, atau budget exceeded.

Jangan menganggap system prompt sebagai authorization control.

### 13. Hermes dan Docker

#### Development lokal

- Gunakan Docker Desktop sebagai engine di macOS.
- Operasikan melalui Docker CLI/Compose.
- Persist data di volume/bind mount di luar repository, misalnya `~/.hermes`.
- Jalankan setup interaktif; jangan menaruh API key pada command atau `compose.yaml`.
- Mulai tanpa scheduler permanen dan tanpa tool berisiko.
- Gunakan credential development dan dummy data.
- Content Studio manual tidak boleh bergantung pada container aktif.

#### Staging/production

- Gunakan Docker Engine, CLI, dan Compose plugin; tidak ada Docker Desktop.
- Pin image version/digest yang diuji; jangan memakai floating `latest` untuk production.
- Mount hanya volume data Hermes.
- Jangan mount source/`.env`/log/backup Laravel, SSH key, atau Docker socket.
- Non-root, `no-new-privileges`, resource limit, restart policy, health check, log rotation.
- Gateway/dashboard tidak dibuka ke internet publik.
- Secret terpisah dari Laravel dan permission minimum.
- Batasi egress ke OpenAI, search/extract provider, dan internal Content Studio endpoint.
- Uji recreate, restart, backup volume, restore, upgrade, dan rollback image.

Siapkan manifest/example tanpa secret. Jangan menjalankan instalasi atau deployment VPS tanpa persetujuan eksplisit.

### 14. Budget dan reliability

Implementasi harus mendukung configurable limits:

- max agent turns;
- max search calls;
- max extract calls;
- max sources per item;
- max copy generation attempts;
- max images per content item;
- token/cost cap per run, week, dan brand;
- timeout per tool dan total run;
- cancellation;
- provider circuit breaker/backoff;
- queue isolation dari queue payment/platform kritis.

Jika budget habis:

- hentikan call baru;
- simpan partial result yang valid;
- tandai `PARTIALLY_FAILED` atau status yang disetujui;
- jelaskan limit yang tercapai;
- jangan auto-retry tanpa budget baru atau aksi pengguna.

### 15. Tahap implementasi wajib

#### Fase 0 — Decision, ADR, baseline

- Audit repository dan isi matriks capability.
- Jawab open decisions bersama pemilik.
- Buat ADR authority boundary Laravel/Hermes/OpenAI.
- Update data dictionary dan threat model draft.
- Tetapkan quality evaluation fixtures dan baseline UI/performance.
- Dokumentasikan instalasi Docker/Hermes; jangan install tanpa izin.

**Gate:** decisions, ADR, data contract, access, budget, retention, dan risk treatment disetujui. Berhenti untuk review.

#### Fase 1 — Content Studio manual

- Migration, models, factories, domain actions, requests, controller/presenter.
- Kalender, daftar, create/edit/detail, upload asset, revision history.
- Submit review, revision request, approve, reject, mark published, audit.
- Download dan copy helpers.
- Tanpa OpenAI dan Hermes.

**Gate:** satu minggu konten dapat dikelola dan diposting manual end-to-end. Berhenti untuk UAT/review.

#### Fase 2 — Brand knowledge

- Brand profile, approved facts, forbidden claims, tone, visual guidance.
- Pillar, campaign, target composition, repetition rule.
- Version/audit dan deterministic validator.
- Seed/template hanya untuk data yang disetujui.

**Gate:** content revision dapat divalidasi terhadap aturan brand tanpa AI. Berhenti untuk review data brand.

#### Fase 3 — KelontongAI copy generation

- Provider contracts dan adapter.
- Structured copy generation; gambar tetap melalui upload manual.
- Queue, prompt version, usage/cost, retry, timeout, idempotency.
- Regenerate caption/gambar secara terpisah.
- Provider health/status tanpa secret.

**Gate:** brief dapat menghasilkan caption dan image draft yang versioned, auditable, dan reviewable. Berhenti untuk evaluasi kualitas/biaya.

#### Fase 4 — Research tools

- Internal research sources.
- External search/extract provider abstraction.
- Source snapshots, trust, citation, domain policy, SSRF protection.
- Similarity terhadap recent content.
- Deterministic research flow tanpa Hermes.

**Gate:** research fixture menghasilkan sumber traceable tanpa prompt injection/SSRF. Berhenti untuk security review.

#### Fase 5 — Hermes strategist

- Siapkan Docker Compose development tanpa secret.
- Setelah izin, install/run Hermes lokal dalam container.
- Daftarkan read/bounded-write tools.
- AgentRun, limits, cancellation, trace summary.
- Weekly research, strategy, plan, briefs, generation orchestration.
- Shadow mode dahulu; output tidak langsung masuk official weekly plan.

**Gate:** Hermes menghasilkan satu minggu draft dengan citations dan alasan dalam budget, tanpa forbidden capability. Berhenti untuk review.

#### Fase 6 — Revision loop

- Structured feedback dan partial revision.
- Compare revisions.
- Preserve selected caption/image sections.
- Acceptance/rejection feedback untuk evaluasi, bukan automatic uncontrolled learning.

**Gate:** revisi granular tidak menghilangkan versi, approval, atau audit lama.

#### Fase 7 — Manual posting workflow

- Final posting package, download, copy, checklist.
- Mark published, actual timestamp, URL, note.
- Reminder approved-but-not-posted.
- Tidak ada Instagram API.

**Gate:** admin menyiapkan dan mencatat posting manual dengan langkah minimum dan evidence lengkap.

#### Fase 8 — Insight feedback

- Input manual metrik yang disetujui.
- Summary per pillar/topic/campaign/visual.
- Hermes membaca performance snapshot untuk rekomendasi berikutnya.
- Tidak ada automatic strategy/prompt mutation tanpa approval.

**Gate:** weekly plan baru dapat menjelaskan penggunaan data performa masa lalu.

Jangan menggabungkan semua fase dalam satu PR. Fase 0–2, OpenAI, research, Hermes, dan optimization harus mudah direview dan rollback secara terpisah.

### 16. Required tests

#### Domain/state

1. Seluruh transisi valid dan invalid.
2. Approval terikat revision.
3. Edit approved content membuat revision baru dan review ulang.
4. Publish manual menolak draft/non-approved/stale revision.
5. Reject/revision/publish mencatat actor, note, timestamp, audit.
6. Public id route dan numeric id 404.
7. Access/auth sesuai keputusan pemilik.

#### Upload/asset

1. MIME dari byte, extension, size, dimensions, aspect ratio.
2. Malformed/decompression-bomb-like image ditolak secara aman.
3. Checksum/deduplication.
4. Provider temporary URL dimaterialisasi ke storage.
5. Unauthorized download ditolak.
6. Asset revision ownership tidak bocor.

#### KelontongAI copy

1. HTTP seluruhnya difake; test tidak memanggil jaringan nyata.
2. Valid structured output.
3. Invalid JSON/schema, refusal, timeout, 429, dan 5xx.
4. Retry bounded dan idempotent.
5. Token/cost/latency/prompt version tercatat.
6. Secret/raw Authorization tidak tampil.
7. Budget menghentikan call baru.
8. Partial copy generation tidak merusak revision.

#### Research/security

1. Allowlist/blocklist.
2. localhost, private, link-local, metadata, redirect, dan unsupported scheme ditolak.
3. Response size/type/timeout limits.
4. Hostile prompt injection diperlakukan sebagai source content.
5. Approved internal facts menang atas external claim.
6. Source canonical URL, fetched time, trust, relevance, citation tersimpan.
7. Similar topic detection sesuai lookback.

#### Hermes/internal tools

1. Service authentication.
2. Tool allowlist; forbidden endpoint/tool tidak ada atau ditolak.
3. Active run, idempotency, expected version, rate limit.
4. Cancelled/expired/budget-exceeded run menolak write.
5. Duplicate tool calls tidak membuat data ganda.
6. Partial failure dan resume policy.
7. Weekly plan selalu draft.
8. No DB/SSH/Docker socket/Laravel secret access dibuktikan lewat manifest/config test bila memungkinkan.

#### Frontend/component

1. Form validation dan accessible errors.
2. Status dan action availability per state.
3. Revision compare.
4. Copy/download feedback.
5. Approval/rejection focus management.
6. Calendar/list filter URL state.
7. Loading, empty, no-result, error, retry.

#### E2E

1. Buat content manual untuk satu minggu.
2. Upload gambar dan isi caption.
3. Submit review, request revision, approve revision baru.
4. Download/copy dan mark published.
5. Gagal publish non-approved/stale revision.
6. Generate caption dan gambar dengan provider fake.
7. Regenerate salah satu bagian tanpa mengubah yang dipertahankan.
8. Research source dan citation tampil.
9. Weekly Hermes draft fixture masuk sebagai reviewable draft.
10. Keyboard, axe, reduced motion, mobile/tablet/desktop tanpa global overflow.

### 17. Evaluation AI

Automated tests tidak cukup untuk kualitas generatif. Buat fixture evaluasi versioned tanpa data production:

- minimal beberapa brand/voice scenarios;
- campaign dan approved facts;
- repeated-topic cases;
- malicious research content;
- copy dengan unsupported claims;
- caption terlalu panjang/tidak sesuai tone;
- image prompt dengan forbidden visual/text;
- weekly plan yang tidak seimbang.

Nilai minimal:

- factual grounding;
- brand voice;
- relevance;
- diversity/non-repetition;
- citation quality;
- policy compliance;
- actionability;
- cost/latency.

Gunakan deterministic rules untuk hard constraints dan human rubric untuk kualitas. Model evaluator boleh membantu tetapi tidak menjadi satu-satunya judge.

### 18. Performance dan observability

- Baseline query count dan response time halaman Content Studio utama.
- Hindari N+1 pada calendar, list, revisions, sources, dan assets.
- Server-side pagination/filtering.
- Jangan memuat full revision/source/trace history di list.
- Queue AI terpisah atau diberi priority agar tidak mengganggu payment jobs.
- Metrics: run success, duration, tokens, cost, tool calls, retries, provider errors, approval/rejection/revision rate, published rate, queue depth, Hermes health.
- Structured log tanpa full prompt/caption/secret kecuali sanitized debugging yang diaktifkan eksplisit.
- Alert untuk runaway cost, repeated failure, queue backlog, dan Hermes unavailable.

### 19. Documentation updates

Update pada fase yang relevan:

- `docs/content-studio-ai-planning-plan.md` progress/status;
- ADR Content Studio/Hermes;
- `docs/README.md`;
- `docs/architecture.md`;
- `docs/design.md`;
- `docs/data-dictionary.md`;
- `docs/security/threat-model.md`;
- `docs/testing/strategy.md`;
- `docs/runbooks/deployment.md`;
- `docs/runbooks/incident-response.md`;
- runbook baru Content Studio/Hermes bila diperlukan;
- `.env.example` hanya nama variable dan nilai dummy/non-secret;
- Docker Compose example tanpa key/token.

Dokumentasikan install lokal, VPS, backup volume, credential rotation, provider outage, budget incident, upgrade image, rollback image, dan disable-agent fallback ke manual workflow.

### 20. Quality gates

Jalankan seluruh gate `AGENTS.md`:

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

Selama iterasi boleh menjalankan targeted test, tetapi seluruh gate wajib sebelum handoff tiap fase yang mergeable. Jangan mengklaim lulus jika command tidak dijalankan, skipped tidak terduga, atau gagal.

Visual QA minimal:

- 360, 768, dan 1280 px;
- reduced motion;
- kalender, list, detail, brand knowledge, AI settings;
- long caption/hashtag/source URL;
- no image, one image, generation failure;
- empty/loading/error/pagination;
- approval/revision dialogs;
- posting package;
- tidak ada global horizontal overflow, clipped action, status color-only, atau inaccessible focus.

### 21. Commit dan PR strategy

Gunakan Conventional Commits dan commit kecil. Contoh:

1. `docs: decide content studio agent boundaries`
2. `feat: add manual content studio workflow`
3. `feat: add content brand knowledge`
4. `feat: generate content drafts with openai`
5. `feat: add traceable content research`
6. `feat: orchestrate weekly plans with hermes`
7. `feat: add content revision workflow`
8. `feat: add manual posting package`
9. `feat: analyze manual content insights`

Idealnya satu fase satu PR. Jangan mencampur instalasi VPS, provider credential, atau unrelated refactor. Jangan force push/rewrite perubahan pengguna.

### 22. Rollout

1. Merge decision/ADR/docs.
2. Deploy Content Studio manual tanpa AI credential.
3. UAT satu brand dan satu minggu.
4. Aktifkan OpenAI development/staging dengan budget kecil.
5. Jalankan generation manual, bukan scheduler.
6. Aktifkan satu research provider staging.
7. Jalankan Hermes lokal dalam shadow mode.
8. Bandingkan hasil Hermes dengan planning manusia.
9. Siapkan container VPS dengan pinned image, volume, secret, resource limit, private endpoint, health check.
10. Jalankan Hermes staging; tetap shadow mode.
11. Izinkan Hermes membuat official draft untuk satu brand setelah approval kualitas/security/cost.
12. Pertahankan approval dan posting manual.
13. Tambahkan brand hanya setelah stabil.

Jangan deploy production, membuat provider key, atau mengaktifkan scheduler tanpa persetujuan eksplisit pengguna.

### 23. Rollback

- Matikan weekly agent scheduler.
- Revoke/disable Hermes service token.
- Hentikan queue generation/agent tanpa mengganggu queue payment.
- Stop/revert Hermes container ke pinned image sebelumnya.
- Pertahankan volume untuk investigasi; jangan hapus sebelum backup/retention decision.
- Nonaktifkan OpenAI/research provider dan kembali ke manual workflow.
- Jangan menghapus content, revision, research, generation, approval, publication, atau audit history.
- Approved/published asset tetap tersedia.
- Content Studio manual harus tetap berfungsi ketika Hermes/KelontongAI/search provider mati.

### 24. Definition of done

Implementasi keseluruhan selesai hanya jika:

- manual Content Studio bekerja tanpa AI;
- brand knowledge dan approved facts authoritative;
- KelontongAI copy output structured, validated, versioned, auditable, dan budgeted;
- research traceable dan terlindungi dari SSRF/prompt injection;
- Hermes menyusun weekly draft dalam tool/budget boundary;
- tidak ada AI route/tool untuk approval, publication, delete, credential, atau business-fact mutation;
- manusia dapat review, revise, approve, download, copy, dan mark published;
- posting tetap manual dan tidak ada Instagram API/n8n;
- Docker development dan VPS manifest aman, reproducible, dan tanpa secret;
- manual fallback, outage handling, backup, upgrade, rollback, dan credential rotation terdokumentasi;
- semua test, quality gate, evaluation, dan visual QA lulus;
- tidak ada secret atau production data di code, commit, fixture, output, screenshot, atau docs.

### 25. Format laporan setiap fase

Laporkan:

1. Outcome fase.
2. Keputusan/assumption yang digunakan.
3. Capability dan file utama yang berubah.
4. Data/migration impact.
5. State transition dan authorization yang diverifikasi.
6. Security/privacy controls.
7. Provider/tool calls dan failure handling.
8. Hasil test aktual per command.
9. Visual QA dan viewport.
10. Performance/query comparison.
11. Cost/usage evidence bila AI aktif.
12. Risiko, limitation, dan open decisions.
13. Rollout/rollback fase.
14. Branch, commit, dan PR.

Berhenti dan minta keputusan pengguna jika diperlukan perubahan scope, akses/role, retention, budget, automatic publishing, migration destruktif, penggunaan data pelanggan, tool agent baru, provider baru, instalasi software, deployment VPS, atau pembuatan credential.

---

## Catatan penggunaan

Jalankan prompt ini per fase, bukan sebagai satu implementasi panjang. Mulai dari Fase 0. Setelah Fase 0 disetujui, implementasikan Fase 1 dan berhenti untuk UAT. Jangan menyalakan OpenAI sebelum manual workflow stabil, jangan menyalakan Hermes sebelum research tools aman, dan jangan memindahkan Hermes ke VPS sebelum shadow-mode lokal serta staging lulus.

