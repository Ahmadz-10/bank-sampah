# Applying these files

Copy the `database/` and `docs/` folders into your Laravel project root (overwrite `DatabaseSeeder.php` when asked), then:

```bash
php artisan migrate:fresh --seed     # local only: wipes the database!
```

Demo logins (local only, password `password`): superadmin@ / admin@ / petugas@ / pimpinan@ `banksampah.test`

Then commit on a branch, not main:

```bash
git checkout develop && git pull origin develop
git checkout -b feature/database-schema
git add database docs
git commit -m "Add database schema migrations and base seeders"
git push -u origin feature/database-schema
```
