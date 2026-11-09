# Content Studio AI copy

Phase 3 of [ADR-0011](../decisions/0011-content-studio-authority-boundary.md). AI drafts caption, hashtags, alt text, and visual direction from an item's brief, its brand rules, and approved facts. Images stay manual. The result is always a new draft revision that a person edits, submits, and another person approves. With AI off, nothing else changes.

## Providers

`CONTENT_TEXT_PROVIDER` selects one:

| Value | Use |
|---|---|
| `none` (default) | AI off. The panel explains why; manual work is unaffected |
| `fake` | Deterministic drafts with no network and no cost, for local demos and browser tests. Refused in production |
| `openai_compatible` | Any Chat Completions API: DeepSeek, OpenAI, OpenRouter, or a compatible gateway |

Personal subscriptions (Claude Pro, ChatGPT Plus, Command Code Go) do not include API access, and routing them through a proxy is not allowed for this system. Use a provider's API key with pay-as-you-go billing.

### Candidate providers (owner notes)

| Provider | Base URL | Notes (checked 2026-11-08) |
|---|---|---|
| DeepSeek | `https://api.deepseek.com` | Official API, pay as you go. Planned first choice |
| KelontongAI | `https://api.kelontongai.id/v1` | Selected copy provider; OpenAI-compatible and prepaid Coin via QRIS. No usable Image API alias is active, so images remain manual. Confirm model sourcing, retention, and SLA before sensitive production use. Convert Rupiah token prices to USD for `CONTENT_TEXT_PRICE_*` |
| Vengetic (AI API Proxy Indonesia) | `https://api.vengetic.biz.id/v1` | OpenAI-compatible; free plan of 100 requests a day (gemini-flash, deepseek-chat). No terms, privacy policy, or legal entity published; model sourcing not stated, so upstream free tiers may keep or train on prompts. Local trials only. A free plan still needs a small nominal `CONTENT_TEXT_PRICE_*` (e.g. 0.01), because a real provider with zero prices stays off |

## Enable on a machine

1. Create an API key at the provider and set a **monthly spending limit there** as well.
2. In that machine's `.env` only (never in git, chat, screenshots, or this repository):

   ```dotenv
   CONTENT_TEXT_PROVIDER=openai_compatible
   CONTENT_TEXT_BASE_URL=https://api.deepseek.com   # example; must be https
   CONTENT_TEXT_MODEL=...                           # from the provider's model list
   CONTENT_TEXT_API_KEY=...
   ```

   There is no spending limit in the application (owner decision, 2026-11-08). **Set a monthly spending limit at the provider.** Prices are optional: `CONTENT_TEXT_PRICE_INPUT` and `CONTENT_TEXT_PRICE_OUTPUT` (USD per 1M tokens) only estimate cost in the run records; without them cost is recorded as 0.

   To let people choose the model in **Pengaturan AI**, list the allowed models (prices optional, as `model:input:output`); the screen can only pick from this list:

   ```dotenv
   CONTENT_TEXT_MODELS=deepseek-v4-flash:0.02:0.02,claude-haiku-5.5:0.04:0.04,claude-sonnet-5.5:0.06:0.06
   ```

   Compare a model before choosing it: `php artisan content:eval-copy --model=claude-haiku-5.5` (does not change the saved choice). A choice is audited as `content.ai_model_selected` and applies from the next run; a choice later removed from the list falls back to `CONTENT_TEXT_MODEL`.
3. `php artisan config:clear`, then evaluate before anyone uses it:

   ```sh
   php artisan content:eval-copy            # calls the provider for each scenario in resources/content-eval/copy-v1.json
   ```

   Every scenario must pass the hard rules. Read the saved outputs (path printed at the end) for voice and relevance before switching providers.
4. Run a worker for the AI queue, kept apart from anything else:

   ```sh
   php artisan queue:work --queue=content-ai --tries=1
   ```

   Runs left queued or running for 15 minutes (worker stopped) are closed as `stalled` on the next request.

Production (cPanel) has no queue worker today, so keep `CONTENT_TEXT_PROVIDER=none` there until a worker host is decided.

## Limits and what happens

There is no cost, weekly, per-revision, or per-day limit in the application (owner decision, 2026-11-08); control spending with the provider's own limits. These technical safeguards remain:

| Limit | Config | When reached |
|---|---|---|
| One run at a time | Per item | A second request waits until the first finishes (or is closed as stalled after 15 minutes) |
| Output tokens, timeout, attempts | `CONTENT_TEXT_MAX_OUTPUT_TOKENS` (1500), `CONTENT_TEXT_TIMEOUT` (60 s), `CONTENT_TEXT_MAX_ATTEMPTS` (2) | Retries only after a timeout, rate limit, server error, or invalid output, with backoff |
| Provider failing | 3 failures in a row within 10 minutes | New requests wait 10 minutes |

Content edited while the provider works is never overwritten: the answer is discarded (`DISCARDED`).

## Brand rules

The proposed Prasetya Webworks rules, pillars, and facts (from the live site and the package catalogue) are in `resources/content-brands/prasetya-webworks.json`. After the owner reviews them, apply on any machine through the audited actions:

