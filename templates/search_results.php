<h3>Search Results</h3>

<p class="text-muted">
  <?= count($results) ?> result<?= count($results) === 1 ? "" : "s" ?>
  for &ldquo;<?= e($search_string) ?>&rdquo;
</p>

<?php if (empty($results)): ?>
  <p>No brands matched that search.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table" id="tbl">
      <thead>
        <tr>
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
            <td><?= e($result["brand"]) ?></td>
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

<form action="search.php" method="get">
  <div class="input-group col-md-6">
    <input class="form-control" name="search_string" type="text"
           placeholder="Search String" value="<?= e($search_string) ?>">
    <button id="btnSearch" type="submit" class="btn btn-outline-primary">Search Again</button>
  </div>
</form>
