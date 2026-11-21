<?php
/**
 * Previous / next pager. Expects $pager_page, $pager_pages, and $pager_url,
 * a function from page number to href.
 */
?>
<?php if ($pager_pages > 1): ?>
  <nav aria-label="Pages">
    <ul class="pagination pagination-sm flex-wrap">
      <li class="page-item<?= $pager_page <= 1 ? " disabled" : "" ?>">
        <a class="page-link" href="<?= e($pager_url(max(1, $pager_page - 1))) ?>">Previous</a>
      </li>
      <li class="page-item disabled">
        <span class="page-link">Page <?= (int) $pager_page ?> of <?= (int) $pager_pages ?></span>
      </li>
      <li class="page-item<?= $pager_page >= $pager_pages ? " disabled" : "" ?>">
        <a class="page-link" href="<?= e($pager_url(min($pager_pages, $pager_page + 1))) ?>">Next</a>
      </li>
    </ul>
  </nav>
<?php endif ?>
