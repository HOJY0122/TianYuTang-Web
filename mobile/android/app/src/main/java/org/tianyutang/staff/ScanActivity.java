package org.tianyutang.staff;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.media.Image;
import android.os.Bundle;
import android.text.InputType;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.HapticFeedbackConstants;
import android.view.KeyEvent;
import android.view.View;
import android.view.ViewGroup;
import android.view.inputmethod.EditorInfo;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.OptIn;
import androidx.appcompat.app.AppCompatActivity;
import androidx.camera.core.Camera;
import androidx.camera.core.CameraSelector;
import androidx.camera.core.ExperimentalGetImage;
import androidx.camera.core.ImageAnalysis;
import androidx.camera.core.ImageProxy;
import androidx.camera.core.Preview;
import androidx.camera.lifecycle.ProcessCameraProvider;
import androidx.camera.view.PreviewView;
import androidx.core.content.ContextCompat;

import com.google.android.gms.tasks.Task;
import com.google.android.gms.tasks.Tasks;
import com.google.common.util.concurrent.ListenableFuture;
import com.google.mlkit.vision.barcode.BarcodeScanner;
import com.google.mlkit.vision.barcode.BarcodeScannerOptions;
import com.google.mlkit.vision.barcode.BarcodeScanning;
import com.google.mlkit.vision.barcode.common.Barcode;
import com.google.mlkit.vision.common.InputImage;
import com.google.mlkit.vision.text.Text;
import com.google.mlkit.vision.text.TextRecognition;
import com.google.mlkit.vision.text.TextRecognizer;
import com.google.mlkit.vision.text.latin.TextRecognizerOptions;

