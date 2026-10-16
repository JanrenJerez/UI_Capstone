# Capstone UI PROJECT-E — Gym Management & POS

## A. Install and use (Windows)

1. Download `PROJECT - E Setup 1.0.1.exe` from the **Releases** page.
2. Run it.
3. Open **PROJECT - E**.
4. Enter PIN `1234`.
5. Go to **Settings**.
6. Change the PIN.
7. Change the admin password (default `admin123`).
8. Click **Download Backup**. Keep the file safe.

### Forgot
- **PIN:** lock screen → Forgot PIN? → admin username + password.
- **Password:** lock screen → Forgot admin password? → username + PIN.

### Restore
1. Settings → Restore Backup.
2. Choose the `.db` file.
3. Enter the admin password.
4. Click Restore.

---

## B. Run in VSCODE

### Windows
1. Install Node.js and Git.
2. Run:
   ```
   git clone https://github.com/JanrenJerez/UI_Capstone.git
   cd UI_Capstone
   npm install
   npm start
   ```

### For Linux / macOS
1. Install Node.js, Git, and PHP 8 with `pdo_sqlite`.
2. Run the same commands as on Windows.

### for Browser only (no Electron)
1. Run:
   ```
   php -S 127.0.0.1:8000 router.php
   ```
2. Open http://127.0.0.1:8000

---

## C. Creating and Building the installer

### On Windows
1. Run:
   ```
   npm install
   npm run build
   ```
2. Get the installer from `dist/PROJECT - E Setup 1.0.1.exe`.

### On Linux
1. Run:
   ```
   sudo dnf install wine
   npm install
   node node_modules/electron-builder/cli.js --win --x64
   ```
2. Get the installer from `dist/PROJECT - E Setup 1.0.1.exe`.

---

## D. Data location (Windows)

`%APPDATA%\PROJECT - E\`
- `gym.db`: database
- `assets\`: product images
- `exports\`: sales report
- `backups\`: safety copies

---

## E. If you encounter Problems

| Problem | Fix |
|---|---|
| PHP server cannot start | Reinstall the app |
| Camera not working | Close other apps using the camera, and check Windows camera privacy settings |
| App locked | Enter the PIN |
| many wrong pass attempts | Wait 30 seconds |