```sh
php artisan content:import-brand WEBWORKS resources/content-brands/prasetya-webworks.json --actor=<username>
```

Re-running skips unchanged rules, existing pillars, and facts already in force.

## Records

`content_generation_runs` keeps who asked, which fields, provider, model, prompt version, tokens, estimated cost, latency, attempts, and a safe error message. Prompts and raw responses are not stored. **Content Studio → Pengaturan AI** shows status, the week's spend, and the last 30 runs; it never shows or accepts a key. Audit: `content.generation_requested`, `content.generation_applied`, `content.generation_failed` on the item.

## Web research

Research runs on the same `content-ai` queue, one search at a time per item, with no daily or cost limit. Internal context (facts in force, running campaigns, similar posts within 90 days) shows first; web results are untrusted material, never business facts.

```dotenv
CONTENT_SEARCH_PROVIDER=tavily          # none (default) | fake | tavily
CONTENT_SEARCH_API_KEY=...              # from tavily.com; stays in .env, agents never see it
CONTENT_SEARCH_PRICE_PER_CALL=         # optional USD per search, only for the cost record
CONTENT_SEARCH_MAX_RESULTS=5            # per search, at most 10
CONTENT_RESEARCH_BLOCKED_DOMAINS=       # comma-separated, dropped from results and refused as manual sources
CONTENT_RESEARCH_ALLOWED_DOMAINS=       # when set, the only domains research may use
```

Results are filtered by `UrlPolicy` (http/https on standard ports, no credentials, no private, loopback, link-local, metadata, or internal hosts), canonicalised without tracking parameters, deduplicated per brand, and stored with title, domain, a summary of at most 600 characters, trust (official, general, user-generated), relevance, and access time. Manual sources are always available; with no title the server reads it through `SafePageReader` (resolved addresses must be public, the connection is pinned to them, each redirect is re-checked, HTML only, 8 s and 512 KB). Cited sources go to AI copy as untrusted background (`copy-v4`).

## Brand Knowledge retrieval

The Brand Knowledge screen stores one free-text document as an immutable version. Saving calls no model: Laravel splits paragraphs into ordered chunks of about 1,200 characters (maximum 100). Copy generation ranks the active chunks against the item's title, topic, objective, audience, and brief; short documents send every chunk, while longer documents send at most six. These chunks enter the untrusted DATA section as `brand_knowledge`.

**Bantuan AI** uses the same configured text provider and price list. It asks a fixed brand interview and `brand-knowledge-v2` turns the answers into an operational guide, including concrete editorial do/don't guidance without inventing business facts. Ambiguous numbers are omitted rather than guessed. Missing details are listed in a final **Informasi yang Perlu Dikonfirmasi** section: people can see it in the editor, but the chunker excludes it from AI retrieval. The operator must review and save the proposal separately. One synchronous provider call is made per request; its status, token counts, latency, estimated cost, provider, model, and prompt version are recorded in `content_brand_knowledge_assists`. Answers and raw responses are deliberately not stored. A failed or unavailable provider leaves the editor and the manual save flow untouched.

The provider returns one JSON response rather than streaming tokens. During that request the interview dialog stays open, reports the live elapsed time and request state, and disables cancellation. A valid result is handed directly to the textarea before the dialog closes. Provider, budget, or validation errors remain in the dialog and preserve every answer for correction or retry.

Some compatible models ignore JSON mode even when the gateway accepts `response_format`. The result parser accepts a strict `source_text` object, a fenced or prose-wrapped JSON object, or a direct Markdown document with at least two headings. Arbitrary prose, empty output, and documents over 50,000 characters remain invalid. Token usage from an invalid provider answer is still recorded for budget accuracy.

This is retrieval/RAG, not model training. To update knowledge, edit the one source document and save a new version. Never paste customer data, credentials, API keys, SSH keys, or other secrets. If embeddings are introduced later, backfill them onto the chunk index and keep `source_text` as the authority.

## Revision requests and evaluation

When an item is in *Perlu revisi*, the AI panel preselects the copy parts the reviewer named and sends the reviewer's note and the writer's optional instruction with the prompt (`copy-v2`). The note never overrides the brand rules. People rate finished drafts *Berguna* or *Kurang berguna* (with a reason); **Pengaturan AI → Evaluasi draft AI** shows ratings, approvals, and cost per model and prompt version. Use it, together with `content:eval-copy`, before switching model or prompt; ratings never change a prompt by themselves.

## Workflow simplification (ADR-0012)

The workflow is moving to Draft, Siap posting, Sudah diposting, and Diarsipkan with no approval step. AI keeps writing only to Draft: no run marks content Ready, Published, or Archived. Before the status migration runs on any database, run the read-only audit there (or on a copy) and resolve every reported breach:

```sh
php artisan content:status-audit          # table; exits non-zero on an unknown status or an invariant breach
php artisan content:status-audit --json   # counts only, never content text
```

## Switch or roll back

- Switch provider or model: change the `.env` values, `php artisan config:clear`, run `content:eval-copy`.
- Turn off: `CONTENT_TEXT_PROVIDER=none`. Revisions already drafted stay; nothing else changes.
- A changed prompt is a new `CopyPromptBuilder::VERSION`; old runs keep the version they used.
