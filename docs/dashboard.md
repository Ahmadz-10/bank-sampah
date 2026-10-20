# Dashboard (Phase 6)

Branch: `feature/dashboard`

Delivers the real dashboard (it replaces the placeholder): number cards, three charts (reduction vs target, sorting households, waste per category), the latest deposits and sales, and the pending-survey queue, each shown only to the roles that may see it. Covers tasks P6-BE-1 to 4, P6-FE-1 to 4 and P6-SH-2; the contract of P6-SH-1 (decision **D2**, cards per role) is sections 2 and 3 below. No migration and no new routes: it is the existing `/dashboard` page.

## 1. Install

```powershell
git checkout feature/monitoring-frontend
git checkout -b feature/dashboard
```

This branch builds on the chart work, so commit `feature/monitoring-frontend` first (`git status` should say the working tree is clean). If that branch has been merged into `develop` by the time you read this, branch from `develop` instead.

Extract the zip into the project root. Windows will ask about 10 existing files; choose **Replace** (these are Phase 5 files that the dashboard extends; if you edited any of them since the chart update, merge by hand instead):

| File | What changed |
| --- | --- |
| `app/Http/Controllers/DashboardController.php` | the placeholder now asks `DashboardService` for the numbers |
| `app/Services/MonitoringService.php` | adds `monthlyRows()` and `reductionChart()` (moved here from the Waste Reduction controller so both pages draw the same chart) |
| `app/Http/Controllers/WasteReductionController.php` | uses `MonitoringService::reductionChart()`; behaviour unchanged |
| `resources/views/dashboard.blade.php` | the dashboard page |
| `resources/views/components/line-chart.blade.php` | now a thin wrapper of `<x-chart type="line">`, so the Waste Reduction page keeps working |
| `resources/js/charts.js` | adds bar and doughnut charts; the chart attribute is now `data-chart` |
| `lang/id.json` | adds Indonesian text for the dashboard (keeps all earlier text; drops the "will be added in Phase 6" placeholder) |
| `tests/Feature/Monitoring/WasteReductionTest.php` | one assertion follows the `data-chart` rename |
| `docs/monitoring.md`, `docs/monitoring-chart.md` | notes updated: the dashboard now uses the chart component |

New files: `app/Services/DashboardService.php`, `resources/views/components/chart.blade.php` (the shared chart), `resources/views/components/stat-card.blade.php` (a number card), three test files in `tests/Feature/Dashboard/`, and this document. No other manual edits are needed. No new npm package either, but `charts.js` changed, so the assets must be rebuilt.

Then:

```powershell
php artisan optimize:clear
php artisan test                                  # 202 tests, 19 of them new
php artisan serve                                 # terminal 1
npm run dev                                       # terminal 2   (or: npm run build)
```

The dashboard is only worth looking at with data. Each of these is optional, local only and run once; skip any you already ran in earlier phases:

```powershell
php artisan db:seed --class=DemoDataSeeder
php artisan db:seed --class=DemoSurveySeeder
php artisan db:seed --class=DemoTransactionSeeder
php artisan db:seed --class=DemoWeighingSeeder
```

## 2. Who sees what (decision D2)

The rule: **a block is shown when the user may already open the page behind it.** Pimpinan (viewer) can open none of those pages but reads the reports, so `view-reports` also unlocks the read-only totals and charts. Rows with names (recent transactions) and the survey queue are operational, so they need the matching ability.

| Block | Shown when the user can | Super Admin | Admin | Petugas | Pimpinan |
| --- | --- | :-: | :-: | :-: | :-: |
| Households card, sorting households chart | `manage-households` or `view-reports` | yes | yes | yes | yes |
| Active customers card | `manage-customers` or `view-reports` | yes | yes | yes | yes |
| Deposits, Sales and Stock cards, waste per category chart | `manage-transactions` or `view-reports` | yes | yes | yes | yes |
| Reduction vs target chart | `manage-monitoring` or `view-reports` | yes | yes | no | yes |
| Recent deposits and recent sales | `manage-transactions` | yes | yes | yes | no |
| Pending surveys card | `survey-households` | yes | yes | yes | no |

