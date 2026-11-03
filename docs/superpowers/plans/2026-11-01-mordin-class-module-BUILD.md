# Mordin Class Module — Build Tracker

**Master plan:** [2026-11-01-mordin-class-module.md](./2026-11-01-mordin-class-module.md)

**Status:** Task 1 verified on VPS · **Last updated:** 2026-11-03

Use this file to track execution. Check boxes as tasks complete; paste verification notes under each phase.

---

## How we’re building

- **Order:** Task 0 → Task 10 from the master plan (Task 11 is post-MVP only).
- **Mode:** Inline (this project) — one task at a time, verify before the next.
- **Server:** SQL deploys and `CALL` on `fhorsitedb` (VPS).
- **Repo:** PHP/theme changes; commit per task as in the master plan.
- **Out of scope:** Regenerating `backtest_cr_data` (legacy FSr only). Mordin does not depend on it.

---

## Prerequisites

- [ ] MySQL/MariaDB access to `fhorsitedb` (window functions + `REGEXP_SUBSTR`).
- [ ] Staging or production path to deploy `*_UPDATE.sql` and theme files.
- [ ] PHP 8.3+ locally (optional) for `php tests/mordin-class-test.php`.
- [ ] Stakeholder sign-off recorded in master plan (2026-11-02) — **done**.

---

## Phase 0 — Discovery (before any Mordin SQL)

**Goal:** Confirm data shapes so Tasks 1–4 don’t encode wrong assumptions.

| Step | Action | Done | Notes |
| --- | --- | --- | --- |
| 0.1 | Run discovery queries (master plan Task 0) | [x] | phpMyAdmin PDF 2026-11-02 |
| 0.2 | Create/update `mordin_class_NOTES.md` at repo root | [x] | `mordin_class_NOTES.md` |
| 0.3 | Record: class format, `distance_beaten` cumulative?, country values, trainer name format | [x] | See NOTES § Discovery |
| 0.4 | Record: daily_races coverage; `backtest_cr_data` row count (info only) | [x] | 32252/32252; no `backtest_cr_data` table |
| 0.5 | Fix trainer seed names / drop advance INSERT if columns missing | [x] | NI/UAE/SG in Task 1; `Charles Hills` in Task 2; keep advance INSERT |
| 0.6 | Commit NOTES (master plan Task 0 Step 4) | [ ] | |

**Gate:** Do **not** start Task 1 until 0.3 is filled in.

---

## Phase 1 — Database (server-first)

| Task | Files | Verify | Done | Notes |
| --- | --- | --- | --- | --- |
| **1** | `mordin_functions.sql`, `tests/mordin_functions_TEST.sql` | Test script: `failures = 0` | [x] | VPS `coolwed1_wp364`: checks **77**, failures **0** (2026-11-03) |
| **2** | `mordin_seed_tables.sql`, `tests/mordin_seed_TEST.sql` | 60 matrix rows; trainers match DB | [ ] | Repo ready 2026-11-03; `Charles Hills` (not Charlie); VPS deploy + test pending |
| **3** | `mordin_history_UPDATE.sql` | `CALL mordin_history_UPDATE()`; G1 sample sane | [x] | VPS 2026-11-03: **32226** races, **307609** runs; G1/trainer SR OK; **no_tier 6305 (~19.6%)** — see NOTES |
| **4** | `mordin_runner_flags_UPDATE.sql` | `leaks = 0`; today’s card spot check | [x] | VPS 2026-11-03: **leaks 0**; aggregates OK; today **C67_BOTTOM** / **C4_LOWMID** vs titles; hcap **outclassed** 0/1/NULL |
| **5** | `mordin_calibration_REPORT.sql` | Paste calibration into NOTES | [x] | VPS 2026-11-03: **50** rows ok; LISTED vs C2_OPEN SR reversal (small n); no matrix change |
| **6** | `RUNME_UpdateCall.sql` | Full nightly run includes both Mordin calls | [x] | VPS 2026-11-03: full `CALL RUNME_UpdateCall()`; both Mordin **completed at** status rows |

**Milestone:** After Task 4, `SELECT * FROM mordin_runner_flags WHERE meeting_date = CURDATE() LIMIT 20` is meaningful without WordPress.

**Deploy reminder:** Load each `.sql` file into the DB, then `CALL` procedures. Task 6 adds them to the nightly chain.

