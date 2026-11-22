import { spawn } from 'node:child_process';
import process from 'node:process';

const args = process.argv.slice(2);
const isMobile = args.includes('--mobile') || args.includes('-m');
const urlArg = args.find(a => a.startsWith('http://') || a.startsWith('https://')) || 'http://localhost:3000';

console.log('==============================================');
console.log(' Safari / WebKit Preview Tool for Linux');
console.log(` Target URL : ${urlArg}`);
console.log(` Mode       : ${isMobile ? 'iPhone Emulation (Mobile)' : 'Desktop Safari / WebKit'}`);
console.log('==============================================');

async function launchWithPlaywright() {
  try {
    const { webkit, devices } = await import('playwright');
    console.log('Launching Playwright WebKit...');
    const browser = await webkit.launch({ headless: false });
    const context = await browser.newContext(
      isMobile
        ? { ...devices['iPhone 14 Pro'] }
        : { viewport: { width: 1440, height: 900 } }
    );
    const page = await context.newPage();
    await page.goto(urlArg);
    console.log('WebKit launched successfully! Close browser window to exit.');
    return true;
  } catch (err) {
    return false;
  }
}

function launchWithEpiphany() {
  try {
    console.log('Launching Epiphany (GNOME Web - Native WebKit Engine)...');
    console.log('💡 Tip: Press F12 (Inspect) or Ctrl+Shift+M (Mobile Device Mode)');
    const child = spawn('flatpak', ['run', 'org.gnome.Epiphany', '--new-window', urlArg], {
      detached: true,
      stdio: 'ignore'
    });
    child.unref();
    console.log('Epiphany browser launched!');
    return true;
  } catch (err) {
    return false;
  }
}

async function main() {
  if (isMobile) {
    const pwOk = await launchWithPlaywright();
    if (!pwOk) {
      console.log('Note: Playwright WebKit needs 3 Ubuntu system libraries:');
      console.log('  sudo apt-get install -y libevent-2.1-7t64 libgstreamer-plugins-bad1.0-0 libavif16');
      console.log('Opening Epiphany instead (Use Ctrl+Shift+M for responsive mobile mode)...');
      launchWithEpiphany();
    }
  } else {
    const epOk = launchWithEpiphany();
    if (!epOk) {
      const pwOk = await launchWithPlaywright();
      if (!pwOk) {
        console.error('Neither Epiphany nor Playwright WebKit could be launched.');
      }
    }
  }
}

main();
