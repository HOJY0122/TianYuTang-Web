import type { CapacitorConfig } from '@capacitor/cli';

/**
 * The app is the website's staff pages in a phone shell, plus a native
 * camera scanner. Which website: TYT_SITE_URL when the app is built,
 * e.g. TYT_SITE_URL=https://www.tianyutang.org npx cap sync android
 * Only that site gets the native bridge; other links open in the browser.
 */
const site = (process.env.TYT_SITE_URL || 'https://tianyutang.example').replace(/\/+$/, '');

const config: CapacitorConfig = {
  appId: 'org.tianyutang.staff',
  appName: '天玉堂 Staff',
  webDir: 'www',
  server: {
    url: site,
    appStartPath: '/admin/login',
    // Plain http only for testing on a local network (e.g. http://192.168.1.20:8000)
    cleartext: site.startsWith('http://'),
  },
  android: {
    allowMixedContent: false,
    webContentsDebuggingEnabled: process.env.TYT_DEBUG === '1',
  },
};

export default config;