A card is a link only when the user may open the page it points to, so Pimpinan's cards are plain numbers. Data for a hidden block is not even queried.

## 3. Behaviour and design decisions

- **D2 is a default, not a final answer.** It follows the permission matrix you already have, so no new abilities were needed. *Tell me if Pimpinan should also see the latest transactions, or Petugas the reduction chart; each is one line in `DashboardService::forUser()`.*
- **"This month" is the calendar month of the deposit or sale date**, the same date the Deposits and Sales filters use. The Deposits and Sales cards link to those lists with that month's range, so the list's totals bar shows the same figures as the card.
- **Voided deposits and sales never count**, in any card, chart or list on the dashboard.
- **Stock and the category chart count kg only.** A category in liters or pieces cannot be added to kilograms, so it is left out of the stock card and the chart (the deposit and sale totals still include everything, exactly like the list pages).
- **Waste per category** shows this month's top 8 categories by weight, largest first.
- **The reduction chart is the same one as on the Waste Reduction page** (`MonitoringService::reductionChart()`), for the current year, with gaps for months not weighed.
- **Not cached.** The task allowed an optional 5-minute cache. A cache would show a stale total right after someone saves a deposit, which looks like a bug, and the queries are small. *Tell me if you want it once the real data is large.*
- **Shared chart component.** `<x-chart type="line|bar|doughnut" ...>` replaces the line-only component; `<x-line-chart>` still works. Charts follow the user's light/dark theme, and an empty chart is replaced by a short message and not loaded at all.
- **Zero states.** Cards show 0, lists say "No deposits yet.", charts say what is missing.
- **Automatic checks.** `DashboardServiceTest` covers the numbers (month edges, voided, stock, ordering), `DashboardPageTest` covers what each role sees and compares the dashboard with the Households, Customers, Deposits and Sales list pages (task P6-SH-2), `ChartComponentTest` covers the chart types.

## 4. Manual check list

1. Log in as `admin@banksampah.test` (password `password`). With demo data loaded you see cards for Households, Active customers, Deposits this month, Sales this month, Stock in hand and Pending surveys; the reduction line chart; the sorting doughnut; the category bars; and the two "Recent" lists.
2. Click **Deposits this month**: the Deposits list opens on this month and its totals bar matches the card (count, kg, Rp). Do the same with **Sales this month**.
3. Click **Households** and **Pending surveys**: they open the Households list and the Surveys list (filtered to Pending); the counts match the card.
4. Hover the charts: tooltips show values with an Indonesian decimal comma. The category bars are sorted largest first, with kg on the axis.
5. Void a deposit from this month (Deposits, open it, void) and reload the dashboard: the deposit count and kg drop, the category bars update, and it disappears from Recent deposits.
6. Log in as `petugas@banksampah.test`, save a new deposit, open the dashboard: it appears in Recent deposits and the cards changed straight away. There is **no** reduction chart.
7. Log in as `pimpinan@banksampah.test`: you see the cards and all three charts, but **no** Recent lists and **no** Pending surveys, and the cards are not clickable.
8. Dark mode: `php artisan tinker`, then `\App\Models\User::where('email', 'admin@banksampah.test')->update(['theme' => 'dark']);` and reload. All three charts switch to light text. Set it back to `'light'` afterwards.
9. Make the browser window phone-narrow: the cards sit two per row, the charts shrink to fit, nothing scrolls sideways, and the Recent lists stay readable.

## 5. Known limits

- **The page layout was not viewed in a real browser.** The charts were tested headless in light and dark with demo-like data, and the numbers were checked by tests, but the card grid and the phone layout use standard Bootstrap classes that I could not look at. Checks 1 and 9 are where to confirm them; tell me if something looks off.
- **Always the current month and year.** There is no date picker on the dashboard; other periods belong to Reports (Phase 7).
- **No per-RW comparison chart** in this phase (the data exists in the weighing sheets if you want it later).
- **Kg only** for stock and the category chart (see above); liter and piece categories are not charted.
- The category chart shows the top 8 only.
