# PharmaCentral - Simple India Edition

Pharmacy Management System — clean, simple, and working.
- **Head Pharmacist**: Dr. Iqbalhusen
- **Developed by**: Rehan
- **Currency**: Indian Rupee (₹)
- **Tax**: GST @ 12%

---

## Pages Included
- **Dashboard** (index.php)
- **POS Billing** (pos.php)
- **Medicine Stock** (inventory.php)
- **Sales Records** (sales.php)

---

## How to Run

1. Open terminal in this folder.
2. Run: `php -S localhost:8000`
3. Open browser: `http://localhost:8000`

### IMPORTANT: First-Time Setup (Reset Database)
If you already have an old `database.sqlite`, visit:
```
http://localhost:8000/reset_db.php
```
This will wipe the old database and create a fresh one with correct Indian data.
After running, delete `reset_db.php`.
