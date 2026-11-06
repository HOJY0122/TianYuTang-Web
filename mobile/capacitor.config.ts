import type { CapacitorConfig } from '@capacitor/cli';

/**
 * One APK for every site: the address is typed on the app's first screen
 * (www/index.html) and kept on the phone — see SiteAddress.java and
 * MainActivity.java. Nothing site-specific is built in.
 */
const config: CapacitorConfig = {
  appId: 'org.tianyutang.staff',
  appName: '天玉堂 Staff',
  webDir: 'www',
  android: {
    allowMixedContent: false,
  },
};

export default config;
