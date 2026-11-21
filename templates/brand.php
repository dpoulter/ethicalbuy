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

<div class="card mb-4 page-hero">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <h3 class="card-title mb-0"><?= e($brand["brand"]) ?></h3>
      <div class="text-end score-hero">
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
            <span class="pill me-1"><?= e(trim($certification)) ?></span>
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

<?php require(TEMPLATE_DIR . "/partials/score_card.php"); ?>

<?php if (!empty($product_total)): ?>
  <div class="card mb-4" id="products">
    <div class="card-body">
      <h5 class="card-title">Products</h5>
      <p class="text-muted small">
        <?= (int) $product_total ?> product<?= $product_total === 1 ? "" : "s" ?> listed by Open Food Facts.
        Each is scored on its own nutrition, environment and welfare, plus the
        brand's overall view and ownership. Best for your priorities first.
      </p>

      <form class="row g-2 mb-3" method="get" action="brand.php#products">
        <input type="hidden" name="brand" value="<?= e($brand["brand"]) ?>">
        <div class="col-sm-6">
          <label class="visually-hidden" for="pq">Filter products by name</label>
          <input class="form-control form-control-sm" id="pq" name="pq" type="text"
                 placeholder="Filter by name" value="<?= e($product_filter) ?>">
        </div>
        <div class="col-auto">
          <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
          <?php if ($product_filter !== ""): ?>
            <a class="btn btn-sm btn-outline-secondary" href="brand.php?brand=<?= urlencode($brand["brand"]) ?>#products">Clear</a>
          <?php endif ?>
        </div>
      </form>

      <?php if (!$product_rows): ?>
        <p class="mb-0">No products match &ldquo;<?= e($product_filter) ?>&rdquo;.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle table-cards brand-products">
            <thead>
              <tr>
                <th>Product</th>
                <th><?= $chosen ? "Your rating" : "Balanced" ?></th>
                <th>Nutrition</th>
                <th>Environment</th>
                <th>Welfare</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($product_rows as $prod): ?>
                <tr>
                  <td>
                    <a href="product.php?code=<?= urlencode($prod["code"]) ?>">
                      <?= e($prod["name"] !== null && $prod["name"] !== "" ? $prod["name"] : "Product " . $prod["code"]) ?>
                    </a>
                  </td>
                  <td>
                    <?php $personal_saved = $personal; $personal = $prod["personal"]; $personal_compact = true;
                          require(TEMPLATE_DIR . "/partials/personal_score.php"); $personal = $personal_saved; ?>
                  </td>
                  <?php foreach (["nutrition", "environment", "welfare"] as $dim): ?>
                    <td><?php $dim_score = $prod["scores"][$dim] ?? null; require(TEMPLATE_DIR . "/partials/dimension_badge.php"); ?></td>
                  <?php endforeach ?>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <?php
          $pager_page = $product_page;
          $pager_pages = $product_pages;
          $pager_url = function ($n) use ($brand, $product_filter) {
              return "brand.php?" . http_build_query(array_filter([
                  "brand" => $brand["brand"], "pq" => $product_filter, "pp" => $n,
              ], function ($v) { return $v !== ""; })) . "#products";
          };
          require(TEMPLATE_DIR . "/partials/pager.php");
        ?>
        <p class="small text-muted mb-0">Product data from Open Food Facts (ODbL).</p>
      <?php endif ?>
    </div>
  </div>
<?php endif ?>

<?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>

<?php if (!empty($alternatives)): ?>
  <h4>Other brands in <?= e($brand["category"] ?? "this category") ?></h4>
  <p class="text-muted">Best rated first.</p>

  <ul class="list-group mb-4 brand-list">
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
