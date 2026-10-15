# PROJECT-E — Gym Management & POS System
> **Capstone Project Summary** | Last Updated: October 15, 2026

---

## 📌 Overview

**Project-E** is a desktop-first gym management and point-of-sale (POS) system built as a capstone project. It is packaged as an **Electron desktop application** that bundles a local **PHP server** to serve a PHP/SQLite-powered web interface. The system is designed to be used by gym staff and administrators to manage memberships, track attendance via QR code scanning, handle product sales, log expenses, and manage staff records.

---

## 🎯 GOAL: Fully Offline Windows Executable

> **This is the primary objective.** The app must work on any Windows PC with zero internet dependency and zero manual PHP installation. Everything — PHP binary, SQLite DB, and all PHP/HTML files — must be bundled inside the installer.

### Why It Doesn't Work Offline Today
The current build (`dist/PROJECT - E Setup 1.0.0.exe`) calls `php` as a global system command (`cp.spawn('php', ...)` in `main.js`). If PHP is not installed on the target Windows machine, the app will open a blank white screen and nothing will work.

### The Solution: Bundle a PHP Windows Binary
The fix is to ship a **portable PHP for Windows** (`php.exe`) inside the Electron app's `resources/` folder and have `main.js` point to it instead of the system `php` command.

---

### 📋 Step-by-Step Plan for Windows Offline Build

#### Step 1 — Download Portable PHP for Windows
- Go to: https://windows.php.net/download/
- Download the **PHP 8.x Non-Thread Safe (NTS) ZIP** (x64)
- Extract it into a new folder: `resources/php/` inside the project root
- The folder must contain `php.exe` and all required DLLs
- Copy `php.ini-development` → rename to `php.ini`
- In `php.ini`, enable the following extensions (uncomment lines):
  - `extension=pdo_sqlite`
  - `extension=sqlite3`
  - `extension=mbstring`
  - `extension=openssl`
  - Set `extension_dir = "ext"` (relative path)

#### Step 2 — Fix `main.js` to Use Bundled PHP
Replace the current `main.js` with the following logic:

```javascript
// Detect if running from a packaged Electron build or in dev mode
const isPackaged = app.isPackaged;
const phpBinary = isPackaged
    ? path.join(process.resourcesPath, 'php', 'php.exe')   // packaged path
    : path.join(__dirname, 'resources', 'php', 'php.exe'); // dev path

// Spawn the PHP server using the bundled binary
phpServer = cp.spawn(phpBinary, ['-S', '127.0.0.1:8000', '-t', publicPath]);
```

Also fix the database path (current bug — points to wrong file):
```javascript
// WRONG (current):
const dbSource = path.join(__dirname, 'includes', 'database.sqlite');
// CORRECT:
const dbSource = path.join(__dirname, 'database', 'gym.db');
```

#### Step 3 — Fix `package.json` Build Config
The `files` array must include `admin/**/*` (currently missing) and the PHP binary must be added as `extraResources`:

```json
"files": [
  "main.js",
  "index.php",
  "forgotpass.php",
  "reset_pass.php",
  "logout.php",
  "member_profile.php",
  "admin/**/*",
  "pos/**/*",
  "includes/**/*",
  "assets/**/*",
  "database/**/*",
  "exports/**/*",
  "package.json"
],
"extraResources": [
  {
    "from": "resources/php/",
    "to": "php",
    "filter": ["**/*"]
  }
]
```

#### Step 4 — Fix Critical Bugs Before Building
These bugs will cause runtime failures in the packaged app:

| File | Line | Fix |
|---|---|---|
| `pos/check_member.php` | L35 | Replace `NOW()` with `datetime('now')` |
| `member_profile.php` | L52, 56, 65 | Replace `username`→`name`, `expires_at`→`expiry_date` |
| `member_profile.php` | L3 | Fix db.php include path |
| `reset_pass.php` | L10 | Redirect to `forgotpass.php` not `forgot_password.php` |
| `includes/nav.php` | L15 | Fix hardcoded `/capstone/` path and dead `dashboard.php` link |

