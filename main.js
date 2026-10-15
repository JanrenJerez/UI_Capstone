const { app, BrowserWindow, nativeImage } = require('electron');
const cp = require('child_process');
const path = require('path');
const fs = require('fs');

let mainWindow = null;
let phpServer = null;

const iconPath = nativeImage.createFromPath(path.join(__dirname, 'assets/logo.ico'));

function setupUserData() {
    const userDataPath = app.getPath('userData');
    
    // Create a writable assets/uploads directory in user data
    const uploadsDir = path.join(userDataPath, 'assets');
    if (!fs.existsSync(uploadsDir)) {
        fs.mkdirSync(uploadsDir, { recursive: true });
    }

    // Path for SQLite database in userData
    const dbTarget = path.join(userDataPath, 'database.sqlite');
    // Adjust this source path to where your initial database file is located inside your project
    const dbSource = path.join(__dirname, 'includes', 'database.sqlite'); 

    // Copy initial database on first launch if it doesn't exist in userData yet
    if (!fs.existsSync(dbTarget) && fs.existsSync(dbSource)) {
        fs.copyFileSync(dbSource, dbTarget);
    }

    return userDataPath;
}

function startPhpServer(userDataPath) {
    const phpPort = 8000;
    const publicPath = path.join(__dirname);
    
    // Pass USER_DATA_PATH to the PHP server environment
    phpServer = cp.spawn('php', ['-S', `127.0.0.1:${phpPort}`, '-t', publicPath], {
        env: { ...process.env, USER_DATA_PATH: userDataPath }
    });
}

function createWindow() {
    const userDataPath = setupUserData();
    startPhpServer(userDataPath);

    mainWindow = new BrowserWindow({
        width: 1200,
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

    setTimeout(() => {
        mainWindow.loadURL('http://127.0.0.1:8000/index.php');
    }, 1000);

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    if (phpServer) phpServer.kill();
    if (process.platform !== 'darwin') app.quit();
});