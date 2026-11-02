# Mordin Class Analysis Module Implementation Plan

> **Build tracker (checkboxes):** [2026-11-01-mordin-class-module-BUILD.md](./2026-11-01-mordin-class-module-BUILD.md)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Classify every historic flat race with a Mordin Class Rating, compute per-runner Mordin flags (outclassed, juvenile low-tier, northern form, clear 2nd, debut pockets, stamina, trainer group strike rate) for today's cards and the last 5 years, and expose them on the racecard and as System Builder filters so each rule can be backtested before it is trusted.

**Architecture:** Pure classification rules live in MySQL stored functions (`mordin_functions.sql`) tested by an assertion script. Two stored procedures build history tables (`mordin_race_class`, `mordin_runner_perf`, `mordin_trainer_group_sr`) and point-in-time runner flags (`mordin_runner_flags`, incremental for history, rebuilt for today/advance). PHP (`inc/mordin-class.php`) reads the flags, adds the Dosage-based stamina rule (DI is only computed in PHP), renders racecard pills, and plugs into the existing System Builder filter pipeline.

**Tech Stack:** MySQL 8 / MariaDB 10.3+ stored procedures and functions, WordPress PHP (Bricks child theme), vanilla JS (`system-builder.js`).

## Global Constraints

- Flat races only: `race_type = 'Flat'` (turf and all-weather; AW is distinguished by `track_type`, not `race_type`).
- History window: 7 years (`DATE_SUB(CURDATE(), INTERVAL 7 YEAR)`); flagged backtest window: 1825 days (matches premium `fhor_sb_max_lookback_days()`).
- Tier codes and ranks (lower rank = higher class) are fixed: `G1`=1, `G2`=2, `G3`=3, `LISTED`=4, `C2_OPEN`=5, `C2_HIGH`=6, `C2_MID`=7, `C3_MIDHIGH`=8, `C34_MID`=9, `C4_LOWMID`=10, `C5_LOW`=11, `C67_BOTTOM`=12. `UNQ` = unquantifiable foreign form (rank NULL). "Class 1" in the spec = tier rank 1–4.
- BHA class numbers run backwards: "today's class or higher" is `past.class_num <= today.class_num`.
- Boolean flag columns are `TINYINT`: `1` = true, `0` = false/not applicable, `NULL` = unknown (only `outclassed_handicapper` and `meets_class_requirement` use NULL).
- Points-per-length scale: `1760 / distance_yards` (1 point = 1 length at a mile, per Mordin's "two points, or two lengths").
- Flags are **filters and badges, never hard deletions** of runners.
- **`backtest_cr_data` is legacy-only and must not be regenerated** — Mordin work must not depend on it; historic FSr backtests remain best-effort where legacy rows exist.
- SQL follows the repo's `*_UPDATE.sql` style (`DELIMITER $$`, one routine per file, status `SELECT` at the end). PHP follows `if (!function_exists(...))` guards.
- The working tree has unrelated uncommitted changes (`functions.php`, several `*_UPDATE.sql`). Every commit step stages only the files named in that step.

## Spec deviations (decided, apply throughout)

1. **Competitiveness level** is driven by the winning margin and field depth (`distance_beaten`), with the spec's keyword regex as a modifier (easy keywords force Level 1 only when the margin is ≥ 2L; hard-fought keywords lift to ≥ Level 4 only when the margin is < 2L). "clear" and "every chance" are dropped from the keyword lists because they appear in losers' comments.
2. **Easy Winner Override** is +1/+2/+3 by margin (< 3L / 3–5L / ≥ 5L) and only for Level 1 winners whose comment says "won easily" or "eased".
3. **Outclassed_Handicapper** is `NULL` (unknown) when the horse has no run yet this calendar year, instead of TRUE.
4. **Germany/Italy:** G1 → `G3`; G2/G3/Listed → `C2_HIGH` (below UK Listed). **USA:** G1 at parity; everything else → `UNQ` and counted in `us_lower_graded_runs` when it was G2/G3. **Rest of world:** `UNQ`, counted in `foreign_unquantified_runs`, never auto-eliminated.
5. **Novice races** are treated as straight maidens in the maiden tier ladder.
6. **`rolling_5yr_group_strike_rate`** is today's value (not point-in-time), so backtests of `group_trainer` carry mild look-ahead bias. `trainer_locations` is likewise current.
7. **Northern courses** seed list = spec's five plus Ayr, Beverley, Carlisle, Pontefract, Ripon, Thirsk (editable table).
8. **`lengths_to_winner`** reuses `distance_beaten` (already in lengths) rather than converting finish times.

## Stakeholder sign-off (2026-11-02)

**Reviewer:** non-technical lead (via Mike).

**Decision:** Approve all items in the executive “Decisions needed” brief — spec deviations, tier cut-offs, competitiveness bands, trainer/track seed lists, and the 10-point ROI bar for keeping racecard badges. Nothing to add or remove from those lists at this stage.

**Follow-up interest:** Reuse Francesca’s comment-scoring work (if it improves race competitiveness) rather than relying only on margin + keyword tweaks. Treat as a **post-MVP enhancement** after the baseline Mordin module ships and backtests cleanly (see “Related: Fhorsite comment ratings” below). Use the **regenerable** `separated_comments_win_value` path only — **not** `backtest_cr_data`.

**Reference material:** Mordin book chapter scans live in `books/` (JPEG photos). Use when validating tier gaps and “class vs speed” wording against the implementation.

## Related: Fhorsite comment ratings (Francesca’s pipeline)

This is **not** in the daily R speed-rating chain (`do.R` → `load.R` … `write.R`). FSr on today’s card comes from **MySQL**, rebuilt in `RUNME_UpdateCall.sql`:

| Step | File | What it does |
| --- | --- | --- |
| 1 | `separated_comments_UPDATE.sql` | Splits each `in_race_comment` into comma-separated phrases; stores phrase + **digit** (position in comment). |
| 2 | `separated_comment_count_UPDATE.sql` | Counts how often each (phrase, digit) appears in history. |
| 3 | `separated_comments_win_value_UPDATE.sql` | For phrases with ≥50 runs, stores **win_pct** when that phrase appears (winners only counted in numerator). |
| 4 | `daily_comment_ratings_UPDATE.sql` / `adv_daily_comment_ratings_UPDATE.sql` | Aggregates matched phrases into **`comment_rating`** (FSr) and **`comment_reliability_factor`** (FSRR) per runner. |
| 5 | `speed&performance_table_UPDATE.sql` | Exposes FSr/FSRR on the racecard as `fhorsite_rating` / `fhorsite_rating_reliability`. |

There is **no separate upload file** (CSV/Excel) or filename mentioning Francesca in this git repo — the “heavy system” is the **regenerable** SQL chain above (`separated_comments_*` → `daily_comment_ratings`). That produces **today’s** FSr on the racecard only; it is **not** stored per historic `(race_id, runner_id)` row.

**`backtest_cr_data` (cannot be regenerated):** Historic, point-in-time FSr for backtests and horse history joins this table (`functions.php`, `inc/system-builder.php`). There is **no** rebuild script in the repo, and **the team cannot regenerate it**. Treat whatever rows exist on the server as a **frozen legacy snapshot** (or empty). Do **not** plan features, backtests, or Mordin logic on repopulating or extending this table.

| Capability | Without regenerating `backtest_cr_data` |
| --- | --- |
| **Mordin class module (this plan)** | **Unaffected.** Uses `mordin_*` tables, margins, tiers, `mordin_runner_flags` — no FSr backtest table. |
| **Today’s card FSr** | **Fine.** `daily_comment_ratings` / advance equivalent still run nightly. |
| **System Builder historic FSr / FSr rank** | **Limited.** Works only for dates present in legacy `backtest_cr_data`; otherwise FSr is NULL and rank filters skip or warn (existing behaviour). |
| **Horse history FSr column** | **Same** — only where legacy rows exist. |
| **Optional Task 11 (phrase win_pct for competitiveness)** | **Still viable** — uses `separated_comments` + `separated_comments_win_value`, which **can** be rebuilt from `in_race_comment` via existing procedures. |

**Optional Task 11 (after Task 10):** For winner competitiveness, join the winner’s separated phrases to `separated_comments_win_value` and derive a `comp_comment_score` (e.g. average or min win_pct of matched phrases on digit 1–3). Compare Level 1–5 distribution vs margin-only `mordin_comp_level` on `mordin_calibration_REPORT`; adopt only if it improves tier separation without breaking the 10-point badge rules. **Do not** use `backtest_cr_data` for this.

## File map

| File | Responsibility |
| --- | --- |
| `mordin_functions.sql` (create) | Stored functions: class number, band, tier code/rank, country group, international tier, competitiveness level, easy bonus, performance MCR, maiden tier, 2yo-only, low-tier juvenile race |
| `tests/mordin_functions_TEST.sql` (create) | Assertion script for every function |
| `mordin_seed_tables.sql` (create) | `mordin_class_matrix` (spec matrix), `mordin_northern_courses`, `trainer_locations` |
| `mordin_history_UPDATE.sql` (create) | Builds `mordin_race_class`, `mordin_runner_perf`, `mordin_trainer_group_sr` |
| `mordin_runner_flags_UPDATE.sql` (create) | Builds/maintains `mordin_runner_flags` |
| `mordin_calibration_REPORT.sql` (create) | Compares spec MCR matrix with observed winner speed ratings |
| `RUNME_UpdateCall.sql` (modify) | Calls the two new procedures |
| `inc/mordin-class.php` (create) | Flag fetch, stamina rule, badges, System Builder row check |
| `tests/mordin-class-test.php` (create) | PHP unit checks for the pure functions |
| `functions.php` (modify) | Require module; build per-race lookup and per-runner badges |
| `inc/runner-card-wide.php` (modify) | Render Mordin pills |
| `race-detail-premium.css` (modify) | Pill tones |
| `inc/system-builder.php` (modify) | Sanitize `mordin` filter, join flags, apply rule, UI checkboxes |
| `system-builder.js` (modify) | Collect/restore `mordin[]` |
| `mordin_class_NOTES.md` (create) | Discovery results + backtest verdicts |

---

### Task 0: Data discovery and local tooling

**Files:**
- Create: `mordin_class_NOTES.md`

**Interfaces:**
- Produces: confirmed column names/value formats that Tasks 1–6 rely on.

- [ ] **Step 1: Install PHP locally for the unit tests (or plan to run them on the VPS)**

Run: `winget install --id PHP.PHP.8.3 -e`
Then open a new terminal and run: `php -v`
Expected: `PHP 8.3.x (cli)`

- [ ] **Step 2: Run the discovery queries on the server database (`fhorsitedb`)**

```sql
SELECT VERSION();
SHOW COLUMNS FROM historic_races_beta;
SHOW COLUMNS FROM historic_runners_beta;
SHOW COLUMNS FROM daily_races_beta;
SHOW COLUMNS FROM daily_runners_beta;
SHOW COLUMNS FROM advance_daily_races_beta;
SHOW COLUMNS FROM advance_daily_runners_beta;

SELECT class, COUNT(*) AS n FROM historic_races_beta
WHERE race_type = 'Flat' AND meeting_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
GROUP BY class ORDER BY n DESC;

SELECT COUNT(*) AS flat_races, SUM(dracb.race_id IS NOT NULL) AS with_daily_row
FROM historic_races_beta hracb
LEFT JOIN daily_races_beta dracb ON dracb.race_id = hracb.race_id
WHERE hracb.race_type = 'Flat' AND hracb.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 7 YEAR);

SELECT race_title FROM daily_races_beta
WHERE race_type = 'Flat' AND LOWER(race_title) REGEXP 'group|grade|listed' LIMIT 30;

SELECT race_title, class FROM daily_races_beta
WHERE race_type = 'Flat' AND handicap = 1 LIMIT 30;

SELECT country, COUNT(*) AS n FROM daily_races_beta GROUP BY country ORDER BY n DESC;

SELECT age_range, COUNT(*) AS n FROM daily_races_beta WHERE race_type = 'Flat' GROUP BY age_range ORDER BY n DESC;

SELECT hrunb.finish_position, hrunb.distance_beaten
FROM historic_runners_beta hrunb
WHERE hrunb.race_id = (SELECT MAX(race_id) FROM historic_races_beta WHERE race_type = 'Flat')
ORDER BY CAST(hrunb.finish_position AS UNSIGNED);

SELECT trainer_name, COUNT(*) AS n FROM historic_runners_beta
WHERE trainer_name LIKE '%Appleby%' OR trainer_name LIKE '%Gosden%' OR trainer_name LIKE '%Hills%'
GROUP BY trainer_name;
```

- [ ] **Step 3: Apply the decision rules and record results in `mordin_class_NOTES.md`**

Decision rules (apply before starting Task 1):
- `VERSION()` must be MySQL ≥ 8.0 or MariaDB ≥ 10.3 (window functions + `REGEXP_SUBSTR`). If not, stop and upgrade.
- `daily_runners_beta` must have `age` and `trainer_name`; `daily_races_beta` must have `race_title`, `country`, `prize_pos_1`, `age_range`, `class`, `handicap`, `distance_yards`, `meeting_date`. The `advance_*` tables must have the same columns. If an `advance_*` column is missing, delete the advance `INSERT` block from Task 4 Step 1.
- `distance_beaten` must increase down the finishing order (cumulative). If instead it is the gap to the horse in front, replace every `hrunb.distance_beaten` read in Task 3 with `SUM(hrunb.distance_beaten) OVER (PARTITION BY hrunb.race_id ORDER BY CAST(hrunb.finish_position AS UNSIGNED))` computed in a derived table.
- If `country` values include anything not matched by `mordin_country_group` in Task 1 (e.g. `GER`, `JPN`, `NIR`), add them to the matching regex in Task 1 Step 3 before running it.
- Rewrite the `trainer_locations` seed names in Task 2 Step 1 to the exact format returned by the trainer query (e.g. `C Appleby` vs `Charlie Appleby`).
- If `with_daily_row / flat_races < 0.9`, note it: races without a daily row fall back to class-only tiers (no Group/Listed detection).

Write `mordin_class_NOTES.md`:

```markdown
# Mordin Class Module — Notes

## Discovery (Task 0)
- DB version:
- class format (e.g. "Class 4" or "4"):
- daily_races_beta coverage of historic flat races: x / y
- Pattern title format (sample):
- Handicap band format in title (sample, or "none"):
- country values:
- age_range values:
- distance_beaten cumulative: yes/no
- trainer name format:

## Backtest verdicts (Task 10)
| Rule | Bets | Strike % | ROI % | Baseline ROI % | Verdict |
| --- | --- | --- | --- | --- | --- |
```

- [ ] **Step 4: Commit**

```bash
git add mordin_class_NOTES.md
git commit -m "docs: Mordin class module discovery notes"
```

---

### Task 1: Classification stored functions

**Files:**
- Create: `mordin_functions.sql`
- Test: `tests/mordin_functions_TEST.sql`

**Interfaces:**
- Produces (all `DETERMINISTIC NO SQL`):
  - `mordin_class_num(p_class VARCHAR(50)) RETURNS TINYINT`
  - `mordin_band_top(p_title VARCHAR(255)) RETURNS SMALLINT`
  - `mordin_tier_code(p_title VARCHAR(255), p_class VARCHAR(50), p_handicap TINYINT) RETURNS VARCHAR(12)`
  - `mordin_tier_rank(p_code VARCHAR(12)) RETURNS TINYINT`
  - `mordin_country_group(p_country VARCHAR(50)) RETURNS VARCHAR(8)` → `GB|IRE|FR|GER_ITY|USA|OTHER`
  - `mordin_intl_tier(p_code VARCHAR(12), p_country_group VARCHAR(8)) RETURNS VARCHAR(12)`
  - `mordin_comp_level(p_margin DECIMAL(6,2), p_within2 INT, p_comment TEXT) RETURNS TINYINT`
  - `mordin_easy_bonus(p_level TINYINT, p_margin DECIMAL(6,2), p_comment TEXT) RETURNS TINYINT`
  - `mordin_perf_mcr(p_race_mcr DECIMAL(5,1), p_bonus TINYINT, p_finish_pos INT, p_lengths DECIMAL(6,2), p_distance_yards INT) RETURNS DECIMAL(5,1)`
  - `mordin_maiden_tier(p_title VARCHAR(255), p_prize DECIMAL(12,2)) RETURNS TINYINT`
  - `mordin_is_2yo_only(p_age_range VARCHAR(20)) RETURNS TINYINT`
  - `mordin_is_low_tier_juv(p_title VARCHAR(255), p_handicap TINYINT, p_age_range VARCHAR(20)) RETURNS TINYINT`

- [ ] **Step 1: Write the failing test script**

Create `tests/mordin_functions_TEST.sql`:

```sql
-- Mordin stored-function checks. Run against fhorsitedb after deploying mordin_functions.sql.
-- Expected: final row shows failures = 0.
DROP TEMPORARY TABLE IF EXISTS tmp_mordin_fn_test;
CREATE TEMPORARY TABLE tmp_mordin_fn_test AS
SELECT label, expected, actual, IF(expected <=> actual, 'ok', 'FAIL') AS result FROM (
    SELECT 'class_num Class 4' AS label, '4' AS expected, CAST(mordin_class_num('Class 4') AS CHAR) AS actual
    UNION ALL SELECT 'class_num bare 2', '2', CAST(mordin_class_num('2') AS CHAR)
    UNION ALL SELECT 'class_num empty', NULL, CAST(mordin_class_num('') AS CHAR)
    UNION ALL SELECT 'band_top 0-105', '105', CAST(mordin_band_top('Sky Bet Handicap (0-105, 3yo+)') AS CHAR)
    UNION ALL SELECT 'band_top 46-60', '60', CAST(mordin_band_top('Apprentice Handicap (46-60)') AS CHAR)
    UNION ALL SELECT 'band_top none', NULL, CAST(mordin_band_top('Maiden Stakes') AS CHAR)
    UNION ALL SELECT 'tier G1', 'G1', mordin_tier_code('Juddmonte International Stakes (Group 1)', 'Class 1', 0)
    UNION ALL SELECT 'tier G2', 'G2', mordin_tier_code('Lennox Stakes (Group 2)', 'Class 1', 0)
    UNION ALL SELECT 'tier G3 short', 'G3', mordin_tier_code('Chester Vase (G3)', 'Class 1', 0)
    UNION ALL SELECT 'tier Listed', 'LISTED', mordin_tier_code('Hyperion Stakes (Listed)', 'Class 1', 0)
    UNION ALL SELECT 'tier heritage', 'C2_OPEN', mordin_tier_code('Royal Hunt Cup (Heritage Handicap)', 'Class 2', 1)
    UNION ALL SELECT 'tier C2 0-110', 'C2_HIGH', mordin_tier_code('Handicap (0-110)', 'Class 2', 1)
    UNION ALL SELECT 'tier C2 0-100', 'C2_MID', mordin_tier_code('Handicap (0-100)', 'Class 2', 1)
    UNION ALL SELECT 'tier C3 0-95', 'C3_MIDHIGH', mordin_tier_code('Handicap (0-95)', 'Class 3', 1)
    UNION ALL SELECT 'tier C3 0-90', 'C34_MID', mordin_tier_code('Handicap (0-90)', 'Class 3', 1)
    UNION ALL SELECT 'tier C3 no band', 'C3_MIDHIGH', mordin_tier_code('Conditions Stakes', 'Class 3', 0)
    UNION ALL SELECT 'tier C4 0-80', 'C4_LOWMID', mordin_tier_code('Handicap (0-80)', 'Class 4', 1)
    UNION ALL SELECT 'tier C5', 'C5_LOW', mordin_tier_code('Handicap (0-70)', 'Class 5', 1)
    UNION ALL SELECT 'tier seller', 'C67_BOTTOM', mordin_tier_code('Selling Stakes', 'Class 5', 0)
    UNION ALL SELECT 'tier C6', 'C67_BOTTOM', mordin_tier_code('Handicap (0-60)', 'Class 6', 1)
    UNION ALL SELECT 'tier unknown', NULL, mordin_tier_code('Prix Something', '', 0)
    UNION ALL SELECT 'rank G3', '3', CAST(mordin_tier_rank('G3') AS CHAR)
    UNION ALL SELECT 'rank bottom', '12', CAST(mordin_tier_rank('C67_BOTTOM') AS CHAR)
    UNION ALL SELECT 'rank UNQ', NULL, CAST(mordin_tier_rank('UNQ') AS CHAR)
    UNION ALL SELECT 'country France', 'FR', mordin_country_group('France')
    UNION ALL SELECT 'country IRE', 'IRE', mordin_country_group('IRE')
    UNION ALL SELECT 'country blank', 'GB', mordin_country_group('')
    UNION ALL SELECT 'country Scotland', 'GB', mordin_country_group('Scotland')
    UNION ALL SELECT 'country Germany', 'GER_ITY', mordin_country_group('Germany')
    UNION ALL SELECT 'country USA', 'USA', mordin_country_group('USA')
    UNION ALL SELECT 'country Japan', 'OTHER', mordin_country_group('Japan')
    UNION ALL SELECT 'intl GER G1', 'G3', mordin_intl_tier('G1', 'GER_ITY')
    UNION ALL SELECT 'intl ITY Listed', 'C2_HIGH', mordin_intl_tier('LISTED', 'GER_ITY')
    UNION ALL SELECT 'intl FR G1', 'G1', mordin_intl_tier('G1', 'FR')
    UNION ALL SELECT 'intl USA G1', 'G1', mordin_intl_tier('G1', 'USA')
    UNION ALL SELECT 'intl USA G2', 'UNQ', mordin_intl_tier('G2', 'USA')
    UNION ALL SELECT 'intl other G1', 'UNQ', mordin_intl_tier('G1', 'OTHER')
    UNION ALL SELECT 'intl GB null', NULL, mordin_intl_tier(NULL, 'GB')
    UNION ALL SELECT 'level 6L made all', '1', CAST(mordin_comp_level(6, 0, 'made all, clear 2f out') AS CHAR)
    UNION ALL SELECT 'level 3.5L', '2', CAST(mordin_comp_level(3.5, 0, 'led over 1f out, ridden out') AS CHAR)
    UNION ALL SELECT 'level 1.5L', '3', CAST(mordin_comp_level(1.5, 1, '') AS CHAR)
    UNION ALL SELECT 'level tight 4 within 2L', '5', CAST(mordin_comp_level(0.5, 4, '') AS CHAR)
    UNION ALL SELECT 'level tight', '4', CAST(mordin_comp_level(0.5, 1, '') AS CHAR)
    UNION ALL SELECT 'level hard kw lifts', '4', CAST(mordin_comp_level(1.5, 1, 'driven out to lead final strides') AS CHAR)
    UNION ALL SELECT 'level easy kw', '1', CAST(mordin_comp_level(2.5, 1, 'won easily') AS CHAR)
    UNION ALL SELECT 'level easy kw small margin ignored', '4', CAST(mordin_comp_level(0.5, 1, 'won easily') AS CHAR)
    UNION ALL SELECT 'level no margin', '3', CAST(mordin_comp_level(NULL, 0, '') AS CHAR)
    UNION ALL SELECT 'bonus 6L eased', '3', CAST(mordin_easy_bonus(1, 6, 'eased down') AS CHAR)
    UNION ALL SELECT 'bonus 3.5L', '2', CAST(mordin_easy_bonus(1, 3.5, 'won easily') AS CHAR)
    UNION ALL SELECT 'bonus 2.5L', '1', CAST(mordin_easy_bonus(1, 2.5, 'won easily') AS CHAR)
    UNION ALL SELECT 'bonus no kw', '0', CAST(mordin_easy_bonus(1, 6, 'made all') AS CHAR)
    UNION ALL SELECT 'bonus not level 1', '0', CAST(mordin_easy_bonus(3, 1.5, 'won easily') AS CHAR)
    UNION ALL SELECT 'perf winner', '87.0', CAST(mordin_perf_mcr(85.0, 2, 1, 0, 1760) AS CHAR)
    UNION ALL SELECT 'perf 2L at 1m', '83.0', CAST(mordin_perf_mcr(85.0, 0, 3, 2, 1760) AS CHAR)
    UNION ALL SELECT 'perf 2L at 5f', '81.8', CAST(mordin_perf_mcr(85.0, 0, 3, 2, 1100) AS CHAR)
    UNION ALL SELECT 'perf capped 20L', '65.0', CAST(mordin_perf_mcr(85.0, 0, 9, 30, 1760) AS CHAR)
    UNION ALL SELECT 'perf no race mcr', NULL, CAST(mordin_perf_mcr(NULL, 0, 1, 0, 1760) AS CHAR)
    UNION ALL SELECT 'maiden rich', '1', CAST(mordin_maiden_tier('Maiden Stakes', 12000) AS CHAR)
    UNION ALL SELECT 'maiden poor', '2', CAST(mordin_maiden_tier('Maiden Stakes', 5000) AS CHAR)
    UNION ALL SELECT 'novice rich', '1', CAST(mordin_maiden_tier('EBF Novice Stakes', 15000) AS CHAR)
    UNION ALL SELECT 'maiden auction', '2', CAST(mordin_maiden_tier('Maiden Auction Stakes', 20000) AS CHAR)
    UNION ALL SELECT 'maiden claimer', '3', CAST(mordin_maiden_tier('Maiden Claiming Stakes', 4000) AS CHAR)
    UNION ALL SELECT 'maiden seller', '4', CAST(mordin_maiden_tier('Maiden Selling Stakes', 3000) AS CHAR)
    UNION ALL SELECT 'not maiden', NULL, CAST(mordin_maiden_tier('Handicap (0-70)', 5000) AS CHAR)
    UNION ALL SELECT '2yo only', '1', CAST(mordin_is_2yo_only('2yo') AS CHAR)
    UNION ALL SELECT '2yo plus', '0', CAST(mordin_is_2yo_only('2yo+') AS CHAR)
    UNION ALL SELECT '3yo plus', '0', CAST(mordin_is_2yo_only('3yo+') AS CHAR)
    UNION ALL SELECT 'juv nursery', '1', CAST(mordin_is_low_tier_juv('Nursery Handicap', 1, '2yo') AS CHAR)
    UNION ALL SELECT 'juv 2yo handicap', '1', CAST(mordin_is_low_tier_juv('Handicap (0-75)', 1, '2yo') AS CHAR)
    UNION ALL SELECT 'juv novice', '0', CAST(mordin_is_low_tier_juv('Novice Stakes', 0, '2yo') AS CHAR)
    UNION ALL SELECT 'juv claimer', '1', CAST(mordin_is_low_tier_juv('Claiming Stakes', 0, '3yo+') AS CHAR)
) t;

SELECT * FROM tmp_mordin_fn_test WHERE result = 'FAIL';
SELECT COUNT(*) AS checks, SUM(result = 'FAIL') AS failures FROM tmp_mordin_fn_test;
```

- [ ] **Step 2: Run it to make sure it fails**

Run the script in your MySQL client against `fhorsitedb`.
Expected: error `FUNCTION fhorsitedb.mordin_class_num does not exist`.

- [ ] **Step 3: Write the functions**

Create `mordin_functions.sql`:

```sql
DELIMITER $$

DROP FUNCTION IF EXISTS `mordin_class_num`$$
CREATE FUNCTION `mordin_class_num`(p_class VARCHAR(50)) RETURNS TINYINT
DETERMINISTIC NO SQL
BEGIN
    DECLARE v VARCHAR(5);
    SET v = REGEXP_SUBSTR(IFNULL(p_class, ''), '[1-7]');
    RETURN IF(v IS NULL OR v = '', NULL, CAST(v AS UNSIGNED));
END$$

DROP FUNCTION IF EXISTS `mordin_band_top`$$
CREATE FUNCTION `mordin_band_top`(p_title VARCHAR(255)) RETURNS SMALLINT
DETERMINISTIC NO SQL
BEGIN
    DECLARE v VARCHAR(10);
    SET v = REGEXP_SUBSTR(IFNULL(p_title, ''), '[0-9]{1,3}-[0-9]{2,3}');
    IF v IS NULL OR v = '' THEN
        RETURN NULL;
    END IF;
    RETURN CAST(SUBSTRING_INDEX(v, '-', -1) AS UNSIGNED);
END$$

DROP FUNCTION IF EXISTS `mordin_tier_code`$$
CREATE FUNCTION `mordin_tier_code`(p_title VARCHAR(255), p_class VARCHAR(50), p_handicap TINYINT) RETURNS VARCHAR(12)
DETERMINISTIC NO SQL
BEGIN
    DECLARE t VARCHAR(255) DEFAULT LOWER(IFNULL(p_title, ''));
    DECLARE c TINYINT DEFAULT mordin_class_num(p_class);
    DECLARE b SMALLINT DEFAULT mordin_band_top(p_title);

    IF t REGEXP 'group[[:space:]]*1([^0-9]|$)|grade[[:space:]]*1([^0-9]|$)|\\(g1\\)' THEN RETURN 'G1'; END IF;
    IF t REGEXP 'group[[:space:]]*2([^0-9]|$)|grade[[:space:]]*2([^0-9]|$)|\\(g2\\)' THEN RETURN 'G2'; END IF;
    IF t REGEXP 'group[[:space:]]*3([^0-9]|$)|grade[[:space:]]*3([^0-9]|$)|\\(g3\\)' THEN RETURN 'G3'; END IF;
    IF t REGEXP 'listed|\\(l\\)' THEN RETURN 'LISTED'; END IF;
    IF t REGEXP 'sell' THEN RETURN 'C67_BOTTOM'; END IF;
    IF c = 1 OR (c = 2 AND IFNULL(p_handicap, 0) = 1 AND (t REGEXP 'heritage' OR IFNULL(b, 0) >= 111)) THEN
        RETURN 'C2_OPEN';
    END IF;
    IF c = 2 THEN RETURN IF(IFNULL(b, 0) >= 106, 'C2_HIGH', 'C2_MID'); END IF;
    IF c = 3 THEN RETURN IF(b IS NOT NULL AND b <= 90, 'C34_MID', 'C3_MIDHIGH'); END IF;
    IF c = 4 THEN RETURN IF(IFNULL(b, 0) >= 86, 'C34_MID', 'C4_LOWMID'); END IF;
    IF c = 5 THEN RETURN 'C5_LOW'; END IF;
    IF c IN (6, 7) THEN RETURN 'C67_BOTTOM'; END IF;
    RETURN NULL;
END$$

DROP FUNCTION IF EXISTS `mordin_tier_rank`$$
CREATE FUNCTION `mordin_tier_rank`(p_code VARCHAR(12)) RETURNS TINYINT
DETERMINISTIC NO SQL
RETURN CASE p_code
    WHEN 'G1' THEN 1 WHEN 'G2' THEN 2 WHEN 'G3' THEN 3 WHEN 'LISTED' THEN 4
    WHEN 'C2_OPEN' THEN 5 WHEN 'C2_HIGH' THEN 6 WHEN 'C2_MID' THEN 7
    WHEN 'C3_MIDHIGH' THEN 8 WHEN 'C34_MID' THEN 9 WHEN 'C4_LOWMID' THEN 10
    WHEN 'C5_LOW' THEN 11 WHEN 'C67_BOTTOM' THEN 12
    ELSE NULL
END$$

DROP FUNCTION IF EXISTS `mordin_country_group`$$
CREATE FUNCTION `mordin_country_group`(p_country VARCHAR(50)) RETURNS VARCHAR(8)
DETERMINISTIC NO SQL
BEGIN
    DECLARE c VARCHAR(50) DEFAULT LOWER(TRIM(IFNULL(p_country, '')));
    IF c = '' OR c REGEXP '^(gb|gbr|uk|eng|england|sco|scotland|wal|wales|great britain|united kingdom)$' THEN RETURN 'GB'; END IF;
    IF c REGEXP '^(ire|irl|ireland|eire)$' THEN RETURN 'IRE'; END IF;
    IF c REGEXP '^(fr|fra|france)$' THEN RETURN 'FR'; END IF;
    IF c REGEXP '^(ger|deu|germany|ity|ita|it|italy)$' THEN RETURN 'GER_ITY'; END IF;
    IF c REGEXP '^(usa|us|united states)$' THEN RETURN 'USA'; END IF;
    RETURN 'OTHER';
END$$

DROP FUNCTION IF EXISTS `mordin_intl_tier`$$
CREATE FUNCTION `mordin_intl_tier`(p_code VARCHAR(12), p_country_group VARCHAR(8)) RETURNS VARCHAR(12)
DETERMINISTIC NO SQL
BEGIN
    IF p_country_group = 'OTHER' THEN RETURN 'UNQ'; END IF;
    IF p_country_group = 'USA' THEN RETURN IF(p_code = 'G1', 'G1', 'UNQ'); END IF;
    IF p_country_group = 'GER_ITY' THEN
        RETURN CASE p_code
            WHEN 'G1' THEN 'G3'
            WHEN 'G2' THEN 'C2_HIGH'
            WHEN 'G3' THEN 'C2_HIGH'
            WHEN 'LISTED' THEN 'C2_HIGH'
            ELSE p_code
        END;
    END IF;
    RETURN p_code;
END$$

DROP FUNCTION IF EXISTS `mordin_comp_level`$$
CREATE FUNCTION `mordin_comp_level`(p_margin DECIMAL(6,2), p_within2 INT, p_comment TEXT) RETURNS TINYINT
DETERMINISTIC NO SQL
BEGIN
    DECLARE c TEXT DEFAULT LOWER(IFNULL(p_comment, ''));
    DECLARE lvl TINYINT;
    IF p_margin IS NULL THEN
        RETURN 3;
    END IF;
    SET lvl = CASE
        WHEN p_margin >= 5 THEN 1
        WHEN p_margin >= 3 THEN 2
        WHEN p_margin >= 1 THEN 3
        WHEN IFNULL(p_within2, 0) >= 4 THEN 5
        ELSE 4
    END;
    IF p_margin >= 2 AND c REGEXP 'won easily|eased down|eased (near|close to|towards) finish|unextended|comfortabl|readily|cosily|made all|shaken up to lead' THEN
        SET lvl = 1;
    END IF;
    IF p_margin < 2 AND c REGEXP 'driven out|gamely|all out|hard pressed|sustained (duel|challenge)|hard ridden|just held on|on the nod' THEN
        SET lvl = GREATEST(lvl, 4);
    END IF;
    RETURN lvl;
END$$

DROP FUNCTION IF EXISTS `mordin_easy_bonus`$$
CREATE FUNCTION `mordin_easy_bonus`(p_level TINYINT, p_margin DECIMAL(6,2), p_comment TEXT) RETURNS TINYINT
DETERMINISTIC NO SQL
BEGIN
    IF IFNULL(p_level, 0) <> 1 OR p_margin IS NULL
       OR LOWER(IFNULL(p_comment, '')) NOT REGEXP 'won easily|eased down|eased (near|close to|towards) finish' THEN
        RETURN 0;
    END IF;
    RETURN CASE WHEN p_margin >= 5 THEN 3 WHEN p_margin >= 3 THEN 2 ELSE 1 END;
END$$

DROP FUNCTION IF EXISTS `mordin_perf_mcr`$$
CREATE FUNCTION `mordin_perf_mcr`(p_race_mcr DECIMAL(5,1), p_bonus TINYINT, p_finish_pos INT, p_lengths DECIMAL(6,2), p_distance_yards INT) RETURNS DECIMAL(5,1)
DETERMINISTIC NO SQL
BEGIN
    IF p_race_mcr IS NULL OR p_finish_pos IS NULL OR IFNULL(p_distance_yards, 0) <= 0 THEN
        RETURN NULL;
    END IF;
    IF p_finish_pos = 1 THEN
        RETURN p_race_mcr + IFNULL(p_bonus, 0);
    END IF;
    RETURN ROUND(p_race_mcr - LEAST(IFNULL(p_lengths, 20), 20) * 1760 / p_distance_yards, 1);
END$$

DROP FUNCTION IF EXISTS `mordin_maiden_tier`$$
CREATE FUNCTION `mordin_maiden_tier`(p_title VARCHAR(255), p_prize DECIMAL(12,2)) RETURNS TINYINT
DETERMINISTIC NO SQL
BEGIN
    DECLARE t VARCHAR(255) DEFAULT LOWER(IFNULL(p_title, ''));
    IF t NOT REGEXP 'maiden|novice' THEN RETURN NULL; END IF;
    IF t REGEXP 'sell' THEN RETURN 4; END IF;
    IF t REGEXP 'claim' THEN RETURN 3; END IF;
    IF t REGEXP 'auction|median' THEN RETURN 2; END IF;
    RETURN IF(IFNULL(p_prize, 0) >= 10000, 1, 2);
END$$

DROP FUNCTION IF EXISTS `mordin_is_2yo_only`$$
CREATE FUNCTION `mordin_is_2yo_only`(p_age_range VARCHAR(20)) RETURNS TINYINT
DETERMINISTIC NO SQL
RETURN IF(LOWER(TRIM(IFNULL(p_age_range, ''))) REGEXP '^2[^0-9+-]*$', 1, 0)$$

DROP FUNCTION IF EXISTS `mordin_is_low_tier_juv`$$
CREATE FUNCTION `mordin_is_low_tier_juv`(p_title VARCHAR(255), p_handicap TINYINT, p_age_range VARCHAR(20)) RETURNS TINYINT
DETERMINISTIC NO SQL
RETURN IF(
    LOWER(IFNULL(p_title, '')) REGEXP 'nursery|claim|sell'
    OR (IFNULL(p_handicap, 0) = 1 AND mordin_is_2yo_only(p_age_range) = 1),
    1, 0
)$$

DELIMITER ;
```

If deployment fails with error 1418 (binary logging), run `SET GLOBAL log_bin_trust_function_creators = 1;` as an admin user, then redeploy.

- [ ] **Step 4: Deploy the functions, then rerun the test script**

Run `mordin_functions.sql`, then `tests/mordin_functions_TEST.sql`.
Expected: the `WHERE result = 'FAIL'` query returns no rows; final row `checks = 71, failures = 0`.

- [ ] **Step 5: Commit**

```bash
git add mordin_functions.sql tests/mordin_functions_TEST.sql
git commit -m "feat: Mordin class stored functions with SQL checks"
```

---

### Task 2: Seed tables (class matrix, northern courses, trainer locations)

**Files:**
- Create: `mordin_seed_tables.sql`

**Interfaces:**
- Produces tables:
  - `mordin_class_matrix(tier_code VARCHAR(12), comp_level TINYINT, mcr DECIMAL(5,1))`, PK `(tier_code, comp_level)`
  - `mordin_northern_courses(course_key VARCHAR(50) PK)` — lowercase course-name prefixes
  - `trainer_locations(trainer_name VARCHAR(100) PK, location VARCHAR(30))` — `location` ∈ `Newmarket|Lambourn`

- [ ] **Step 1: Write the seed script**

Create `mordin_seed_tables.sql` (re-runnable; `REPLACE`/`INSERT IGNORE` keep manual edits to other rows):

```sql
CREATE TABLE IF NOT EXISTS mordin_class_matrix (
    tier_code VARCHAR(12) NOT NULL,
    comp_level TINYINT NOT NULL,
    mcr DECIMAL(5,1) NOT NULL,
    PRIMARY KEY (tier_code, comp_level)
);

REPLACE INTO mordin_class_matrix (tier_code, comp_level, mcr) VALUES
('G1',1,87),('G1',2,87),('G1',3,88),('G1',4,89),('G1',5,90),
('G2',1,85),('G2',2,86),('G2',3,87),('G2',4,87),('G2',5,88),
('G3',1,81),('G3',2,84),('G3',3,86),('G3',4,86),('G3',5,86),
('LISTED',1,80),('LISTED',2,82),('LISTED',3,85),('LISTED',4,86),('LISTED',5,86),
('C2_OPEN',1,80),('C2_OPEN',2,82),('C2_OPEN',3,85),('C2_OPEN',4,86),('C2_OPEN',5,86),
('C2_HIGH',1,78),('C2_HIGH',2,80),('C2_HIGH',3,82),('C2_HIGH',4,84),('C2_HIGH',5,85),
('C2_MID',1,77),('C2_MID',2,79),('C2_MID',3,81),('C2_MID',4,82),('C2_MID',5,84),
('C3_MIDHIGH',1,75),('C3_MIDHIGH',2,77),('C3_MIDHIGH',3,80),('C3_MIDHIGH',4,81),('C3_MIDHIGH',5,83),
('C34_MID',1,73),('C34_MID',2,74),('C34_MID',3,76),('C34_MID',4,79),('C34_MID',5,81),
('C4_LOWMID',1,70),('C4_LOWMID',2,72),('C4_LOWMID',3,74),('C4_LOWMID',4,77),('C4_LOWMID',5,79),
('C5_LOW',1,68),('C5_LOW',2,70),('C5_LOW',3,71),('C5_LOW',4,75),('C5_LOW',5,78),
('C67_BOTTOM',1,59),('C67_BOTTOM',2,63),('C67_BOTTOM',3,67),('C67_BOTTOM',4,71),('C67_BOTTOM',5,73);

CREATE TABLE IF NOT EXISTS mordin_northern_courses (
    course_key VARCHAR(50) NOT NULL PRIMARY KEY
);

INSERT IGNORE INTO mordin_northern_courses (course_key) VALUES
('newcastle'),('catterick'),('redcar'),('musselburgh'),('hamilton'),
('ayr'),('beverley'),('carlisle'),('pontefract'),('ripon'),('thirsk');

CREATE TABLE IF NOT EXISTS trainer_locations (
    trainer_name VARCHAR(100) NOT NULL PRIMARY KEY,
    location VARCHAR(30) NOT NULL
);

-- Names must match historic_runners_beta.trainer_name exactly (see mordin_class_NOTES.md).
INSERT IGNORE INTO trainer_locations (trainer_name, location) VALUES
('Charlie Appleby','Newmarket'),('John & Thady Gosden','Newmarket'),('William Haggas','Newmarket'),
('Roger Varian','Newmarket'),('Sir Michael Stoute','Newmarket'),('James Fanshawe','Newmarket'),
('George Boughey','Newmarket'),('David Simcock','Newmarket'),('Simon & Ed Crisford','Newmarket'),
('Charlie Fellowes','Newmarket'),('Michael Bell','Newmarket'),('Marco Botti','Newmarket'),
('Sir Mark Prescott Bt','Newmarket'),('Kevin Philippart De Foy','Newmarket'),('Jane Chapple-Hyam','Newmarket'),
('Harry Eustace','Newmarket'),
('Charlie Hills','Lambourn'),('Clive Cox','Lambourn'),('Archie Watson','Lambourn'),
('Jamie Osborne','Lambourn'),('Ed Walker','Lambourn'),('Joseph Tuite','Lambourn');
```

- [ ] **Step 2: Run it and verify**

Run `mordin_seed_tables.sql`, then:

```sql
SELECT COUNT(*) FROM mordin_class_matrix;
SELECT COUNT(*) FROM trainer_locations tl
JOIN (SELECT DISTINCT trainer_name FROM historic_runners_beta) h ON h.trainer_name = tl.trainer_name;
```

Expected: `60`; second count equals the number of seeded trainers (22). If lower, fix names to the format recorded in `mordin_class_NOTES.md` and rerun.

- [ ] **Step 3: Commit**

```bash
git add mordin_seed_tables.sql
git commit -m "feat: Mordin class matrix, northern courses and trainer location seeds"
```

---

### Task 3: History procedure (race class, runner performances, trainer group SR)

**Files:**
- Create: `mordin_history_UPDATE.sql`

**Interfaces:**
- Consumes: all Task 1 functions; `mordin_class_matrix`.
- Produces tables (rebuilt each run):
  - `mordin_race_class` — one row per flat race in 7 years: `race_id` PK, `meeting_date`, `course`, `country_group`, `race_title`, `class_num`, `handicap`, `age_range`, `distance_yards`, `prize_pos_1`, `tier_code`, `tier_rank`, `foreign_unquantified`, `us_lower_graded`, `is_2yo_only`, `is_low_tier_juv`, `maiden_tier`, `winner_runner_id`, `winning_margin`, `runners_within_2l`, `lengths_2nd_to_3rd`, `comp_level`, `easy_bonus`, `race_mcr`, `winner_speed_rating`.
  - `mordin_runner_perf` — one row per run: PK `(runner_id, race_id)`, `meeting_date`, `course`, `trainer_name`, `class_num`, `handicap`, `distance_yards`, `tier_code`, `tier_rank`, `foreign_unquantified`, `us_lower_graded`, `is_low_tier_juv`, `maiden_tier`, `finish_pos`, `lengths_to_winner`, `lengths_2nd_to_3rd`, `mordin_class_rating`.
  - `mordin_trainer_group_sr` — `trainer_name` PK, `group_runs`, `group_wins`, `rolling_5yr_group_strike_rate`, `is_group_elite`.

- [ ] **Step 1: Write the verification queries (they fail until the procedure exists)**

```sql
SELECT COUNT(*) AS races, SUM(tier_code IS NULL) AS no_tier, SUM(race_mcr IS NULL) AS no_mcr
FROM mordin_race_class;

SELECT comp_level, COUNT(*) AS n FROM mordin_race_class GROUP BY comp_level ORDER BY comp_level;

SELECT tier_code, COUNT(*) AS n FROM mordin_race_class GROUP BY tier_code ORDER BY mordin_tier_rank(tier_code);

SELECT r.race_title, r.tier_code, r.comp_level, r.race_mcr, p.finish_pos, p.lengths_to_winner, p.mordin_class_rating
FROM mordin_race_class r JOIN mordin_runner_perf p ON p.race_id = r.race_id
WHERE r.tier_code = 'G1' ORDER BY r.meeting_date DESC, p.finish_pos LIMIT 10;

SELECT * FROM mordin_trainer_group_sr ORDER BY rolling_5yr_group_strike_rate DESC LIMIT 10;
```

Run them. Expected: `Table 'fhorsitedb.mordin_race_class' doesn't exist`.

- [ ] **Step 2: Write the procedure**

Create `mordin_history_UPDATE.sql`:

```sql
DELIMITER $$
DROP PROCEDURE IF EXISTS `mordin_history_UPDATE`$$
CREATE PROCEDURE `mordin_history_UPDATE`()
BEGIN
    DECLARE v_from DATE DEFAULT DATE_SUB(CURDATE(), INTERVAL 7 YEAR);

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_finish;
    CREATE TEMPORARY TABLE tmp_mordin_finish (PRIMARY KEY (race_id)) AS
    SELECT
        hrunb.race_id,
        MAX(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) = 1 THEN hrunb.runner_id END) AS winner_runner_id,
        MAX(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) = 1 THEN hrunb.in_race_comment END) AS winner_comment,
        MIN(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) = 2 THEN hrunb.distance_beaten END) AS db_2nd,
        MIN(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) = 3 THEN hrunb.distance_beaten END) AS db_3rd,
        SUM(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) > 1 AND hrunb.distance_beaten <= 2 THEN 1 ELSE 0 END) AS within_2l
    FROM historic_runners_beta hrunb
    JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
    WHERE hracb.meeting_date >= v_from
      AND hracb.race_type = 'Flat'
      AND hrunb.finish_position REGEXP '^[0-9]+$'
    GROUP BY hrunb.race_id;

    DROP TABLE IF EXISTS mordin_race_class;
    CREATE TABLE mordin_race_class (
        race_id INT NOT NULL PRIMARY KEY,
        meeting_date DATE,
        course VARCHAR(50),
        country_group VARCHAR(8),
        race_title VARCHAR(255),
        class_num TINYINT,
        handicap TINYINT,
        age_range VARCHAR(20),
        distance_yards INT,
        prize_pos_1 DECIMAL(12,2),
        tier_code VARCHAR(12),
        tier_rank TINYINT,
        foreign_unquantified TINYINT NOT NULL DEFAULT 0,
        us_lower_graded TINYINT NOT NULL DEFAULT 0,
        is_2yo_only TINYINT NOT NULL DEFAULT 0,
        is_low_tier_juv TINYINT NOT NULL DEFAULT 0,
        maiden_tier TINYINT,
        winner_runner_id INT,
        winning_margin DECIMAL(6,2),
        runners_within_2l INT,
        lengths_2nd_to_3rd DECIMAL(6,2),
        comp_level TINYINT,
        easy_bonus TINYINT NOT NULL DEFAULT 0,
        race_mcr DECIMAL(5,1),
        winner_speed_rating INT,
        INDEX idx_mrc_date (meeting_date),
        INDEX idx_mrc_tier (tier_code, comp_level)
    );

    INSERT INTO mordin_race_class (
        race_id, meeting_date, course, country_group, race_title, class_num, handicap, age_range,
        distance_yards, prize_pos_1, tier_code, tier_rank, foreign_unquantified, us_lower_graded,
        is_2yo_only, is_low_tier_juv, maiden_tier, winner_runner_id, winning_margin, runners_within_2l,
        lengths_2nd_to_3rd, comp_level, easy_bonus, race_mcr, winner_speed_rating
    )
    SELECT
        c.race_id, c.meeting_date, c.course, c.country_group, c.race_title, c.class_num, c.handicap, c.age_range,
        c.distance_yards, c.prize_pos_1, c.tier_code, mordin_tier_rank(c.tier_code),
        IF(c.tier_code = 'UNQ', 1, 0),
        IF(c.country_group = 'USA' AND c.raw_tier IN ('G2', 'G3'), 1, 0),
        mordin_is_2yo_only(c.age_range),
        mordin_is_low_tier_juv(c.race_title, c.handicap, c.age_range),
        mordin_maiden_tier(c.race_title, c.prize_pos_1),
        c.winner_runner_id, c.db_2nd, c.within_2l,
        IF(c.db_2nd IS NOT NULL AND c.db_3rd IS NOT NULL, c.db_3rd - c.db_2nd, NULL),
        c.comp_level,
        mordin_easy_bonus(c.comp_level, c.db_2nd, c.winner_comment),
        m.mcr,
        sr.speed_rating
    FROM (
        SELECT b.*,
               mordin_intl_tier(b.raw_tier, b.country_group) AS tier_code,
               mordin_comp_level(b.db_2nd, b.within_2l, b.winner_comment) AS comp_level
        FROM (
            SELECT
                hracb.race_id, hracb.meeting_date, hracb.course,
                mordin_country_group(dracb.country) AS country_group,
                dracb.race_title,
                mordin_class_num(hracb.class) AS class_num,
                hracb.handicap,
                dracb.age_range,
                hracb.distance_yards,
                dracb.prize_pos_1,
                mordin_tier_code(dracb.race_title, hracb.class, hracb.handicap) AS raw_tier,
                f.winner_runner_id, f.winner_comment, f.db_2nd, f.db_3rd, f.within_2l
            FROM historic_races_beta hracb
            JOIN tmp_mordin_finish f ON f.race_id = hracb.race_id
            LEFT JOIN daily_races_beta dracb ON dracb.race_id = hracb.race_id
        ) b
    ) c
    LEFT JOIN mordin_class_matrix m ON m.tier_code = c.tier_code AND m.comp_level = c.comp_level
    LEFT JOIN sr_results sr ON sr.race_id = c.race_id AND sr.runner_id = c.winner_runner_id;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_finish;

    DROP TABLE IF EXISTS mordin_runner_perf;
    CREATE TABLE mordin_runner_perf (
        runner_id INT NOT NULL,
        race_id INT NOT NULL,
        meeting_date DATE,
        course VARCHAR(50),
        trainer_name VARCHAR(100),
        class_num TINYINT,
        handicap TINYINT,
        distance_yards INT,
        tier_code VARCHAR(12),
        tier_rank TINYINT,
        foreign_unquantified TINYINT NOT NULL DEFAULT 0,
        us_lower_graded TINYINT NOT NULL DEFAULT 0,
        is_low_tier_juv TINYINT NOT NULL DEFAULT 0,
        maiden_tier TINYINT,
        finish_pos INT,
        lengths_to_winner DECIMAL(6,2),
        lengths_2nd_to_3rd DECIMAL(6,2),
        mordin_class_rating DECIMAL(5,1),
        PRIMARY KEY (runner_id, race_id),
        INDEX idx_mrp_race (race_id),
        INDEX idx_mrp_runner_date (runner_id, meeting_date)
    );

    INSERT IGNORE INTO mordin_runner_perf (
        runner_id, race_id, meeting_date, course, trainer_name, class_num, handicap, distance_yards,
        tier_code, tier_rank, foreign_unquantified, us_lower_graded, is_low_tier_juv, maiden_tier,
        finish_pos, lengths_to_winner, lengths_2nd_to_3rd, mordin_class_rating
    )
    SELECT
        p.runner_id, p.race_id, p.meeting_date, p.course, p.trainer_name, p.class_num, p.handicap, p.distance_yards,
        p.tier_code, p.tier_rank, p.foreign_unquantified, p.us_lower_graded, p.is_low_tier_juv, p.maiden_tier,
        p.finish_pos, p.lengths_to_winner, p.lengths_2nd_to_3rd,
        mordin_perf_mcr(p.race_mcr, p.easy_bonus, p.finish_pos, p.lengths_to_winner, p.distance_yards)
    FROM (
        SELECT
            hrunb.runner_id, mrc.race_id, mrc.meeting_date, mrc.course, hrunb.trainer_name,
            mrc.class_num, mrc.handicap, mrc.distance_yards, mrc.tier_code, mrc.tier_rank,
            mrc.foreign_unquantified, mrc.us_lower_graded, mrc.is_low_tier_juv, mrc.maiden_tier,
            mrc.lengths_2nd_to_3rd, mrc.race_mcr, mrc.easy_bonus,
            IF(hrunb.finish_position REGEXP '^[0-9]+$', CAST(hrunb.finish_position AS UNSIGNED), NULL) AS finish_pos,
            CASE
                WHEN hrunb.finish_position REGEXP '^[0-9]+$' AND CAST(hrunb.finish_position AS UNSIGNED) = 1 THEN 0
                WHEN hrunb.finish_position REGEXP '^[0-9]+$' THEN hrunb.distance_beaten
                ELSE NULL
            END AS lengths_to_winner
        FROM mordin_race_class mrc
        JOIN historic_runners_beta hrunb ON hrunb.race_id = mrc.race_id
        WHERE hrunb.runner_id IS NOT NULL
          AND (hrunb.unfinished != 'Non-Runner' OR hrunb.unfinished IS NULL)
    ) p;

    DROP TABLE IF EXISTS mordin_trainer_group_sr;
    CREATE TABLE mordin_trainer_group_sr (
        trainer_name VARCHAR(100) NOT NULL PRIMARY KEY,
        group_runs INT NOT NULL,
        group_wins INT NOT NULL,
        rolling_5yr_group_strike_rate DECIMAL(5,1),
        is_group_elite TINYINT NOT NULL DEFAULT 0
    );

    INSERT INTO mordin_trainer_group_sr (trainer_name, group_runs, group_wins, rolling_5yr_group_strike_rate, is_group_elite)
    SELECT
        trainer_name,
        COUNT(*),
        SUM(finish_pos = 1),
        ROUND(100 * SUM(finish_pos = 1) / COUNT(*), 1),
        IF(COUNT(*) >= 20 AND 100 * SUM(finish_pos = 1) / COUNT(*) >= 15, 1, 0)
    FROM mordin_runner_perf
    WHERE tier_rank <= 4
      AND meeting_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
      AND trainer_name IS NOT NULL AND trainer_name != ''
    GROUP BY trainer_name;

    SELECT CONCAT(
        'mordin_history_UPDATE completed — races: ', (SELECT COUNT(*) FROM mordin_race_class),
        ', runs: ', (SELECT COUNT(*) FROM mordin_runner_perf),
        ' at ', NOW()
    ) AS status;
END $$
DELIMITER ;
```

- [ ] **Step 3: Deploy, run, and check the verification queries**

Run `mordin_history_UPDATE.sql`, then `CALL mordin_history_UPDATE();`, then the Step 1 queries.

Expected:
- `no_tier` is a small share of `races` (UK/IRE races without class or daily row). If it is above 10%, open `mordin_class_NOTES.md` coverage numbers and check `class` format.
- `comp_level` 1–5 all present, none above ~45% of races.
- Every `tier_code` from `G1` to `C67_BOTTOM` appears; `G1` is the smallest UK bucket.
- G1 sample: winners have `lengths_to_winner = 0` and `mordin_class_rating = race_mcr + easy_bonus`; losers fall by about `lengths × 1760 / distance_yards`.
- Top `mordin_trainer_group_sr` rows show recognisable Group-race trainers.

- [ ] **Step 4: Commit**

```bash
git add mordin_history_UPDATE.sql
git commit -m "feat: Mordin history procedure (race class, runner MCR, trainer group SR)"
```

---

### Task 4: Runner flags procedure

**Files:**
- Create: `mordin_runner_flags_UPDATE.sql`

**Interfaces:**
- Consumes: `mordin_race_class`, `mordin_runner_perf`, `mordin_trainer_group_sr`, `mordin_northern_courses`, `trainer_locations`, Task 1 functions; `daily_races_beta`/`daily_runners_beta`, `advance_daily_races_beta`/`advance_daily_runners_beta`.
- Produces: `mordin_runner_flags`, PK `(race_id, runner_id)`, columns: `meeting_date`, `logic_version`, `age`, `race_tier_code`, `race_tier_rank`, `race_class_num`, `distance_yards`, `career_runs`, `season_runs`, `best_tier_won_rank`, `meets_class_requirement`, `best_mcr_12m`, `last_mcr`, `outclassed_handicapper`, `eliminate_juvenile`, `downgrade_northern_form`, `foreign_unquantified_runs`, `us_lower_graded_runs`, `latest_position`, `latest_lengths_2nd_to_3rd`, `high_value_maiden_placing`, `is_premier_debut_track`, `yarmouth_newmarket_pocket`, `salisbury_lambourn_pocket`, `debut_penalty`, `trainer_group_strike_rate`, `trainer_group_elite`.
- Bump `v_logic_version` whenever flag logic changes; the procedure deletes and recomputes rows with an older version.

- [ ] **Step 1: Write the procedure**

Create `mordin_runner_flags_UPDATE.sql`:

```sql
DELIMITER $$
DROP PROCEDURE IF EXISTS `mordin_runner_flags_UPDATE`$$
CREATE PROCEDURE `mordin_runner_flags_UPDATE`()
BEGIN
    DECLARE v_logic_version INT DEFAULT 1;
    DECLARE v_hist_from DATE DEFAULT DATE_SUB(CURDATE(), INTERVAL 1825 DAY);

    CREATE TABLE IF NOT EXISTS mordin_runner_flags (
        race_id INT NOT NULL,
        runner_id INT NOT NULL,
        meeting_date DATE NOT NULL,
        logic_version INT NOT NULL,
        age TINYINT,
        race_tier_code VARCHAR(12),
        race_tier_rank TINYINT,
        race_class_num TINYINT,
        distance_yards INT,
        career_runs INT NOT NULL DEFAULT 0,
        season_runs INT NOT NULL DEFAULT 0,
        best_tier_won_rank TINYINT,
        meets_class_requirement TINYINT,
        best_mcr_12m DECIMAL(5,1),
        last_mcr DECIMAL(5,1),
        outclassed_handicapper TINYINT,
        eliminate_juvenile TINYINT NOT NULL DEFAULT 0,
        downgrade_northern_form TINYINT NOT NULL DEFAULT 0,
        foreign_unquantified_runs INT NOT NULL DEFAULT 0,
        us_lower_graded_runs INT NOT NULL DEFAULT 0,
        latest_position INT,
        latest_lengths_2nd_to_3rd DECIMAL(6,2),
        high_value_maiden_placing TINYINT NOT NULL DEFAULT 0,
        is_premier_debut_track TINYINT NOT NULL DEFAULT 0,
        yarmouth_newmarket_pocket TINYINT NOT NULL DEFAULT 0,
        salisbury_lambourn_pocket TINYINT NOT NULL DEFAULT 0,
        debut_penalty TINYINT NOT NULL DEFAULT 0,
        trainer_group_strike_rate DECIMAL(5,1),
        trainer_group_elite TINYINT NOT NULL DEFAULT 0,
        PRIMARY KEY (race_id, runner_id),
        INDEX idx_mrf_date (meeting_date)
    );

    DELETE FROM mordin_runner_flags
    WHERE logic_version <> v_logic_version
       OR meeting_date >= CURDATE()
       OR meeting_date < v_hist_from;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_targets;
    CREATE TEMPORARY TABLE tmp_mordin_targets (
        race_id INT NOT NULL,
        runner_id INT NOT NULL,
        meeting_date DATE NOT NULL,
        course VARCHAR(50),
        trainer_name VARCHAR(100),
        age TINYINT,
        tier_code VARCHAR(12),
        tier_rank TINYINT,
        class_num TINYINT,
        handicap TINYINT,
        is_2yo_only TINYINT,
        is_maiden_race TINYINT,
        distance_yards INT,
        PRIMARY KEY (race_id, runner_id)
    );

    INSERT IGNORE INTO tmp_mordin_targets
    SELECT
        mrp.race_id, mrp.runner_id, mrp.meeting_date, mrp.course, mrp.trainer_name,
        CASE
            WHEN hrunb.foaling_date IS NULL OR hrunb.foaling_date IN ('', '0000-00-00') THEN NULL
            ELSE YEAR(mrp.meeting_date) - YEAR(hrunb.foaling_date)
        END,
        mrc.tier_code, mrc.tier_rank, mrc.class_num, mrc.handicap, mrc.is_2yo_only,
        IF(mrc.maiden_tier IS NULL, 0, 1), mrc.distance_yards
    FROM mordin_runner_perf mrp
    JOIN mordin_race_class mrc ON mrc.race_id = mrp.race_id
    JOIN historic_runners_beta hrunb ON hrunb.race_id = mrp.race_id AND hrunb.runner_id = mrp.runner_id
    LEFT JOIN mordin_runner_flags mrf ON mrf.race_id = mrp.race_id AND mrf.runner_id = mrp.runner_id
    WHERE mrp.meeting_date >= v_hist_from
      AND mrp.meeting_date < CURDATE()
      AND mrf.race_id IS NULL;

    INSERT IGNORE INTO tmp_mordin_targets
    SELECT d.race_id, d.runner_id, d.meeting_date, d.course, d.trainer_name, d.age,
           d.tier_code, mordin_tier_rank(d.tier_code), d.class_num, d.handicap, d.is_2yo_only, d.is_maiden_race, d.distance_yards
    FROM (
        SELECT drunb.race_id, drunb.runner_id, dracb.meeting_date, dracb.course, drunb.trainer_name, drunb.age,
               mordin_intl_tier(mordin_tier_code(dracb.race_title, dracb.class, dracb.handicap), mordin_country_group(dracb.country)) AS tier_code,
               mordin_class_num(dracb.class) AS class_num,
               dracb.handicap,
               mordin_is_2yo_only(dracb.age_range) AS is_2yo_only,
               IF(mordin_maiden_tier(dracb.race_title, dracb.prize_pos_1) IS NULL, 0, 1) AS is_maiden_race,
               dracb.distance_yards
        FROM daily_races_beta dracb
        JOIN daily_runners_beta drunb ON drunb.race_id = dracb.race_id
        WHERE dracb.meeting_date >= CURDATE()
          AND dracb.race_type = 'Flat'
    ) d;

    INSERT IGNORE INTO tmp_mordin_targets
    SELECT d.race_id, d.runner_id, d.meeting_date, d.course, d.trainer_name, d.age,
           d.tier_code, mordin_tier_rank(d.tier_code), d.class_num, d.handicap, d.is_2yo_only, d.is_maiden_race, d.distance_yards
    FROM (
        SELECT adrunb.race_id, adrunb.runner_id, adracb.meeting_date, adracb.course, adrunb.trainer_name, adrunb.age,
               mordin_intl_tier(mordin_tier_code(adracb.race_title, adracb.class, adracb.handicap), mordin_country_group(adracb.country)) AS tier_code,
               mordin_class_num(adracb.class) AS class_num,
               adracb.handicap,
               mordin_is_2yo_only(adracb.age_range) AS is_2yo_only,
               IF(mordin_maiden_tier(adracb.race_title, adracb.prize_pos_1) IS NULL, 0, 1) AS is_maiden_race,
               adracb.distance_yards
        FROM advance_daily_races_beta adracb
        JOIN advance_daily_runners_beta adrunb ON adrunb.race_id = adracb.race_id
        WHERE adracb.meeting_date >= CURDATE()
          AND adracb.race_type = 'Flat'
    ) d;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_hist;
    CREATE TEMPORARY TABLE tmp_mordin_hist (PRIMARY KEY (race_id, runner_id)) AS
    SELECT
        t.race_id,
        t.runner_id,
        COUNT(h.race_id) AS career_runs,
        SUM(YEAR(h.meeting_date) = YEAR(t.meeting_date)) AS season_runs,
        MAX(
            YEAR(h.meeting_date) = YEAR(t.meeting_date)
            AND h.class_num IS NOT NULL AND t.class_num IS NOT NULL
            AND h.class_num <= t.class_num
            AND (h.finish_pos BETWEEN 1 AND 3 OR h.lengths_to_winner <= 3)
        ) AS season_class_ok,
        MIN(CASE WHEN h.finish_pos = 1 AND h.tier_rank IS NOT NULL THEN
            CASE
                WHEN t.age >= 4 AND t.tier_rank <= 4 AND t.distance_yards <= 1320
                     AND h.distance_yards <= 1320 AND h.tier_rank = 2 THEN 1
                ELSE h.tier_rank
            END
        END) AS best_tier_won_rank,
        MAX(CASE WHEN h.meeting_date >= DATE_SUB(t.meeting_date, INTERVAL 365 DAY) THEN h.mordin_class_rating END) AS best_mcr_12m,
        MAX(h.is_low_tier_juv) AS ever_low_tier_juv,
        MAX(nc.course_key IS NOT NULL AND (h.tier_rank IS NULL OR h.tier_rank > 4)) AS northern_minor,
        SUM(h.foreign_unquantified) AS foreign_unquantified_runs,
        SUM(h.us_lower_graded) AS us_lower_graded_runs
    FROM tmp_mordin_targets t
    LEFT JOIN mordin_runner_perf h ON h.runner_id = t.runner_id AND h.meeting_date < t.meeting_date
    LEFT JOIN mordin_northern_courses nc ON LOWER(h.course) LIKE CONCAT(nc.course_key, '%')
    GROUP BY t.race_id, t.runner_id;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_edges;
    CREATE TEMPORARY TABLE tmp_mordin_edges (PRIMARY KEY (race_id, runner_id)) AS
    SELECT
        race_id,
        runner_id,
        MAX(CASE WHEN rn_desc = 1 THEN finish_pos END) AS latest_position,
        MAX(CASE WHEN rn_desc = 1 THEN lengths_2nd_to_3rd END) AS latest_l2to3,
        MAX(CASE WHEN rn_desc = 1 THEN mordin_class_rating END) AS last_mcr,
        MAX(CASE WHEN rn_asc = 1 THEN course END) AS debut_course
    FROM (
        SELECT
            t.race_id, t.runner_id, h.finish_pos, h.lengths_2nd_to_3rd, h.mordin_class_rating, h.course,
            ROW_NUMBER() OVER (PARTITION BY t.race_id, t.runner_id ORDER BY h.meeting_date DESC, h.race_id DESC) AS rn_desc,
            ROW_NUMBER() OVER (PARTITION BY t.race_id, t.runner_id ORDER BY h.meeting_date ASC, h.race_id ASC) AS rn_asc
        FROM tmp_mordin_targets t
        JOIN mordin_runner_perf h ON h.runner_id = t.runner_id AND h.meeting_date < t.meeting_date
    ) x
    WHERE rn_desc = 1 OR rn_asc = 1
    GROUP BY race_id, runner_id;

    INSERT INTO mordin_runner_flags (
        race_id, runner_id, meeting_date, logic_version, age, race_tier_code, race_tier_rank, race_class_num,
        distance_yards, career_runs, season_runs, best_tier_won_rank, meets_class_requirement, best_mcr_12m,
        last_mcr, outclassed_handicapper, eliminate_juvenile, downgrade_northern_form, foreign_unquantified_runs,
        us_lower_graded_runs, latest_position, latest_lengths_2nd_to_3rd, high_value_maiden_placing,
        is_premier_debut_track, yarmouth_newmarket_pocket, salisbury_lambourn_pocket, debut_penalty,
        trainer_group_strike_rate, trainer_group_elite
    )
    SELECT
        t.race_id, t.runner_id, t.meeting_date, v_logic_version, t.age, t.tier_code, t.tier_rank, t.class_num,
        t.distance_yards,
        IFNULL(hs.career_runs, 0),
        IFNULL(hs.season_runs, 0),
        hs.best_tier_won_rank,
        CASE
            WHEN t.tier_rank IS NULL THEN NULL
            WHEN hs.best_tier_won_rank IS NULL THEN 0
            WHEN hs.best_tier_won_rank <= t.tier_rank THEN 1
            ELSE 0
        END,
        hs.best_mcr_12m,
        e.last_mcr,
        CASE
            WHEN IFNULL(t.handicap, 0) <> 1 OR IFNULL(t.age, 0) < 3 THEN 0
            WHEN IFNULL(hs.season_runs, 0) = 0 THEN NULL
            WHEN IFNULL(hs.season_class_ok, 0) = 1 THEN 0
            ELSE 1
        END,
        IF(t.is_2yo_only = 1 AND IFNULL(t.tier_rank, 99) <= 4 AND IFNULL(hs.ever_low_tier_juv, 0) = 1, 1, 0),
        IF(t.is_2yo_only = 1 AND IFNULL(t.tier_code, '') = 'G1' AND IFNULL(hs.northern_minor, 0) = 1, 1, 0),
        IFNULL(hs.foreign_unquantified_runs, 0),
        IFNULL(hs.us_lower_graded_runs, 0),
        e.latest_position,
        e.latest_l2to3,
        IF(t.is_maiden_race = 1 AND IFNULL(e.latest_position, 0) = 2 AND IFNULL(e.latest_l2to3, 0) >= 5, 1, 0),
        IF(LOWER(IFNULL(e.debut_course, t.course)) REGEXP '^(great yarmouth|yarmouth|newmarket|ascot|goodwood|doncaster|salisbury|newbury)', 1, 0),
        IF(IFNULL(t.age, 0) = 2 AND IFNULL(hs.career_runs, 0) = 0
           AND LOWER(IFNULL(t.course, '')) REGEXP 'yarmouth' AND IFNULL(tl.location, '') = 'Newmarket', 1, 0),
        IF(IFNULL(t.age, 0) = 2 AND IFNULL(hs.career_runs, 0) = 0
           AND LOWER(IFNULL(t.course, '')) REGEXP 'salisbury' AND IFNULL(tl.location, '') = 'Lambourn', 1, 0),
        IF(IFNULL(t.age, 0) = 2 AND IFNULL(hs.career_runs, 0) = 0
           AND NOT (
               (LOWER(IFNULL(t.course, '')) REGEXP 'yarmouth' AND IFNULL(tl.location, '') = 'Newmarket')
               OR (LOWER(IFNULL(t.course, '')) REGEXP 'salisbury' AND IFNULL(tl.location, '') = 'Lambourn')
           ), 1, 0),
        tg.rolling_5yr_group_strike_rate,
        IFNULL(tg.is_group_elite, 0)
    FROM tmp_mordin_targets t
    LEFT JOIN tmp_mordin_hist hs ON hs.race_id = t.race_id AND hs.runner_id = t.runner_id
    LEFT JOIN tmp_mordin_edges e ON e.race_id = t.race_id AND e.runner_id = t.runner_id
    LEFT JOIN trainer_locations tl ON tl.trainer_name = t.trainer_name
    LEFT JOIN mordin_trainer_group_sr tg ON tg.trainer_name = t.trainer_name;

    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_targets;
    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_hist;
    DROP TEMPORARY TABLE IF EXISTS tmp_mordin_edges;

    SELECT CONCAT(
        'mordin_runner_flags_UPDATE completed — today/advance rows: ',
        (SELECT COUNT(*) FROM mordin_runner_flags WHERE meeting_date >= CURDATE()),
        ', total rows: ', (SELECT COUNT(*) FROM mordin_runner_flags),
        ' at ', NOW()
    ) AS status;
END $$
DELIMITER ;
```

- [ ] **Step 2: Deploy and run (first run back-fills 5 years and is slow; later runs are incremental)**

Run `mordin_runner_flags_UPDATE.sql`, then `CALL mordin_runner_flags_UPDATE();`.
Expected: status row with non-zero today/advance rows on a racing day and a total in the hundreds of thousands.

- [ ] **Step 3: Run the sanity queries**

```sql
SELECT
    SUM(outclassed_handicapper = 1) AS outclassed,
    SUM(outclassed_handicapper IS NULL) AS outclassed_unknown,
    SUM(eliminate_juvenile) AS juv_elim,
    SUM(downgrade_northern_form) AS northern,
    SUM(high_value_maiden_placing) AS clear_2nd,
    SUM(yarmouth_newmarket_pocket) AS yar_pocket,
    SUM(salisbury_lambourn_pocket) AS sal_pocket,
    SUM(debut_penalty) AS debut_pen,
    SUM(meets_class_requirement = 1) AS class_ok,
    SUM(trainer_group_elite) AS elite_trainer
FROM mordin_runner_flags;

-- Point-in-time check: no flag row may use a run on/after its own date.
SELECT COUNT(*) AS leaks
FROM mordin_runner_flags f
JOIN mordin_runner_perf p ON p.runner_id = f.runner_id AND p.race_id = f.race_id
WHERE f.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND f.career_runs > (
    SELECT COUNT(*) FROM mordin_runner_perf q
    WHERE q.runner_id = f.runner_id AND q.meeting_date < f.meeting_date
);

-- Today's card spot check.
SELECT f.*, dracb.race_title
FROM mordin_runner_flags f
JOIN daily_races_beta dracb ON dracb.race_id = f.race_id
WHERE f.meeting_date = CURDATE()
ORDER BY f.race_id
LIMIT 40;
```

Expected: every count is non-zero except possibly `northern`/`juv_elim` out of season; `leaks = 0`; today's rows have `race_tier_code` matching their titles.

- [ ] **Step 4: Commit**

```bash
git add mordin_runner_flags_UPDATE.sql
git commit -m "feat: Mordin point-in-time runner flags procedure"
```

---

### Task 5: Calibration report (spec matrix vs. observed winner speed ratings)

**Files:**
- Create: `mordin_calibration_REPORT.sql`

**Interfaces:**
- Consumes: `mordin_race_class`, `mordin_class_matrix`.
- Produces: `mordin_class_calibration(tier_code, comp_level, races, avg_winner_sr, sd_winner_sr, spec_mcr)`.

- [ ] **Step 1: Write the procedure**

```sql
DELIMITER $$
DROP PROCEDURE IF EXISTS `mordin_calibration_REPORT`$$
CREATE PROCEDURE `mordin_calibration_REPORT`()
BEGIN
    DROP TABLE IF EXISTS mordin_class_calibration;
    CREATE TABLE mordin_class_calibration AS
    SELECT
        mrc.tier_code,
        mrc.comp_level,
        COUNT(*) AS races,
        ROUND(AVG(mrc.winner_speed_rating), 1) AS avg_winner_sr,
        ROUND(STDDEV_SAMP(mrc.winner_speed_rating), 1) AS sd_winner_sr,
        MAX(m.mcr) AS spec_mcr
    FROM mordin_race_class mrc
    LEFT JOIN mordin_class_matrix m ON m.tier_code = mrc.tier_code AND m.comp_level = mrc.comp_level
    WHERE mrc.winner_speed_rating IS NOT NULL
      AND mrc.tier_code IS NOT NULL AND mrc.tier_code <> 'UNQ'
      AND mrc.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
    GROUP BY mrc.tier_code, mrc.comp_level;

    SELECT * FROM mordin_class_calibration ORDER BY mordin_tier_rank(tier_code), comp_level;
END $$
DELIMITER ;
```

- [ ] **Step 2: Deploy, run, and record the result**

Run the file, then `CALL mordin_calibration_REPORT();`.
Expected: 50–60 rows. Compare the **gaps** between tiers in `avg_winner_sr` with the gaps in `spec_mcr` (absolute values will differ because the site's speed-rating scale is not Mordin's). Paste the table into `mordin_class_NOTES.md` under a new `## Calibration` heading. If an observed gap is reversed (a lower tier beats a higher one in 3+ levels), note it; adjusting `mordin_class_matrix` rows is a data edit, not a code change.

- [ ] **Step 3: Commit**

```bash
git add mordin_calibration_REPORT.sql mordin_class_NOTES.md
git commit -m "feat: Mordin class calibration report"
```

---

### Task 6: Wire into the nightly run

**Files:**
- Modify: `RUNME_UpdateCall.sql:103` (before the final `SELECT CONCAT('RUNME_UpdateCall completed at ', NOW())`)

**Interfaces:**
- Consumes: `mordin_history_UPDATE`, `mordin_runner_flags_UPDATE`.

- [ ] **Step 1: Add the guarded calls**

Insert before `SELECT CONCAT('RUNME_UpdateCall completed at ', NOW()) AS status;`:

```sql
IF EXISTS (
  SELECT 1
  FROM information_schema.ROUTINES
  WHERE ROUTINE_SCHEMA = DATABASE()
    AND ROUTINE_TYPE = 'PROCEDURE'
    AND ROUTINE_NAME = 'mordin_history_UPDATE'
) THEN
  CALL `mordin_history_UPDATE`();
  SELECT CONCAT('mordin_history_UPDATE completed at ', NOW()) AS status;
ELSE
  SELECT CONCAT('mordin_history_UPDATE not found in ', DATABASE(), ', skipped at ', NOW()) AS status;
END IF;

IF EXISTS (
  SELECT 1
  FROM information_schema.ROUTINES
  WHERE ROUTINE_SCHEMA = DATABASE()
    AND ROUTINE_TYPE = 'PROCEDURE'
    AND ROUTINE_NAME = 'mordin_runner_flags_UPDATE'
) THEN
  CALL `mordin_runner_flags_UPDATE`();
  SELECT CONCAT('mordin_runner_flags_UPDATE completed at ', NOW()) AS status;
ELSE
  SELECT CONCAT('mordin_runner_flags_UPDATE not found in ', DATABASE(), ', skipped at ', NOW()) AS status;
END IF;
```

- [ ] **Step 2: Redeploy and run**

Run `DROP PROCEDURE IF EXISTS RUNME_UpdateCall;`, then `RUNME_UpdateCall.sql`, then `CALL RUNME_UpdateCall();`.
Expected: both `mordin_* completed at` status rows; total extra runtime noted in `mordin_class_NOTES.md`.

- [ ] **Step 3: Commit**

```bash
git add RUNME_UpdateCall.sql
git commit -m "feat: run Mordin procedures in nightly update"
```

---

### Task 7: PHP module (stamina rule, badges, System Builder row check)

**Files:**
- Create: `inc/mordin-class.php`
- Test: `tests/mordin-class-test.php`

**Interfaces:**
- Consumes: `mordin_runner_flags` (via `$wpdb`).
- Produces:
  - `fhor_mordin_rule_keys(): string[]`
  - `fhor_mordin_sb_columns(): string[]`
  - `fhor_mordin_flags_for_race(int $race_id): array<int, array>` keyed by `runner_id`
  - `fhor_mordin_stamina($distance_yards, $tier_rank, $di, $di_infinite): array{applies:bool,exceeded:bool,target:bool}`
  - `fhor_mordin_badges($flags, array $stamina): array<array{label:string,tone:string,title:string}>` — `tone` ∈ `pos|neg|warn`
  - `fhor_mordin_row_passes($row, array $wanted): bool` — reads `mrf_*` properties, `distance_yards`, `_dosage_di`, `_dosage_di_infinite`

- [ ] **Step 1: Write the failing test**

Create `tests/mordin-class-test.php`:

```php
<?php
/**
 * Mordin class module unit checks (no WordPress DB).
 *
 * Run from repo root:
 *   php tests/mordin-class-test.php
 */

$failures = 0;

function mc_assert($label, $cond) {
    global $failures;
    if ($cond) {
        echo "ok  $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n";
}

function mc_eq($label, $expected, $actual) {
    mc_assert(
        $label . ' (expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ')',
        $expected === $actual
    );
}

require dirname(__DIR__) . '/inc/mordin-class.php';

$s = fhor_mordin_stamina(2200, 1, 5.5, false);
mc_eq('10f G1 DI 5.5 exceeded', true, $s['exceeded']);
mc_eq('10f G1 DI 5.5 not target', false, $s['target']);
$s = fhor_mordin_stamina(2420, 4, null, true);
mc_eq('Listed 11f infinite DI exceeded', true, $s['exceeded']);
$s = fhor_mordin_stamina(2640, 2, 3.3, false);
mc_eq('12f G2 DI 3.30 target', true, $s['target']);
mc_eq('12f G2 DI 3.30 not exceeded', false, $s['exceeded']);
$s = fhor_mordin_stamina(1980, 1, 9.0, false);
mc_eq('9f race does not apply', false, $s['applies']);
mc_eq('9f race never exceeded', false, $s['exceeded']);
$s = fhor_mordin_stamina(2640, 7, 9.0, false);
mc_eq('Class 2 handicap does not apply', false, $s['applies']);

$labels = function (array $badges) {
    return array_map(function ($b) {
        return $b['label'];
    }, $badges);
};
$none = ['applies' => false, 'exceeded' => false, 'target' => false];
mc_eq('no flags no badges', [], fhor_mordin_badges([], $none));
mc_eq('clear 2nd badge', ['Clear 2nd'], $labels(fhor_mordin_badges(['high_value_maiden_placing' => '1'], $none)));
mc_eq('outclassed badge', ['Outclassed'], $labels(fhor_mordin_badges(['outclassed_handicapper' => '1'], $none)));
mc_eq('outclassed unknown no badge', [], $labels(fhor_mordin_badges(['outclassed_handicapper' => null], $none)));
mc_eq('stamina doubt badge', ['Stamina doubt'], $labels(fhor_mordin_badges([], ['applies' => true, 'exceeded' => true, 'target' => false])));
mc_eq('positives before negatives', ['Class proven', 'Debut'], $labels(fhor_mordin_badges(['debut_penalty' => '1', 'meets_class_requirement' => '1'], $none)));
mc_eq('foreign form warn', ['Foreign form'], $labels(fhor_mordin_badges(['foreign_unquantified_runs' => '2'], $none)));

mc_eq('not_outclassed rejects flagged', false, fhor_mordin_row_passes((object) ['mrf_outclassed_handicapper' => '1'], ['not_outclassed']));
mc_eq('not_outclassed keeps unflagged', true, fhor_mordin_row_passes((object) ['mrf_outclassed_handicapper' => '0'], ['not_outclassed']));
mc_eq('not_outclassed keeps missing data', true, fhor_mordin_row_passes((object) [], ['not_outclassed']));
mc_eq('class_ok needs data', false, fhor_mordin_row_passes((object) [], ['class_ok']));
mc_eq('class_ok passes', true, fhor_mordin_row_passes((object) ['mrf_meets_class_requirement' => '1'], ['class_ok']));
mc_eq('debut_pocket salisbury', true, fhor_mordin_row_passes((object) ['mrf_salisbury_lambourn_pocket' => '1'], ['debut_pocket']));
mc_eq('debut_pocket none', false, fhor_mordin_row_passes((object) ['mrf_yarmouth_newmarket_pocket' => '0'], ['debut_pocket']));
mc_eq('stamina_ok rejects DI 6 at 12f G1', false, fhor_mordin_row_passes((object) [
    'distance_yards' => 2640, 'mrf_race_tier_rank' => '1', '_dosage_di' => 6.0, '_dosage_di_infinite' => false,
], ['stamina_ok']));
mc_eq('dosage_target passes when rule not applicable', true, fhor_mordin_row_passes((object) [
    'distance_yards' => 1320, 'mrf_race_tier_rank' => '1', '_dosage_di' => 6.0,
], ['dosage_target']));
mc_eq('dosage_target rejects DI 4 at 12f G1', false, fhor_mordin_row_passes((object) [
    'distance_yards' => 2640, 'mrf_race_tier_rank' => '1', '_dosage_di' => 4.0,
], ['dosage_target']));
mc_eq('unknown rule ignored', true, fhor_mordin_row_passes((object) [], ['bogus']));
mc_eq('rule keys', 9, count(fhor_mordin_rule_keys()));

echo $failures === 0 ? "\nAll Mordin class checks passed.\n" : "\n$failures Mordin class check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `php tests/mordin-class-test.php`
Expected: fatal error `Failed opening required '.../inc/mordin-class.php'`.

- [ ] **Step 3: Write the module**

Create `inc/mordin-class.php`:

```php
<?php
/**
 * Mordin class analysis: reads mordin_runner_flags (built by mordin_runner_flags_UPDATE.sql),
 * applies the Dosage stamina ceiling, and exposes racecard badges and System Builder rules.
 */

if (!function_exists('fhor_mordin_rule_keys')) {
    function fhor_mordin_rule_keys() {
        return [
            'class_ok', 'not_outclassed', 'not_juv_eliminated', 'not_northern', 'clear_2nd',
            'debut_pocket', 'group_trainer', 'stamina_ok', 'dosage_target',
        ];
    }
}

if (!function_exists('fhor_mordin_sb_columns')) {
    function fhor_mordin_sb_columns() {
        return [
            'meets_class_requirement', 'outclassed_handicapper', 'eliminate_juvenile', 'downgrade_northern_form',
            'high_value_maiden_placing', 'yarmouth_newmarket_pocket', 'salisbury_lambourn_pocket',
            'trainer_group_elite', 'race_tier_rank',
        ];
    }
}

if (!function_exists('fhor_mordin_flags_for_race')) {
    function fhor_mordin_flags_for_race($race_id) {
        global $wpdb;
        $race_id = intval($race_id);
        if ($race_id <= 0 || !isset($wpdb)) {
            return [];
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'mordin_runner_flags')) !== 'mordin_runner_flags') {
            return [];
        }
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM mordin_runner_flags WHERE race_id = %d', $race_id),
            ARRAY_A
        );
        $out = [];
        foreach ((array) $rows as $row) {
            $out[intval($row['runner_id'])] = $row;
        }
        return $out;
    }
}

