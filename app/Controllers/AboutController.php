<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Models\AboutBlock;
use App\Models\Event;
use App\Models\Setting;
use RuntimeException;

/**
 * AboutController — 關於我們 About us.
 *
 * The public page is a column of blocks: big centred pictures (e.g. the
 * main deity's poster) and headings with paragraphs, in the order the
 * system admin sets in System → 關於我們 About page. The menu item shows
 * once the page has at least one block and is switched on.
 */
class AboutController extends Controller
{
    /** GET /about */
    public function show(): void
    {
        $blocks = (new AboutBlock())->all();
        if (!$blocks || !self::enabled()) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
        }
        $this->view('about/index', [
            'event'     => (new Event())->active(),
            'activeNav' => 'about',
            'blocks'    => $blocks,
        ]);
    }

    /** Is the page switched on (System → About page)? */
    public static function enabled(): bool
    {
        return ((new Setting())->site()['about_enabled'] ?? '1') === '1';
    }

    // ------------------------------------------------------------------
    // System → 關於我們 About page

    /** GET /system/about */
    public function manage(): void
    {
        $this->requireSystemAdmin();
        $this->view('system/about', [
            'pageTitle' => '關於我們 About page',
            'nav'       => 'about',
            'blocks'    => (new AboutBlock())->all(),
            'enabled'   => self::enabled(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /system/about/save — add a block, or change one (id given). */
    public function save(): void
    {
        $this->requireSystemAdmin();
        if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '圖片太大 Picture too large', '圖片超過伺服器限制。The picture is over the server limit.');
            $this->redirect('/system/about');
        }
        $this->requireCsrf();

        $model = new AboutBlock();
        $id    = (int) ($_POST['id'] ?? 0);
        $row   = $id ? $model->find($id) : null;
        if ($id && $row === null) {
            $this->redirect('/system/about');
        }
        $kind = $row['kind'] ?? (($_POST['kind'] ?? '') === 'image' ? 'image' : 'text');

        $str = static fn(string $k, int $max): ?string => (($v = mb_substr(trim(is_string($_POST[$k] ?? null) ? str_replace("\r\n", "\n", $_POST[$k]) : ''), 0, $max)) !== '') ? $v : null;
        $v = [
            'kind'       => $kind,
            'image_path' => $row['image_path'] ?? null,
            'heading_zh' => $str('heading_zh', 120),
            'heading_en' => $str('heading_en', 160),
            'body_zh'    => $str('body_zh', 20000),
            'body_en'    => $str('body_en', 30000),
            // Layout: only the listed choices, otherwise the default.
            'width'      => self::pick('width', AboutBlock::WIDTHS),
            'text_size'  => self::pick('text_size', AboutBlock::TEXT_SIZES),
            'align'      => self::pick('align', AboutBlock::ALIGNS),
        ];

        $uploader = new ImageUploader('about');
        $newImage = null;
        if ($kind === 'image' && ImageUploader::wasProvided($_FILES['image'] ?? null)) {
            try {
                // 2000px wide: a full-width poster stays sharp on big and 3× screens.
                $newImage = $uploader->store($_FILES['image'], 2000);
            } catch (RuntimeException $e) {
                $this->flash('error', '圖片 Picture', $e->getMessage());
                $this->redirect('/system/about');
            }
            $v['image_path'] = $newImage;
        }
        if ($kind === 'image' && $v['image_path'] === null) {
            $this->flash('error', '未儲存 Not saved', '請選擇一張圖片。Please choose a picture.');
            $this->redirect('/system/about');
        }
        if ($kind === 'text' && $v['heading_zh'] === null && $v['heading_en'] === null && $v['body_zh'] === null && $v['body_en'] === null) {
            $this->flash('error', '未儲存 Not saved', '請輸入標題或內容。Please write a heading or a paragraph.');
            $this->redirect('/system/about');
        }

        if ($row) {
            $model->update($id, $v);
            if ($newImage && $row['image_path']) {
                $uploader->delete($row['image_path']);   // replaced
            }
        } else {
            $id = $model->create($v);
        }
        $this->flash('success', '已儲存 Saved', "關於我們頁面已更新。\nThe About page is updated.");
        $this->redirect('/system/about#block' . $id);
    }

    /** A posted layout choice, or the list's first (default) value. */
    private static function pick(string $key, array $choices): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) && isset($choices[$v]) ? $v : (string) array_key_first($choices);
    }

    /** POST /system/about/delete */
    public function delete(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $model = new AboutBlock();
        $row   = $model->find((int) ($_POST['id'] ?? 0));
        if ($row) {
            $model->delete((int) $row['id']);
            (new ImageUploader('about'))->delete($row['image_path']);
            $this->flash('success', '已刪除 Deleted', '這一段已刪除。The block was deleted.');
        }
        $this->redirect('/system/about');
    }

    /** POST /system/about/move — the ↑ ↓ buttons. */
    public function move(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        (new AboutBlock())->move((int) ($_POST['id'] ?? 0), ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down');
        $this->redirect('/system/about#block' . (int) ($_POST['id'] ?? 0));
    }

    /** POST /system/about/reorder — drag and drop (JSON). */
    public function reorder(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        (new AboutBlock())->reorder(array_slice($ids, 0, 500));
        $this->json(['ok' => true]);
    }

    /** POST /system/about/toggle — show or hide the page and its menu item. */
    public function toggle(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $on = ($_POST['to'] ?? '') === 'on';
        (new Setting())->set('about_enabled', $on ? '1' : '0');
        $this->flash('success', $on ? '已顯示 Shown' : '已隱藏 Hidden', $on
            ? "「關於我們」已在網站選單顯示。\nThe About page is shown in the website menu."
            : "「關於我們」已從網站隱藏。\nThe About page is hidden from the website.");
        $this->redirect('/system/about');
    }
}
