<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php">Categories</a></li>
    <?php if ($brand["category"] !== null && $brand["category"] !== ""): ?>
      <li class="breadcrumb-item">
        <a href="search.php?submitted=1&amp;field=category&amp;search_string=<?= urlencode($brand["category"]) ?>">
          <?= e($brand["category"]) ?>
        </a>
      </li>
    <?php endif ?>
    <li class="breadcrumb-item active" aria-current="page"><?= e($brand["brand"]) ?></li>
  </ol>
</nav>

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <h3 class="card-title mb-0"><?= e($brand["brand"]) ?></h3>
      <div class="text-end">
        <div class="small text-muted mb-1">
          <?= $chosen ? "Your rating" : "Balanced rating" ?>
        </div>
        <?php require(TEMPLATE_DIR . "/partials/personal_score.php"); ?>
      </div>
    </div>

    <dl class="row mt-3 mb-0">
      <dt class="col-sm-3">Category</dt>
      <dd class="col-sm-9"><?= e($brand["category"] ?? "Uncategorised") ?></dd>

      <dt class="col-sm-3">Type</dt>
      <dd class="col-sm-9"><?= e($brand["type"]) ?></dd>

      <dt class="col-sm-3">Owner</dt>
      <dd class="col-sm-9">
        <?= e($brand["owner"]) ?>
        <?php if (!empty($brand["owner_company_number"])): ?>
          <div class="small text-muted">
            Registered as <?= e($brand["owner_company_name"]) ?>
            <?php if (!empty($brand["owner_source_url"])): ?>
              (<a href="<?= e($brand["owner_source_url"]) ?>" rel="noopener nofollow"
                  target="_blank">company <?= e($brand["owner_company_number"]) ?></a>)
            <?php else: ?>
              (company <?= e($brand["owner_company_number"]) ?>)
            <?php endif ?>
          </div>
        <?php endif ?>
      </dd>

      <?php if (!empty($brand["owner_parent_name"])): ?>
        <dt class="col-sm-3">Ultimately controlled by</dt>
        <dd class="col-sm-9">
          <?= e($brand["owner_parent_name"]) ?>
          <div class="small text-muted">
            From the Companies House register of persons with significant control.
          </div>
        </dd>
      <?php endif ?>

      <dt class="col-sm-3">Availability</dt>
      <dd class="col-sm-9"><?= e($brand["availability"]) ?></dd>

      <?php if (!empty($brand["certifications"])): ?>
        <dt class="col-sm-3">Certifications</dt>
        <dd class="col-sm-9">
          <?php foreach (explode(",", $brand["certifications"]) as $certification): ?>
            <span class="badge text-bg-light border me-1"><?= e(trim($certification)) ?></span>
          <?php endforeach ?>
        </dd>
      <?php endif ?>

      <dt class="col-sm-3">Notes</dt>
      <dd class="col-sm-9"><?= nl2br(e($brand["notes"])) ?></dd>
    </dl>

    <?php if (!empty($brand["source_url"])): ?>
      <p class="small text-muted mb-0 mt-3">
        Product facts from
        <a href="<?= e($brand["source_url"]) ?>" rel="noopener nofollow" target="_blank">
          <?= e($brand["source"] === "openfoodfacts" ? "Open Food Facts" : $brand["source"]) ?></a><?php
        if (!empty($brand["source_licence"])): ?>, licensed <?= e($brand["source_licence"]) ?><?php
        endif ?><?php if (!empty($brand["retrieved_at"])): ?>,
        retrieved <?= e(substr((string) $brand["retrieved_at"], 0, 10)) ?><?php endif ?>.
        The rating and notes are our own.
      </p>
    <?php endif ?>

    <?php if (!empty($brand["owner_company_number"])): ?>
      <p class="small text-muted mb-0 mt-2">
        Company information from Companies House. Contains public sector
        information licensed under the Open Government Licence v3.0.
      </p>
    <?php endif ?>
  </div>
