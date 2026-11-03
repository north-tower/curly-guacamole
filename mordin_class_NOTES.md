# Mordin Class Module — Notes

Discovery source: phpMyAdmin export on **server.fhor.site** / database **`coolwed1_wp364`** (2026-11-02). Plan references **`fhorsitedb`** on the VPS — treat as the same racing schema unless deployment uses a different database name.

---

## Discovery (Task 0)

- **DB version:** MariaDB **10.5.29** (`SELECT VERSION()`). Meets gate (MariaDB ≥ 10.3; window functions + `REGEXP_SUBSTR` OK).

- **class format:**
  - **`historic_races_beta`:** numeric `tinyint(2)` — values **1–6** plus large **NULL** count (912 NULL vs 1006 for class 4 in last 12 months flat). Not the string `"Class 4"`.
  - **`daily_races_beta` / `advance_daily_races_beta`:** `varchar(5)` — single-digit strings **`"1"`–`"6"`** (e.g. handicap rows show `"6"`, `"5"`, `"4"`). Mordin tier parsing uses **daily/advance** `race_title` + `class` + `handicap`; historic rebuild must **join daily** (or map historic tinyint consistently) for Group/Listed detection.

- **daily_races_beta coverage of historic flat races:** **32,252 / 32,252** (100%) for flat races in the last **7 years**. No coverage fallback needed (`with_daily_row / flat_races ≥ 0.9`).

- **Pattern title format (sample):** Sponsored name + pattern marker in title, e.g. `betdirect.com Winter Derby (Group 3)`, `williamhill.co.uk Cammidge Trophy (Listed)`, `Leopardstown 1,000 Guineas Trial Stakes (Group 3)`, `Leslie Harrison Memorial Nell Gwyn Stakes (Fillies...`. False positives possible (e.g. `Scots Group Quiach Maiden Stakes`, `Taylor Wimpey And PPS Group Conditions Stakes`) — rely on `mordin_tier_code` regex, not `group_race` flag alone.

- **Handicap band format in title (sample):** Usually **no** band in title; class comes from **`class` column**. Exception: **`Bailey's Apprentice Handicap (55-75)`** with **`class` NULL** — band-only handicaps need title regex or NULL-class handling in Task 1.

- **country values (daily_races_beta, by volume):** England, Eire, Scotland, Wales, Northern Ireland, USA, United Arab Emirates, Singapore (full names, not ISO codes). No `France` / `Germany` in top counts; international rows are mostly UAE + Singapore + USA.

- **age_range values (flat daily, top):** `3YO plus`, `4YO plus`, `2YO only`, `3YO only`, `3YO to 5YO`, `3YO to 4YO`, plus smaller variants (`3YO & 4YO`, `6YO plus`, etc.) and some NULL.

- **distance_beaten cumulative:** **No.** Values are **margins to the horse immediately in front** (non-monotonic down the result, e.g. 0.12, 0.25, 1.00, 0.75…). Winner often **NULL** at finish position 1.
  - **Task 3 action:** Use cumulative margin via `SUM(hrunb.distance_beaten) OVER (PARTITION BY hrunb.race_id ORDER BY CAST(hrunb.finish_position AS UNSIGNED))` in a derived table (per master plan), **or** evaluate `distance_behind_winner` on `historic_runners_beta` (also present) for “lengths behind winner” if it is already cumulative — spot-check one race before choosing.
  - Do **not** read raw `distance_beaten` as total beaten distance.

- **trainer name format:** Full names and initials, e.g. **`Charlie Appleby`**, **`M Appleby`**, **`John & Thady Gosden`**, **`J H M Gosden`**, **`Charles Hills`**, **`B W Hills`**, **`J W Hills`**, **`Michael Appleby`** (legacy/low volume). Discovery query only covered Appleby / Gosden / Hills; other Task 2 seeds must be verified with the Task 2 JOIN query before deploy.

- **`backtest_cr_data`:** Table **does not exist** on this database (no row count). Legacy historic FSr / System Builder FSr remains unavailable except any other legacy paths. **Mordin build does not depend on it.**

---

## Task 0 decision rules (applied)

