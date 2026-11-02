# My Tracker + trainer quotes — design spec

**Date:** 2026-11-01  
**Status:** Draft — awaiting product review  
**Goal:** Let logged-in users save **personal** trainer quotes and general notes in one place (“My Tracker”), with UI aligned to the **race detail premium** design (`race-detail-premium.css` / `fhor-race-premium`).

---

## Background

- Post-race narrative already lives in `in_race_comment` / comment history.
- Personal research already lives in WordPress user meta `bricks_horse_tracker_notes` via `inc/tracker.php`.
- Race cards render `bricks_tracker_render_horse_widget()` on each runner (`inc/runner-card-wide.php`).
- Premium race pages use design tokens `--rd-*` (mapped to `--fhor-*` on `.race-detail-page-shell:has(.fhor-race-premium)`).

Trainer quotes are **not** a separate product surface. They are a **typed entry** inside the existing tracker, with distinct display when `kind === trainer_quote`.

Future licensed CSV feeds are **out of scope** for v1 but the data model leaves a clean extension point (see §8).

---

## Requirements

### Functional

1. **Single panel:** “My Tracker” on the racecard opens one panel for all entries (general notes + trainer quotes).
2. **Entry types:** User chooses **General note** or **Trainer quote** when saving.
3. **Trainer quote fields (when type = trainer quote):**
   - **Quote text** (required) — multiline.
   - **Source** (optional) — fixed list: `TV`, `Newspaper`, `Trainer website`, `Podcast / radio`, `Stable / social`, `Other`.
   - **Trainer name** (optional) — pre-filled from runner’s `trainer_name` on the racecard when available; user may edit before save.
4. **Privacy:** Only the owning WordPress user sees their entries. No site-wide or shared quotes in v1.
5. **Race context:** Continue storing `race_id`, `race_date`, `race_time`, `course` on save (unchanged behaviour).
6. **Dashboard:** `/my-tracker/` lists the same entries, shows type badges, and renders trainer quotes with FTS-style emphasis (italic body, optional trainer + source line).
7. **Backward compatibility:** Existing notes without `kind` are treated as `note`. No migration script required.

### Non-functional

- Reuse existing AJAX actions where possible; extend payload/response rather than new endpoints unless clarity demands it.
- Move tracker styling out of inline `<style>` in `tracker.php` into shared CSS aligned with premium race tokens.
- Avoid full page reload after save/delete on racecard where feasible (append DOM node); dashboard may keep reload initially if cheaper.
- Sanitize all user input (`sanitize_textarea_field`, allowlisted `source`, `kind`).

---

## Data model

Each note object in `bricks_horse_tracker_notes[horse_key].notes[]`:

| Field | Type | Notes |
|--------|------|--------|
| `id` | string | Existing `uniqid('tn_', true)` |
| `kind` | `note` \| `trainer_quote` | Default `note` if missing |
| `note` | string | Body text (quote or general note) |
| `source` | string | Empty for general notes; allowlisted for quotes |
| `trainer_name` | string | Optional; meaningful for quotes |
| `created_at` | mysql datetime | Existing |
| `race_date`, `race_time`, `course`, `race_id` | string | Existing |

**Validation (`bricks_tracker_add_note`):**

- `kind` must be `note` or `trainer_quote`; else coerce to `note`.
- If `kind === trainer_quote`, `source` must be empty or one of the allowlist; invalid → empty string.
- `trainer_name` optional for both kinds; max length 100.

No database schema change in v1 (user meta only).

---

## UI design — racecard inline widget

**Placement:** Unchanged — `runner-card-wide__act` / `.tracker-race-row` beside Bet Tracker.

**Trigger button**

- Label: `My Tracker (n)` or `My Tracker` when empty.
- Style: match premium secondary buttons (`.fhor-race-premium` — `var(--rd-s2)` fill, `var(--rd-line)` border, 10px radius, 11–12px type). Remove purple hover from legacy inline CSS.

**Collapsed preview (optional flag `show_latest_flag`)**

- Latest entry for **this meeting** preferred: match `race_id` if set, else `race_date` + horse.
- General note: small muted line — `Note: …` (trimmed words).
- Trainer quote: `Trainer quote` label + italic snippet; do not use emoji prefix.

**Expanded panel** (`.fhor-tracker-panel`)

Structure top → bottom:

1. **List** of entries for this horse (all meetings), newest first. Each row:
   - Meta line: date/time saved · race context (date \| time \| course).
   - Type pill: `Note` (neutral) or `Quote` (accent outline).
   - Body: normal text for notes; **italic** + optional leading `"` styling for quotes.
   - Quote footer when applicable: `Trainer: {name}` · `Source: {source}` (omit empty parts).
   - Text button **Delete** (danger-muted, not purple).
2. **Composer**
   - Segmented control or `<select>`: **General note** | **Trainer quote**.
   - When **Trainer quote** selected: show optional **Trainer** input (pre-filled) + **Source** `<select>`.
   - `<textarea>` placeholder switches by type.
   - Primary **Save** button (premium accent `var(--rd-ac2)`).

