const { app, BrowserWindow, nativeImage } = require('electron');
const cp = require('child_process');
const path = require('path');
const fs = require('fs');
const http = require('http');

const PHP_HOST = '127.0.0.1';
const PHP_PORT = 8000;
const START_URL = `http://${PHP_HOST}:${PHP_PORT}/index.php`;

let mainWindow = null;
let phpServer = null;

const iconPath = nativeImage.createFromPath(path.join(__dirname, 'assets/logo.ico'));

// Folder holding the bundled portable PHP (php.exe + php.ini + ext/)
function getPhpDir() {
    return app.isPackaged
        ? path.join(process.resourcesPath, 'php')
        : path.join(__dirname, 'resources', 'php');
}

function getPhpBinary() {
    const bundled = path.join(getPhpDir(), 'php.exe');
    if (process.platform === 'win32' && fs.existsSync(bundled)) {
        return bundled;
    }
    // Dev fallback on Linux/macOS: use the system PHP
    return 'php';
}

function setupUserData() {
    const userDataPath = app.getPath('userData');

    // Writable folder for uploaded product images
    const uploadsDir = path.join(userDataPath, 'assets');
    if (!fs.existsSync(uploadsDir)) {
        fs.mkdirSync(uploadsDir, { recursive: true });
    }

    // Copy the starter database on first launch so data survives reinstalls
    const dbTarget = path.join(userDataPath, 'gym.db');
    const dbSource = path.join(app.getAppPath(), 'database', 'gym.db');
    if (!fs.existsSync(dbTarget) && fs.existsSync(dbSource)) {
        fs.copyFileSync(dbSource, dbTarget);
    }

    return userDataPath;
}

function startPhpServer(userDataPath) {
    const phpBinary = getPhpBinary();
    const docRoot = app.getAppPath();
    // router.php blocks direct access to the database and internal files
    const args = ['-S', `${PHP_HOST}:${PHP_PORT}`, '-t', docRoot, path.join(docRoot, 'router.php')];

    // Only force our php.ini when using the bundled Windows binary
    if (phpBinary !== 'php') {
        args.unshift('-c', path.join(getPhpDir(), 'php.ini'));
    }

    phpServer = cp.spawn(phpBinary, args, {
        cwd: docRoot,
        env: { ...process.env, USER_DATA_PATH: userDataPath },
        windowsHide: true
    });

    phpServer.stderr.on('data', (data) => console.log(`[php] ${data}`));
    phpServer.on('error', (err) => console.error('Failed to start PHP:', err));
}

function stopPhpServer() {
    if (phpServer) {
        phpServer.kill();
        phpServer = null;
    }
}

// Poll the PHP server until it answers, then load the app
function waitForServer(onReady, attempt = 0) {
    const req = http.get(START_URL, (res) => {
        res.resume();
        onReady();
    });
    req.on('error', () => {
        if (attempt < 40) {
            setTimeout(() => waitForServer(onReady, attempt + 1), 250);
        } else {
            onReady();
        }
    });
}

function createWindow() {
    const userDataPath = setupUserData();
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

    if (process.platform === 'darwin') {
        app.dock.setIcon(iconPath);
    }

    waitForServer(() => {
        if (mainWindow) mainWindow.loadURL(START_URL);
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(createWindow);

app.on('before-quit', stopPhpServer);

app.on('window-all-closed', () => {
    stopPhpServer();
    if (process.platform !== 'darwin') app.quit();
});
