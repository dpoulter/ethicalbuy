<?php
/**
 * The "Why this score" card: per-dimension table, collapsed derivation for
 * each scored dimension, and the collapsed overall working.
 *
 * Expects $breakdown and $personal (includes/scoring.php), $chosen, and
 * $derivations (dimension => derivation, see brand_derivations() and
 * product_derivations()). Optional $subject_label for the score column and
 * $row_notes (dimension => short note under the label).
 */
?>
<div class="card mb-4">
  <div class="card-body">
    <h5 class="card-title">Why this score</h5>
    <p class="text-muted small">
      <?php if ($chosen): ?>
        Weighted by the priorities you set.
        <a href="/priorities.php">Change them</a>.
      <?php else: ?>
        Everything weighted equally, because you haven't set priorities yet.
        <a href="/priorities.php">Tell us what matters to you</a> and this
        recalculates.
      <?php endif ?>
    </p>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Dimension</th>
            <th>Your weighting</th>
            <th><?= e($subject_label ?? "This brand") ?></th>
            <th>Source</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($breakdown as $row): ?>
            <tr<?= $row["counted"] ? "" : ' class="text-muted"' ?>>
              <?php $d = $row["score"] !== null ? ($derivations[$row["key"]] ?? ["kind" => "hand"]) : null; ?>
              <td>
                <?php if ($d === null): ?>
                  <?= e($row["label"]) ?>
                <?php else: ?>
                  <details class="dimension-derivation">
                    <summary><?= e($row["label"]) ?></summary>
                    <div class="small mt-2">
                      <?php require(TEMPLATE_DIR . "/partials/derivation.php"); ?>
                    </div>
                  </details>
                <?php endif ?>
                <?php if (!empty($row_notes[$row["key"]])): ?>
                  <div class="small text-muted"><?= e($row_notes[$row["key"]]) ?></div>
                <?php endif ?>
              </td>
              <td>
                <?= e(weight_label($row["weight"])) ?>
                <?php if ($row["weight"] === 0): ?>
                  <span class="small">(excluded)</span>
                <?php endif ?>
              </td>
              <td>
                <?php if ($row["score"] === null): ?>
                  <span class="score score--none score--sm"><span class="score__value score__value--text">Not assessed</span></span>
                <?php else: ?>
                  <?php $rating = $row["score"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?>
                  <span class="score-bar score-bar--<?= e(rating_class($row["score"])) ?>" aria-hidden="true"><svg viewBox="0 0 100 1" preserveAspectRatio="none" focusable="false"><rect width="<?= max(0, min(100, (int) round($row["score"] * 10))) ?>" height="1"/></svg></span>
                <?php endif ?>
              </td>
              <td class="small"><?= e($row["sourced"]) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <details class="mt-4 score-working">
    <summary class="h6">How this number is worked out</summary>
    <?php if ($personal["score"] === null): ?>
      <p class="small text-muted mb-0">
        None of the dimensions you weighted has data here, so there
        is no score to calculate.
      </p>
    <?php else: ?>
      <?php if (!$chosen): ?>
        <p class="small text-muted mb-2">
          You haven't set priorities, so every dimension is weighted
          <?= (int) WEIGHT_DEFAULT ?> and this is a plain average.
        </p>
      <?php endif ?>
      <div class="small font-monospace score-calc">
        <?php foreach ($personal["terms"] as $t): ?>
          <div><?= e($t["label"]) ?> <?= e(number_format($t["score"], 1)) ?> &times; <?= (int) $t["weight"] ?> = <?= e(number_format($t["product"], 1)) ?></div>
        <?php endforeach ?>
        <div class="mt-1 score-calc-total">
          <?php
            $sumTerms = implode(" + ", array_map(function ($t) { return number_format($t["product"], 1); }, $personal["terms"]));
            $sumWeights = implode(" + ", array_map(function ($t) { return (string) $t["weight"]; }, $personal["terms"]));
          ?>
          (<?= e($sumTerms) ?>) / (<?= e($sumWeights) ?>) = <?= e(number_format($personal["numerator"], 1)) ?> / <?= (int) $personal["weight_used"] ?> = <strong class="score-calc-result"><?= e(rating_score($personal["score"])) ?></strong>
        </div>
      </div>
    <?php endif ?>
    </details>

    <?php
      $excluded = array_values(array_filter($breakdown, function ($r) { return !$r["counted"]; }));
    ?>
    <?php if ($excluded): ?>
      <p class="small text-muted mt-2 mb-1">Not counted:</p>
      <ul class="small text-muted mb-0 score-excluded">
        <?php foreach ($excluded as $r): ?>
          <li>
            <?= e($r["label"]) ?>:
            <?php if ($r["weight"] === 0): ?>
              weighted 0 (ignored)
            <?php else: ?>
              no data (excluded from the average, not counted as zero)
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>

    <?php if ($personal["missing"]): ?>
      <p class="small <?= $personal["confident"] ? "text-muted" : "text-danger" ?> mt-3 mb-0">
        <?php if (!$personal["confident"]): ?>
          <strong>Treat this score with caution.</strong>
          We could only assess <?= (int) round($personal["coverage"] * 100) ?>%
          of what you said matters. Missing data is left out of the average
          rather than guessed, so the number above reflects less than you asked
          for.
        <?php else: ?>
          We don't hold data for every dimension you care about. Missing ones
          are excluded from the average rather than counted as zero.
        <?php endif ?>
      </p>
    <?php endif ?>
  </div>
</div>

