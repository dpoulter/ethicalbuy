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
              <td><?= e($row["label"]) ?></td>
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
