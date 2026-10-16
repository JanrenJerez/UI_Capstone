# PROJECT-E — Gym Management & POS

This is my capstone project: a desktop app for running a small gym. It handles member registration with QR codes, attendance scanning, a point of sale for products and walk-in day passes, expenses and salaries, and staff records.

It's an Electron app with a small PHP + SQLite website inside it. The big thing I wanted was for it to run on **any Windows PC with no internet and no PHP installed**, so everything it needs ships inside the installer.

---

## What I worked on

When I picked this up, the app only worked on a PC that already had PHP installed. Without it you just got a blank white window. A few pages were also broken. Here's what I did:

- **Made it fully offline.** I bundled a portable PHP for Windows (plus the Visual C++ runtime files it needs) inside the app. The QR scanner and QR generator libraries used to load from the internet, so I saved them into the project.
- **Moved the data somewhere safe.** The database now lives in `%APPDATA%\PROJECT - E\`, so reinstalling or updating the app doesn't wipe it.
- **Added a PIN lock.** The app opens to a PIN screen and locks itself after 5 minutes of no activity. There's a Settings page to change the PIN and the admin password, and a way to recover either one.
- **Fixed a lot of bugs.** QR scanning never actually worked (wrong file path, and members never got a QR id). Renewals weren't counted as revenue. Deleting a payment row deleted the whole member. Stock could go negative. Refreshing a page saved forms twice. And more. The full list is in [`CHANGES.md`](CHANGES.md).
- **Added backup and restore**, CSV export that Excel opens cleanly, pagination and search for members, and renewal payments.

---

## Using the app

### First time
1. Run the installer (`PROJECT - E Setup 1.0.0.exe`). It installs for the current user, no admin rights needed.
2. Open **PROJECT - E**. You'll see the PIN screen.
3. The default PIN is **`1234`**.
4. Go to **Settings** and change two things right away:
   - the **PIN**
   - the **admin password** (the default is **`admin123`**, and the app keeps reminding you until you change it)

The admin password matters. You need it to reset a forgotten PIN and to restore a backup.

### Day to day
- **Checking members in:** on the dashboard, switch the **scanner** on and have members show their QR code to the camera. While the scanner is on, the app won't auto-lock, so it can sit at the front desk. A member is only counted once per day.
- **Registering members:** go to **Manage Members**. Choose the tier and how many months, and you get a QR code to print right away. Use **Encode Existing / Legacy Member** for people who already paid before you started using the app; those don't count as new revenue.
- **Renewing:** open the member (the **QR** button in the list), click **Update Membership**, pick **+1 Month / +3 Months / ...**, and save. The renewal is recorded as a payment.
- **Selling products:** use the **POS** page. Click products to add them to the cart, then pay with cash or GCash. It won't let you sell more than you have in stock. Setting a sale to **Void** puts the items back in stock.
- **Walk-in day pass:** there's a button on the dashboard and on the POS page. It counts toward today's attendance.
- **Expenses and salaries:** use the **Expenses** page. Add your staff on the **Staff** page first so they show up in the salary list.
- **Exporting sales:** click **Export to Excel (CSV)**. It includes POS sales and membership payments.

### Forgot something?
- **Forgot the PIN:** on the lock screen, click **Forgot PIN?** and enter the admin username and password.
- **Forgot the admin password:** on the lock screen, click **Forgot admin password?** and enter the username and the current PIN.
- If you forget **both**, there's no way back in. That's why backups matter.

### Backups
Go to **Settings → Download Backup** every now and then and keep the file somewhere else (a USB drive or another PC). **Restore Backup** replaces everything with a backup file. It asks for the admin password and saves a safety copy of the current data first.

---

## Setting it up for development

### What you need
- [Node.js](https://nodejs.org/) (I used Node 20+)
- Git
- **On Windows:** nothing else. The app uses the PHP bundled in `resources/php/`.
- **On Linux/macOS:** PHP 8 with the `pdo_sqlite` extension. In dev mode the app uses your system's `php`.

### Running it
```bash
git clone https://github.com/JanrenJerez/UI_Capstone.git
cd UI_Capstone
npm install
npm start
```

`npm start` opens the Electron window and starts PHP in the background. In dev mode, the database used is `database/gym.db` inside the project.

If you just want to poke at the pages in a browser, you can run the PHP side on its own:
```bash
php -S 127.0.0.1:8000 router.php
```
Then open http://127.0.0.1:8000. `router.php` is the thing that stops the database and internal files from being downloadable, so always start the server with it.

### Building the Windows installer
On Windows:
```bash
npm install
npm run build
```
The installer ends up in `dist/PROJECT - E Setup 1.0.0.exe`.

I built mine on Fedora Linux, which also works, but you need **Wine** installed (`sudo dnf install wine`) because the Windows installer tools run through it:
```bash
node node_modules/electron-builder/cli.js --win --x64
```

The installer is about 138 MB, which is too big to keep in the repo, so `dist/` is ignored by git. If you need a ready-made installer, check this repo's **Releases** page or build it yourself.

---

## How it fits together

```
main.js              Electron: finds a free port, starts the bundled PHP, opens the window
router.php           Blocks direct access to the database and internal files
index.php            Dashboard: stats, QR scanner, sales history
lock.php             PIN screen (plus forgot_pin.php, forgotpass.php, reset_pass.php)
member_profile.php   Member card with a printable QR code
uploads.php          Serves product images from AppData
keepalive.php        Keeps the session alive while the scanner is on
admin/
  manage.php         Register / import / edit / search members
  log.php            Update a membership (renewals)
  staff.php          Staff list
  settings.php       PIN, admin password, backup and restore
pos/
  sales.php          POS, products, sales history, CSV export
  expenses.php       Expenses and salaries
  check_member.php   Called by the scanner to check in a member
includes/
  db.php             Database connection (runs migrations automatically)
  migrate.php        Database upgrades (tracked with PRAGMA user_version)
  auth.php           PIN lock / session handling
  helpers.php        Shared functions (history query, pagination, payments)
  nav.php            Top menu + the auto-lock timer
assets/              CSS, images, and the offline QR libraries (assets/vendor/)
database/gym.db      Starter database, copied to AppData on first launch
resources/php/       Portable PHP 8.5 for Windows + VC++ runtime DLLs
```

**Where the real data lives on Windows:** `%APPDATA%\PROJECT - E\`
- `gym.db`: the database
- `assets\`: uploaded product images
- `exports\`: an auto-updated copy of the sales report
- `backups\`: safety copies made before a restore

**Database upgrades:** when the app starts, `includes/migrate.php` checks the database version and upgrades it if needed. That means an older database (or an old backup) gets brought up to date automatically.

---

## Troubleshooting

- **The app says the PHP server failed to start:** reinstall the app. If it keeps happening, open Command Prompt and run
  `"%LOCALAPPDATA%\Programs\PROJECT - E\resources\php\php.exe" -v`
  to see the actual error.
- **The camera doesn't turn on:** make sure no other app is using the webcam, and that Windows camera privacy settings allow desktop apps.
- **The app locked itself:** that's the 5-minute auto-lock. Enter the PIN. Leaving the scanner switched on keeps it unlocked.
- **"Too many wrong attempts":** wait 30 seconds and try again.

For the full history of what changed and why, see [`CHANGES.md`](CHANGES.md). The original project notes are in [`PROJECT_SUMMARY.md`](PROJECT_SUMMARY.md).
