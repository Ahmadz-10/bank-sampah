# Monitoring Chart: Reduction vs Target (Phase 5, task P5-FE-2)

Branch: `feature/monitoring-frontend`

Delivers decision **D4 (Chart.js)**, a reusable `<x-line-chart>` Blade component, and a line chart of **reduction % against target %** at the top of the Waste Reduction page. No migration and no new routes: the figures already come from the monthly weighing sheet.

## 1. Install

```powershell
git checkout develop
git pull
git checkout -b feature/monitoring-frontend
```

This assumes `feature/monitoring` (with the RW fix) is already merged into `develop`. If it is not, branch from `feature/monitoring` instead.

Extract the zip into the project root. Windows will ask about 8 existing files; choose **Replace** (these are Phase 5 files that the chart extends; if you edited any of them since the RW fix, merge by hand instead):

| File | What changed |
| --- | --- |
| `app/Http/Controllers/WasteReductionController.php` | builds the chart data: 12 months, `null` where nothing was weighed |
| `resources/views/waste-reductions/index.blade.php` | adds the chart card above the table |
| `package.json`, `package-lock.json` | add `chart.js` (4.5.1, MIT) |
| `vite.config.js` | adds `resources/js/charts.js` as its own entry |
| `lang/id.json` | adds Indonesian text for the chart (keeps all earlier text) |
| `tests/Feature/Monitoring/WasteReductionTest.php` | adds 3 chart tests |
| `docs/monitoring.md` | updates the Waste Reduction notes and the manual check list |

New files: `resources/js/charts.js` (draws the chart), `resources/views/components/line-chart.blade.php` (the reusable component) and this document. No other manual edits are needed.

Then:

```powershell
npm install                  # installs chart.js
php artisan optimize:clear
php artisan test             # 183 tests, 3 of them new
php artisan serve            # terminal 1
npm run dev                  # terminal 2   (or: npm run build)
```

`public/build` is not in the zip (it is git-ignored); `npm run dev` or `npm run build` creates it.

## 2. Who can open what

| Screen | Ability | Roles |
| --- | --- | --- |
| Waste Reduction (with the chart) | `manage-monitoring` | Super Admin, Admin |

Nothing changed here: the chart is part of the existing page, so Petugas and Pimpinan still cannot open it (403 by URL, not in their menu).

## 3. Behaviour and design decisions

- **Library (D4): Chart.js.** MIT licence and the smallest of the three I measured for line + bar + doughnut (about 64 KB gzipped, against 192 KB for ECharts and 271 KB for ApexCharts). ApexCharts is also no longer MIT: it is free only under a revenue limit, which is one more reason to avoid it.
- **Its own bundle.** Chart.js is in a separate `charts` file (about 55 KB gzipped) that only pages containing a chart load, so every other page is unchanged. The component adds that file to the page itself; you never add it by hand.
- **Two lines, not relying on colour.** *Pengurangan* is a solid green line and *Target* a dashed blue one.
- **Gaps, not zeros.** A month with no weighing is left blank. Drawing 0 would look like a real result.
- **The axis starts at 0 and scales to the data**, so a 20-40% range fills the chart instead of sitting in the bottom of a 0-100% axis.
- **Dark mode.** A chart is a canvas, so CSS cannot colour it. It reads Bootstrap's colour variables when it loads, which differ between `data-bs-theme="light"` and `"dark"`, and it redraws if that attribute changes while the page is open.
- **Month labels** are Carbon's short Indonesian names (Jan, Feb, Mar, Apr, Mei, Jun, Jul, Agt, Sep, Okt, Nov, Des). *Tell me if you prefer "Agu" for August.*
- **Empty year.** With no numbers at all, the chart is replaced by a short message and Chart.js is not loaded.
- **Accessibility.** The canvas has `role="img"` and a text description; the table below remains the full text version of the same figures.
- **Reusable.** `<x-line-chart :title :labels :series unit :min :max />`, where each series is `['label' => ..., 'data' => [numbers or null], 'color' => 'success', 'dashed' => false]` and `color` is a Bootstrap theme colour name. Phase 6 adds bar and doughnut by registering the extra Chart.js parts in `resources/js/charts.js`.

## 4. Manual check list

1. Log in as `admin@banksampah.test` (password `password`) and open **Waste Reduction** for a year that has weighings. Above the table you see a solid green *Pengurangan* line and a dashed blue *Target* line, with points only for weighed months.
2. Hover a point: the tooltip shows the values with an Indonesian decimal comma, for example `Pengurangan: 33,75%`.
3. Click **Set target** on a month, change it, save. Back on the page the dashed line follows the new target.
4. Open `/waste-reductions?year=2030` (the year dropdown only lists years that have data). A message replaces the chart (*Belum ada data untuk ditampilkan pada grafik.*).
5. Dark mode: run `php artisan tinker`, then `\App\Models\User::where('email', 'admin@banksampah.test')->update(['theme' => 'dark']);` and reload. Text and gridlines switch to light colours and both lines stay visible. Set it back to `'light'` afterwards.
6. Make the browser window narrow, or use a phone: the chart shrinks to fit and the page does not scroll sideways.
7. Log in as `petugas@banksampah.test`: Waste Reduction is not in the menu and the URL gives 403.

## 5. Known limits

- **Only line charts in this update.** Phase 6 added bar and doughnut charts: the component became `<x-chart type="line|bar|doughnut">` and `<x-line-chart>` is now a thin wrapper that keeps working (see `docs/dashboard.md`).
- **No theme switch screen yet** (the profile page is Phase 8), so you change the theme in the database for now. The live redraw has nothing in the interface to trigger it until then.
- **Tested in a simulated browser** (the built bundle, run headless against the real page HTML, in light and dark), not in Chrome, Firefox or a phone. Checks 1-6 above are the real-browser test.
- Printing and exporting the chart come with Reports (Phase 7).