#### Step 5 — Build the Windows Installer
Run on a Windows machine (or use a cross-compile CI):
```bash
npm install
npm run build
```
Output: `dist/PROJECT - E Setup x.x.x.exe` — a standalone NSIS installer.

#### Step 6 — Test the Packaged App
- Install on a **clean Windows PC with no PHP installed**
- Verify the app launches and shows the dashboard
- Test QR code scanning (requires webcam access — Electron may need `nodeIntegration` adjusted)
- Test a walk-in Day Pass sale end-to-end
- Test member registration and QR generation

---

### ⚙️ Full Revised `main.js` (Target State)

```javascript
const { app, BrowserWindow, nativeImage } = require('electron');
const cp = require('child_process');
const path = require('path');
const fs = require('fs');

let mainWindow = null;
let phpServer = null;

const iconPath = nativeImage.createFromPath(path.join(__dirname, 'assets/logo.ico'));

function getPhpBinary() {
    if (app.isPackaged) {
        return path.join(process.resourcesPath, 'php', 'php.exe');
    }
    return path.join(__dirname, 'resources', 'php', 'php.exe');
}

function setupDatabase() {
    const userDataPath = app.getPath('userData');
    const dbTarget = path.join(userDataPath, 'gym.db');
    const dbSource = path.join(
        app.isPackaged ? process.resourcesPath + '/app.asar.unpacked' : __dirname,
        'database', 'gym.db'
    );
    if (!fs.existsSync(dbTarget) && fs.existsSync(dbSource)) {
        fs.copyFileSync(dbSource, dbTarget);
    }
    return userDataPath;
}

function startPhpServer(userDataPath) {
    const phpBinary = getPhpBinary();
    const publicPath = app.isPackaged
        ? path.join(process.resourcesPath, 'app.asar.unpacked')
        : path.join(__dirname);

    phpServer = cp.spawn(phpBinary, ['-S', '127.0.0.1:8000', '-t', publicPath], {
        env: { ...process.env, USER_DATA_PATH: userDataPath }
    });
}

function createWindow() {
    const userDataPath = setupDatabase();
    startPhpServer(userDataPath);

    mainWindow = new BrowserWindow({
        width: 1280,
        height: 800,
        icon: iconPath,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true
        }
    });

    setTimeout(() => {
        mainWindow.loadURL('http://127.0.0.1:8000/index.php');
    }, 1500);

    mainWindow.on('closed', () => { mainWindow = null; });
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    if (phpServer) phpServer.kill();
    if (process.platform !== 'darwin') app.quit();
});
```

> **Note:** The database file (`gym.db`) must be added to `asarUnpack` in `package.json` so it can be accessed by PHP outside the `.asar` archive:
> ```json
> "asarUnpack": ["database/**", "assets/**", "exports/**"]
> ```

---

## 🏗️ Tech Stack

| Layer | Technology |
|---|---|
| Desktop Shell | Electron (v28) |
| Backend | PHP (built-in server via `php -S`) |
| Database | SQLite (`gym.db` via PDO) |
| Frontend | Vanilla HTML, CSS, JavaScript |
| QR Scanning | `html5-qrcode` (CDN) |
| QR Generation | `qrcodejs` (CDN) |
| Build Tool | `electron-builder` |
| Export | CSV / Tab-delimited `.xlsx` (manual) |

---

## 📁 Project Structure

