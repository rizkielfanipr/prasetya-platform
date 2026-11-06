# ADR-0006: Integer rupiah, public ULIDs, and reference-based idempotency

- Status: Proposed
- Date: 2026-11-01
- Decision owners: Prasetya Group
- Related: [`../data-dictionary.md`](../data-dictionary.md)

## Context

The data model needs one answer for how money is stored, what identifies a record publicly, and what an idempotency key remembers.

## Decision

- **Money** is an unsigned integer in whole rupiah. IDR has no decimals in Duitku Pop, so a "minor unit" of sen would only add a conversion to get wrong. `App\Support\Money` holds it in code; floats are never used.
- **Public identifiers** are ULIDs in `public_id`. Numeric ids stay internal and route binding rejects them.
- **Invoices** are `PREFIX-YYYYMM-NNNNNN`, the format in the approved reference, numbered per brand and month in the business timezone from a row-locked sequence. The longest retry suffix stays inside Duitku's 50-character limit for `merchantOrderId`.
- **Idempotency keys** store a request fingerprint and a reference to the resulting resource, not the response body. Personal data is not copied into another table, and a replay returns the resource's current state, which for a checkout is more useful than a stale copy. Failed attempts release the key.
- Keys are pruned after seven days.

## Alternatives considered

- **Decimal columns.** Rejected: no decimals exist to store.
- **Storing response bodies.** Rejected: duplicates customer data and returns outdated payment state.

## Consequences

A replayed response is not byte-identical if the resource changed in between. A second currency would need this decision revisited.

## Validation

Unit tests for `Money`, invoice format, and the idempotency decision table. Feature tests for replay, reuse with a different body, and failure release.
