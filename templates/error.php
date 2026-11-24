<div class="notice-panel" role="alert">
  <svg class="notice-panel__mark" viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" focusable="false">
    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.6"/>
    <path d="M12 7v6M12 16.5v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
  </svg>
  <h1 class="notice-panel__title h3"><?= e($heading) ?></h1>
  <p class="notice-panel__message"><?= e($message) ?></p>
  <p class="mb-0">
    <a href="/">Home</a>
    &middot; <a href="/search.php">Search</a>
    <?php if ((int) $code !== 503): ?>
      &middot; <a href="/contact.php">Contact us</a>
    <?php endif ?>
  </p>
</div>
