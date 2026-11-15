# ADR-0017: Simplify Content Studio to a calendar, one brand document, and a lean editor

- Status: Accepted
- Date: 2026-11-15
- Decision owners: Prasetya Group (owner, 2026-11-15)
- Related: [ADR-0011](0011-content-studio-authority-boundary.md), [ADR-0012](0012-simplified-content-studio-workflow.md), [ADR-0013](0013-status-driven-content-detail-workspace.md) (each amended), [ADR-0014](0014-back-office-redesign.md), [ADR-0016](0016-simplified-transaction-workspace.md)

## Context

ADR-0012 and ADR-0013 removed approval and folded Content Studio into a calendar and a status-driven detail page. One owner plans, writes, and posts by hand. Real use still shows more surface than work:

- **The rules cannot be edited.** The page that edited brand rules, pillars, and facts (`ContentBrandPage`) is no longer routed. The stored rules still decide the brand check, which can block *Siap posting*: forbidden claims such as "garansi uang kembali", forbidden terms, caption and hashtag limits, required hashtags, approved CTAs, and topic spacing. Pillars still appear as a calendar filter, but none can be added.
- **Facts duplicate Brand Knowledge.** Every structured fact (package prices, inclusions, timelines) is also written in the Brand Knowledge document. Both feed the claim check and the AI prompt, so a price changed in the document but not in a fact would still pass.
- **Research is heavy and unused.** The research sheet offers web search runs, manual sources, citations, trust levels, and cost. It was used once.
- **The editor splits one brief into four.** *Detail kreatif* (objective, audience, visual direction) sits in its own sheet beside the brief.
- **History lives in two sheets.** *Riwayat versi* and *Aktivitas* both tell the story of one item.
- **Evaluation controls sit in daily work.** *Berguna / Kurang berguna* on AI runs exists to evaluate providers, not to write posts.
- **Model choice is a work tab.** *Pengaturan AI* sits beside the calendar, although it is set once.
- **Insight asks for eight numbers by hand.** No reading has been recorded yet.

Established content planners (Buffer, Later, Planoly, Meta Business Suite Planner) centre on a calendar and a one-screen composer. AI is a button inside the composer, pillars and campaigns are labels, the brand guide is one document, and posting by hand is a reminder plus a ready package.

## Decision

1. **Three Content Studio sections: Kalender, Insight, Brand.**
   - Pengaturan AI moves to Pengaturan as **Model AI** (`/settings/ai`); `/content/settings` redirects there.
   - Insight is listed once at least one reading exists. Until then, readings are recorded from the published item.
2. **One brand document.**
   - Brand Knowledge is the only brand editor. Its optional **`## Aturan Posting`** section holds the rules as bracketed blocks: `[BATAS]`, `[KATA TERLARANG]`, `[KLAIM TERLARANG]`, `[CTA DISETUJUI]`, `[HASHTAG WAJIB]`, `[HASHTAG TERLARANG]`.
   - **When the section is present:** saving the document parses it and writes a new rules version whenever the rules changed (audited, change note naming the knowledge version). An invalid section refuses the save and names the problem.
   - **When it is absent:** the rules in force stay unchanged, and the page says so. **Sisipkan aturan yang berlaku** appends the current rules to the editor as that section, so nothing is lost by moving them in.
   - Profile fields outside the section (audience, voice, visual guidance, preferred terms, examples) are carried forward unchanged and remain AI context only.
3. **Facts retire into Brand Knowledge.**
   - Structured facts no longer feed the claim check or the AI prompt.
   - A price or percentage in public text must appear in the current Brand Knowledge.
   - Fact rows stay stored and readable in the audit, and their routes are removed.
4. **Pillars are labels.**
   - A pillar is picked or added by name in *Ubah rencana*.
   - There is no pillar management page. An inactive pillar stays readable on old items.
5. **Lean detail page.**
   - *Detail kreatif* is removed. Its three fields are no longer edited, but their stored values carry into each new revision and show read-only under the brief when present.
   - *Riset & sumber* is replaced by **Referensi** in the Draft editor: a list of links, each with an optional note, that can be added and removed. A reference is a cited manual source, so the AI still reads it as untrusted background.
   - Web research runs are no longer offered. Earlier runs and sources stay stored.
   - *Riwayat versi* and *Aktivitas* become one **Riwayat** sheet: revisions (with compare) first, then the item's audit.
   - *Berguna / Kurang berguna* leaves the UI. Existing ratings stay stored.
   - The Lainnya menu holds *Ubah rencana*, *Riwayat*, and the lifecycle actions.
6. **Shorter insight form.** *Jangkauan* (required), *Suka*, *Komentar*, *Simpan*, and *Bagikan* are shown. *Tayangan*, *Kunjungan profil*, and *Pengikut baru* sit behind **Angka lain**. The reading time defaults to now. The stored metrics are unchanged.
7. **Visual migration.** Every Content Studio screen moves to the DESIGN.md world in the same change: Heroicons, flat regions instead of cards, the type scale, and the tint ramp.

### Unchanged

- The four-state lifecycle and its transitions (ADR-0012).
- Deterministic brand checks: a blocking finding still prevents *Siap posting*.
- AI may only write a Draft.
- Revisions, publication evidence, audit, generation runs, and research rows are retained.
- Manual posting.
- Campaigns: name, inclusive period, objective, *Kelola campaign* on the Kalender.

## Amends

- **ADR-0011:** facts are no longer authoritative for claims; Brand Knowledge is. Web research is not offered in the UI.
- **ADR-0012 point 7:** audience, objective, and visual direction are no longer edited. Their values are carried forward.
- **ADR-0013:** the detail menu loses *Detail kreatif* and *Riset & sumber*, and *Riwayat versi* and *Aktivitas* merge.

## Alternatives considered

- **Restore the old rules page beside Brand Knowledge.** Rejected by the owner: two brand editors for one brand. One document is easier to keep true.
- **Drop the rules and keep only mechanical checks.** Rejected: the stored forbidden claims guard real legal and reputational risk.
- **Keep facts as a second source.** Rejected: duplicated prices go stale silently.
- **Hide research behind Lainnya.** Rejected by the owner in favour of plain references.
- **A backlog of unscheduled ideas.** Not now (owner): a planned time stays required, and an idea gets an estimated date that is moved later.

## Consequences

- Rules are editable again, in the same document the AI reads.
- The claim check can no longer pass on a stale fact.
- Old tests that seed facts for the claim check move to Brand Knowledge.
- Five routes go: brand rules, adding facts, retiring facts, starting a web search, and the research sheet. The web-search pipeline (Tavily client, queue job, and settings) is deleted with them. Two routes move: the AI settings page and its model update. *Berguna / Kurang berguna* keeps its route for now, with no UI.
- The Content Studio tracker rows in `back-office-redesign.md` become Migrated.

## Validation

- **Rules section:**
  - parsing (present, absent, invalid);
  - the insert action rendering the current rules exactly;
  - a new rules version only when the rules changed.
- **Claim check:**
  - prices pass only when Brand Knowledge states them;
  - facts are ignored;
  - the AI prompt carries no facts.
- **Redirects:**
  - `/content/settings` redirects to `/settings/ai`;
  - the retired brand-rule, fact, and research routes are gone.
- **Editor:**
  - references are added and removed;
  - the carried-forward creative fields survive a save;
  - a pillar is added by name.
- **Browser tests:**
  - the three sections and the merged Riwayat sheet;
  - the insight form;
  - axe and no horizontal overflow from 320 px.
