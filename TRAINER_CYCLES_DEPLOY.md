# Trainer Cycles — deploy and performance

## Staging target

After deploy, cold-cache macro load (`/trainer-cycles/` or `fhor_tc_dashboard` AJAX with transients flushed) should complete in **under 3 seconds** end-to-end. Inspect response `meta`:

- `ms` — total PHP time for the request
- `sql_ms` — sum of Query A/B SQL only (present on cache **miss**)
- `sql` — per-query breakdown (`macro_baselines`, `monthly_buckets`, …)
- `cache` — `hit` | `miss` | `bypass`

If `sql_ms` alone exceeds ~2500 ms, tune before production (see below).

## wp-config overrides

Define these **before** the theme loads (typically `wp-config.php`):

```php
// Smaller macro pool (faster Query B); default 40.
define('FHOR_TC_MACRO_TOP_N', 36);

// Longer transient TTL; defaults 12 hours.
define('FHOR_TC_MACRO_CACHE_TTL', 12 * HOUR_IN_SECONDS);
define('FHOR_TC_PROFILE_CACHE_TTL', 12 * HOUR_IN_SECONDS);

// Optional: limit historic scan when DB is large (also baked into cache keys).
define('FHOR_TC_DATE_FROM', '2018-01-01');
```

Other tunables: `FHOR_TC_MIN_MONTH_RUNNERS`, `FHOR_TC_MACRO_MIN_LIFETIME_RUNNERS`, spotlight thresholds — see `inc/trainer-cycles.php`.

## Cache invalidation

After code deploy that changes SQL, metrics, or client bootstrap JSON:

```bash
wp eval 'echo fhor_tc_bump_cache_ver();'
```

Or increment WordPress option `fhor_tc_cache_ver`. Macro, profile, and slug-index transients include this version in their keys.

Bump is **optional** when only changing tunables already embedded in cache keys (TOP_N, min runners, etc.).

## Verify on staging

1. `wp transient delete --all` (or delete keys matching `fhor_tc_` / `trainer_cycles` namespace).
2. Load `/trainer-cycles/` once — note `meta.sql_ms` in page source (`window.fhorTcDashboard.meta`) or Network → `fhor_tc_dashboard`.
3. Reload — expect `meta.cache` = `hit` and no `sql_ms`.
4. Repeat with `?code=flat`.

## Phase 4.3 QA lab

After deploy, open **`/trainer-cycles-qa/`** (administrators, or `FHOR_TC_QA_OPEN` on staging). Shortcode: `[trainer_cycles_qa]`.

- **Self-tests** — slug rules, small-month eligibility, non-runner SQL fragment, slug index sample.
- **Cache probe** — double `fhor_tc_get_macro_pool` call; second response should show `cache: hit`.
- **Release checklist** — jumps/flat, profile slug, small months, non-runners, transient hit, perf, a11y, CLI unit test.

Rewrite flush runs once via option `fhor_tc_qa_rewrite_flushed`; re-save permalinks if the route 404s.

## Local tests (no DB)

```bash
php tests/trainer-cycles-test.php
```
