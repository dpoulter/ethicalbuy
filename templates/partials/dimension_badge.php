<?php
/**
 * One product dimension: a rating badge, or "Not assessed". Expects $dim_score
 * (["score" => float, "input" => string] or null).
 */
?>
<?php if ($dim_score === null): ?>
  <span class="score score--none score--sm"><span class="score__value score__value--text">Not assessed</span></span>
<?php else: ?>
  <?php $rating = $dim_score["score"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?>
  <?php if ($dim_score["input"] !== null && $dim_score["input"] !== ""): ?>
    <span class="score-input"><?= e($dim_score["input"]) ?></span>
  <?php endif ?>
<?php endif ?>