import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Full-screen scanner.
 *
 * The camera looks for a MyKad IC number (YYMMDD-PB-###G) and/or a QR code
 * on every frame and closes the moment it is sure. Everything happens on
 * the phone (ML Kit, offline): no picture is saved or sent anywhere — only
 * the number or code that was read goes back to the page.
 *
 * If the camera cannot read it (glare, worn card, no permission), the box at
 * the bottom takes the IC, name or reference by hand.
 *
 * Result extras: "type" = ic | qr | manual, "value" = what was read/typed.
 */
public class ScanActivity extends AppCompatActivity {

    public static final String EXTRA_MODE = "mode";      // ic | qr | any
    public static final String EXTRA_TITLE = "title";
    public static final String RESULT_TYPE = "type";
    public static final String RESULT_VALUE = "value";

    /** 12 digits as printed on a MyKad: 900101-14-5678, with or without dashes/spaces. */
    private static final Pattern IC = Pattern.compile("(?<!\\d)(\\d{6})\\s*[-–—]?\\s*(\\d{2})\\s*[-–—]?\\s*(\\d{4})(?!\\d)");
    /** The same number must be read on this many frames in a row before we trust it. */
    private static final int IC_CONFIRM_FRAMES = 2;

    private String mode = "any";
    private PreviewView preview;
    private TextView status;
    private EditText manual;
    private Button torchBtn;
    private Camera camera;
    private boolean torchOn = false;
    private volatile boolean finished = false;

    private ExecutorService analysisExecutor;
    private TextRecognizer textRecognizer;
    private BarcodeScanner barcodeScanner;
    private String lastIc = null;
    private int icHits = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        String m = getIntent().getStringExtra(EXTRA_MODE);
        if ("ic".equals(m) || "qr".equals(m)) {
            mode = m;
        }
        setContentView(buildLayout(getIntent().getStringExtra(EXTRA_TITLE)));

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            analysisExecutor = Executors.newSingleThreadExecutor();
            if (!"qr".equals(mode)) {
                textRecognizer = TextRecognition.getClient(TextRecognizerOptions.DEFAULT_OPTIONS);
            }
            if (!"ic".equals(mode)) {
                barcodeScanner = BarcodeScanning.getClient(new BarcodeScannerOptions.Builder()
                        .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
                        .build());
            }
            startCamera();
        } else {
            say("相機未允許，請在下面手動輸入。", "Camera not allowed — please type below.");
            torchBtn.setEnabled(false);
            manual.requestFocus();
        }
    }

    // ------------------------------------------------------------------ UI

    private int dp(int v) {
        return (int) TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, v, getResources().getDisplayMetrics());
    }

    private View buildLayout(String title) {
        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.BLACK);

        preview = new PreviewView(this);
        root.addView(preview, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        // Top: what to point at
        TextView head = new TextView(this);
        String zh, en;
        if ("ic".equals(mode)) {
            zh = "對準身份證號碼";
            en = "Point at the IC number";
        } else if ("qr".equals(mode)) {
            zh = "對準 QR Code";
            en = "Point at the QR code";
        } else {
            zh = "對準身份證號碼或 QR Code";
            en = "Point at the IC number or QR code";
        }
        head.setText((title != null && !title.isEmpty() ? title + "\n" : "") + zh + "\n" + en);
        head.setTextColor(Color.WHITE);
        head.setTextSize(TypedValue.COMPLEX_UNIT_SP, 18);
        head.setTypeface(Typeface.DEFAULT_BOLD);
        head.setGravity(Gravity.CENTER);
        head.setPadding(dp(16), dp(28), dp(16), dp(16));
        head.setBackgroundColor(0x99000000);
        FrameLayout.LayoutParams hp = new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.TOP);
        root.addView(head, hp);

        // Middle: a guide frame
        View frame = new View(this);
        GradientDrawable box = new GradientDrawable();
        box.setStroke(dp(3), 0xFFFFD54F);
        box.setCornerRadius(dp(14));
        frame.setBackground(box);
        FrameLayout.LayoutParams fp = "qr".equals(mode)
                ? new FrameLayout.LayoutParams(dp(240), dp(240), Gravity.CENTER)
                : new FrameLayout.LayoutParams(dp(300), dp(190), Gravity.CENTER);
        root.addView(frame, fp);

        // Bottom: status, manual entry, buttons
        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setBackgroundColor(0xEE111111);
        panel.setPadding(dp(16), dp(12), dp(16), dp(20));

        status = new TextView(this);
        status.setTextColor(0xFFDDDDDD);
        status.setTextSize(TypedValue.COMPLEX_UNIT_SP, 15);
        status.setGravity(Gravity.CENTER);
        status.setPadding(0, 0, 0, dp(10));
        panel.addView(status);
        say("掃描中…", "Scanning…");

        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        manual = new EditText(this);
        manual.setHint("ic".equals(mode) ? "手動輸入身份證 Type IC" : "qr".equals(mode) ? "手動輸入編號 Type reference" : "手動輸入 IC／姓名／編號 Type IC, name or ref");
        manual.setHintTextColor(0xFF999999);
        manual.setTextColor(Color.BLACK);
        manual.setTextSize(TypedValue.COMPLEX_UNIT_SP, 18);
        manual.setSingleLine(true);
        manual.setInputType("ic".equals(mode) ? InputType.TYPE_CLASS_PHONE : InputType.TYPE_CLASS_TEXT);
        manual.setImeOptions(EditorInfo.IME_ACTION_SEARCH);
        GradientDrawable field = new GradientDrawable();
        field.setColor(Color.WHITE);
        field.setCornerRadius(dp(10));
        manual.setBackground(field);
        manual.setPadding(dp(12), dp(10), dp(12), dp(10));
        manual.setOnEditorActionListener((v, actionId, event) -> {
            boolean enter = event != null && event.getKeyCode() == KeyEvent.KEYCODE_ENTER && event.getAction() == KeyEvent.ACTION_DOWN;
            if (actionId == EditorInfo.IME_ACTION_SEARCH || enter) {
                submitManual();
                return true;
            }
            return false;
        });
        row.addView(manual, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        Button go = button("搜尋\nSearch", 0xFFB71C1C);
        go.setOnClickListener(v -> submitManual());
        LinearLayout.LayoutParams gp = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.MATCH_PARENT);
        gp.leftMargin = dp(8);
        row.addView(go, gp);
        panel.addView(row);

        LinearLayout row2 = new LinearLayout(this);
        row2.setOrientation(LinearLayout.HORIZONTAL);
        row2.setPadding(0, dp(10), 0, 0);
        torchBtn = button("燈 Light", 0xFF424242);
        torchBtn.setOnClickListener(v -> toggleTorch());
        Button cancel = button("取消 Cancel", 0xFF424242);
        cancel.setOnClickListener(v -> {
            setResult(RESULT_CANCELED);
            finish();
        });
        LinearLayout.LayoutParams half = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
        row2.addView(torchBtn, half);
        LinearLayout.LayoutParams half2 = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
        half2.leftMargin = dp(8);
        row2.addView(cancel, half2);
        panel.addView(row2);

        root.addView(panel, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.BOTTOM));
        return root;
    }

    private Button button(String label, int color) {
        Button b = new Button(this);
        b.setText(label);
        b.setAllCaps(false);
        b.setTextColor(Color.WHITE);
        b.setTextSize(TypedValue.COMPLEX_UNIT_SP, 15);
        GradientDrawable bg = new GradientDrawable();
        bg.setColor(color);
        bg.setCornerRadius(dp(10));
        b.setBackground(bg);
        b.setPadding(dp(14), dp(8), dp(14), dp(8));
        return b;
    }

    private void say(String zh, String en) {
        runOnUiThread(() -> status.setText(zh + "\n" + en));
    }

    // -------------------------------------------------------------- Camera

    private void startCamera() {
        ListenableFuture<ProcessCameraProvider> future = ProcessCameraProvider.getInstance(this);
        future.addListener(() -> {
            try {
                ProcessCameraProvider provider = future.get();
                Preview p = new Preview.Builder().build();
                p.setSurfaceProvider(preview.getSurfaceProvider());
                ImageAnalysis analysis = new ImageAnalysis.Builder()
                        .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                        .build();
                analysis.setAnalyzer(analysisExecutor, this::analyze);
                provider.unbindAll();
                camera = provider.bindToLifecycle(this, CameraSelector.DEFAULT_BACK_CAMERA, p, analysis);
            } catch (Exception e) {
                say("相機無法開啟，請在下面手動輸入。", "Camera could not start — please type below.");
            }
        }, ContextCompat.getMainExecutor(this));
    }

    private void toggleTorch() {
        if (camera == null || !camera.getCameraInfo().hasFlashUnit()) {
            return;
        }
        torchOn = !torchOn;
        camera.getCameraControl().enableTorch(torchOn);
    }

    @OptIn(markerClass = ExperimentalGetImage.class)
    private void analyze(ImageProxy proxy) {
        Image media = proxy.getImage();
        if (media == null || finished) {
            proxy.close();
            return;
        }
        InputImage image = InputImage.fromMediaImage(media, proxy.getImageInfo().getRotationDegrees());
        List<Task<?>> tasks = new ArrayList<>();
        Task<List<Barcode>> qrTask = barcodeScanner != null ? barcodeScanner.process(image) : null;
        Task<Text> textTask = textRecognizer != null ? textRecognizer.process(image) : null;
        if (qrTask != null) tasks.add(qrTask);
        if (textTask != null) tasks.add(textTask);

        Tasks.whenAllComplete(tasks).addOnCompleteListener(done -> {
            try {
                if (qrTask != null && qrTask.isSuccessful() && qrTask.getResult() != null) {
                    for (Barcode b : qrTask.getResult()) {
                        String raw = b.getRawValue();
                        if (raw != null && !raw.trim().isEmpty()) {
                            deliver("qr", raw.trim());
                            return;
                        }
                    }
                }
                if (textTask != null && textTask.isSuccessful() && textTask.getResult() != null) {
                    String ic = findIc(textTask.getResult().getText());
                    if (ic == null) {
                        icHits = 0;
                        lastIc = null;
                    } else if (ic.equals(lastIc)) {
                        icHits++;
                    } else {
                        lastIc = ic;
                        icHits = 1;
                    }
                    if (ic != null) {
                        say("讀到 " + ic + "，請保持不動…", "Read " + ic + " — hold still…");
                    }
                    if (icHits >= IC_CONFIRM_FRAMES) {
                        deliver("ic", ic);
                    }
                }
            } finally {
                proxy.close();
            }
        });
    }

    /** First plausible MyKad number in the text, formatted 900101-14-5678, or null. */
    static String findIc(String text) {
        if (text == null) {
            return null;
        }
        Matcher m = IC.matcher(text.replace('O', '0').replace('o', '0'));
        while (m.find()) {
            String d = m.group(1);
            int month = Integer.parseInt(d.substring(2, 4));
            int day = Integer.parseInt(d.substring(4, 6));
            if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
                return d + "-" + m.group(2) + "-" + m.group(3);
            }
        }
        return null;
    }

    // -------------------------------------------------------------- Result

    private void submitManual() {
        String v = manual.getText().toString().trim();
        if (v.isEmpty()) {
            manual.requestFocus();
            return;
        }
        deliver("manual", v);
    }

    private void deliver(String type, String value) {
        if (finished) {
            return;
        }
        finished = true;
        runOnUiThread(() -> {
            preview.performHapticFeedback(HapticFeedbackConstants.LONG_PRESS);
            Intent data = new Intent();
            data.putExtra(RESULT_TYPE, type);
            data.putExtra(RESULT_VALUE, value.length() > 200 ? value.substring(0, 200) : value);
            setResult(RESULT_OK, data);
            finish();
        });
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (analysisExecutor != null) analysisExecutor.shutdown();
        if (textRecognizer != null) textRecognizer.close();
        if (barcodeScanner != null) barcodeScanner.close();
    }
}