```
Capstone/
├── main.js                  # Electron entry point — starts PHP server, creates window
├── index.php                # Main dashboard (attendance scanner, POS history, stats)
├── forgotpass.php           # Password recovery (username lookup)
├── reset_pass.php           # Password reset (new password form)
├── logout.php               # Session logout
├── member_profile.php       # Individual member profile view with QR & history
│
├── admin/
│   ├── manage.php           # Member registration, editing, listing, legacy import
│   ├── log.php              # Per-member edit log / individual member edit form
│   └── staff.php            # Staff/roles management (add, edit, delete)
│
├── pos/
│   ├── sales.php            # POS interface — products, cart, checkout, sales history
│   ├── expenses.php         # Expenses & salary records management
│   └── check_member.php     # AJAX endpoint — validates QR scans, logs attendance
│
├── includes/
│   ├── db.php               # PDO SQLite connection (exposes both $pdo and $conn)
│   ├── nav.php              # Shared navigation bar
│   └── exporthelper.php     # CSV export helper for sales data
│
├── database/
│   └── gym.db               # SQLite database file
│
├── assets/
│   ├── style.css            # Global stylesheet
│   ├── sales.js             # Sales-related JS
│   ├── logo.ico / logo.jpg  # App icons
│   ├── gym.jpg              # Background image
│   └── gcash.jpg / *.jpg    # Product images & payment QR
│
├── exports/                 # Auto-generated export files (CSV)
├── dist/                    # Electron build output
└── package.json             # Electron app config & build settings
```

---

## ✅ Implemented Features

### 🏠 Dashboard (`index.php`)
- Summary stats cards: Total Members, Attendance Today, Revenue Today, Monthly Revenue, Expenses, Net Income
- **Membership & Traffic Analytics** — active/expired member progress bars, day passes sold today
- **QR Code Scanner** — camera-based attendance scanning via `html5-qrcode`, real-time scan history sidebar
- **POS History Table** — combined view of POS sales and membership payments, filterable by Today / This Month / All
- **Walk-In Day Pass** modal — quick checkout via Cash or GCash with auto-calculated qty
- **App Lock Screen** — 4-digit PIN lock with 5-minute inactivity auto-lock (PIN stored in `localStorage`)
- **Change PIN** modal
- **Export to Excel** button (routes to `sales.php?export=xlsx`, outputs tab-delimited file)
- **Delete Transaction** and **Delete All History** (POS records only)

### 👥 Member Management (`admin/manage.php`)
- Register new members with name, contact, address, expiry date, tier, base rate, discount, final rate
- **Legacy Import** mode — import pre-existing members without generating QR at registration
- Inline member editing (edit modal within the table)
- Member list table with search/filter
- Delete member records
- Auto-generates member QR code on registration (shown in a printable modal)

### 📋 Individual Member Edit/Log (`admin/log.php`)
- Edit a specific member's details (name, contact, address, expiry, tier, rates)
- Redirects to `login.php` if not authenticated — **but no login system exists** (see Issues)

### 👤 Member Profile (`member_profile.php`)
- Displays member QR code (for re-printing)
- Shows member status (Active / Expired), expiry date, join date
- Lists last 5 check-in timestamps (from `attendance` table)

### 🛒 Point of Sale (`pos/sales.php`)
- Product catalog with images, add-to-cart, quantity adjustment, remove items
- Cart checkout with customer name, payment method (Cash / GCash)
- GCash QR modal display
- Add / Edit / Delete products (with image upload)
- Sales history table (POS + Membership UNION query)
- Edit sale status (Pending / Paid / Cancelled)
- Delete individual sales or delete all sales
- Export sales as `.xlsx` (tab-delimited, named as Excel)

### 💸 Expenses & Salaries (`pos/expenses.php`)
- Add expense entries with category, description, amount, and date
- Add salary entries linked to staff names
- Delete individual expense records
- Lists all expense records ordered by date

### 🧑‍💼 Staff Management (`admin/staff.php`)
- Add, edit, and delete staff members with name, role, and contact
- Staff list is used to populate salary dropdown in `expenses.php`

### 🔐 Auth / Security
- App-level PIN lock on the dashboard (client-side `localStorage`)
- Forgot Password flow: username lookup → session token → password reset with `password_hash()`
- `check_member.php` validates QR codes and logs attendance

---

## 🗄️ Database Schema (Inferred from Code)

