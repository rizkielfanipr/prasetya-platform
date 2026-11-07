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
| KelontongAI | `https://api.kelontongai.my.id/v1` | Indonesian gateway, OpenAI-compatible, prepaid Coin via QRIS. Prices are far below the model makers' list prices and the terms do not say how models are sourced; no SLA; models may be withdrawn on provider policy changes; Coin expires after 14–60 days; privacy policy not reviewed. Acceptable for local evaluation with a small top-up; do not rely on it for production without confirming sourcing and data handling. Its prices are in Rupiah: convert to USD for `CONTENT_TEXT_PRICE_*` |
| Vengetic (AI API Proxy Indonesia) | `https://api.vengetic.biz.id/v1` | OpenAI-compatible; free plan of 100 requests a day (gemini-flash, deepseek-chat). No terms, privacy policy, or legal entity published; model sourcing not stated, so upstream free tiers may keep or train on prompts. Local trials only. A free plan still needs a small nominal `CONTENT_TEXT_PRICE_*` (e.g. 0.01), because a real provider with zero prices stays off |

## Enable on a machine

1. Create an API key at the provider and set a **monthly spending limit there** as well.
2. In that machine's `.env` only (never in git, chat, screenshots, or this repository):

   ```dotenv
   CONTENT_TEXT_PROVIDER=openai_compatible
   CONTENT_TEXT_BASE_URL=https://api.deepseek.com   # example; must be https
   CONTENT_TEXT_MODEL=...                           # from the provider's model list
   CONTENT_TEXT_API_KEY=...
   CONTENT_TEXT_PRICE_INPUT=...                     # USD per 1M input tokens, from the price page
   CONTENT_TEXT_PRICE_OUTPUT=...                    # USD per 1M output tokens
   CONTENT_AI_BUDGET_RUN_USD=0.50
   CONTENT_AI_BUDGET_WEEK_USD=5.00
   ```

   Without both prices a real provider stays off, because the budget is enforced on estimated cost.

   To let people choose the model in **Pengaturan AI**, list the allowed models with their prices; the screen can only pick from this list:

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

| Limit | Config | When reached |
|---|---|---|
| Cost per run | `CONTENT_AI_BUDGET_RUN_USD` | Checked before the call from the prompt size and output allowance; the run fails as `run_budget` without calling |
| Cost per week (Monday–Sunday WIB) | `CONTENT_AI_BUDGET_WEEK_USD` | New requests are refused; a run that could pass it fails as `week_budget` |
| Runs per revision | `CONTENT_AI_RUNS_PER_REVISION` (5) | New requests are refused until the content is edited or submitted |
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

Research runs on the same `content-ai` queue and weekly budget. Internal context (facts in force, running campaigns, similar posts within 90 days) shows first; web results are untrusted material, never business facts.

```dotenv
CONTENT_SEARCH_PROVIDER=tavily          # none (default) | fake | tavily
CONTENT_SEARCH_API_KEY=...              # from tavily.com; stays in .env, agents never see it
CONTENT_SEARCH_PRICE_PER_CALL=0.008     # USD per search from the price page; a small nominal value on a free plan
CONTENT_SEARCH_MAX_RESULTS=5            # per search, at most 10
CONTENT_SEARCH_PER_ITEM_PER_DAY=5
CONTENT_RESEARCH_BLOCKED_DOMAINS=       # comma-separated, dropped from results and refused as manual sources
CONTENT_RESEARCH_ALLOWED_DOMAINS=       # when set, the only domains research may use
```

Results are filtered by `UrlPolicy` (http/https on standard ports, no credentials, no private, loopback, link-local, metadata, or internal hosts), canonicalised without tracking parameters, deduplicated per brand, and stored with title, domain, a summary of at most 600 characters, trust (official, general, user-generated), relevance, and access time. Manual sources are always available; with no title the server reads it through `SafePageReader` (resolved addresses must be public, the connection is pinned to them, each redirect is re-checked, HTML only, 8 s and 512 KB). Cited sources go to AI copy as untrusted background (`copy-v3`).

## Revision requests and evaluation

When an item is in *Perlu revisi*, the AI panel preselects the copy parts the reviewer named and sends the reviewer's note and the writer's optional instruction with the prompt (`copy-v2`). The note never overrides the brand rules. People rate finished drafts *Berguna* or *Kurang berguna* (with a reason); **Pengaturan AI → Evaluasi draft AI** shows ratings, approvals, and cost per model and prompt version. Use it, together with `content:eval-copy`, before switching model or prompt; ratings never change a prompt by themselves.

## Switch or roll back

- Switch provider or model: change the `.env` values, `php artisan config:clear`, run `content:eval-copy`.
- Turn off: `CONTENT_TEXT_PROVIDER=none`. Revisions already drafted stay; nothing else changes.
- A changed prompt is a new `CopyPromptBuilder::VERSION`; old runs keep the version they used.