if (!function_exists('fhor_mordin_stamina')) {
    /**
     * Class 1 (tier rank 1-4) races of 10f+ only.
     *
     * @return array{applies:bool,exceeded:bool,target:bool}
     */
    function fhor_mordin_stamina($distance_yards, $tier_rank, $di, $di_infinite) {
        $applies = is_numeric($distance_yards) && (int) $distance_yards >= 2200
            && is_numeric($tier_rank) && (int) $tier_rank >= 1 && (int) $tier_rank <= 4;
        if (!$applies) {
            return ['applies' => false, 'exceeded' => false, 'target' => false];
        }
        $di_num = is_numeric($di) ? (float) $di : null;
        return [
            'applies' => true,
            'exceeded' => !empty($di_infinite) || ($di_num !== null && $di_num > 5.0),
            'target' => empty($di_infinite) && $di_num !== null && $di_num <= 3.30,
        ];
    }
}

if (!function_exists('fhor_mordin_badges')) {
    function fhor_mordin_badges($flags, array $stamina) {
        if (is_object($flags)) {
            $flags = get_object_vars($flags);
        }
        $flags = is_array($flags) ? $flags : [];
        $on = function ($key) use ($flags) {
            return isset($flags[$key]) && (string) $flags[$key] === '1';
        };

        $badges = [];
        if ($on('high_value_maiden_placing')) {
            $badges[] = ['label' => 'Clear 2nd', 'tone' => 'pos', 'title' => 'Finished 2nd last time, 5+ lengths clear of the 3rd.'];
        }
        if ($on('meets_class_requirement')) {
            $badges[] = ['label' => 'Class proven', 'tone' => 'pos', 'title' => 'Has won at this class tier or higher.'];
        }
        if ($on('yarmouth_newmarket_pocket')) {
            $badges[] = ['label' => 'Yarmouth debut', 'tone' => 'pos', 'title' => 'Newmarket-trained 2yo debuting at Yarmouth (high strike-rate pocket).'];
        }
        if ($on('salisbury_lambourn_pocket')) {
            $badges[] = ['label' => 'Salisbury debut', 'tone' => 'pos', 'title' => 'Lambourn-trained 2yo debuting at Salisbury (above-average strike-rate pocket).'];
        }
        if ($on('trainer_group_elite')) {
            $rate = isset($flags['trainer_group_strike_rate']) ? (string) $flags['trainer_group_strike_rate'] : '';
            $badges[] = ['label' => 'Group trainer', 'tone' => 'pos', 'title' => 'Trainer Group/Listed strike rate ' . $rate . '% over 5 years.'];
        }
        if (!empty($stamina['target'])) {
            $badges[] = ['label' => 'DI ≤ 3.30', 'tone' => 'pos', 'title' => 'Dosage Index within the stamina target for a 10f+ Class 1 race.'];
        }
        if ($on('outclassed_handicapper')) {
            $badges[] = ['label' => 'Outclassed', 'tone' => 'neg', 'title' => 'No top-3 or within-3-lengths run at this class or higher this season.'];
        }
        if ($on('eliminate_juvenile')) {
            $badges[] = ['label' => 'Low-grade 2yo form', 'tone' => 'neg', 'title' => 'Has run in a nursery, claimer or seller.'];
        }
        if ($on('downgrade_northern_form')) {
            $badges[] = ['label' => 'Northern form', 'tone' => 'neg', 'title' => 'Has run in a non-Pattern race at a northern track.'];
        }
        if (!empty($stamina['exceeded'])) {
            $badges[] = ['label' => 'Stamina doubt', 'tone' => 'neg', 'title' => 'Dosage Index above 5.00 in a 10f+ Class 1 race.'];
        }
        if ($on('debut_penalty')) {
            $badges[] = ['label' => 'Debut', 'tone' => 'neg', 'title' => 'Juvenile debutant outside the Yarmouth/Salisbury pockets.'];
        }
        if (isset($flags['foreign_unquantified_runs']) && (int) $flags['foreign_unquantified_runs'] > 0) {
            $badges[] = ['label' => 'Foreign form', 'tone' => 'warn', 'title' => 'Has form abroad that cannot be converted to a UK class.'];
        }
        return $badges;
    }
}

