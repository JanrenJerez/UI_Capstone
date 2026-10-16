const { app, BrowserWindow, nativeImage, dialog } = require('electron');
const cp = require('child_process');
const path = require('path');
const fs = require('fs');
const http = require('http');
const net = require('net');

const PHP_HOST = '127.0.0.1';

let mainWindow = null;
let phpServer = null;
let quitting = false;

const iconPath = nativeImage.createFromPath(path.join(__dirname, 'assets/logo.ico'));

// Only one copy of the app may run: a second launch just focuses the open window
if (!app.requestSingleInstanceLock()) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (mainWindow) {
            if (mainWindow.isMinimized()) mainWindow.restore();
            mainWindow.focus();
        }
    });
}

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

// Ask the OS for a free port, so another program on port 8000 can't clash with the app
function findFreePort() {
    return new Promise((resolve, reject) => {
        const srv = net.createServer();
        srv.unref();
        srv.on('error', reject);
        srv.listen(0, PHP_HOST, () => {
            const { port } = srv.address();
            srv.close(() => resolve(port));
        });
    });
}

function showFatalError(message) {
    dialog.showErrorBox('PROJECT - E could not start', message);
    app.quit();
}

function startPhpServer(userDataPath, port) {
    const phpBinary = getPhpBinary();
    const docRoot = app.getAppPath();
    // router.php blocks direct access to the database and internal files
    const args = ['-S', `${PHP_HOST}:${port}`, '-t', docRoot, path.join(docRoot, 'router.php')];

    // Only force our php.ini when using the bundled Windows binary.
    // extension_dir must be absolute: a relative "ext" depends on the working folder,
    // and without it PHP starts with no SQLite driver ("Database connection failed").
    if (phpBinary !== 'php') {
        args.unshift(
            '-c', path.join(getPhpDir(), 'php.ini'),
            '-d', `extension_dir=${path.join(getPhpDir(), 'ext')}`
        );
    }

    phpServer = cp.spawn(phpBinary, args, {
        cwd: docRoot,
        env: { ...process.env, USER_DATA_PATH: userDataPath },
        windowsHide: true
    });

    phpServer.stderr.on('data', (data) => console.log(`[php] ${data}`));
    phpServer.on('error', (err) => {
        console.error('Failed to start PHP:', err);
        showFatalError(`The built-in PHP server failed to start.\n\n${err.message}`);
    });
    phpServer.on('exit', (code) => {
        if (!quitting) {
            showFatalError(`The built-in PHP server stopped unexpectedly (exit code ${code}). Please reopen the app.`);
        }
    });
}

function stopPhpServer() {
    quitting = true;
    if (phpServer) {
        phpServer.kill();
        phpServer = null;
    }
}

// Poll the PHP server until it answers (up to ~15 seconds)
function waitForServer(url, attempt = 0) {
    return new Promise((resolve, reject) => {
        const req = http.get(url, (res) => {
            res.resume();
            resolve();
        });
        req.on('error', () => {
            if (attempt >= 60) return reject(new Error('timeout'));
            setTimeout(() => waitForServer(url, attempt + 1).then(resolve, reject), 250);
        });
    });
}

async function createWindow() {
    const userDataPath = setupUserData();

    let port;
    try {
        port = await findFreePort();
    } catch (err) {
        return showFatalError(`No free network port is available for the app.\n\n${err.message}`);
    }
    const startUrl = `http://${PHP_HOST}:${port}/index.php`;
    startPhpServer(userDataPath, port);

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

    mainWindow.on('closed', () => {
        mainWindow = null;
    });

    try {
        await waitForServer(startUrl);
    } catch (err) {
        return showFatalError('The built-in PHP server did not respond. Please reopen the app.');
    }
    if (mainWindow) mainWindow.loadURL(startUrl);
}

if (app.hasSingleInstanceLock()) {
    app.whenReady().then(createWindow);
}

app.on('before-quit', stopPhpServer);

app.on('window-all-closed', () => {
    stopPhpServer();
    if (process.platform !== 'darwin') app.quit();
});
