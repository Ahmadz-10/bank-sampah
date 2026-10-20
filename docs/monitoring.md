# Waste Reduction Monitoring (Phase 5), rebuilt around the kelurahan's workbook

Branch: `feature/monitoring`

This phase was re-scoped after reading `Buku_laporan_sampah.xlsx` (the kelurahan's weighbridge report). It adds three things: **RWs as real records**, a **monthly weighing sheet** (one editable row per RW, like the workbook) and a **monthly summary** that is *calculated* from the weighings. It also replaces my sample waste categories with the **real price list**.

## 1. Install

```powershell
git checkout develop
git pull
git checkout -b feature/monitoring
```

Extract the zip into the project root. Windows will ask about **20 existing files** from earlier phases; choose **Replace**:

| Area | Files replaced |
| --- | --- |
| Households use an RW link | `app/Models/Household.php`, `app/Http/Controllers/HouseholdController.php`, `app/Http/Requests/HouseholdRequest.php`, `database/factories/HouseholdFactory.php`, `resources/views/households/{form,index,show}.blade.php` |
| Categories get a group | `app/Models/WasteCategory.php`, `app/Http/Controllers/WasteCategoryController.php`, `app/Http/Requests/WasteCategoryRequest.php`, `database/factories/WasteCategoryFactory.php`, `resources/views/waste-categories/{form,index}.blade.php` |
| Real price list | `database/seeders/WasteCategorySeeder.php` |
| Menu, permissions, texts | `config/menu.php`, `config/permissions.php`, `lang/id.json` |
| Tests that changed with the above | `tests/Feature/Auth/RoleAccessTest.php`, `tests/Feature/MasterData/HouseholdCrudTest.php`, `tests/Feature/MasterData/WasteCategoryTest.php` |

If you edited any of those yourself, merge by hand (or send them to me).

```powershell
php artisan migrate
php artisan db:seed --class=WasteCategorySeeder
php artisan optimize:clear
php artisan test
php artisan db:seed --class=DemoWeighingSeeder     # optional, local only, run once
php artisan serve                                   # terminal 1
npm run dev                                         # terminal 2
```

Use `migrate`, **not** `migrate:fresh`, if you already have data you want to keep.

What the four new migrations do:

1. Create `rws` and insert **RW 01 to RW 11** (reference data, so every environment has them).
2. Add `households.rw_id` and **convert the old free-text RW** (`002`, `RW 02`, `2` all become RW 02). Values that match no RW (for example `12`) stay unlinked and can be fixed on the household's edit page. The old `rw` text column is kept by that migration so no data is lost, then removed by `2026_10_15_000001_drop_legacy_rw_column_from_households_table` (a leftover `rw` column shadows the `rw()` relation, so `$household->rw` would return null).
3. Add `waste_categories.waste_group`. Existing categories default to *Anorganik*.
4. Create `rw_waste_reports` (one row per RW per month).

The seeder loads the six real categories and prices. It **updates** `PLS`, `KRT`, `LGM` and **adds** `ORG`, `B3`, `RSD`, each with a new price effective today (old prices stay as history). The old sample categories (`KRD`, `KCA`, `MNJ`) are not deleted: deactivate them under Waste Categories if you do not need them.

## 2. What is new

| Screen | Who | Purpose |
| --- | --- | --- |
| **RWs** (Master Data) | Admin | RW 01-11 with chairman name and the official number of households (Jumlah KK). RWs are deactivated, never deleted |
| **Weighing Sheet** (Monitoring) | Admin, Petugas | Pick a month; one editable row per RW: kg/day for Organik, Anorganik, B3, Residu, plus carts, collectors, pickup frequency, days counted and remarks. Totals update as you type |
| **Waste Reduction** (Monitoring) | Admin | Twelve months of one year: total generated, diverted, to TPS, reduction %, target %, and the weight recorded through bank sampah deposits as a cross-check. Only the **target** and a note can be edited. A line chart above the table plots reduction % against target % for the year |
| Waste Categories | Admin | New **waste group** field (Organik / Anorganik / B3 / Residu) |
| Households | Admin, Petugas | RW is now a dropdown of the real RWs, and the household list shows the RW name |

## 3. How the workbook maps to the system

| Workbook column | In the system |
| --- | --- |
| RW | `rws.number`, shown as "RW 01" |
| Nama Ketua RW | `rws.chairman_name` |
| Jumlah KK | `rws.household_count` (official figure; the RWs page also shows how many households are registered in the app) |
| Organik / Anorganik / B3 / Residu (kg/hari) | `rw_waste_reports.*_kg_per_day`, entered monthly |
| Total (kg/hari) | **calculated**, never typed |
| Gerobak, Petugas, Frekuensi Angkut, Hari/Bulan | `carts`, `collectors`, `pickup_frequency`, `days_counted` (blank = the real number of days in the month) |
| Terangkut (kg/bulan) | **calculated**: total kg/day x days counted |
| Keterangan | `notes` |
| Price sheet (Harga Beli / Harga Jual) | Waste Categories and their prices, with the new waste group |

## 4. How the monthly figures are calculated

From the weighings of a month (kg per day x days counted, added over all RWs):

```
total_waste_generated = all four groups
waste_diverted        = Organik + Anorganik
waste_to_tps          = Residu
reduction %           = waste_diverted / total_waste_generated x 100
```

B3 (hazardous) counts in the total only. The groups are listed in **`config/monitoring.php`**, and the percentage is calculated in one place (`MonitoringService::percentage`), so a different rule is a one-line change. Saving the sheet recalculates the month automatically. A new month inherits the previous month's target.

**Deliberate differences from the workbook's formulas** (all of its formulas were inconsistent):

- *Pickup frequency is information only.* The workbook multiplied the transported weight by it and by 30 days, about 900 times too much.
- *Totals are always calculated*, never typed. In the workbook "Total kg/hari" was a formula in only 4 of 11 rows, and one cell referred to itself.
- *Days default to the real month length* (28 to 31), not a fixed 30.

## 5. Please confirm with the kelurahan

These are **defaults I chose**, not facts. Until confirmed, treat the percentages as provisional.

1. What does "Terangkut kg/bulan" really mean, and what is "Frekuensi Angkut" (times per day, week or month)?
2. Which groups count as **diverted** and which as **to TPS**? (Default: Organik + Anorganik diverted, Residu to TPS, B3 neither.)
3. Is the report monthly, as built, or yearly?
4. Are B3 and Residu ever deposited or sold? They are priced at 0, but they still appear in the deposit and sale forms.
5. Who enters the weighings? (Default: Admin and Petugas. Only Admin sets targets and manages RWs.)

## 6. Manual check list

1. **RWs:** RW 01 to 11 are listed. Fill in a chairman name and Jumlah KK for RW 01.
2. **Households:** edit a household and pick its RW. The list shows "RW 02". Old households were converted by the migration.
3. **Categories:** Organik, Plastik, Kertas, Logam, B3 and Residu exist with the real prices. Open **Waste Prices** to see them.
4. **Weighing Sheet:** fill RW 01 and RW 02 (try 100 / 50 / 2 / 80 for RW 01). Row totals update while typing. Save.
5. **Waste Reduction:** the month now shows total, diverted, to TPS and the reduction %. Set a target and see *Target met* or *Below target*. Above the table the chart shows *Pengurangan* (solid) and *Target* (dashed) for each weighed month; months with no weighing are left blank. Pick a year with no data and the chart is replaced by a short message.
6. Clear RW 02's row and save: it disappears from the count, and the month recalculates.
7. As `petugas@banksampah.test`: Weighing Sheet works; RWs and Waste Reduction are not in the menu (403 by URL). As `pimpinan@`: none of them.

## 7. For the next phases

- **Phase 6 (Dashboard)** can use `MonthlyWasteReduction` for the reduction-vs-target chart and `RwWasteReport` for a per-RW comparison. The dashboard (Phase 6, see `docs/dashboard.md`) now does this, reusing `MonitoringService::reductionChart()` and the shared `<x-chart>` component.
- **Chart library (decision D4):** Chart.js (MIT). Install with `npm install chart.js`. It lives in its own `charts` bundle, loaded only on pages that contain a chart, so other pages are unaffected. The chart reads Bootstrap's colour variables, so it follows the user's light/dark theme.
- **Phase 7 (Reports)** should reproduce the workbook layout as a "Laporan timbangan sampah" for a month, exported as **.xlsx**, because the kelurahan works in Excel. That needs a spreadsheet library (decision D3).
- **Phase 8 (Settings)** should hold the kelurahan, kecamatan and city for report headers.
- The Phase 5 contract in `task-split.md` (hand-typed monthly records and a "suggest" endpoint) is **replaced** by the design above: records are calculated, not typed.

## 8. Known limits

- The old `households.rw` text column has been dropped (it shadowed the `rw()` relation). Households whose old text matched no RW (for example `12`) have no RW and need one picked on the edit page.
- The sheet allows editing any past month. Changes are not logged until Phase 8's activity log exists.
- Weight recorded through deposits is shown for comparison only; it does not enter the formula.

## 8. Update: reduction-vs-target chart (P5-FE-2)

The chart on the Waste Reduction page, the Chart.js decision (D4) and its install steps are in `docs/monitoring-chart.md`.
