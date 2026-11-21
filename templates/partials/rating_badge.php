<?php
/**
 * A score pill, e.g. "8/10 Good". Expects $rating to be set by the caller.
 * Colours come from the score--{band} classes in public/assets/site.css,
 * keyed by rating_class(); the label is always shown so colour is never the
 * only signal.
 */
$badge_class = rating_class($rating);
?>
<span class="score score--<?= $badge_class === "" ? "none" : e($badge_class) ?>">
  <span class="score__value"><?= e(rating_score($rating)) ?></span>
  <?php if ($badge_class !== ""): ?>
    <span class="score__label"><?= e(rating_label($rating)) ?></span>
  <?php endif ?>
</span>
