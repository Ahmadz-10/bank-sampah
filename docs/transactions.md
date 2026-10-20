# Transaction System (Phase 4)

Branch: `feature/transactions`

Delivers the document's Phase 4: **Setoran Sampah**, **Detail Setoran**, **Penjualan Sampah**, **Detail Penjualan** and **Riwayat Transaksi** (the history lists with filters, plus deposit history on each customer).

## 1. Install

```powershell
git checkout develop
git pull
git checkout -b feature/transactions
```

Extract the zip into the project root. Windows will ask about 8 existing files from earlier phases; choose **Replace**:

| File | What changed |
| --- | --- |
| `app/Models/Customer.php` | adds `deposits()` |
| `app/Models/WasteCategory.php` | adds `depositDetails()` and `saleDetails()` |
| `app/Http/Controllers/CustomerController.php` | adds `show()` (customer detail with deposit history) |
| `routes/modules/customers.php` | enables the `show` route |
| `resources/views/customers/index.blade.php` | code and **View** button link to the detail page |
| `config/permissions.php` | adds the `correct-transactions` ability (Admin) |
| `tests/Feature/Auth/RoleAccessTest.php` | adds that ability to the permission matrix |
| `lang/id.json` | adds the new Indonesian text (keeps everything before) |

If you edited any of those yourself, merge by hand instead of replacing (or send them to me).

```powershell
php artisan optimize:clear
php artisan test
php artisan db:seed --class=DemoTransactionSeeder    # optional, local only, run once
php artisan serve                                    # terminal 1
npm run dev                                          # terminal 2
```

The demo seeder needs `DemoDataSeeder` (customers) and the waste categories to exist first.

## 2. How it works

**Deposit (setoran).** A Petugas picks a customer and a date and adds one line per waste category with its weight. On save the system:

1. looks up each category's **buy price in effect on the deposit date** (`WasteCategory::priceOn()`),
2. stores that price on the line as a **snapshot** and calculates `subtotal = weight × price`,
3. calculates the header totals from the lines,
4. does all of it in **one database transaction**: either everything is saved or nothing is.

Prices and totals typed in the browser are ignored. The form shows a live *estimate* only.

**Sale (penjualan).** Same shape, except the **price per unit is typed per line** (it is suggested from the price list's reference sell price; selling prices are negotiated). A sale is refused if it would make the stock of any category negative.

**Stock** of a category = weight deposited − weight sold. Voided deposits and sales are ignored. The Sales page shows current stock per category.

## 3. Rules worth knowing

| Rule | Detail |
| --- | --- |
| **No editing** | Saved deposits and sales cannot be edited. A mistake is fixed by **voiding** (an Admin action) and entering it again. This keeps the money trail honest. |
| **Who may do what** | Petugas and Admin record transactions. Only Admin / Super Admin can void and see voided records. Pimpinan has no access. |
| **Voiding a deposit** | Refused if part of it was already sold (stock would go negative). |
| **Voiding a sale** | Always allowed; the weight returns to stock. |
| **Price must exist** | A deposit is refused for a date when a category has no price yet. |
| **Customers and categories** | Deposits need an **active** customer and **active** categories. Sales may still sell a *retired* category that holds stock. |
| **Dates** | Not in the future. |
| **Numbers** | `STR-0001` for deposits, `PNJ-0001` for sales. Automatic, never reused. |
| **Printing** | Each deposit and sale has a **Print** button (the menu is hidden when printing). |

## 4. Decisions I made for you (change any of them)

These were open in the model checklist. I picked safe defaults:

- **Overselling is blocked.** To only warn instead, set `'block_oversell' => false` in `config/transactions.php`.
- **Buyers are free text** (`buyer_name`), no buyer table.
- **No withdrawals / customer balance.** Your document has no such module, so this phase does not add one. Tell me if customers should withdraw money.
- **Void instead of edit.** If you would rather allow editing, say so; it needs rules for re-pricing and re-checking stock.

## 5. Manual check list

1. As `petugas@banksampah.test`: **Deposits → New deposit**. Pick a customer, add two categories with weights. Watch the estimate update; change the date and see the price change. Save.
2. Open the deposit: prices, subtotals and totals are shown. Press **Print**.
3. Open the customer's page: the deposit appears in the history with totals.
4. **Sales → New sale**: current stock is listed. Sell less than the stock: saved, stock drops. Try one gram more than the stock: refused with the category and the shortfall.
5. As `admin@banksampah.test`: void the sale; stock returns. Filter **Voided** to find it.
6. Try to void the deposit after selling most of it: refused with an explanation.
7. As `petugas@`: there is no Void button, and `?status=voided` shows nothing extra. As `pimpinan@`: Deposits and Sales are not in the menu (403 by URL).

## 6. For the next phases

- **Monitoring (Phase 5)** can fill `waste_diverted` from deposit weights: `WasteDepositDetail` joined to `WasteDeposit` (not voided) per month.
- **Dashboard / Reports (Phases 6–7)** can reuse `StockService::levels()` and the totals queries in the two index controllers.
- Phase 8's activity log should record deposit/sale creation and voiding (`TransactionService` is the single place to hook into).

## 7. Known limits

- Stock is the overall balance, not "as of the sale date", so back-dated sales are checked against today's stock.
- Two people saving at the *same instant* could in theory get the same number (the second save then fails with an error and can be repeated). Very unlikely at this scale.
- Validation messages are still English (Indonesian validation text comes with Phase 8).
