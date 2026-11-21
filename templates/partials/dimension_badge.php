<?php
/**
 * One product dimension: a rating badge, or "Not assessed". Expects $dim_score
 * (["score" => float, "input" => string] or null).
 */
?>
<?php if ($dim_score === null): ?>
  <span class="badge text-bg-light border">Not assessed</span>
<?php else: ?>
  <?php $rating = $dim_score["score"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?>
  <?php if ($dim_score["input"] !== null && $dim_score["input"] !== ""): ?>
    <span class="small text-muted"><?= e($dim_score["input"]) ?></span>
  <?php endif ?>
<?php endif ?>
