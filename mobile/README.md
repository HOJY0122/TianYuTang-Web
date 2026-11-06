# 天玉堂 Staff — Android app

The staff pages of the website (easy mode, check-in, counter, walk-in…) in a
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
| 現場報到 Check-in | QR | opens the registration |
| 現場布施 Counter | QR | opens the donation |

NFC: MyKad's chip is a **contact** chip (read by a card reader), not NFC, so
phones cannot read it by tapping. Camera reading is the way.

## Get the APK (no Android Studio needed)

1. GitHub → **Actions** → **Android staff app** → **Run workflow**.
2. Enter the site address, e.g. `https://www.tianyutang.org` → Run.
3. When it is green (≈5–8 min), open the run and download
   **tianyutang-staff-apk** (a zip with `tianyutang-staff.apk`).
4. Send the APK to staff phones (WhatsApp / Google Drive). On the phone: open
   it → allow "Install unknown apps" for that app once → Install.

Tip: set a repository **variable** `TYT_SITE_URL` (Settings → Secrets and
variables → Actions → Variables) and every automatic build uses it.

The site must be on **HTTPS** (Cloudflare gives it free). For testing on the
local network only, `http://192.168.x.x:8000` also works.

### Same signing key every time (recommended before the event)

Without a key, each build is signed with a throw-away key, so a new APK will
not install over the old one (uninstall first). To keep one key:

```bash
keytool -genkeypair -v -keystore tyt.jks -alias tyt -keyalg RSA -keysize 2048 -validity 10000
base64 -w0 tyt.jks > tyt.jks.b64
```

Add repository **secrets**: `TYT_KEYSTORE_BASE64` (contents of `tyt.jks.b64`),
`TYT_KEYSTORE_PASSWORD`, `TYT_KEY_ALIAS` (`tyt`), `TYT_KEY_PASSWORD`.
Keep `tyt.jks` safe and **never commit it** (git ignores `*.jks`).

## Build locally (developers)

Needs Node 22, JDK 21 and the Android SDK (Android Studio).

```bash
cd mobile
npm ci
TYT_SITE_URL=https://www.tianyutang.org npx cap sync android
cd android && ./gradlew assembleDebug     # → app/build/outputs/apk/debug/app-debug.apk
```

## How it fits together

- `capacitor.config.ts` — which site the app opens (`TYT_SITE_URL`), starting
  at `/admin/login`. Only that site gets the native bridge; other links open
  in the phone's browser.
- `android/app/src/main/java/org/tianyutang/staff/ScanActivity.java` — the
  camera screen (CameraX + ML Kit text and QR) with the manual box.
- `.../TytScanPlugin.java` — `Capacitor.Plugins.TytScan.scan({mode})` for the page.
- Website side: `public/assets/js/app-scan.js` adds the buttons when it runs
  inside the app (`data-app-scan="ic|qr|any"`, `data-app-scan-submit`); in a
  normal browser it does nothing.
