<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Models\Post;
use RuntimeException;

/**
 * PostController — news and announcements shown on the home page.
 *
 * Admins write a post in Chinese (English optional), may add a picture,
 * pin it to the top, or keep it as an unpublished draft.
 */
class PostController extends Controller
{
    /** GET /admin/posts */
    public function index(): void
    {
        $this->requireAdmin();
        $this->view('admin/posts', [
            'pageTitle' => '最新消息 News',
            'nav'       => 'posts',
            'posts'     => (new Post())->all(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** GET /admin/posts/new  and  GET /admin/posts/edit?id= */
    public function form(): void
    {
        $this->requireAdmin();
        $id   = (int) ($_GET['id'] ?? 0);
        $post = $id > 0 ? (new Post())->find($id) : null;
        if ($id > 0 && $post === null) {
            $this->redirect('/admin/posts');
        }

        $old = $_SESSION['post_old'] ?? null;
        $errors = $_SESSION['post_errors'] ?? [];
        unset($_SESSION['post_old'], $_SESSION['post_errors']);

        $this->view('admin/post_form', [
            'pageTitle' => $post ? '編輯消息 Edit Post' : '新增消息 New Post',
            'nav'       => 'posts',
            'post'      => array_merge([
                'id' => 0, 'title_zh' => '', 'title_en' => '', 'body_zh' => '', 'body_en' => '',
                'image_path' => null, 'is_published' => 1, 'is_pinned' => 0,
            ], $post ?? [], $old ?? []),
            'errors'    => $errors,
        ]);
    }

    /** POST /admin/posts/save — create or update */
    public function save(): void
    {
        $this->requireAdmin();
        if (empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '圖片太大 Image too large', '超過伺服器限制（' . ini_get('post_max_size') . '）。Please use a smaller picture.');
            $this->redirect('/admin/posts');
        }
        $this->requireCsrf();

        $model = new Post();
        $id    = (int) ($_POST['id'] ?? 0);
        $post  = $id > 0 ? $model->find($id) : null;
        if ($id > 0 && $post === null) {
            $this->redirect('/admin/posts');
        }

        $str = static fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        $f = [
            'title_zh'     => $str('title_zh'),
            'title_en'     => $str('title_en') ?: null,
            'body_zh'      => $str('body_zh') ?: null,
            'body_en'      => $str('body_en') ?: null,
            'is_published' => !empty($_POST['is_published']),
            'is_pinned'    => !empty($_POST['is_pinned']),
        ];
        $errors = Post::validate($f);

        // Picture: only touched once the text is valid, and the OLD file
        // is deleted only after the row has been saved.
        $uploader = new ImageUploader('posts');
        $oldImage = $post['image_path'] ?? null;
        $newImage = null;
        if (!$errors && ImageUploader::wasProvided($_FILES['image'] ?? null)) {
            try {
                $newImage = $uploader->store($_FILES['image'], 1600);
                $f['image_path'] = $newImage;
            } catch (RuntimeException $e) {
                $errors[] = '圖片 Image：' . $e->getMessage();
            }
        } elseif (!$errors && !empty($_POST['remove_image'])) {
            $f['image_path'] = null;
        }

        if ($errors) {
            $_SESSION['post_errors'] = $errors;
            $_SESSION['post_old']    = array_diff_key($f, ['image_path' => 1]);
            $this->redirect($id ? '/admin/posts/edit?id=' . $id : '/admin/posts/new');
        }

        if ($id) {
            $model->update($id, $f);
            // Pinning a post that was not pinned brings it to the top.
            if ($f['is_pinned'] && empty($post['is_pinned'])) {
                $model->moveToTop($id);
            }
        } else {
            $id = $model->create($f, (string) ($_SESSION['admin_username'] ?? 'admin'));
        }

        if ($oldImage && array_key_exists('image_path', $f) && $f['image_path'] !== $oldImage) {
            $uploader->delete($oldImage);
        }

        $this->flash('success', '已儲存 Saved', $f['is_published']
            ? '消息已發佈到首頁。The post is live on the home page.'
            : '已存為草稿，首頁不會顯示。Saved as a draft — not shown on the home page.');
        $this->redirect('/admin/posts');
    }

    /** POST /admin/posts/reorder — ids[] top to bottom, from drag and drop. Answers JSON. */
    public function reorder(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        (new Post())->reorder(array_slice($ids, 0, 500));
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    /** POST /admin/posts/delete */
    public function delete(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Post();
        $post  = $model->find((int) ($_POST['id'] ?? 0));
        if ($post !== null) {
            $model->delete((int) $post['id']);
            (new ImageUploader('posts'))->delete($post['image_path']);
            $this->flash('success', '已刪除 Deleted', '消息已刪除。The post has been deleted.');
        }
        $this->redirect('/admin/posts');
    }
}
