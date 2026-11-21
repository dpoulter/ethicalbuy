<div class="notice-panel" role="alert">
  <svg class="notice-panel__mark" viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" focusable="false">
    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.6"/>
    <path d="M12 7v6M12 16.5v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
  </svg>
  <h3 class="notice-panel__title">Sorry!</h3>
  <p class="notice-panel__message"><?= e($message) ?></p>
  <p class="mb-0">
    <a href="javascript:history.go(-1);">Back</a>
    &middot; <a href="/search.php">Search</a>
    &middot; <a href="/index.php">Home</a>
  </p>
</div>
