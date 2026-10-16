# PROJECT-E — Gym Management & POS

## A. Install and use (Windows)

1. Download `PROJECT - E Setup 1.0.0.exe` from the **Releases** page.
2. Run it.
3. Open **PROJECT - E**.
4. Enter PIN `1234`.
5. Go to **Settings**.
6. Change the PIN.
7. Change the admin password (default `admin123`).
8. Click **Download Backup**. Keep the file safe.

### Daily use
- **Check in a member:** Dashboard → turn the scanner on → scan the member's QR.
- **New member:** Manage Members → fill in the form → Complete Registration → print the QR.
- **Old member:** Manage Members → Encode Existing / Legacy Member.
- **Renew:** member's **QR** button → Update Membership → +1 Month (or more) → Save.
- **Sell products:** POS → click products → Complete (Cash) or Pay via GCash.
- **Day pass:** Walk-In Day Pass → Pay.
- **Expense:** Expenses → Add Expense.
- **Salary:** Staff → add the staff member → Expenses → Add Employee Salary.
- **Export:** Export to Excel (CSV).
- **Lock:** 🔒 Lock.

### Forgot
- **PIN:** lock screen → Forgot PIN? → admin username + password.
- **Password:** lock screen → Forgot admin password? → username + PIN.

### Restore
1. Settings → Restore Backup.
2. Choose the `.db` file.
3. Enter the admin password.
4. Click Restore.

---

## B. Run from source

### Windows
1. Install Node.js and Git.
2. Run:
   ```
   git clone https://github.com/JanrenJerez/UI_Capstone.git
   cd UI_Capstone
   npm install
   npm start
   ```

### Linux / macOS
1. Install Node.js, Git, and PHP 8 with `pdo_sqlite`.
2. Run the same commands as on Windows.

### Browser only (no Electron)
1. Run:
   ```
   php -S 127.0.0.1:8000 router.php
   ```
2. Open http://127.0.0.1:8000

---

## C. Build the installer

### On Windows
1. Run:
   ```
   npm install
   npm run build
   ```
2. Get the installer from `dist/PROJECT - E Setup 1.0.0.exe`.

### On Linux
1. Run:
   ```
   sudo dnf install wine
   npm install
   node node_modules/electron-builder/cli.js --win --x64
   ```
2. Get the installer from `dist/PROJECT - E Setup 1.0.0.exe`.

---

## D. Data location (Windows)

`%APPDATA%\PROJECT - E\`
- `gym.db`: database
- `assets\`: product images
- `exports\`: sales report
- `backups\`: safety copies

---

## E. Problems

| Problem | Fix |
|---|---|
| PHP server failed to start | Reinstall the app |
| Camera not working | Close other apps using the camera, and check Windows camera privacy settings |
| App locked | Enter the PIN |
| Too many wrong attempts | Wait 30 seconds |

More details: `CHANGES.md`
