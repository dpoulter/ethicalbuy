<?php
/**
 * Rating key. Driven by rating_bands() so the legend and the row colours
 * always agree. Include with:
 *   <?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>
 */
?>
<details class="mb-4">
  <summary class="text-muted">What do the colours mean?</summary>
  <ul class="list-group mt-2">
    <?php foreach (rating_bands() as $band): ?>
      <li class="list-group-item<?= $band["class"] === "" ? "" : " list-group-item-" . $band["class"] ?>">
        <strong><?= e($band["label"]) ?></strong>
        <span class="text-muted">(<?= e($band["range"]) ?>)</span>
        &mdash; <?= e($band["blurb"]) ?>
      </li>
    <?php endforeach ?>
  </ul>
</details>
