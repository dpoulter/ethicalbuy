<section class="hero">
<h3>Search</h3>
<p class="text-muted">Search brands and products by name. The filters below apply to brands.</p>

<form action="search.php" method="get" class="search-form">
  <input type="hidden" name="submitted" value="1">

  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label for="q" class="form-label">Search for</label>
      <input class="form-control" id="q" name="q" type="search"
             placeholder="Brand or product" value="<?= e($filters["term"]) ?>" autofocus>
    </div>

    <div class="col-md-2">
      <label for="field" class="form-label">Match brands on</label>
      <select class="form-select" id="field" name="field">
        <?php foreach (SEARCH_FIELDS as $f): ?>
          <option value="<?= e($f) ?>"<?= $filters["field"] === $f ? " selected" : "" ?>>
            <?= e(ucfirst($f)) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-2">
      <label for="min_rating" class="form-label">Minimum rating</label>
      <select class="form-select" id="min_rating" name="min_rating">
        <option value="">Any</option>
        <?php foreach ([9, 7, 5, 4] as $min): ?>
          <option value="<?= $min ?>"<?= $filters["min_rating"] === (string) $min ? " selected" : "" ?>>
            <?= $min ?>+ (<?= e(rating_label($min)) ?>)
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-2">
      <label for="availability" class="form-label">Availability</label>
      <select class="form-select" id="availability" name="availability">
        <option value="">Any</option>
        <?php foreach ($availability_options as $option): ?>
          <option value="<?= e($option) ?>"<?= $filters["availability"] === $option ? " selected" : "" ?>>
            <?= e($option) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-2">
      <label for="sort" class="form-label">Sort by</label>
      <select class="form-select" id="sort" name="sort">
        <?php
          $sort_labels = [
              "relevance"   => "Best match",
              "personal"    => "Best for your priorities",
              "brand"       => "Brand (A-Z)",
              "category"    => "Category",
              "rating_desc" => "Best rated first",
              "rating_asc"  => "Worst rated first",
          ];
        ?>
        <?php foreach ($sort_labels as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= $filters["sort"] === $key ? " selected" : "" ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <div class="mt-3">
    <button type="submit" class="btn btn-primary">Search</button>
    <a href="search.php" class="btn btn-outline-secondary">Reset</a>
  </div>
</form>
</section>

<?php if ($submitted): ?>

  <?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>

  <h4 class="mt-4 group-heading">Brands</h4>
  <p class="text-muted">
    <?= count($results) ?> brand<?= count($results) === 1 ? "" : "s" ?>
    <?php if ($filters["term"] !== ""): ?>
      for &ldquo;<?= e($filters["term"]) ?>&rdquo; in <?= e($filters["field"]) ?>
    <?php endif ?>
    <?php if ($filters["min_rating"] !== ""): ?>
      rated <?= e($filters["min_rating"]) ?> or better
    <?php endif ?>
    <?php if ($filters["availability"] !== ""): ?>
      available at <?= e($filters["availability"]) ?>
    <?php endif ?>
  </p>

  <?php if (empty($results)): ?>
    <div class="alert alert-info">
      No brands matched. Try a broader search, or
      <a href="search.php">reset the filters</a>.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle table-cards" id="tbl">
        <thead>
          <tr>
            <th><?= $chosen ? "Your rating" : "Balanced" ?></th>
            <th>Our view</th>
            <th>Brand</th>
            <th>Category</th>
            <th>Type</th>
            <th>Owner</th>
            <th>Notes</th>
            <th>Availability</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $result): ?>
            <?php $class = rating_class($result["personal"]["score"]); ?>
            <tr<?= $class === "" ? "" : ' class="table-' . $class . '"' ?>>
              <td>
                <?php
                  $personal = $result["personal"];
                  $personal_compact = true;
                  require(TEMPLATE_DIR . "/partials/personal_score.php");
                ?>
              </td>
              <td><?php $rating = $result["rating"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?></td>
              <td>
                <a href="brand.php?brand=<?= urlencode($result["brand"]) ?>">
                  <?= e($result["brand"]) ?>
                </a>
              </td>
              <td><?= e($result["category"]) ?></td>
              <td><?= e($result["type"]) ?></td>
              <td><?= e($result["owner"]) ?></td>
              <td><?= e($result["notes"]) ?></td>
              <td><?= e($result["availability"]) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

  <?php
    $products = $products ?? [];
    $product_total = $product_total ?? 0;
    $product_page = $product_page ?? 1;
    $product_pages = $product_pages ?? 1;
  ?>
  <h4 class="mt-4 group-heading" id="products">Products</h4>
  <?php if ($filters["term"] === ""): ?>
    <p class="text-muted">Enter a name to search products.</p>
  <?php elseif (!$products): ?>
    <div class="alert alert-info">No products matched &ldquo;<?= e($filters["term"]) ?>&rdquo;.</div>
  <?php else: ?>
    <p class="text-muted">
      <?= (int) $product_total ?> product<?= $product_total === 1 ? "" : "s" ?> matched.
      <?php if ($product_total > PRODUCT_SEARCH_CANDIDATES): ?>
        Showing the <?= (int) PRODUCT_SEARCH_CANDIDATES ?> closest name matches; search more precisely to narrow it.
      <?php endif ?>
    </p>
    <div class="table-responsive">
      <table class="table align-middle table-cards search-products">
        <thead>
          <tr>
            <th><?= $chosen ? "Your rating" : "Balanced" ?></th>
            <th>Product</th>
            <th>Brand</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $prod): ?>
            <tr>
              <td>
                <?php
                  $personal = $prod["personal"];
                  $personal_compact = true;
                  require(TEMPLATE_DIR . "/partials/personal_score.php");
                ?>
              </td>
              <td>
                <a href="product.php?code=<?= urlencode($prod["code"]) ?>">
                  <?= e($prod["name"] !== null && $prod["name"] !== "" ? $prod["name"] : "Product " . $prod["code"]) ?>
                </a>
              </td>
              <td><a href="brand.php?brand=<?= urlencode($prod["brand"]) ?>"><?= e($prod["brand"]) ?></a></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php
      $pager_page = $product_page;
      $pager_pages = $product_pages;
      $pager_url = function ($n) use ($filters) {
          return "search.php?" . http_build_query(array_filter([
              "q" => $filters["term"], "field" => $filters["field"], "min_rating" => $filters["min_rating"],
              "availability" => $filters["availability"], "sort" => $filters["sort"], "page" => $n,
          ], function ($v) { return $v !== ""; })) . "#products";
      };
      require(TEMPLATE_DIR . "/partials/pager.php");
    ?>
    <p class="small text-muted">Product data from Open Food Facts (ODbL).</p>
  <?php endif ?>

<?php endif ?>
