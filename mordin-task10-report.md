# Mordin Task 10 backtest report

- **Generated:** 2026-11-03T11:42:10+00:00
- **Lookback days:** 1825 (2021-11-03 → 2026-11-02)
- **Premium lookback:** yes (max 1825 days)
- **Engine:** `fhor_sb_run_backtest()` / Industry SP

## Verdict table (paste into `mordin_class_NOTES.md`)

| Rule | Bets | Strike % | ROI % | Baseline ROI % | Verdict |
| --- | ---: | ---: | ---: | ---: | --- |
| not_outclassed | 11972 | 10.9 | -25.5 | -26.7 | Drop badge |
| class_ok | 2737 | 18.3 | -18.9 | -17.6 | Drop badge |
| not_juv_eliminated | 0 | 0.0 | 0.0 | 0.0 | Drop badge |
| not_northern | 0 | 0.0 | 0.0 | 0.0 | Drop badge |
| clear_2nd · Clear 2nd (maidens, age band 2) | 0 | 0.0 | 0.0 | 0.0 | Drop badge |
| clear_2nd · Clear 2nd (maidens, age band 3) | 0 | 0.0 | 0.0 | 0.0 | Drop badge |
| debut_pocket · Debut pocket (Yarmouth) | 0 | 0.0 | 0.0 | 0.0 | Drop badge |
| debut_pocket · Debut pocket (Salisbury) | 0 | 0.0 | 0.0 | 0.0 | Drop badge |
| group_trainer | 4970 | 18.7 | -8.4 | -17.6 | Drop badge |
| stamina_ok | 5851 | 12.9 | -24.1 | -24.3 | Drop badge |
| dosage_target | 5358 | 13.2 | -23.1 | -24.3 | Drop badge |

## Detail

### not_outclassed — Not outclassed (handicaps)

- Polarity: **negative** · Mordin rule `not_outclassed`
- Baseline: **17701** bets, **9.8%** strike, **-26.7%** ROI
- With rule: **11972** bets, **10.9%** strike, **-25.5%** ROI
- Removed: **5729** bets, ROI **-29%**, gap **3.5**
- **Warning:** scan cap hit — tighten filters or shorten lookback.
- **Badge:** Drop badge — Removed vs remainder gap 3.5 (need ≥10)

### class_ok — Won at this class or higher

- Polarity: **positive** · Mordin rule `class_ok`
- Baseline: **17930** bets, **11.5%** strike, **-17.6%** ROI
- With rule: **2737** bets, **18.3%** strike, **-18.9%** ROI
- **Badge:** Drop badge — ROI +-1.3 pts vs baseline (need +10)

### not_juv_eliminated — No nursery/claimer/seller form (2yo Group)

- Polarity: **negative** · Mordin rule `not_juv_eliminated`
- Baseline: **0** bets, **0%** strike, **0%** ROI
- With rule: **0** bets, **0%** strike, **0%** ROI
- Removed: **0** bets, ROI **n/a**, gap **n/a**
- Note: Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time. Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time.
- **Badge:** Drop badge — Removed 0 bets (< 50)

### not_northern — No northern minor form (2yo G1)

- Polarity: **negative** · Mordin rule `not_northern`
- Baseline: **0** bets, **0%** strike, **0%** ROI
- With rule: **0** bets, **0%** strike, **0%** ROI
- Removed: **0** bets, ROI **n/a**, gap **n/a**
- Note: Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time. Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time.
- **Badge:** Drop badge — Removed 0 bets (< 50)

### clear_2nd_age2 — Clear 2nd (maidens, age band 2)

- Polarity: **positive** · Mordin rule `clear_2nd`
- Baseline: **0** bets, **0%** strike, **0%** ROI
- With rule: **0** bets, **0%** strike, **0%** ROI
- **Warning:** scan cap hit — tighten filters or shorten lookback.
- Note: Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time. Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time.
- **Badge:** Drop badge — With-rule bets 0 (< 100)

### clear_2nd_age3 — Clear 2nd (maidens, age band 3)

- Polarity: **positive** · Mordin rule `clear_2nd`
- Baseline: **0** bets, **0%** strike, **0%** ROI
- With rule: **0** bets, **0%** strike, **0%** ROI
- **Warning:** scan cap hit — tighten filters or shorten lookback.
- Note: Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time. Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time.
- **Badge:** Drop badge — With-rule bets 0 (< 100)

### debut_pocket_yarmouth — Debut pocket (Yarmouth)

- Polarity: **positive** · Mordin rule `debut_pocket`
- Baseline: **0** bets, **0%** strike, **0%** ROI
- With rule: **0** bets, **0%** strike, **0%** ROI
- Note: Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time. Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time.
- **Badge:** Drop badge — With-rule bets 0 (< 100)

### debut_pocket_salisbury — Debut pocket (Salisbury)

- Polarity: **positive** · Mordin rule `debut_pocket`
- Baseline: **0** bets, **0%** strike, **0%** ROI
- With rule: **0** bets, **0%** strike, **0%** ROI
- Note: Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time. Runners loaded but no horse passed every rule. Widen FSr rank, clear optional filters, then tighten one step at a time.
- **Badge:** Drop badge — With-rule bets 0 (< 100)

### group_trainer — Group trainer (15%+)

- Polarity: **positive** · Mordin rule `group_trainer`
- Baseline: **17930** bets, **11.5%** strike, **-17.6%** ROI
- With rule: **4970** bets, **18.7%** strike, **-8.4%** ROI
- **Badge:** Drop badge — ROI +9.2 pts vs baseline (need +10)

### stamina_ok — No stamina doubt (10f+ Class 1)

- Polarity: **negative** · Mordin rule `stamina_ok`
- Baseline: **5869** bets, **12.8%** strike, **-24.3%** ROI
- With rule: **5851** bets, **12.9%** strike, **-24.1%** ROI
- Removed: **18** bets, ROI **-100%**, gap **75.9**
- **Badge:** Drop badge — Removed 18 bets (< 300)

### dosage_target — DI ≤ 3.30 (10f+ Class 1)

- Polarity: **positive** · Mordin rule `dosage_target`
- Baseline: **5869** bets, **12.8%** strike, **-24.3%** ROI
- With rule: **5358** bets, **13.2%** strike, **-23.1%** ROI
- **Badge:** Drop badge — ROI +1.2 pts vs baseline (need +10)