| Table | Key Columns |
|---|---|
| `members` | `id`, `name`, `contact`, `address`, `expiry_date`, `tier`, `base_rate`, `discount_amount`, `final_rate`, `qr_id`, `membership_type`, `created_at` |
| `attendance` | `id`, `member_id`, `scan_time` |
| `sales` | `id`, `customer_name`, `amount`, `status`, `payment_method`, `sale_date` |
| `sale_items` | `id`, `sale_id`, `product_id`, `qty`, `price` |
| `products` | `id`, `name`, `price`, `stock`, `image_url` |
| `expenses` | `id`, `category`, `description`, `amount`, `expense_date` |
| `staff` | `id`, `name`, `role`, `contact`, `created_at` |
| `users` | `id`, `username`, `password` |

---

## ⚠️ Issues / Bugs to Fix

### 🔴 Critical Bugs

1. **`check_member.php` uses MySQL syntax in SQLite**
   - Line 35: `INSERT INTO attendance (member_id, scan_time) VALUES (?, NOW())`
   - `NOW()` is a MySQL function. SQLite uses `datetime('now')` or `CURRENT_TIMESTAMP`.
   - **Fix:** Replace `NOW()` with `datetime('now')`.

2. **`member_profile.php` references wrong column names**
   - Uses `$member['username']` and `$member['expires_at']` — neither column exists in the `members` table.
   - Correct columns are `name` and `expiry_date`.
   - **Fix:** Replace `$member['username']` with `$member['name']` and `$member['expires_at']` with `$member['expiry_date']`.

3. **`member_profile.php` incorrect path for `db.php`**
   - Line 3: `include __DIR__ . '/../includes/db.php';` — file is at the root, not in a subdirectory.
   - **Fix:** Change to `include __DIR__ . '/includes/db.php';`

4. **`admin/log.php` redirects to non-existent pages**
   - Lines 6–13: Redirects unauthenticated users to `login.php` and unauthorized to `members.php` — neither file exists in the project.
   - **Fix:** Either create a login system, or change redirect targets to `index.php`.

5. **`reset_pass.php` redirects to `forgot_password.php` (wrong filename)**
   - Line 10: `header("Location: forgot_password.php");` — the actual file is `forgotpass.php`.
   - **Fix:** Change to `header("Location: forgotpass.php");`

6. **`main.js` references wrong database path for Electron packaging**
   - Line 23: `const dbSource = path.join(__dirname, 'includes', 'database.sqlite');`
   - The actual database is `database/gym.db`, not `includes/database.sqlite`.
   - This means the initial DB is never copied on first launch in a packaged build.
   - **Fix:** Change to `path.join(__dirname, 'database', 'gym.db')` and update `dbTarget` accordingly.

7. **`forgotpass.php` references non-existent CSS**
   - Line 47: `<link rel="stylesheet" href="css/style.css">` — no `css/` directory exists (styles are in `assets/`).
   - **Fix:** Remove the dead `<link>` tag or fix the path to `assets/style.css`.

8. **`manage.php` background image uses wrong relative path**
   - Line 156: `url('./assets/gym.jpg')` — `manage.php` is inside `/admin/`, so the path is wrong.
   - **Fix:** Change to `url('../assets/gym.jpg')`.

---

### 🟡 Incomplete / Missing Features

9. **No authentication / login system**
   - `log.php` checks for `$_SESSION['isAdminLoggedIn']` and redirects to `login.php`, but no login page exists.
   - The app relies entirely on the client-side PIN lock (stored in `localStorage`), which is trivially bypassable.
   - **To Do:** Create a proper `login.php` with session-based authentication using the `users` table.

10. **Export generates a `.xlsx` file but outputs plain TSV**
    - `sales.php` sends a `.xlsx` Content-Type header but writes tab-separated plain text.
    - This results in a file that Excel opens with a compatibility warning.
    - **To Do:** Integrate a real XLSX library (e.g., PhpSpreadsheet) or rename the export to `.csv`.

11. **`exporthelper.php` exports only POS sales, not membership payments**
    - The export helper query only reads from `sales` and `sale_items` — membership revenue is excluded.
    - **To Do:** Add the UNION ALL with `members` to match the dashboard's full history view.

12. **`qr_id` may be NULL for legacy-imported members**
    - If members are added via the "Legacy Import" path, `qr_id` may never be set, making attendance scanning impossible for those members.
    - **To Do:** Ensure `qr_id` is auto-generated (UUID or member ID) on all member creation paths.

