<?php
/**
 * Result modal — shown after a form submission (success or failure).
 * Expects $flash = ['type'=>'success'|'error', 'title'=>..., 'message'=>...]
 */
if (empty($flash)) {
    return;
}
$isError = ($flash['type'] ?? '') === 'error';
?>
<div class="modal" id="resultModal">
  <div class="modal-box<?= $isError ? ' is-error' : '' ?>">
    <div class="icon"><?= $isError ? '⚠️' : '🙏' ?></div>
    <h2 class="kai"><?= h($flash['title']) ?></h2>
    <p><?= h($flash['message']) ?></p>
    <button class="primary" onclick="document.getElementById('resultModal').remove()">好的 OK</button>
  </div>
</div>
