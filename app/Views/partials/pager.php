<?php
/**
 * Page links for a list. Expects $page, $pages, $pagerBase (path) and
 * $pagerQuery (the current filters, kept on every link).
 */
if ($pages <= 1) {
    return;
}
$link = static fn(int $p): string => url($pagerBase) . '?' . h(http_build_query($pagerQuery + ['page' => $p]));
?>
<nav class="pager" aria-label="分頁 Pages">
  <?php if ($page > 1): ?><a href="<?= $link($page - 1) ?>">‹ 上一頁 Prev</a><?php endif; ?>
  <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
    <?php if ($p === $page): ?><span class="cur" aria-current="page"><?= $p ?></span>
    <?php else: ?><a href="<?= $link($p) ?>"><?= $p ?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?><a href="<?= $link($page + 1) ?>">下一頁 Next ›</a><?php endif; ?>
</nav>
