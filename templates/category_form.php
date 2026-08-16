<h3>Browse by Category</h3>

<div class="row g-2 align-items-end mb-3">
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
    <a class="btn btn-outline-primary" href="search.php">Search all brands</a>
  </div>
</div>

<?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>

<ul class="list-group" id="brandList"></ul>

<script type="application/json" id="brandsData"><?= json_for_html($categories) ?></script>

<script>
(function () {
  const brands = JSON.parse(document.getElementById('brandsData').textContent);
  const categorySelect = document.getElementById('categorySelect');
  const brandList = document.getElementById('brandList');

  function field(label, value) {
    const div = document.createElement('div');
    const strong = document.createElement('strong');
    strong.textContent = label + ': ';
    div.appendChild(strong);
    div.appendChild(document.createTextNode(value === null ? '' : value));
    return div;
  }

  function ratingBadge(b) {
    const span = document.createElement('span');
    span.className = 'badge ' +
      (b.ratingClass ? 'text-bg-' + b.ratingClass : 'text-bg-light border');
    span.textContent = b.ratingLabel
      ? b.ratingScore + ' · ' + b.ratingLabel
      : b.ratingScore;
    return span;
  }

  function brandItem(b) {
    const li = document.createElement('li');
    li.className = 'list-group-item' +
      (b.ratingClass ? ' list-group-item-' + b.ratingClass : '');

    const head = document.createElement('div');
    head.className = 'd-flex justify-content-between align-items-center flex-wrap gap-2 mb-2';

    const link = document.createElement('a');
    link.href = 'brand.php?brand=' + encodeURIComponent(b.brand);
    link.textContent = b.brand;
    link.className = 'fw-bold';

    head.appendChild(link);
    head.appendChild(ratingBadge(b));
    li.appendChild(head);

    li.appendChild(field('Type', b.type));
    li.appendChild(field('Owner', b.owner));
    li.appendChild(field('Availability', b.availability));
    li.appendChild(field('Notes', b.notes));

    return li;
  }

  function displayBrands(category) {
    brandList.replaceChildren();

    const items = brands[category];
    if (!items) {
      const li = document.createElement('li');
      li.className = 'list-group-item';
      li.textContent = 'No brands found for this category.';
      brandList.appendChild(li);
      return;
    }

    for (const b of items) {
      brandList.appendChild(brandItem(b));
    }
  }

  categorySelect.addEventListener('change', (e) => displayBrands(e.target.value));

  // Initialize with the first category on page load
  displayBrands(categorySelect.value);
})();
</script>
