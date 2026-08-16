<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h3 class="mb-0">Admin &middot; Brands</h3>
  <div>
    <a class="btn btn-outline-secondary me-2" href="/admin/owners.php">Owners</a>
    <a class="btn btn-primary" href="/admin/edit.php">Add brand</a>
  </div>
</div>

<?php if ($flash !== ""): ?>
  <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif ?>

<form action="/admin/index.php" method="get" class="mb-3">
  <div class="input-group col-md-6" style="max-width: 32rem;">
    <input class="form-control" type="text" name="q" placeholder="Filter by brand name"
           value="<?= e($search) ?>">
    <button class="btn btn-outline-primary" type="submit">Filter</button>
    <a class="btn btn-outline-secondary" href="/admin/index.php">Clear</a>
  </div>
</form>

<p class="text-muted">
  <?= count($brands) ?> brand<?= count($brands) === 1 ? "" : "s" ?>
  <?= $search === "" ? "" : "matching &ldquo;" . e($search) . "&rdquo;" ?>
</p>

<?php if (empty($brands)): ?>
  <div class="alert alert-info">
    No brands<?= $search === "" ? " yet" : " matched that filter" ?>.
    <a href="/admin/edit.php">Add one</a>.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Rating</th>
          <th>Brand</th>
          <th>Category</th>
          <th>Type</th>
          <th>Owner</th>
          <th>Availability</th>
          <th>Updated</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($brands as $row): ?>
          <?php $class = rating_class($row["rating"]); ?>
          <tr<?= $class === "" ? "" : ' class="table-' . $class . '"' ?>>
            <td><?php $rating = $row["rating"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?></td>
            <td>
              <a href="/brand.php?brand=<?= urlencode($row["name"]) ?>" target="_blank"
                 title="View on the public site"><?= e($row["name"]) ?></a>
            </td>
            <td><?= e($row["category"] ?? "Uncategorised") ?></td>
            <td><?= e($row["type"]) ?></td>
            <td><?= e($row["owner"]) ?></td>
            <td><?= e($row["availability"]) ?></td>
            <td class="text-muted"><?= e(substr((string) $row["updated_at"], 0, 10)) ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary"
                 href="/admin/edit.php?id=<?= (int) $row["id"] ?>">Edit</a>
              <a class="btn btn-sm btn-outline-danger"
                 href="/admin/delete.php?id=<?= (int) $row["id"] ?>">Delete</a>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
