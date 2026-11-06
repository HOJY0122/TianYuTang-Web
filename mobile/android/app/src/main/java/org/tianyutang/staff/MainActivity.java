package org.tianyutang.staff;

import android.os.Bundle;

import com.getcapacitor.BridgeActivity;

/** The staff pages of the website, plus the native scanner (TytScanPlugin). */
public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(TytScanPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
