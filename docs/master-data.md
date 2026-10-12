# Master Data (Phase 2)

Branch: `feature/master-data`

Delivers models, relationships and CRUD screens for: **users, roles, households, customers, waste categories, waste prices**. The tables already exist from the database-schema step.

## 1. Install

```powershell
git checkout develop
git pull
git checkout -b feature/master-data
```

Extract the zip into the project root. Windows will ask about 4 existing files; choose **Replace** (these are Phase 1 files that Phase 2 extends):

| File | What changed |
| --- | --- |
| `config/menu.php` | adds the **Roles** menu item |
| `lang/id.json` | adds Indonesian text for the new screens (keeps all Phase 1 text) |
| `resources/views/layouts/app.blade.php` | adds an error alert and the delete-confirmation script |
| `resources/views/layouts/guest.blade.php` | loads `resources/css/app.css` |

No other manual edits are needed. Routes in `routes/modules/` load automatically, and the permissions already exist from Phase 1.

Then:

```powershell
php artisan optimize:clear
php artisan test
php artisan db:seed --class=DemoDataSeeder     # optional, local only, run ONCE
php artisan serve                              # terminal 1
npm run dev                                    # terminal 2
```

## 2. Who can open what

| Screen | Ability | Roles |
| --- | --- | --- |
| Households, Customers | `manage-households`, `manage-customers` | Super Admin, Admin, Petugas |
| Waste Categories, Waste Prices | `manage-master-data` | Super Admin, Admin |
| Users, Roles | `manage-users` | Super Admin |

Sidebar links appear automatically per role. Viewer (Pimpinan) sees only the Dashboard until Reports exist.

## 3. Behaviour and design decisions

- **Auto codes**: households `RMH-0001`, customers `NSB-0001`. Generated on create, not editable, never reused (soft-deleted rows count).
- **Households / customers**: soft-deleted (row stays in the database). A household's *sorting status* is read-only here; Phase 3 (surveys) updates it.
- **Waste categories**: created together with their first price. They are **deactivated, not deleted**, so prices and transaction history stay intact.
- **Waste prices**: one row per price change. `WasteCategory::priceOn($date)` returns the price in effect on a date (default today). **Phase 4 deposits will call this** and store the price on each deposit line. A category must always keep at least one price.
- **Users**: **deactivated, not deleted**. A Super Admin cannot change their own role or deactivate themselves (prevents lock-out). Leave the password blank on edit to keep it.
- **Roles**: the 4 roles are fixed (permissions in `config/permissions.php` refer to their names), so this screen only edits the label and description and shows the read-only permission matrix. *Your document lists "CRUD for roles"; creating or deleting roles would break the permission config, so I limited it to view and edit. Tell me if you want full CRUD with database-driven permissions.*
- **No "show" pages** in this phase: Households get a detail page in Phase 3 (with survey history), Customers in Phase 4 (with deposits).

## 4. Manual check list

1. Log in as `superadmin@banksampah.test` (password `password`). Sidebar shows Households, Customers, Waste Categories, Waste Prices, Users, Roles.
2. Add a household. It gets `RMH-000x`. Search and status filter work.
3. Add a customer linked to that household. It gets `NSB-000x`.
4. Add a waste category with a price. Open Prices, add a newer price, check the Current/Past badges.
5. Try to delete a category's only price. It is refused.
6. Users: create a Petugas, log in as them in a private window; they see only Households and Customers.
7. Try to deactivate or change the role of your own account. It is refused.

## 5. Known limits

- Validation error messages are still English (Laravel's Indonesian validation text arrives with the language work in Phase 8).
- Searching does not yet handle very large datasets specially; it is fine for thousands of rows.
