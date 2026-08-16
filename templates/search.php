<h3>Product Search</h3>

<form action="search.php" method="get" class="mb-4">
  <input type="hidden" name="submitted" value="1">

  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label for="search_string" class="form-label">Search for</label>
      <input class="form-control" id="search_string" name="search_string" type="text"
             placeholder="Brand or keyword" value="<?= e($filters["term"]) ?>" autofocus>
    </div>

    <div class="col-md-2">
      <label for="field" class="form-label">Search in</label>
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

<?php if ($submitted): ?>

  <?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>

  <p class="text-muted">
    <?= count($results) ?> result<?= count($results) === 1 ? "" : "s" ?>
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
      <table class="table align-middle" id="tbl">
        <thead>
          <tr>
            <th>Rating</th>
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
            <?php $class = rating_class($result["rating"]); ?>
            <tr<?= $class === "" ? "" : ' class="table-' . $class . '"' ?>>
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

<?php endif ?>
