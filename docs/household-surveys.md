# Household Management + Surveys (Phase 3)

Branch: `feature/household-surveys`

Delivers the document's Phase 3: **Data Rumah** (detail page), **Pendataan Rumah** (survey input), **Status Memilah**, **Riwayat Pendataan** (history) and **Validasi Data** (validation).

## 1. Install

```powershell
git checkout develop
git pull
git checkout -b feature/household-surveys
```

Extract the zip into the project root. Windows will ask about 5 existing Phase 2 files; choose **Replace**:

| File | What changed |
| --- | --- |
| `app/Models/Household.php` | adds `surveys()` and `refreshSortingStatus()` |
| `app/Http/Controllers/HouseholdController.php` | adds `show()` (detail page) |
| `routes/modules/households.php` | enables the `show` route |
| `resources/views/households/index.blade.php` | code and a **View** button link to the detail page |
| `lang/id.json` | adds the new Indonesian text (keeps everything before) |

If you edited any of those files yourself, merge by hand instead of replacing (or send them to me).

The tests that upload photos need PHP's **gd** extension. Check: `php -m | Select-String gd`. If it is missing, enable `extension=gd` in `php.ini`.

```powershell
php artisan optimize:clear
php artisan test
php artisan db:seed --class=DemoSurveySeeder     # optional, local only, run once (after DemoDataSeeder)
php artisan serve                                # terminal 1
npm run dev                                      # terminal 2
```

No `storage:link` is needed: photos are private (see below).

## 2. How the workflow works

```
Petugas / Admin records a survey  ->  PENDING
Admin validates                   ->  VALIDATED  -> household status updated
Admin rejects (notes required)    ->  REJECTED   -> Petugas edits & saves -> back to PENDING
Admin reopens a validated/rejected survey -> PENDING (household status recalculated)
```

| Who | Can do |
| --- | --- |
| Petugas | create surveys; edit/delete **their own** while pending or rejected |
| Admin / Super Admin | everything above on any survey, plus validate, reject, reopen |
| Pimpinan (viewer) | no access to surveys |

## 3. Rules worth knowing

- **Status Memilah is derived, never typed.** A household's status = the status of its **latest validated** survey (newest survey date wins, so validating an old survey later cannot overwrite a newer result). No validated survey = "Not surveyed".
- **Validated surveys are locked** for everyone, including Super Admin. To correct one: *Reopen*, edit, validate again.
- **A survey cannot be moved to another household.** Delete it and create a new one.
- **Survey dates cannot be in the future.**
- **Photos are private.** Stored in `storage/app/private/survey-photos` and shown only through a login-protected route (JPG/PNG/WebP, max 4 MB). Deleting a survey or replacing/removing its photo deletes the file.
- **Deleted households** keep their survey history readable.

## 4. Manual check list

1. As `petugas@banksampah.test`: open **Households**, click a household, press **New survey**, save with a photo. It appears as *Pending*; the household still shows *Not surveyed*.
2. As `admin@banksampah.test`: open **Surveys**, filter *Pending*, open it, press **Validate**. The household now shows *Sorting* (or *Not sorting*).
3. Create a second survey for the same household with an older date and validate it. The status does not change.
4. Reject a survey without notes: refused. With notes: rejected. As the petugas, edit it: it returns to *Pending*.
5. Try to edit a validated survey: refused. **Reopen** it, then edit.
6. As `pimpinan@banksampah.test`: the Surveys menu is not shown, and the URL gives 403.

## 5. For the next phases

- Phase 5/6/7 can count "Rumah Memilah" with `Household::where('sorting_status', 'sorting')`.
- Pending surveys (`HouseholdSurvey::where('validation_status', 'pending')`) are a natural dashboard card.