if (!function_exists('fhor_mordin_row_passes')) {
    function fhor_mordin_row_passes($row, array $wanted) {
        if (!$wanted) {
            return true;
        }
        $get = function ($key) use ($row) {
            return is_object($row) ? ($row->{$key} ?? null) : ($row[$key] ?? null);
        };
        $is1 = function ($key) use ($get) {
            return (string) $get($key) === '1';
        };
        foreach ($wanted as $rule) {
            switch ($rule) {
                case 'class_ok':
                    if (!$is1('mrf_meets_class_requirement')) {
                        return false;
                    }
                    break;
                case 'not_outclassed':
                    if ($is1('mrf_outclassed_handicapper')) {
                        return false;
                    }
                    break;
                case 'not_juv_eliminated':
                    if ($is1('mrf_eliminate_juvenile')) {
                        return false;
                    }
                    break;
                case 'not_northern':
                    if ($is1('mrf_downgrade_northern_form')) {
                        return false;
                    }
                    break;
                case 'clear_2nd':
                    if (!$is1('mrf_high_value_maiden_placing')) {
                        return false;
                    }
                    break;
                case 'debut_pocket':
                    if (!$is1('mrf_yarmouth_newmarket_pocket') && !$is1('mrf_salisbury_lambourn_pocket')) {
                        return false;
                    }
                    break;
                case 'group_trainer':
                    if (!$is1('mrf_trainer_group_elite')) {
                        return false;
                    }
                    break;
                case 'stamina_ok':
                case 'dosage_target':
                    $s = fhor_mordin_stamina($get('distance_yards'), $get('mrf_race_tier_rank'), $get('_dosage_di'), $get('_dosage_di_infinite'));
                    if ($rule === 'stamina_ok' && $s['exceeded']) {
                        return false;
                    }
                    if ($rule === 'dosage_target' && $s['applies'] && !$s['target']) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php tests/mordin-class-test.php`
Expected: every line starts with `ok`, last line `All Mordin class checks passed.`, exit code 0.

- [ ] **Step 5: Commit**

```bash
git add inc/mordin-class.php tests/mordin-class-test.php
git commit -m "feat: Mordin PHP module (stamina rule, badges, System Builder check)"
```

---

### Task 8: Racecard badges

**Files:**
- Modify: `functions.php:58` (require), `functions.php:~2130` (per-race lookup, after the dosage `try/catch`), `functions.php:~4358` (per-runner badges, after the dosage block)
- Modify: `inc/runner-card-wide.php:46` (after the Edge pill)
- Modify: `race-detail-premium.css:~1021` (after `.fhor-pick-pill--edge`)

**Interfaces:**
- Consumes: `fhor_mordin_flags_for_race`, `fhor_mordin_stamina`, `fhor_mordin_badges`; existing `$race_id`, `$race->distance_yards`, `$runner->runner_id`, `$dosage`.
- Produces: `$mordin_lookup` (per race), `$mordin_badges` (per runner) for `inc/runner-card-wide.php`.

Note: `functions.php` already has unrelated uncommitted edits. Stage only the hunks from this task (`git add -p functions.php`).

- [ ] **Step 1: Require the module**

In `functions.php`, directly after `require_once __DIR__ . '/inc/pedigree-dosage.php';` add:

```php
    require_once __DIR__ . '/inc/mordin-class.php';
```

- [ ] **Step 2: Build the per-race lookup**

In `functions.php`, directly after the closing `}` of the `$dosage_lookup` `if (...) { try { ... } catch ... }` block (~line 2130), add:

```php
$mordin_lookup = function_exists('fhor_mordin_flags_for_race') ? fhor_mordin_flags_for_race($race_id) : [];
```

- [ ] **Step 3: Build per-runner badges**

In `functions.php`, directly after the `if (empty($race_pedigree_deferred)) { ... }` dosage block that sets `$dosage_sort_value` (~line 4358) and before `// Row styling`, add:

```php
                            $mordin_badges = [];
                            $mordin_row = $mordin_lookup[intval($runner->runner_id ?? 0)] ?? null;
                            if ($mordin_row && function_exists('fhor_mordin_badges')) {
                                $mordin_badges = fhor_mordin_badges($mordin_row, fhor_mordin_stamina(
                                    $race->distance_yards ?? null,
                                    $mordin_row['race_tier_rank'] ?? null,
                                    $dosage['di'] ?? null,
                                    !empty($dosage['di_is_infinite'])
                                ));
                            }
```

- [ ] **Step 4: Render the pills**

In `inc/runner-card-wide.php`, directly after the `<?php if ($points_edge > 0): ?> ... <?php endif; ?>` block inside `runner-card-wide__pills`, add:

```php
                                        <?php foreach (($mordin_badges ?? []) as $mordin_badge): ?>
                                            <span class="fhor-pick-pill fhor-mordin-pill fhor-mordin-pill--<?php echo esc_attr($mordin_badge['tone']); ?>" title="<?php echo esc_attr($mordin_badge['title']); ?>"><?php echo esc_html($mordin_badge['label']); ?></span>
                                        <?php endforeach; ?>
```

- [ ] **Step 5: Style the tones**

In `race-detail-premium.css`, after the `.fhor-race-premium .fhor-pick-pill--edge { ... }` rule, add:

```css
.fhor-race-premium .fhor-mordin-pill--pos {
  background: color-mix(in srgb, var(--rd-ac) 15%, transparent);
  color: var(--rd-ac);
}

.fhor-race-premium .fhor-mordin-pill--neg {
  background: color-mix(in srgb, #e5484d 14%, transparent);
  color: #c2353a;
}

.fhor-race-premium .fhor-mordin-pill--warn {
  background: color-mix(in srgb, var(--rd-warn) 15%, transparent);
  color: var(--rd-warn);
}
```

- [ ] **Step 6: Verify on staging**

Deploy the theme files, open today's race page for (a) a handicap with exposed 3yo+ runners, (b) a 2yo maiden/novice, (c) a Class 1 race of 10f+.
Expected: (a) shows `Outclassed` on some runners, (b) shows `Clear 2nd`/`Debut`/pocket pills where the SQL spot check in Task 4 Step 3 says so, (c) shows `Stamina doubt` or `DI ≤ 3.30` consistent with the existing DI / CD field. Pages without flags render unchanged; no PHP warnings in `debug.log`.

- [ ] **Step 7: Commit**

```bash
git add -p functions.php
git add inc/runner-card-wide.php race-detail-premium.css
git commit -m "feat: Mordin badges on racecard runner cards"
```

---

### Task 9: System Builder filters

**Files:**
- Modify: `inc/system-builder.php` — `fhor_sb_sanitize_filters` (~210–335), `fhor_sb_fetch_historic` (~533–540), `fhor_sb_fetch_live` (~739–742), `fhor_sb_row_passes_runner_filters` (~1447), `fhor_sb_needs_dosage` (~1595), UI (~2902)
- Modify: `system-builder.js:53`, `system-builder.js:148`

**Interfaces:**
- Consumes: `fhor_mordin_rule_keys`, `fhor_mordin_sb_columns`, `fhor_mordin_row_passes`.
- Produces: filter key `mordin` (string[]) in saved systems and AJAX payloads; row properties `mrf_<column>` on historic and live rows.

- [ ] **Step 1: Sanitize the new filter**

In `fhor_sb_sanitize_filters`, add `'mordin' => [],` after `'flags' => [],` in `$out`; change `$lists` to:

```php
        $lists = ['country', 'race_type', 'going', 'flags', 'mordin'];
```

and after `$out['flags'] = array_values(array_intersect($out['flags'], $allowed_flags));` add:

```php
        $out['mordin'] = function_exists('fhor_mordin_rule_keys')
            ? array_values(array_intersect($out['mordin'], fhor_mordin_rule_keys()))
            : [];
```

- [ ] **Step 2: Join flags into historic rows**

In `fhor_sb_fetch_historic`, directly after the `non_runners` join block (`if (fhor_sb_table_exists('non_runners')) { $joins .= ...; }`), add:

```php
        if (function_exists('fhor_mordin_sb_columns') && fhor_sb_table_exists('mordin_runner_flags')) {
            $joins .= ' LEFT JOIN mordin_runner_flags mrf ON mrf.race_id = hrunb.race_id AND mrf.runner_id = hrunb.runner_id';
            foreach (fhor_mordin_sb_columns() as $mrf_col) {
                $select[] = "mrf.`$mrf_col` AS `mrf_$mrf_col`";
            }
        }
```

- [ ] **Step 3: Join flags into live rows**

In `fhor_sb_fetch_live`, directly after `$from .= ' LEFT JOIN non_runners nr ...';` block, add:

```php
        if (function_exists('fhor_mordin_sb_columns') && fhor_sb_table_exists('mordin_runner_flags')) {
            $from .= ' LEFT JOIN mordin_runner_flags mrf ON mrf.race_id = sp.race_id AND mrf.runner_id = sp.runner_id';
            foreach (fhor_mordin_sb_columns() as $mrf_col) {
                $select[] = "mrf.`$mrf_col` AS `mrf_$mrf_col`";
            }
        }
```

- [ ] **Step 4: Apply the rules per runner**

In `fhor_sb_row_passes_runner_filters`, directly after the `last_placed` check (`if (in_array('last_placed', $flags, true) && ...) { return false; }`), add:

```php
        if (!empty($filters['mordin']) && function_exists('fhor_mordin_row_passes') && !fhor_mordin_row_passes($row, $filters['mordin'])) {
            return false;
        }
```

- [ ] **Step 5: Attach dosage when a stamina rule is selected**

Replace the body of `fhor_sb_needs_dosage` with:

```php
        foreach (['di_min', 'di_max', 'cd_min', 'cd_max'] as $key) {
            if (($filters[$key] ?? '') !== '') {
                return true;
            }
        }
        return (bool) array_intersect(['stamina_ok', 'dosage_target'], $filters['mordin'] ?? []);
```

- [ ] **Step 6: Add the UI checkboxes**

In the "Market and flags" `<details>`, directly after the closing `</div>` of the `Must have` `sb-field span2` block (before the closing `</div>` of `sb-grid`), add:

```html
                            <div class="sb-field span2"><span>Mordin class rules</span>
                                <div class="sb-opts">
                                    <label><input type="checkbox" name="mordin[]" value="class_ok"><span>Won at this class or higher</span></label>
                                    <label><input type="checkbox" name="mordin[]" value="not_outclassed"><span>Not outclassed (handicaps)</span></label>
                                    <label><input type="checkbox" name="mordin[]" value="not_juv_eliminated"><span>No nursery/claimer/seller form (2yo Group)</span></label>
                                    <label><input type="checkbox" name="mordin[]" value="not_northern"><span>No northern minor form (2yo G1)</span></label>
                                    <label><input type="checkbox" name="mordin[]" value="clear_2nd"><span>Clear 2nd last time (maidens)</span></label>
                                    <label><input type="checkbox" name="mordin[]" value="debut_pocket"><span>Yarmouth/Salisbury debut pocket</span></label>
                                    <label><input type="checkbox" name="mordin[]" value="group_trainer"><span>Group trainer (15%+)</span></label>
                                    <label><input type="checkbox" name="mordin[]" value="stamina_ok"><span>No stamina doubt (10f+ Class 1)</span></label>
                                    <label><input type="checkbox" name="mordin[]" value="dosage_target"><span>DI ≤ 3.30 (10f+ Class 1)</span></label>
                                </div>
                            </div>
```

- [ ] **Step 7: Collect and restore in JS**

In `system-builder.js` `collectFilters()`, after `flags: checked('flags[]'),` add:

```js
                mordin: checked('mordin[]'),
```

In `applyFilters()`, after `setChecked('flags[]', filters.flags);` add:

```js
            setChecked('mordin[]', filters.mordin);
```

- [ ] **Step 8: Run existing tests to confirm nothing regressed**

Run:
```
php tests/system-builder-test.php
node tests/system-builder-filters-test.mjs
php tests/mordin-class-test.php
```
Expected: all `ok`, exit code 0 for each.

- [ ] **Step 9: Verify on staging**

Open the System Builder, set race type Flat, last 365 days, tick `Not outclassed (handicaps)`, Run System.
Expected: fewer bets than the same run without the tick; saving the system and reloading it re-ticks the box; Find qualifiers returns today's runners without `Outclassed` badges.

- [ ] **Step 10: Commit**

```bash
git add inc/system-builder.php system-builder.js
git commit -m "feat: Mordin class rules as System Builder filters"
```

---

### Task 10: Backtest every rule and record verdicts

**Files:**
- Modify: `mordin_class_NOTES.md`
- Modify (only if a verdict says so): `inc/mordin-class.php` (`fhor_mordin_badges`)

**Interfaces:**
- Consumes: System Builder with the `mordin` filters.

- [ ] **Step 1: Run the baseline and each rule**

For each row below, run System Builder with bet = win, last 1825 days (premium) and the base filters shown, once **without** and once **with** the rule ticked. Record bets, strike %, ROI % in the `Backtest verdicts` table.

| Rule | Base filters |
| --- | --- |
| `not_outclassed` | Flat, handicap only, age min 3 |
| `class_ok` | Flat, class "1" |
| `not_juv_eliminated` | Flat, class "1", age range "2" |
| `not_northern` | Flat, class "1", age range "2" |
| `clear_2nd` | Flat, non-handicap, course/title free; age range "2" and then "3" |
| `debut_pocket` | Flat, age range "2", course "Yarmouth" then "Salisbury" |
| `group_trainer` | Flat, class "1" |
| `stamina_ok` | Flat, class "1", distance min 10f |
| `dosage_target` | Flat, class "1", distance min 10f |

- [ ] **Step 2: Apply the verdict rule**

- **Keep a negative rule** (Outclassed, Low-grade 2yo form, Northern form, Stamina doubt, Debut) if the runners it removes have ROI at least 10 points worse than the remainder, with ≥ 300 removed bets (≥ 50 for the 2yo Group rules).
- **Keep a positive rule** (Class proven, Clear 2nd, pockets, Group trainer, DI ≤ 3.30) if its ROI beats the baseline by ≥ 10 points with ≥ 100 bets.
- **Otherwise:** keep the System Builder filter (users can still research it) but remove its badge from `fhor_mordin_badges` so it stops influencing the racecard. Delete the matching `if (...) { $badges[] = ...; }` block and the matching assertion in `tests/mordin-class-test.php`, then rerun `php tests/mordin-class-test.php` (expect all `ok`).

- [ ] **Step 3: Commit**

```bash
git add mordin_class_NOTES.md inc/mordin-class.php tests/mordin-class-test.php
git commit -m "docs: Mordin rule backtest verdicts"
```

---

## Spec coverage check

| Spec item | Task |
| --- | --- |
| §1 parse race description, class, band | 1 (`mordin_tier_code`, `mordin_band_top`, `mordin_class_num`), 3 |
| §1 competitiveness level 1–5 from comments | 1 (`mordin_comp_level`, deviation 1), 3 |
| §1 MCR lookup table | 2 (`mordin_class_matrix`), 3 (`race_mcr`) |
| §1 easy winner +1 to +3 | 1 (`mordin_easy_bonus`), 3 |
| §2 Outclassed_Handicapper | 4 (`outclassed_handicapper`), 7/8/9 |
| §2 Eliminate_Juvenile | 1 (`mordin_is_low_tier_juv`), 4, 7/8/9 |
| §2 Downgrade_Northern_Form | 2 (`mordin_northern_courses`), 4, 7/8/9 |
| §3 older sprinters G2 = G1 at ≤ 6f | 4 (`best_tier_won_rank`) |
| §3 international conversion | 1 (`mordin_country_group`, `mordin_intl_tier`), 3, 4 (`foreign_unquantified_runs`, `us_lower_graded_runs`) |
| §3 dosage stamina ceiling / target | 7 (`fhor_mordin_stamina`), 8, 9 |
| §4 maiden tier ladder | 1 (`mordin_maiden_tier`), 3 |
| §4 clear 2nd (`lengths_2nd_to_3rd ≥ 5`) | 3, 4 (`high_value_maiden_placing`) |
| §4 debut pockets + penalty | 2 (`trainer_locations`), 4 |
| §5 `mordin_class_rating` | 3 (`mordin_runner_perf.mordin_class_rating`) |
| §5 `lengths_to_winner` | 3 |
| §5 `lengths_2nd_to_3rd` | 3 |
| §5 `dosage_index` | existing `inc/pedigree-dosage.php`, used in 7 |
| §5 `is_premier_debut_track` | 4 |
| §5 `rolling_5yr_group_strike_rate` | 3 (`mordin_trainer_group_sr`), 4 |
