# ADR-0003: Derived colour tokens for WCAG AA and chart separation

- Status: Proposed
- Date: 2026-11-01
- Decision owners: Prasetya Group
- Related: [`../design.md`](../design.md)

## Context

`design.md` requires both of these, and they conflict:

1. Primary actions in `#FF5100` with white text, orange links, orange and coral brand text.
2. WCAG AA contrast.

White on `#FF5100` is about 3.4:1; normal-size text needs 4.5:1. Separately, Webworks `#FF5100` and Satamu `#FF6542` are nearly the same colour when used as chart series: the palette validator measured ΔE 3.9 against a floor of 15, a hard failure for full-colour vision as well as colour blindness.

## Decision

Brand colours stay exactly as approved for identity and non-text graphics: active icons, focus ring, brand dots, and logos. Derived tokens carry the jobs the brand colours cannot pass:

| Token | Value | Used for | Result |
|---|---|---|---|
| `--primary-strong` | `#C93F00` | Primary button fill, selected segment | White text at about 5:1 |
| `--primary-strong-hover` | `#AD3600` | Hover of the above | |
| `--primary-text` | `#B83A00` | Links and accent text | Passes on white and ivory |
| `--webworks-text`, `--satamu-text` | `#B83A00`, `#B3341A` | Brand badge text | Passes on their surfaces |
| `--chart-webworks` | `#C93F00` | Webworks series | ΔE 20.7 against Satamu series |
| `--chart-satamu` | `#FF8F75` | Satamu series | Same; below 3:1 on white, so relief is required |

The relief, which the chart ships with: different marker shapes per brand, a legend, per-point tooltips, a screen-reader summary, and a data table view.

## Alternatives considered

- **Keep `#FF5100` with white text.** Rejected: fails the accessibility requirement on the most important button in the product.
- **Dark text on `#FF5100`.** Passes at about 5.4:1 but departs further from the approved references, which show white text.
- **Large bold button text only.** Would pass as large text, but not for small buttons or links.

## Consequences

- The primary button is visibly deeper than the mockup. This is the one visible change to the approved look and is the first thing to review.
- Reverting is a one-line token change, which would also reintroduce the contrast failure.
- Logos and approved brand values are untouched.

## Validation

axe (WCAG 2.1 A and AA) runs on every back-office page in Playwright. The chart pair was checked with the palette validator.
