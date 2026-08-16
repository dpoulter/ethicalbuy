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
      <?php $rating = $brand["rating"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?>
    </div>

    <dl class="row mt-3 mb-0">
      <dt class="col-sm-3">Category</dt>
      <dd class="col-sm-9"><?= e($brand["category"] ?? "Uncategorised") ?></dd>

      <dt class="col-sm-3">Type</dt>
      <dd class="col-sm-9"><?= e($brand["type"]) ?></dd>

      <dt class="col-sm-3">Owner</dt>
      <dd class="col-sm-9"><?= e($brand["owner"]) ?></dd>

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
  </div>
</div>

<?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>

<?php if (!empty($alternatives)): ?>
  <h4>Other brands in <?= e($brand["category"] ?? "this category") ?></h4>
  <p class="text-muted">Best rated first.</p>

  <ul class="list-group mb-4">
    <?php foreach ($alternatives as $alt): ?>
      <?php $alt_class = rating_class($alt["rating"]); ?>
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
          <?php $rating = $alt["rating"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?>
        </div>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>