</div>

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
            <th>This brand</th>
            <th>Source</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($breakdown as $row): ?>
            <tr<?= $row["counted"] ? "" : ' class="text-muted"' ?>>
              <?php
                $stored = $row["score"] !== null ? ($scores[$row["key"]] ?? null) : null;
                $derived = $stored !== null && ($stored["source"] ?? "") === OFF_SOURCE;
                // inputs only explain a derived score; a hand edit in /admin supersedes them
                $rowInputs = $derived ? (($inputs ?? [])[$row["key"]] ?? []) : [];
              ?>
              <td>
                <?php if ($row["score"] === null): ?>
                  <?= e($row["label"]) ?>
                <?php else: ?>
                  <details class="dimension-derivation">
                    <summary><?= e($row["label"]) ?></summary>
                    <div class="small mt-2">
                      <?php if ($rowInputs): ?>
                        <?php
                          $method = off_dimension_method($row["key"]);
                          $shown = array_slice($rowInputs, 0, DERIVATION_ROWS_SHOWN);
                          $more = count($rowInputs) - count($shown);
                          $fmt = function ($n) { return rtrim(rtrim(number_format((float) $n, 1), "0"), "."); };
                        ?>
                        <?php if ($method !== null): ?>
                          <p class="text-muted mb-2 derivation-method"><?= e($method) ?></p>
                        <?php endif ?>
                        <table class="table table-sm mb-1 derivation-products">
                          <thead>
                            <tr><th>Product</th><th><?= $row["key"] === "welfare" ? "Labels" : "Grade" ?></th><th>Points</th></tr>
                          </thead>
                          <tbody>
                            <?php foreach ($shown as $in): ?>
                              <?php
                                $inLabel = trim((string) ($in["label"] ?? "")) !== "" ? $in["label"] : ("Product " . ($in["ref"] ?? ""));
                                $inUrl = trim((string) ($in["url"] ?? ""));
                              ?>
                              <tr>
                                <td>
                                  <?php if (preg_match('~^https?://~i', $inUrl)): ?>
                                    <a href="<?= e($inUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($inLabel) ?></a>
                                  <?php else: ?>
                                    <?= e($inLabel) ?>
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
                          (<?= e(implode(" + ", array_map($fmt, array_column($rowInputs, "points")))) ?>) / <?= count($rowInputs) ?> = <?= e(number_format($row["score"], 1)) ?>
                        </div>
                        <p class="text-muted mt-1 mb-0">Product data from Open Food Facts (ODbL).</p>
                      <?php elseif ($derived): ?>
                        <p class="text-muted mb-1">Product detail not recorded yet.</p>
                        <?php $srcUrl = trim((string) ($stored["source_url"] ?? "")); ?>
                        <?php if (preg_match('~^https?://~i', $srcUrl)): ?>
                          <p class="mb-0">Source: <a href="<?= e($srcUrl) ?>" target="_blank" rel="noopener noreferrer">Open Food Facts</a></p>
                        <?php endif ?>
                      <?php else: ?>
                        <p class="text-muted mb-0">Entered by hand.</p>
                      <?php endif ?>
                    </div>
                  </details>
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
                  <span class="badge text-bg-light border">Not assessed</span>
                <?php else: ?>
                  <?php $rating = $row["score"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?>
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
        None of the dimensions you weighted has data for this brand, so there
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

<?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>

<?php if (!empty($alternatives)): ?>
  <h4>Other brands in <?= e($brand["category"] ?? "this category") ?></h4>
  <p class="text-muted">Best rated first.</p>

  <ul class="list-group mb-4">
    <?php foreach ($alternatives as $alt): ?>
      <?php $alt_class = rating_class($alt["personal"]["score"]); ?>
      <li class="list-group-item<?= $alt_class === "" ? "" : " list-group-item-" . $alt_class ?>">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <a href="brand.php?brand=<?= urlencode($alt["brand"]) ?>">
              <strong><?= e($alt["brand"]) ?></strong>
            </a>
            <?php if ($alt["type"] !== null && $alt["type"] !== ""): ?>
              <span class="text-muted">&middot; <?= e($alt["type"]) ?></span>
            <?php endif ?>
          </div>
          <?php
            $personal = $alt["personal"];
            $personal_compact = true;
            require(TEMPLATE_DIR . "/partials/personal_score.php");
          ?>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>
