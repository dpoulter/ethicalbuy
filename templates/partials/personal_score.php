<?php
/**
 * Personalised score badge. Expects $personal (from personal_score()).
 * Optional $personal_compact to drop the coverage caption.
 */
$p = $personal;
$p_class = $p["score"] === null ? "" : rating_class($p["score"]);
?>
<span class="score score--<?= $p_class === "" ? "none" : e($p_class) ?>">
  <?php if ($p["score"] === null): ?>
    <span class="score__value score__value--text">No data for your priorities</span>
  <?php else: ?>
    <span class="score__value"><?= e(rating_score($p["score"])) ?></span>
    <?php if ($p_class !== ""): ?><span class="score__label"><?= e(rating_label($p["score"])) ?></span><?php endif ?>
  <?php endif ?>
</span>

<?php if (empty($personal_compact) && $p["score"] !== null && $p["missing"]): ?>
  <span class="small score-caption <?= $p["confident"] ? "text-muted" : "score-caption--caution" ?>">
    <?php if (!$p["confident"]): ?>
      &#9888; based on only <?= (int) round($p["coverage"] * 100) ?>% of what you asked for
    <?php else: ?>
      missing <?= count($p["missing"]) ?> of your
      <?= count($p["missing"]) + count($p["covered"]) ?> priorities
    <?php endif ?>
  </span>
<?php endif ?>
<?php unset($personal_compact); ?>