13. **Walk-In Day Pass does not log attendance**
    - Walk-in day pass sales are recorded as POS transactions, but no entry is made in the `attendance` table.
    - **To Do:** Log day pass check-ins to `attendance` or create a separate day-pass visit log.

14. **`nav.php` uses hardcoded absolute path and dead link**
    - Line 15: `href="/capstone/pos/dashboard.php"` — assumes a specific server root and links to `dashboard.php` which does not exist.
    - **Fix:** Update to a relative path like `../index.php`.

15. **No stock validation on checkout**
    - `sales.php` decrements stock on checkout, but there is no check to prevent selling more items than are in stock.
    - **To Do:** Add a `WHERE stock >= qty` guard before processing each sale item.

16. **PIN security is weak**
    - The app lock PIN is stored in plain text in `localStorage`, accessible via browser DevTools.
    - **To Do:** Store and validate the PIN on the server side (e.g., in the `users` table).

17. **`manage.php` edit modal does not include `membership_type`**
    - The `members` table has a `membership_type` column, but the edit form only handles `tier`, risking data loss on edit.
    - **To Do:** Add a `membership_type` field to the edit form and update query.

18. **No QR re-generation on member edit**
    - When member info is updated, the existing QR code is not regenerated or re-printed.
    - **To Do:** Trigger a QR re-print/display flow after a successful member edit.

---

### 🟢 Improvements / Nice to Have

19. **Responsive design is incomplete** — scanner grid and sales grid use fixed layouts that break on small screens.
20. **No pagination** on the members list or sales history tables.
21. **Timezone inconsistency** — some queries use `DATE('now', 'localtime')` and some don't; standardize all.
22. **Error messages exposed to client** — some PDO catch blocks echo raw error messages (security risk in production).
23. **Electron build missing `/admin/` directory** — `package.json` build `files` array does not include `admin/**/*`.
    - **Fix:** Add `"admin/**/*"` to the `files` array in `package.json`.
24. **Product images stored as absolute paths** — will break when the app is moved or rebuilt on another machine.
25. **No database backup/restore** feature.

---

## 🧭 Recommended Next Steps (Priority Order)

| Priority | Task | File |
|---|---|---|
| 🔴 **WIN-1** | Download & set up portable PHP for Windows in `resources/php/` | (manual step) |
| 🔴 **WIN-2** | Rewrite `main.js` to use bundled `php.exe` instead of system PHP | `main.js` |
| 🔴 **WIN-3** | Add `asarUnpack` for `database/`, `assets/`, `exports/` in `package.json` | `package.json` |
| 🔴 **WIN-4** | Add all missing directories to build `files` array (`admin/**/*`, root `.php` files) | `package.json` |
| 🔴 P1 | Fix `NOW()` → `datetime('now')` | `pos/check_member.php` L35 |
| 🔴 P1 | Fix wrong column names (`username`, `expires_at`) | `member_profile.php` L52, L56, L65 |
| 🔴 P1 | Fix `reset_pass.php` redirect filename | `reset_pass.php` L10 |
| 🔴 P1 | Fix `nav.php` hardcoded path and dead `dashboard.php` link | `includes/nav.php` L15 |
| 🟡 P2 | Build a `login.php` with session authentication | (new file) |
| 🟡 P2 | Fix `.xlsx` export to produce real Excel or proper CSV | `pos/sales.php` L34 |
| 🟡 P2 | Add stock validation before checkout | `pos/sales.php` |
| 🟡 P2 | Ensure `qr_id` always populated on member creation | `admin/manage.php` |
| 🟢 P3 | Add pagination to member list and sales history | `admin/manage.php`, `pos/sales.php` |
| 🟢 P3 | Move PIN storage to server-side | `index.php`, new AJAX endpoint |
| 🟢 P3 | Add full-text search to member management | `admin/manage.php` |
| 🟢 P3 | Add database backup/restore feature | (new feature) |
