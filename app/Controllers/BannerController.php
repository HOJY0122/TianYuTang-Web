<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Models\Banner;
use App\Models\Event;
use App\Models\Setting;
use RuntimeException;

/**
 * 首頁橫幅 Home banner — the pictures that take turns at the top of the
 * home page, and how they are shown (system admin only).
 *
 * Every picture can be nudged and zoomed so the part that matters stays
 * in view at whatever height the banner is given, on computers and on
 * phones. The page shows the real slideshow as a live preview.
 */
class BannerController extends Controller
{
    /** Limits for the settings, shared with the page's sliders. */
    public const HEIGHT_MIN = 15, HEIGHT_MAX = 80;       // % of the page width
    public const INTERVAL_MIN = 3, INTERVAL_MAX = 15;    // seconds
    public const ZOOM_MAX = 250;                         // %

    /** GET /system/banners */
    public function index(): void
    {
        $this->requireSystemAdmin();
        $this->view('system/banners', [
            'pageTitle' => '首頁橫幅 Home banner',
            'nav'       => 'banners',
            'slides'    => (new Banner())->all(),
            'site'      => (new Setting())->site(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /system/banners/add — one new picture, added at the end */
    public function add(): void
    {
        $this->requireSystemAdmin();
        if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '圖片太大 Picture too large',
                '上傳的圖片超過伺服器限制（' . ini_get('post_max_size') . '）。The picture is over the server limit.');
            $this->redirect('/system/banners');
        }
        $this->requireCsrf();
        if (!ImageUploader::wasProvided($_FILES['banner'] ?? null)) {
            $this->flash('error', '沒有圖片 No picture', '請先選擇一張圖片。Please choose a picture first.');
            $this->redirect('/system/banners');
        }
        try {
            $path = (new ImageUploader('banners'))->store($_FILES['banner'], 1920);
        } catch (RuntimeException $e) {
            $this->flash('error', '上傳失敗 Upload failed', $e->getMessage());
            $this->redirect('/system/banners');
        }
        $info = @getimagesize(BASE_PATH . '/public/' . $path);
        $id   = (new Banner())->add($path, $info[0] ?? 1920, $info[1] ?? 640);
        $this->flash('success', '已加入 Added',
            "新圖片已加在最後，可以拖動排序、調整位置。\nThe picture was added at the end — drag to reorder, and nudge it into place.");
        $this->redirect('/system/banners#slide-' . $id);
    }

    /** POST /system/banners/save — how the banner is shown, and every slide's position */
    public function save(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $setting = new Setting();
        $int = static fn($v, int $min, int $max, int $def): int
            => is_numeric($v) ? max($min, min($max, (int) round((float) $v))) : $def;

        // Height: "whole picture" (0) or a share of the page width.
        foreach (['desktop', 'mobile'] as $dev) {
            $whole = !empty($_POST['whole_' . $dev]);
            $h     = $int($_POST['height_' . $dev] ?? null, self::HEIGHT_MIN, self::HEIGHT_MAX, 35);
            $setting->set('banner_height_' . $dev, $whole ? '0' : (string) $h);
        }
        $setting->set('banner_interval', (string) $int($_POST['interval'] ?? null, self::INTERVAL_MIN, self::INTERVAL_MAX, 6));
        $setting->set('banner_effect', ($_POST['effect'] ?? '') === 'slide' ? 'slide' : 'fade');
        $setting->set('banner_controls', ($_POST['controls'] ?? '1') === '0' ? '0' : '1');

        $model = new Banner();
        $known = array_column($model->all(), 'id');
        foreach ((array) ($_POST['slide'] ?? []) as $id => $v) {
            if (!in_array((int) $id, array_map('intval', $known), true) || !is_array($v)) {
                continue;
            }
            $str  = static fn(string $k, int $max): ?string
                => (is_string($v[$k] ?? null) && trim($v[$k]) !== '') ? mb_substr(trim($v[$k]), 0, $max) : null;
            $link = $str('link_url', 255);
            // Only a web address or a page on this site — never javascript: and the like.
            if ($link !== null && !preg_match('#^(https?://|/)[^\s<>"]*$#i', $link)) {
                $link = null;
            }
            $model->tune((int) $id, [
                'pos_x'      => $int($v['pos_x'] ?? null, 0, 100, 50),
                'pos_y'      => $int($v['pos_y'] ?? null, 0, 100, 50),
                'zoom'       => $int($v['zoom'] ?? null, 100, self::ZOOM_MAX, 100),
                'is_active'  => !empty($v['is_active']) ? 1 : 0,
                'caption_zh' => $str('caption_zh', 120),
                'caption_en' => $str('caption_en', 160),
                'link_url'   => $link,
            ]);
        }
        $this->flash('success', '已儲存 Saved', '首頁橫幅已更新。The home banner is updated.');
        $this->redirect('/system/banners');
    }

    /** POST /system/banners/reorder — from drag and drop */
    public function reorder(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        (new Banner())->reorder(array_slice($ids, 0, 200));
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    /** POST /system/banners/delete */
    public function delete(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $model = new Banner();
        $row   = $model->find((int) ($_POST['id'] ?? 0));
        if ($row) {
            $model->delete((int) $row['id']);
            // The file goes only when nothing else still shows it.
            $path = $row['image_path'];
            if ($model->countUsing($path) === 0
                && (new Event())->countOtherEventsUsingImage($path, 0) === 0
                && !in_array($path, (new Setting())->all(), true)) {
                (new ImageUploader('banners'))->delete($path);
            }
            $this->flash('success', '已刪除 Deleted', '圖片已從首頁橫幅移除。The picture was removed from the banner.');
        }
        $this->redirect('/system/banners');
    }
}