---

## Phase 2 — App (theme + tests)

| Task | Files | Verify | Done | Notes |
| --- | --- | --- | --- | --- |
| **7** | `inc/mordin-class.php`, `tests/mordin-class-test.php` | `php tests/mordin-class-test.php` all ok | [x] | Local 2026-11-03: **27** checks, exit **0** |
| **8** | `functions.php`, `inc/runner-card-wide.php`, `race-detail-premium.css` | Staging race page pills; `git add -p functions.php` | [x] | Repo wired 2026-11-03; staging spot-check pending |
| **9** | `inc/system-builder.php`, `system-builder.js` | Backtest e.g. `not_outclassed`; save/load filters | [ ] | |
| **10** | `mordin_class_NOTES.md`, maybe trim badges in `inc/mordin-class.php` | Backtest verdict table; badges match ROI rules | [ ] | |

**Milestone:** Task 9 — Mordin rules appear in System Builder and affect historic ROI using `mordin_runner_flags`, not FSr backtest.

---

## Phase 3 — Optional (after Task 10)

| Task | Description | Done | Notes |
| --- | --- | --- | --- |
| **11** | Winner competitiveness via `separated_comments_win_value` (not `backtest_cr_data`) | [ ] | Compare to margin-only in calibration |

---

## Quick reference — what works without `backtest_cr_data`

| Capability | Blocked? |
| --- | --- |
| Mordin module (this build) | No |
| Today’s card FSr (`daily_comment_ratings`) | No |
| Rebuild phrase win-% tables | No |
| Historic System Builder FSr / FSr rank | Partial (legacy rows only) |
| Horse history FSr column | Partial (legacy rows only) |

---

## Session log

Append dated lines as you go:

```
2026-11-02 — Build tracker created. Awaiting Task 0 discovery.
2026-11-02 — Task 0 discovery written to `mordin_class_NOTES.md` (commit 0.6 pending).
2026-11-02 — Task 1: added `mordin_functions.sql` + `tests/mordin_functions_TEST.sql` (NI→GB; +6 discovery/daily age_range checks → 77 total).
2026-11-03 — Task 1 verified on server: `mysql … < tests/mordin_functions_TEST.sql` → checks 77, failures 0.
2026-11-03 — Task 2: added `mordin_seed_tables.sql` + `tests/mordin_seed_TEST.sql` (trainer seed `Charles Hills` per NOTES).
2026-11-03 — Task 3: added `mordin_history_UPDATE.sql` + `tests/mordin_history_VERIFY.sql` (cumulative `distance_beaten` window sum per NOTES).
2026-11-03 — Task 3 verified on VPS: CALL OK; VERIFY G1 margins/ratings + Appleby elite; flag no_tier 6305/32226.
2026-11-03 — Task 4: added `mordin_runner_flags_UPDATE.sql` + `tests/mordin_runner_flags_VERIFY.sql` (5yr incremental + today/advance; v_logic_version 1).
2026-11-03 — Task 4 verified on VPS: VERIFY leaks **0**; flag totals sane; 2026-11-03 card tiers match handicap/novice/maiden titles.
2026-11-03 — Task 5: added `mordin_calibration_REPORT.sql` + `tests/mordin_calibration_VERIFY.sql` (5yr tiered races → `mordin_class_calibration`).
2026-11-03 — Task 5 verified on VPS: calibration **50** rows; gap notes in `mordin_class_NOTES.md` § Calibration.
2026-11-03 — Task 6: guarded `mordin_history_UPDATE` + `mordin_runner_flags_UPDATE` in `RUNME_UpdateCall.sql` (after `daily_sires_insights_UPDATE`, before final status).
2026-11-03 — Task 6 verified on VPS: redeployed procedure; full nightly chain OK (both Mordin calls ran).
2026-11-03 — Task 7: added `inc/mordin-class.php` + `tests/mordin-class-test.php` (stamina, badges, SB row check).
2026-11-03 — Task 7 verified locally: `php tests/mordin-class-test.php` → all ok, exit 0.
2026-11-03 — Task 8: racecard Mordin pills (require + lookup + badges in `functions.php`, `runner-card-wide.php`, CSS tones).
```

---

## Start command

When ready in chat: **“start Task 0”** (with discovery output pasted) or **“start Task 1”** if NOTES are complete.
