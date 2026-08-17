<?php
/**
 * Personalised score badge. Expects $personal (from personal_score()).
 * Optional $personal_compact to drop the coverage caption.
 */
$p = $personal;
$p_class = $p["score"] === null ? "" : rating_class($p["score"]);
?>
<span class="badge <?= $p_class === "" ? "text-bg-light border" : "text-bg-" . $p_class ?>">
  <?php if ($p["score"] === null): ?>
    No data for your priorities
  <?php else: ?>
    <?= e(rating_score($p["score"])) ?>
    <?php if ($p_class !== ""): ?>&middot; <?= e(rating_label($p["score"])) ?><?php endif ?>
  <?php endif ?>
</span>

<?php if (empty($personal_compact) && $p["score"] !== null && $p["missing"]): ?>
  <span class="small <?= $p["confident"] ? "text-muted" : "text-danger" ?>">
    <?php if (!$p["confident"]): ?>
      &#9888; based on only <?= (int) round($p["coverage"] * 100) ?>% of what you asked for
    <?php else: ?>
      missing <?= count($p["missing"]) ?> of your
      <?= count($p["missing"]) + count($p["covered"]) ?> priorities
    <?php endif ?>
  </span>
<?php endif ?>
<?php unset($personal_compact); ?>
