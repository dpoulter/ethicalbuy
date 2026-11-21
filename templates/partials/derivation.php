<?php
/**
 * How one dimension score was reached. Expects $d, one of:
 *
 *   list          brand score from several products: method, rows, points, score
 *   single        product score from one grade or set of labels: method, input, points
 *   not_recorded  derived score with no product detail stored yet: source_url
 *   inherited     taken from the brand: brand, from (the brand's own derivation)
 *   ownership     from the recorded ownership type: type, category, note, points
 *   hand          entered by hand: note (optional)
 *
 * and $row (the score_breakdown() row).
 */
$fmt = function ($n) { return rtrim(rtrim(number_format((float) $n, 1), "0"), "."); };
$is_link = function ($url) { return preg_match('~^https?://~i', (string) $url) === 1; };
?>
<?php if ($d["kind"] === "list"): ?>
  <?php
    $shown = array_slice($d["rows"], 0, DERIVATION_ROWS_SHOWN);
    $more = count($d["rows"]) - count($shown);
  ?>
  <?php if (!empty($d["method"])): ?>
    <p class="text-muted mb-2 derivation-method"><?= e($d["method"]) ?></p>
  <?php endif ?>
  <table class="table table-sm mb-1 derivation-products">
    <thead>
      <tr><th>Product</th><th><?= $row["key"] === "welfare" ? "Labels" : "Grade" ?></th><th>Points</th></tr>
    </thead>
    <tbody>
      <?php foreach ($shown as $in): ?>
        <tr>
          <td>
            <?php if (!empty($in["href"])): ?>
              <a href="<?= e($in["href"]) ?>"><?= e($in["label"]) ?></a>
            <?php elseif ($is_link($in["url"] ?? "")): ?>
              <a href="<?= e($in["url"]) ?>" target="_blank" rel="noopener noreferrer"><?= e($in["label"]) ?></a>
            <?php else: ?>
              <?= e($in["label"]) ?>
            <?php endif ?>
          </td>
          <td><?= e($in["input"] ?? "") ?></td>
          <td><?= e($fmt($in["points"])) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <?php if ($more > 0): ?>
    <p class="text-muted mb-1">and <?= (int) $more ?> more</p>
  <?php endif ?>
  <div class="font-monospace derivation-sum">
    (<?= e(implode(" + ", array_map($fmt, array_column($d["rows"], "points")))) ?>) / <?= count($d["rows"]) ?> = <?= e(number_format($row["score"], 1)) ?>
  </div>
  <p class="text-muted mt-1 mb-0">Product data from Open Food Facts (ODbL).</p>

<?php elseif ($d["kind"] === "single"): ?>
  <?php if (!empty($d["method"])): ?>
    <p class="text-muted mb-2 derivation-method"><?= e($d["method"]) ?></p>
  <?php endif ?>
  <div class="font-monospace derivation-single">
    <?= e($row["key"] === "welfare" ? "Labels" : "Grade") ?>: <?= e($d["input"] !== null && $d["input"] !== "" ? $d["input"] : "not recorded") ?>
    = <?= e($fmt($d["points"])) ?> points
  </div>
  <p class="text-muted mt-1 mb-0">Product data from Open Food Facts (ODbL).</p>

<?php elseif ($d["kind"] === "not_recorded"): ?>
  <p class="text-muted mb-1">Product detail not recorded yet.</p>
  <?php if ($is_link($d["source_url"] ?? "")): ?>
    <p class="mb-0">Source: <a href="<?= e($d["source_url"]) ?>" target="_blank" rel="noopener noreferrer">Open Food Facts</a></p>
  <?php endif ?>

<?php elseif ($d["kind"] === "inherited"): ?>
  <p class="text-muted mb-2">
    Taken from the brand,
    <a href="brand.php?brand=<?= urlencode($d["brand"]) ?>"><?= e($d["brand"]) ?></a>.
  </p>
  <?php
    // the brand's own working, rendered with this same partial
    $d_saved = $d;
    $d = $d["from"] ?? ["kind" => "hand"];
    require(__FILE__);
    $d = $d_saved;
  ?>

<?php elseif ($d["kind"] === "ownership"): ?>
  <p class="text-muted mb-2 derivation-method">Scored from the brand's recorded ownership type.</p>
  <?php if ($d["category"] !== null && (float) $d["category"]["points"] === (float) $d["points"]): ?>
    <div class="font-monospace derivation-ownership mb-2">
      Recorded as &ldquo;<?= e($d["type"]) ?>&rdquo;, counted as <?= e($d["category"]["label"]) ?>: <?= e($fmt($d["points"])) ?>
    </div>
  <?php else: ?>
    <?php // the table has changed since this score was written: show what was stored ?>
    <p class="mb-2"><?= e($d["note"] ?? "") ?></p>
  <?php endif ?>
  <table class="table table-sm mb-0 ownership-table">
    <thead><tr><th>Category</th><th>Points</th><th>Meaning</th></tr></thead>
    <tbody>
      <?php foreach (ownership_categories() as $c): ?>
        <?php $here = $d["category"] !== null && $c["key"] === $d["category"]["key"]; ?>
        <tr<?= $here ? ' class="table-active fw-semibold"' : "" ?>>
          <td><?= e($c["label"]) ?><?= $here ? " (this brand)" : "" ?></td>
          <td><?= (int) $c["points"] ?></td>
          <td><?= e($c["meaning"]) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

<?php else: ?>
  <p class="text-muted mb-0">Entered by hand.</p>
  <?php if (!empty($d["note"])): ?>
    <p class="mb-0 derivation-note"><?= e($d["note"]) ?></p>
  <?php endif ?>
<?php endif ?>
