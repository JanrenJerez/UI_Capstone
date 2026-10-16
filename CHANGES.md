
## 🩹 v1.0.1 — "Database connection failed" on a fresh PC (October 16)
A fresh install on another Windows PC showed only "Database connection failed", even after a reinstall. The starter database in the installer was checked and is fine, so the cause was on that PC, and the app was hiding the real error.
- **PHP extensions now load by full path.** `php.ini` used `extension_dir = "ext"`, a relative path that depends on how Windows resolves it. If it fails, PHP starts without its SQLite driver and every page shows "Database connection failed". `main.js` now passes the full path (`-d extension_dir=...\resources\php\ext`).
- **The error page now shows the real cause.** Instead of a one-line message, it lists the reason, the database file path, whether the file and folder are usable, whether the SQLite driver loaded, and which `php.ini` was used, so a screenshot is enough to diagnose it.
- Version bumped to **1.0.1** (`dist/PROJECT - E Setup 1.0.1.exe`).