**Guest:** Keep “Log in to use tracker” — same premium muted text style.

**Race detail premium overrides:** Extend `race-detail-premium.css` (or new `tracker-premium.css` enqueued with race detail) under `.fhor-race-premium .fhor-tracker-*` so inline widget inherits `--rd-*` automatically on premium pages; on non-premium pages use `:root` `--fhor-*` from `fhor-ui.css`.

---

## UI design — `/my-tracker/` dashboard

Wrap dashboard in a page shell consistent with race detail premium:

- Container: `.my-tracker-dashboard.fhor-tracker-page` inside a shell that sets the same token block as race detail (reuse `.race-detail-page-shell` class on the WordPress page template **or** duplicate token block on `.fhor-tracker-page` — prefer **duplicate tokens on `.fhor-tracker-page`** to avoid coupling page templates).

**Header**

- Replace emoji title with text-first header matching `.fhor-race-premium` rhythm: title “My Tracker”, subtitle explaining notes + trainer quotes.

**Empty state**

- Card on `var(--rd-s2)` / `--fhor-surface-muted`, border `var(--rd-line)`, link to daily cards.

**List (replace plain table feel with premium cards)**

- Desktop: table is acceptable if styled as premium data grid (already partially in `fhor-ui.css`); enhance with:
  - Column **Type** — latest entry kind or counts “2 notes · 1 quote”.
  - Expandable rows use same note/quote card component as inline panel (shared PHP partial or helper `fhor_tracker_render_note_item()`).
- Mobile: keep existing card-stack pattern from `fhor-ui.css` media queries.

**Logged-out gate:** Restyle login prompt to premium empty card (no inline hard-coded grays).

---

## API / PHP changes

| Area | Change |
|------|--------|
| `bricks_tracker_add_note()` | Accept `kind`, `source`, `trainer_name`; validate; persist on note object |
| `bricks_ajax_add_tracker_note` | Read POST fields; return full note object including new fields |
| `bricks_tracker_render_horse_widget()` | New markup classes (`fhor-tracker-*`); composer UI; pass `trainer_name` in `$race_meta` from caller |
| `inc/runner-card-wide.php` | Pass `trainer_name` into widget `$race_meta` |
| `bricks_my_tracker_dashboard_shortcode()` | Render kind/source/trainer; use shared note item renderer |
| `bricks_tracker_inline_js` | POST new fields; toggle composer fields; optional DOM insert instead of reload |
| CSS | New `tracker-premium.css` (or sections in `fhor-ui.css` + `race-detail-premium.css`); **remove** duplicate rules from `bricks_tracker_inline_styles()` once migrated |

**Allowlist helper:** `fhor_tracker_note_kinds()`, `fhor_tracker_quote_sources()` in `tracker.php` for PHP + localized JS.

---

## Display rules

1. **Ordering:** Newest `created_at` first everywhere.
2. **Quote styling:** Italic body; trainer/source as 11px muted meta below.
3. **General notes:** Regular weight body.
4. **Delete:** Confirm dialog unchanged.
5. **Bet Tracker button:** Unchanged styling on premium card; tracker buttons visually grouped in `.tracker-inline-actions`.

---

## Error handling

- Not logged in → 403 JSON / guest message (existing).
- Empty body → 400 with message.
- Invalid `kind` → coerce to `note`.
- Invalid `source` → cleared (quote still saves).
- Meta size: if user meta grows large, document soft limit (~500 notes total) in code comment only; no enforcement in v1.

---

## Testing

1. **PHP unit-style script** (optional small file in `tests/`): add note, add quote, default kind on legacy-shaped array, source allowlist.
2. **Manual:** Logged-in user on premium race page — save note, save quote with source, verify panel + dashboard + reload persistence.
3. **Manual:** Legacy notes still display as general notes after deploy.
4. **Manual:** Mobile racecard — panel does not break runner card grid.

---

## Future extension (not v1)

**Licensed daily CSV**

- Table `fhor_licensed_trainer_quotes` keyed by `meeting_date`, `runner_id` or normalized horse name, `provider`, `quote_text`, `trainer_name`.
- Nightly import job; display layer merges **read-only** licensed rows only when user enables “Show partner quotes” or site enables for premium tier.
- User meta entries remain separate; never overwrite user text.

---

## Files (expected touch list)

- `inc/tracker.php` — data, widget, dashboard, JS
- `inc/runner-card-wide.php` — pass trainer name
- `fhor-ui.css` — dashboard + shared tracker components
- `race-detail-premium.css` — racecard widget overrides under `.fhor-race-premium`
- `inc/enqueue.php` — register `tracker-premium.css` on tracker routes
- `tests/tracker-notes-test.php` (new, lightweight)

---

## Out of scope

- Public trainer quote feeds, scraping, FTS-style trainer strike-rate sidebar (stats already on card via existing trainer metrics).
- Editing existing notes in place (delete + re-add only, as today).
- Export / share quotes.

---

## Approval

Once this spec is approved, next step is an implementation plan (`writing-plans` skill) with ordered tasks and verification commands.
