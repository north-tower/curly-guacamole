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

## Task 6 verification (2026-11-03, VPS)

- Redeployed `RUNME_UpdateCall` from repo; `CALL RUNME_UpdateCall()` completed successfully.
- Status output included **`mordin_history_UPDATE completed at`** and **`mordin_runner_flags_UPDATE completed at`** (not skipped).
- Nightly chain now refreshes Mordin history + runner flags after daily card / optional sires job.

---

## Backtest verdicts (Task 10)

**Admin page (recommended):** log in as administrator → **`/mordin-backtest/`** → **Run all backtests** → copy NOTES table, download `.md` / `.json`, or paste JSON here in chat.

**CLI** (same engine):

```bash
cd /path/to/wordpress
wp eval-file wp-content/themes/curly-guacamole/tests/mordin-backtest-verdicts.php -- --out=task10-report.md
```

Requires `mordin_runner_flags` on the DB. Paste the generated markdown table below.

| Rule | Bets | Strike % | ROI % | Baseline ROI % | Verdict |
| --- | --- | --- | --- | --- | --- |
| *(paste after batch or manual UI runs)* | | | | | |

---

## Calibration (Task 5)

**Verified 2026-11-03 (VPS):** `CALL mordin_calibration_REPORT();` → **50** rows (`row_count_check = ok`). Window: 5 years, `tier_code <> 'UNQ'`, non-null `winner_speed_rating`. **No rows** for **`C2_HIGH`** or **`C34_MID`** (no qualifying races in window — matrix still has 60 seed rows).

### Observed vs spec (gap review)

- **Scale:** Site `avg_winner_sr` sits ~**68–90**; `spec_mcr` ~**59–90**. Rank order of tiers mostly matches Mordin at **comp_level 3** (main volume): G1 **87.4** → G2 **81.8** → G3 **80.2** → … → C67_BOTTOM **68.8**, while `spec_mcr` steps **88 → 87 → 86 → … → 67**.
- **Class band (comp 3):** C4_LOWMID **71.3** > C5_LOW **69.4** > C67_BOTTOM **68.8** — aligns with spec (**74 / 71 / 67**). Bottom-tier spec gaps are **wider** on MCR than on observed SR (~2–3 pts SR vs ~4–7 pts MCR).
- **Reversal — LISTED vs C2_OPEN:** At the same `comp_level`, **`C2_OPEN` winners rate higher than `LISTED`** on levels **1, 3, and 4** (e.g. comp 3: LISTED **77.5**, C2_OPEN **79.1**; comp 4: **78.8** vs **81.9**). **`C2_OPEN` sample is small** (9–87 races per cell). Not changing `mordin_class_matrix` in this build — optional follow-up if product wants Listed strictly above open Class 2 in SR space.
- **Noise:** G1 comp **5** only **7** races (**81.7** SR vs spec **90**); ignore for matrix tuning.
- **Matrix action:** None required for Tasks 6–10; tier separation for handicap/novice rules uses `comp_level` + flags, not winner SR calibration alone.

### Full table (`mordin_class_calibration`)

| tier_code | comp_level | races | avg_winner_sr | sd_winner_sr | spec_mcr |
| --- | ---: | ---: | ---: | ---: | ---: |
| G1 | 1 | 39 | 89.8 | 11.0 | 87.0 |
| G1 | 2 | 25 | 85.2 | 11.7 | 87.0 |
| G1 | 3 | 86 | 87.4 | 10.7 | 88.0 |
| G1 | 4 | 88 | 86.0 | 10.7 | 89.0 |
| G1 | 5 | 7 | 81.7 | 10.6 | 90.0 |
| G2 | 1 | 44 | 85.6 | 11.1 | 85.0 |
| G2 | 2 | 23 | 79.1 | 14.1 | 86.0 |
| G2 | 3 | 113 | 81.8 | 11.3 | 87.0 |
| G2 | 4 | 105 | 80.5 | 13.8 | 87.0 |
| G2 | 5 | 16 | 77.3 | 11.7 | 88.0 |
| G3 | 1 | 62 | 82.6 | 12.7 | 81.0 |
| G3 | 2 | 32 | 79.3 | 15.6 | 84.0 |
| G3 | 3 | 174 | 80.2 | 13.4 | 86.0 |
| G3 | 4 | 229 | 79.1 | 14.4 | 86.0 |
| G3 | 5 | 24 | 79.2 | 14.1 | 86.0 |
| LISTED | 1 | 107 | 79.4 | 17.2 | 80.0 |
| LISTED | 2 | 44 | 80.0 | 12.8 | 82.0 |
| LISTED | 3 | 232 | 77.5 | 12.3 | 85.0 |
| LISTED | 4 | 273 | 78.8 | 12.0 | 86.0 |
| LISTED | 5 | 32 | 76.7 | 9.9 | 86.0 |
| C2_OPEN | 1 | 9 | 80.4 | 10.1 | 80.0 |
| C2_OPEN | 2 | 11 | 85.5 | 8.9 | 82.0 |
| C2_OPEN | 3 | 73 | 79.1 | 9.2 | 85.0 |
| C2_OPEN | 4 | 87 | 81.9 | 12.4 | 86.0 |
| C2_OPEN | 5 | 40 | 78.7 | 15.7 | 86.0 |
| C2_MID | 1 | 190 | 73.8 | 12.2 | 77.0 |
| C2_MID | 2 | 84 | 76.0 | 13.0 | 79.0 |
| C2_MID | 3 | 505 | 75.4 | 12.0 | 81.0 |
| C2_MID | 4 | 598 | 74.5 | 12.6 | 82.0 |
| C2_MID | 5 | 122 | 76.2 | 10.1 | 84.0 |
| C3_MIDHIGH | 1 | 259 | 74.7 | 12.0 | 75.0 |
| C3_MIDHIGH | 2 | 80 | 75.0 | 11.0 | 77.0 |
| C3_MIDHIGH | 3 | 574 | 74.3 | 12.3 | 80.0 |
| C3_MIDHIGH | 4 | 643 | 73.6 | 12.1 | 81.0 |
| C3_MIDHIGH | 5 | 107 | 73.6 | 9.6 | 83.0 |
| C4_LOWMID | 1 | 796 | 71.2 | 11.6 | 70.0 |
| C4_LOWMID | 2 | 261 | 70.6 | 12.7 | 72.0 |
| C4_LOWMID | 3 | 1427 | 71.3 | 12.0 | 74.0 |
| C4_LOWMID | 4 | 1755 | 71.5 | 11.8 | 77.0 |
| C4_LOWMID | 5 | 195 | 72.9 | 10.9 | 79.0 |
| C5_LOW | 1 | 827 | 70.3 | 11.4 | 68.0 |
| C5_LOW | 2 | 301 | 69.7 | 12.2 | 70.0 |
| C5_LOW | 3 | 1683 | 69.4 | 11.5 | 71.0 |
| C5_LOW | 4 | 2054 | 69.5 | 11.3 | 75.0 |
| C5_LOW | 5 | 207 | 71.2 | 11.2 | 78.0 |
| C67_BOTTOM | 1 | 570 | 69.2 | 11.9 | 59.0 |
| C67_BOTTOM | 2 | 241 | 68.9 | 11.6 | 63.0 |
| C67_BOTTOM | 3 | 1417 | 68.8 | 11.2 | 67.0 |
| C67_BOTTOM | 4 | 1753 | 68.3 | 10.7 | 71.0 |
| C67_BOTTOM | 5 | 226 | 68.2 | 10.8 | 73.0 |
