<h3>Browse by Category</h3>

<div class="mb-3">
  <label for="categorySelect" class="form-label">Select Category:</label>
  <select class="form-select" id="categorySelect">
    <?php foreach ($categories as $cat => $brands): ?>
      <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
    <?php endforeach ?>
  </select>
</div>

<ul class="list-group" id="brandList"></ul>

<script type="application/json" id="brandsData"><?= json_for_html($categories) ?></script>

<script>
(function () {
  const brands = JSON.parse(document.getElementById('brandsData').textContent);
  const categorySelect = document.getElementById('categorySelect');
  const brandList = document.getElementById('brandList');

  function addField(li, label, value) {
    const strong = document.createElement('strong');
    strong.textContent = label + ':';
    li.appendChild(strong);
    li.appendChild(document.createTextNode(' ' + (value === null ? '' : value)));
    li.appendChild(document.createElement('br'));
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
      const li = document.createElement('li');
      li.className = 'list-group-item' +
        (b.ratingClass ? ' list-group-item-' + b.ratingClass : '');

      addField(li, 'Brand', b.brand);
      addField(li, 'Type', b.type);
      addField(li, 'Notes', b.notes);
      addField(li, 'Owner', b.owner);
      addField(li, 'Availability', b.availability);

      brandList.appendChild(li);
    }
  }

  categorySelect.addEventListener('change', (e) => displayBrands(e.target.value));

  // Initialize with the first category on page load
  displayBrands(categorySelect.value);
})();
</script>
