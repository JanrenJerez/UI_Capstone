# PROJECT-E — Change Log & Bug Status
> Covers the work done on October 15–16, 2026. Numbers (#1–#25) match the issue list in `PROJECT_SUMMARY.md`.

---

## 📌 Summary

| Round | What | Status |
|---|---|---|
| **1 — Offline Windows build** | Bundled PHP + VC++ runtime, local QR libraries, data in AppData, critical bugs #1–#8 | ✅ Done and **tested on a real Windows PC** (installs, QR scanner opens, day pass, products with images, data survives restart) |
| **2 — Open issues** | Every remaining item #9–#25, plus 12 more bugs found along the way | ✅ Done, **tested on Windows** |
| **3 — Bug sweep** | Full review of the code: 16 more bugs fixed (revenue, renewals, stock, uploads, forms) | ✅ Done and tested on Linux. ⏳ Needs a quick retest on Windows |

Current installer: `dist/PROJECT - E Setup 1.0.0.exe` (built October 16, 13:19).

### ⚠️ What changes for the user after updating
- **The app now opens to a PIN screen.** The PIN is **1234**.
- **The admin password has been reset to `admin123`** (the old one was unknown). Settings shows a warning until you change it.
- Change both in the new **Settings** page (nav bar → Settings).
- Existing installs upgrade automatically on first launch: no data is lost, and every existing member gets a QR id.

---

## 🔐 How access works now (#9, #16)

- **PIN only:** the app opens to `lock.php`. The PIN is checked by the server and stored **hashed** in the database (new `settings` table). It is no longer stored in `localStorage`, so DevTools can't read it and refreshing can't get past it.
- **Auto-lock:** after **5 minutes** without mouse/keyboard activity on any page. The server also locks after 15 minutes idle, as a backstop.
- **🔒 Lock button** in the nav bar.
- **Wrong attempts:** after 5 wrong PINs or passwords, the app waits 30 seconds. The count is stored in the database, so clearing cookies doesn't reset it.
- **Admin account** (`users` table) is used for recovery and dangerous actions:

| Task | Requires |
|---|---|
| Change PIN (Settings) | Current PIN |
| Change admin password (Settings) | Current password |
| **Forgot PIN** (link on lock screen) | Admin username + password |
| **Forgot admin password** (link on lock screen) | Username + app PIN |
| Restore a backup | Admin password |

If both the PIN **and** the admin password are forgotten, there is no recovery path. Keep a backup and write the password down somewhere safe.

---

## ✅ Issue Status — all items

### 🔴 Critical bugs
| # | Bug | Status | Tested |
|---|---|---|---|
| 1 | `check_member.php` used MySQL `NOW()` | ✅ Now `datetime('now','localtime')` | ✅ |
| 2 | `member_profile.php` wrong column names | ✅ `name` / `expiry_date` | ✅ |
| 3 | `member_profile.php` wrong include path | ✅ | ✅ |
| 4 | `admin/log.php` redirected to missing pages | ✅ Now protected by the PIN lock and works as the "Update Membership" page. Its form also posted to a missing `edit_member.php`; fixed. | ✅ |
| 5 | `reset_pass.php` wrong redirect | ✅ | ✅ |
| 6 | `main.js` wrong DB path | ✅ | ✅ Windows |
| 7 | `forgotpass.php` missing CSS | ✅ Recovery pages share the new `assets/auth.css` | ✅ |
| 8 | `manage.php` background path | ✅ | — |

### 🟡 Incomplete / missing features
| # | Item | What was done | Tested |
|---|---|---|---|
| 9 | No login | ✅ PIN lock on every page and AJAX endpoint (see above) | ✅ |
| 10 | Fake `.xlsx` export | ✅ Real **CSV** download (`sales_report_YYYY-MM-DD.csv`, UTF-8 with BOM) that Excel opens without warnings | ✅ |
| 11 | Export missing memberships | ✅ The export and the auto-saved `exports/sales_report.csv` include POS sales **and** membership payments (one shared query) | ✅ |
| 12 | `qr_id` could be NULL | ✅ Every new member (regular **and** legacy import) gets a unique id like `PE-3FA91C07B2`. Existing members were filled in automatically. The QR code is shown right after registration. | ✅ |
| 13 | Day pass not logged as a visit | ✅ New `walkin_visits` table. **Attendance Today** counts member scans + day passes. | ✅ |
| 14 | `nav.php` hardcoded path | ✅ | ✅ |
| 15 | No stock check | ✅ The server checks stock inside a transaction and refuses with "Not enough stock for X (only N left)". It also uses the **database price**, not the price sent by the browser. The cart's **+** button stops at the available stock. | ✅ |
| 16 | Plain-text PIN in `localStorage` | ✅ Hashed in the database, checked by the server | ✅ |
| 17 | Edit form ignores `membership_type` | ✅ `membership_type` is saved together with `tier` on every insert and edit, and filled in for old rows. History now shows the real tier instead of "Standard". | ✅ |
| 18 | No QR re-print after edit | ✅ After an edit (or legacy import), a QR card pops up with a **Print** button. Each row in the member list has a **QR** button, and the profile has **Print QR Card**. | ✅ |

### 🟢 Improvements
| # | Item | What was done | Tested |
|---|---|---|---|
| 19 | Responsive layout | ✅ Nav wraps. Scanner, sales and profile grids stack on narrow screens. Tables scroll sideways. | Visual check still needed |
| 20 | Pagination | ✅ Members (20/page), POS history and dashboard history (25/page) | ✅ |
| 21 | Timezone inconsistency | ✅ All new records use local time. Old sales and member dates (stored in UTC) were converted once, so "Today" and "This Month" are correct. | ✅ |
| 22 | Raw errors shown | ✅ Users see friendly messages, and details go to the log. `display_errors` is off in the bundled `php.ini`. | ✅ |
| 23 | Build missing `admin/` | ✅ | ✅ |
| 24 | Product images as absolute paths | ✅ New uploads work. Old products with missing images now show the logo instead of a broken image. | ✅ |
| 25 | No backup/restore | ✅ **Settings → Download Backup** (a `.db` file) and **Restore Backup** (checked for validity, requires the admin password, and saves a safety copy to `AppData\PROJECT - E\backups\` first) | ✅ |
| — | Member search (P3) | ✅ Server-side search by name, contact, address, QR id, tier or #ID | ✅ |

---

## 🐞 Extra bugs found and fixed (round 2)

| Bug | Effect | Fix |
|---|---|---|
| Dashboard scanner posted to `check_member.php` instead of `pos/check_member.php` | **Every QR scan failed silently** | Correct path |
| Members never got a `qr_id`, and registration showed no QR | Nobody could be scanned | See #12 |
| Member profile built the QR from `qr_code`, a column that doesn't exist | The printed QR was empty | Uses `qr_id` |
| POS checkout JavaScript called `res.res.trim()` | The sale saved, but no confirmation appeared and the page didn't refresh | Fixed, and errors are now shown |
| Deleting a member who had check-ins | Crashed (foreign key error) | Deletes their attendance first |
| `forgotpass.php` redirected to a missing `reset_password.php` | Password recovery was impossible | Rewritten (see access table) |
| Password recovery only asked for a username | Anyone could reset the admin password | Now also requires the app PIN, valid for 10 minutes |
| Profile "Update Membership" linked to a missing `edit_members.php` | Dead button | Links to `admin/log.php` |
| `log.php` form posted to a missing `edit_member.php`, and its back button went to a missing `members.php` | Edits were lost | Fixed |
| Links to `Manage.php` (capital M) | Broken on case-sensitive systems | `manage.php` |
| Product names and scan names inserted into the page without escaping | A name with quotes broke the page | Escaped |
| `http://127.0.0.1:8000/database/gym.db` was downloadable | Any local program could copy the whole database | New `router.php` blocks `database/`, `includes/`, build files and `.db` files |

---

## 🧹 Round 3 — Bug sweep (October 16)

A line-by-line review of every page after the Windows test. All of these were real bugs in the existing code.

### 💰 Revenue and memberships
| Bug | Effect | Fix |
|---|---|---|
| Revenue was computed from the `members` table (`final_rate` on the day the member was created) | **Renewals never counted as revenue**, and **legacy imports counted as revenue today**, even though those members paid in the past | New **`membership_payments`** table. Registrations and renewals record a payment; legacy imports don't. Existing members were converted to one payment each, so past totals are unchanged. |
| Deleting a membership row in the dashboard's POS History | **Deleted the whole member** (and their QR) | Deletes only that payment record |
| Deleting a member | Their past payments disappeared from revenue | Payments keep a copy of the member's name, so revenue history stays |
| Renewing via **Update Membership** (`log.php`) | Not recorded anywhere | Clicking **+1 Month / +3 Months / ...** shows a ticked **"Record this as a renewal payment"** box; the payment appears in history as "Renewal - TIER (N months)" |
| `log.php` tier names were mixed-case (`Gym Use - Regular`) while the database uses uppercase | The current tier was never highlighted, and saving wrote inconsistent tier names | Uppercase names, shared with `manage.php` (`MEMBERSHIP_TIERS`) |
| `log.php` Base Rate field | **Anything typed was overwritten on every keystroke** | Typing just updates the total; tier and duration buttons recalculate |
| `log.php` duration buttons | Clicking +1 then +2 extended by 3 months (stacked) | Always extends from the saved expiry (or from today if already expired) |
| Edit modal in Manage Members: picking a tier | Didn't change the base rate | Sets the tier's rate |
| Scanning the same member twice | Counted twice in **Attendance Today** | One check-in per member per day ("Already checked in today") |

### 🛒 POS and products
| Bug | Effect | Fix |
|---|---|---|
| Image upload accepted **any file**, including `.php` | In dev mode, an uploaded `.php` file would run as code | Only real JPG/PNG/GIF/WEBP images (checked by content, not file name) |
| Product price/stock accepted negative or blank values | Broken prices and negative stock | Validated with a clear error message |
| Deleting a product | Every past sale of it showed as **"Day Pass"** in history and exports | Each sale line stores the item name (`sale_items.item_name`); old sales were filled in ("Deleted product" where the product was already gone) |
| Setting a sale to **Void** | Items never went back into stock | Void returns the items; un-voiding takes them out again (refused if stock is short) |
| "Day Passes Sold Today" | Counted by searching for "(Day Pass)" in the customer name | Counted from the actual day-pass sale items |
| POS success/error messages (`?success=1`, ...) | Never displayed | Green and red message bars |

### 📝 Forms
| Bug | Effect | Fix |
|---|---|---|
| Expense and salary amounts had `step="100"` | **Amounts like ₱1,234.56 or ₱150 were rejected** by the form | Any amount from ₱0.01 |
| Registration discount, legacy base rate and legacy discount had `step="50"` | Amounts like ₱25 or ₱875 were rejected | Any amount |
| Refreshing after saving a member, expense or staff member | **Saved it again (duplicates)** | Every save redirects, so a refresh only reloads the page |
| Expense date | Any text was accepted | Must be a real date; otherwise today |

Database schema is now **version 2** (new `membership_payments` table and `sale_items.item_name`). Existing installs upgrade automatically on first launch.

### Windows retest for round 3
1. Register a member: **Revenue Today** goes up. Encode a legacy member: it does **not**.
2. Open a member → **Update Membership** → click **+1 Month** → Save. "Renewal payment recorded" appears and revenue goes up.
3. Delete that renewal row from POS History. The **member is still there**.
4. Scan the same QR twice: the second scan says "already checked in", and Attendance Today goes up by 1, not 2.
5. Add an expense of **₱1,234.56**.
6. Sell a product, set the sale to **Void**: the stock returns.
7. After saving a member, expense or staff member, press **Ctrl+R**: no duplicate appears.

---

## 🪟 Offline Windows build (round 1, summary)
- **Bundled PHP:** PHP 8.5.11 for Windows in `resources/php/`, plus `vcruntime140.dll`, `vcruntime140_1.dll` and `msvcp140.dll`.
- **`main.js`:**
  - Starts the bundled `php.exe` with its own `php.ini` and `router.php`, and waits for the server before loading the page.
  - Copies `gym.db` to `%APPDATA%\PROJECT - E\` on first launch.
- **`package.json`:** `asar: false`, every page is included in the build, and PHP ships via `extraResources`.
- **Offline libraries:** the QR libraries are in `assets/vendor/`. Product images are served through `uploads.php`.
- **`index.php` include:** it used Windows backslashes, which crashed the page outside Windows; fixed.

---

## 📁 Files

**New:**
- **Pages:** `lock.php`, `forgot_pin.php`, `admin/settings.php`, `uploads.php`
- **Server:** `router.php`
- **Includes:** `includes/auth.php`, `includes/helpers.php`, `includes/migrate.php`, `includes/upload_paths.php`
- **Assets:** `assets/auth.css`, `assets/vendor/*.js`
- **Bundled PHP runtime:** the three VC++ DLLs, in `resources/php/`

**Rewritten:** `main.js`, `member_profile.php`, `forgotpass.php`, `reset_pass.php`, `logout.php` (now locks the app), `includes/db.php`, `includes/nav.php`, `includes/exporthelper.php`

**Modified:** `index.php`, `admin/manage.php`, `admin/log.php`, `admin/staff.php`, `pos/sales.php`, `pos/expenses.php`, `pos/check_member.php`, `package.json`, `resources/php/php.ini`

**Database:** schema version tracked with `PRAGMA user_version` (now **2**). New tables: `settings`, `walkin_visits`, `membership_payments`. New column: `sale_items.item_name`. The migration runs automatically; `database/gym.db` in the repo is left unchanged as the starter copy.

---

## 🧪 Testing

**Round 2 (Linux, `php -S` + `router.php`, copy of the database, `USER_DATA_PATH` set the way the packaged app sets it). All passed:**
- **Locking:** pages redirect to the lock screen; AJAX gets a 401 response; redirects to outside sites are refused; a wrong PIN is rejected; 5 wrong tries trigger the 30-second wait; the Lock button works.
- **Migration:** existing members got QR ids; dates were converted to local time; admin password is `admin123`; PIN is `1234`.
- **Pages:** all load with no PHP warnings. `/database/gym.db`, `/includes/*` and `/package.json` return 403.
- **Scanning:** a QR scan logs attendance; an unknown QR is refused.
- **POS:**
  - Overselling is refused and stock stays the same.
  - The price sent by the browser is ignored (the DB price is used).
  - A day pass creates a visit; Attendance Today counts it.
- **Export:** CSV download with BOM, includes memberships; the auto-saved copy is written to `USER_DATA_PATH/exports`.
- **Members:**
  - Register and legacy import create unique QR ids and show the QR card.
  - Edit syncs `membership_type` and shows the QR modal; `log.php` editing works.
  - Deleting a member with check-ins works.
  - Search and pagination work.
- **Settings and recovery:**
  - Change PIN and password: wrong current values are rejected; the default-password warning disappears after the change.
  - Forgot PIN works with the admin password; forgot password works with the PIN; `reset_pass.php` can't be opened directly.
  - Backup downloads a valid database. Restore rejects non-database files and wrong passwords, saves a safety copy, then restores.

**Round 2 on Windows:** ✅ tested by the user. The app installs and works.

**Round 3 (Linux, same setup, fresh copy of the starter database). All passed:**
- **Migration:** reached version 2; 3 payments matching the old revenue total (₱2,300); item names filled in.
- **Members:** registering records a payment and legacy import doesn't; register/import/edit redirect after saving; a renewal records "Renewal - GYM USE - STUDENT (2 months)"; a plain edit records nothing; `log.php` highlights the current tier.
- **History:** deleting a membership row keeps the member.
- **Scans:** a second scan doesn't add a row.
- **Uploads and products:** a `.php` upload and a fake `.jpg` are rejected; a negative price is rejected.
- **POS:** void/un-void moves stock correctly; a sold-then-deleted product still shows its name in history.
- **Forms:** an expense of ₱1,234.56 saves; an invalid date becomes today; expenses and staff redirect after saving.
- **Day passes:** counted from the sale items.

**Not yet tested:** round 3 on Windows, and the mobile/narrow-window layout (#19) by eye.

### Windows retest checklist
1. Install over the existing app. Launch: the **PIN screen** appears; unlock with **1234**.
2. **Settings:** change the admin password (the warning disappears) and the PIN. Download a backup.
3. **Manage Members:** register a member. A QR appears; print it or show it on a phone, and **scan it** on the dashboard. **Attendance Today** goes up.
4. **POS:** try to sell more than the stock (refused). Complete a cash sale (confirmation appears and the page refreshes).
5. **Walk-in Day Pass:** Attendance Today goes up.
6. **Export to Excel (CSV):** the save dialog appears and the file opens in Excel with membership rows.
7. Leave the app idle for 5 minutes: it locks.
8. Click **Forgot PIN?** on the lock screen and reset the PIN with the admin password.

---

## 🔧 Build notes
- On Linux: `node node_modules/electron-builder/cli.js --win --x64` (needs Wine; `node_modules/.bin` lost its execute permission when copied from the Mac, and running `npm install` again restores it).
- Dev run on Linux: `npm start` uses the system `php`. Without `USER_DATA_PATH`, the dev server uses (and migrates) `database/gym.db` in the repo.
