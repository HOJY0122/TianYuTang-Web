# 天玉堂 Staff — Android app

The staff pages of the website (Day 3 easy mode and seating, counter, walk-in…) in a
phone app, plus a **native camera scanner**:

- **IC number** — point the camera at the front of a MyKad; the 12-digit
  number (e.g. `900101-14-5678`) is read the moment it is clear (it must read
  the same number twice in a row, so a blurry frame cannot slip through).
- **QR code** — registration / donation QR (`RSVP-0007`, `DON-0012`).
- **Manual box** under the camera — if the card cannot be read (glare, worn
  card, camera refused), type the IC, name or reference there and press 搜尋.

Everything is read **on the phone** (Google ML Kit, works offline). No picture
is saved or uploaded — only the number or code goes into the page's box, and
the server checks it exactly as if it had been typed.

Where the 📷 掃描 Scan button appears (only inside the app):

| Page | Reads | Then |
|---|---|---|
| 施齋 easy mode ① Find | IC or QR | searches |
| Waiting list / walk-in IC boxes | IC | fills the box (live IC check runs) |
| 施齋入座 Day 3 registered list | IC or QR | searches |
| 現場布施 Counter | QR | opens the donation |

Also in the app (version 1.1):

- **Back button** goes back a page, like a browser. On the first page it says
  「再按一次返回鍵離開」 instead of closing the app at once.
- **Screen stays on** while 施齋 easy mode is open (it can be left on the
  counter); on other pages the phone sleeps as usual.

NFC: MyKad's chip is a **contact** chip (read by a card reader), not NFC, so
phones cannot read it by tapping. Camera reading is the way.

## One APK for every site

Each time the app opens it shows the **website address** screen: the last
address is filled in and recent ones are one tap away (handy while testing,
when the PC's address changes). Before opening, the app checks the site
answers (5 s) — if not, it says so (same Wi-Fi? server running? address
changed?) instead of a blank screen, with "Open anyway".

Tick **記住，下次直接打開 Remember** (ticked by itself for an `https://`
address) and from then on the app opens that site straight away — do this
once the live domain is up. The same APK works for testing and the live site:

- Live site: `https://www.your-domain.org` (must be HTTPS).
- Testing on the same Wi-Fi: `http://192.168.x.x:8000` (plain http is only
  accepted for local-network addresses).
- To switch later: on the sign-in page (or the "cannot reach" page) tap
  **更換網站 Change site** — the setup screen comes back, address kept.
- Long-press an address in the recent list to remove it.

## Build the APK with Android Studio (Windows)

One-time installs: **Android Studio** (includes the Android SDK and Java) and
**Node.js 22 LTS**.

```powershell
git clone https://github.com/HOJY0122/TianYuTang-Web.git   # or: git pull
cd TianYuTang-Web\mobile
npm ci
npx cap sync android
```

Then in Android Studio: **Open** → `TianYuTang-Web\mobile\android` → wait for
Gradle sync → **Build → Build App Bundle(s) / APK(s) → Build APK(s)** →
**locate** → `app-debug.apk`.
Or plug in a phone (USB debugging on) and press ▶ **Run**.

After pulling new code, run `npx cap sync android` again before building.

Give the APK to staff (WhatsApp / Google Drive). On the phone: open it →
allow "Install unknown apps" once → Install.

### Testing against your PC (same Wi-Fi)

```powershell
cd TianYuTang-Web
php -S 0.0.0.0:8000 -t public server.php
ipconfig            # your PC's address, e.g. 192.168.1.20
```

In the app's setup screen enter `http://192.168.1.20:8000`. (Windows Firewall
may ask to allow PHP — allow it on private networks.)

### Same signing key every time (before handing out to staff)

A debug APK from another PC will not install over the old one. For the real
hand-out, make one key and keep it safe (**never commit it** — git ignores
`*.jks`):

```powershell
keytool -genkeypair -v -keystore tyt.jks -alias tyt -keyalg RSA -keysize 2048 -validity 10000
```

Android Studio: **Build → Generate Signed App Bundle / APK → APK** → choose
`tyt.jks` → release. Use the same key for every later version.

## GitHub Actions (optional)

`.github/workflows/android-app.yml` can build the APK on GitHub instead
(Actions → Android staff app → Run workflow) once the account's Actions are
available. Optional secrets for a fixed key: `TYT_KEYSTORE_BASE64`
(`base64 -w0 tyt.jks`), `TYT_KEYSTORE_PASSWORD`, `TYT_KEY_ALIAS`, `TYT_KEY_PASSWORD`.

## How it fits together

- `MainActivity.java` — no address saved → built-in setup screen
  (`www/index.html`); saved → opens `<site>/admin/login`. Only that site gets
  the native bridge; other links open in the phone's browser.
- `SiteAddress.java` — checks and keeps the address (`https://`, or `http://`
  for 192.168.x.x / 10.x.x.x / 172.16–31.x.x only).
- `ScanActivity.java` — the camera screen (CameraX + ML Kit text and QR) with
  the manual box. `TytScanPlugin.java` — `Capacitor.Plugins.TytScan.scan({mode})`.
- `TytSetupPlugin.java` — `setSite` / `resetSite` / `getSite`.
- Website side: `public/assets/js/app-scan.js` adds the buttons (and the
  "Change site" link) when it runs inside the app; in a normal browser it does
  nothing.

## App icon and splash screen

Made from `assets/logo.png` (the temple logo, transparent background):

```bash
pip install pillow
python mobile/tools/make-icons.py mobile/assets/logo.png mobile/android/app/src/main/res mobile/assets/icon-1024.png
```

To use a new logo, replace `assets/logo.png`, run the line above, rebuild.
Android 12+ shows the icon on the cream launch colour set in
`res/values/styles.xml` (`windowSplashScreenBackground`).
