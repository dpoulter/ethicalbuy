<section class="hero">
<h3>Browse by Category</h3>

<div class="row g-2 align-items-end">
  <div class="col-md-6">
    <label for="categorySelect" class="form-label">Select Category:</label>
    <select class="form-select" id="categorySelect">
      <?php foreach ($categories as $cat => $brands): ?>
        <option value="<?= e($cat) ?>">
          <?= e($cat) ?> (<?= count($brands) ?>)
        </option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="col-md-6">
    <a class="btn btn-outline-primary" href="/search.php">Search brands and products</a>
    <a class="btn btn-outline-secondary" href="/priorities.php">
      <?= $chosen ? "Your priorities" : "Set your priorities" ?>
    </a>
  </div>
</div>

</section>

<?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>

<ul class="list-group brand-list" id="brandList"></ul>

<script type="application/json" id="brandsData"><?= json_for_html($categories) ?></script>

<script src="<?= e(asset_url("/assets/categories.js")) ?>"></script>
