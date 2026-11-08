package org.tianyutang.staff;

import android.content.pm.ApplicationInfo;
import android.os.Bundle;
import android.webkit.WebView;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;

import com.getcapacitor.BridgeActivity;
import com.getcapacitor.CapConfig;

/**
 * The staff pages of the website, plus the native scanner (TytScanPlugin).
 *
 * No address saved yet → the built-in setup screen (www/index.html) asks for
 * it. Saved → the site opens at /admin/login, and only that site gets the
 * native bridge; links elsewhere open in the phone's browser.
 *
 * Back button: goes back a page, like a browser. On the first page it asks
 * "press again to leave" instead of closing the app at once (Capacitor alone
 * closes the app on every back press).
 */
public class MainActivity extends BridgeActivity {
    private long lastBack = 0;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        String site = SiteAddress.get(this);
        if (site != null) {
            config = new CapConfig.Builder(this)
                    .setServerUrl(SiteAddress.origin(site))
                    .setStartPath(SiteAddress.path(site) + "/admin/login")
                    .setAllowMixedContent(false)
                    // Site unreachable → built-in page with Retry / Change site
                    .setErrorPath("error.html")
                    .setWebContentsDebuggingEnabled((getApplicationInfo().flags & ApplicationInfo.FLAG_DEBUGGABLE) != 0)
                    .create();
        }
        registerPlugin(TytScanPlugin.class);
        registerPlugin(TytSetupPlugin.class);
        super.onCreate(savedInstanceState);

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                WebView web = getBridge() != null ? getBridge().getWebView() : null;
                if (web != null && web.canGoBack()) {
                    web.goBack();
                    return;
                }
                long now = System.currentTimeMillis();
                if (now - lastBack < 2000) {
                    finish();
                    return;
                }
                lastBack = now;
                Toast.makeText(MainActivity.this, "再按一次返回鍵離開\nPress back again to leave", Toast.LENGTH_SHORT).show();
            }
        });
    }
}