| Rule | Result |
| --- | --- |
| DB version ≥ MySQL 8 / MariaDB 10.3 | **Pass** (10.5.29) |
| Required columns on daily/advance races & runners | **Pass** — includes `race_title`, `country`, `prize_pos_1`, `age_range`, `class`, `handicap`, `distance_yards`, `meeting_date`; runners have `age`, `trainer_name` |
| Drop Task 4 advance INSERT for missing columns | **Not needed** — `advance_*` mirrors daily shape |
| `distance_beaten` cumulative | **Fail** — use window sum (or `distance_behind_winner` after verify) in Task 3 |
| `mordin_country_group` vs actual countries | **Task 1 change:** Map **`Northern Ireland`** to **GB** (plan regex lists england/scotland/wales but not NI; otherwise NI → `OTHER` → `UNQ`). **`United Arab Emirates`** and **`Singapore`** → **`OTHER`** (same as Japan in plan tests). **`Eire`** → **IRE**; **`England`/`Scotland`/`Wales`** → **GB**; **`USA`** → **USA**. |
| Historic flat without daily row | **N/A** — 100% join coverage in sample window |
| `trainer_locations` seed names | **Task 2:** Plan draft uses **`Charlie Hills`** — DB has **`Charles Hills`** (7,663 runs), not “Charlie Hills” in discovery sample. **`Charlie Appleby`**, **`John & Thady Gosden`** match. Run seed JOIN for all 22 names; fix any zero-match names to exact `historic_runners_beta.trainer_name`. Do not seed **`M Appleby`** / **`J H M Gosden`** unless product intent is separate trainers. |

---

## Task 3 verification (2026-11-03, VPS)

- **Procedure:** `32226` races, `307609` runner rows (7-year flat window).
- **G1 sample:** Winners `lengths_to_winner = 0`; placings scale with cumulative margins (Futurity Trophy example looks consistent with `mordin_perf_mcr`).
- **Trainer group SR:** `Charlie Appleby` / `Owen Burrows` elite at realistic volume; small-sample foreign names at top of sort order is expected.
- **comp_level:** Levels 1–5 all present; largest bucket level 4 at ~40% (within plan “none above ~45%”).
- **Tier buckets:** `G1` (350) smallest pattern bucket; full ladder through `C67_BOTTOM`.
- **Follow-up — `no_tier` / `no_mcr` = 6305 (~19.6%):** Above master-plan 10% guideline. These rows have **`tier_code IS NULL`** (not `UNQ`), so no `mordin_class_matrix` join → no `race_mcr`. Likely NULL **`daily_races_beta.class`** plus no Group/Listed/sell match in title (see band-only handicap note in Discovery). Optional diagnostic on server:

```sql
SELECT COUNT(*) AS n,
  SUM(dracb.class IS NULL OR dracb.class = '') AS null_daily_class,
  SUM(mordin_tier_code(dracb.race_title, dracb.class, hracb.handicap) IS NULL) AS fn_null
FROM mordin_race_class mrc
JOIN daily_races_beta dracb ON dracb.race_id = mrc.race_id
JOIN historic_races_beta hracb ON hracb.race_id = mrc.race_id
WHERE mrc.tier_code IS NULL;
```

Improvement (if product wants fewer gaps): extend `mordin_tier_code` for NULL-class band handicaps, or Task 4+ rules for untiered races — not a blocker for deploying Task 4.

---

## Task 4 verification (2026-11-03, VPS)

- **Point-in-time:** `leaks = 0` (last 30 days vs prior `mordin_runner_perf` counts).
- **Flag volumes (all rows):** outclassed **64241**; outclassed_unknown **28863** (handicap 3yo+ with no season runs); juv_elim **285**; northern **42**; clear_2nd **326**; yar/sal pockets **162** / **33**; debut_pen **14540**; class_ok **53370**; elite_trainer **29076**.
- **Today’s card (2026-11-03):** Handicaps → **C67_BOTTOM** with **outclassed_handicapper** 0/1/**NULL** (NULL = no qualifying season activity); novice/maiden → **C4_LOWMID** with **debut_penalty** on first-time 2yos as expected. Titles align with tier codes on spot sample.

---

## Backtest verdicts (Task 10)

| Rule | Bets | Strike % | ROI % | Baseline ROI % | Verdict |
| --- | --- | --- | --- | --- | --- |
| *(paste after Task 10)* | | | | | |

---

## Calibration (Task 5)

**Deploy (VPS):** load `mordin_calibration_REPORT.sql`, then `CALL mordin_calibration_REPORT();` (or `mysql … < tests/mordin_calibration_VERIFY.sql` after the CALL). Expect **50–60** rows in `mordin_class_calibration`. Compare **gaps** between tiers in `avg_winner_sr` with gaps in `spec_mcr` (absolute levels differ from Mordin’s scale). If a lower tier beats a higher one at the same `comp_level` in **3+** adjacent tier steps, note it here; matrix edits are data-only (`mordin_class_matrix`).

*(Paste full `SELECT * FROM mordin_class_calibration …` output below after VPS run.)*
