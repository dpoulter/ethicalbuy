<?php
/**
 * A rating pill, e.g. "8/10 Good". Expects $rating to be set by the caller.
 */
$badge_class = rating_class($rating);
?>
<span class="badge <?= $badge_class === "" ? "text-bg-light border" : "text-bg-" . $badge_class ?>">
  <?= e(rating_score($rating)) ?>
  <?php if (rating_class($rating) !== ""): ?>
    &middot; <?= e(rating_label($rating)) ?>
  <?php endif ?>
</span>
