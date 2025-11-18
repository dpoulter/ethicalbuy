
<?php
$categories = [
  'Fruits' => ['Apple', 'Banana', 'Orange'],
  'Vegetables' => ['Carrot', 'Broccoli', 'Spinach'],
  'Dairy' => ['Milk Brand A', 'Cheese Brand B']
];


$result = get_categories();   

if ($result === false) {
    // Query error occurred
    apologize('Database query error: ' );
} elseif (!$result) {
    // No rows returned
    apologize('No brand categories found.');
}
else
{
    $categories = [];
 
    foreach ($result as $row ) {
    $categories[$row['category']][] = [
        'brand' => $row['brand'],
        'type' => $row['type'],
        'notes' => $row['notes'],
        'owner' => $row['owner'],
        'availability' => $row['availability'],
        'rating' => $row['rating']
    ];
    }
}    
?>

<!DOCTYPE html>
<html lang="en">

 <body>
<div class="container mt-4">
  <div class="row">

  <!--
    <div class="col-4">
      <ul class="list-group" id="categoryList">
      //  <?php
      //  $first = true;
      //  foreach ($categories as $cat => $brands) {
      //    $active = $first ? 'active' : '';
      //    echo "<li class='list-group-item $active' data-category='$cat'>$cat</li>";
       //   $first = false;
       // }
        ?>
      </ul>
    </div>
    <div class="col-8">
      <ul class="list-group" id="brandList"></ul>
    </div>
  </div>
</div>

      -->

<div class="col-12">
  <div class="mb-3">
    <label for="categorySelect" class="form-label">Select Category:</label>
    <select class="form-select" id="categorySelect">
      <?php
        $first = true;
        foreach ($categories as $cat => $brands) {
          $active = $first ? 'active' : '';
          echo "<option value='$cat'>$cat</option>";
          $first = false;
        }
      ?>
    </select>
  </div>
  <ul class="list-group" id="brandList"></ul>
</div>

<!--
<script>
  const brands = <?php echo json_encode($categories); ?>;



document.querySelectorAll('#categoryList .list-group-item').forEach(item => {
    item.addEventListener('click', () => {
      document.querySelectorAll('#categoryList .list-group-item').forEach(i => i.classList.remove('active'));
      item.classList.add('active');

      let category = item.getAttribute('data-category');
      const brandList = document.getElementById('brandList');
      brandList.innerHTML = brands[category].map(b => `
    <li class="list-group-item${
    b.rating === 1 ? ' list-group-item-danger' : 
    b.rating === 2 ? ' list-group-item-danger' : 
    b.rating === 3 ? ' list-group-item-danger' : 
    b.rating === 5 ? ' list-group-item-secondary' : 
    b.rating === 6 ? ' list-group-item-secondary' : 
    b.rating === 10 ? ' list-group-item-success' : 
    b.rating === 9 ? ' list-group-item-success' : 
    b.rating === 7 ? ' list-group-item-primary' :
    b.rating === 8 ? ' list-group-item-primary' :
    b.rating === 4 ? ' list-group-item-warning' : ''
    }">
    <strong>Brand:</strong> ${b.brand} <br>
    <strong>Type:</strong> ${b.type} <br>
    <strong>Notes:</strong> ${b.notes} <br>
    <strong>Owner:</strong> ${b.owner} <br>
    <strong>Availability:</strong> ${b.availability}
    </li>
`).join('');    });
  });

  // Initialize with first category brands on page load
  document.querySelector('#categoryList .list-group-item.active').click();
-->
  <script>
const brands = <?php echo json_encode($categories); ?>;
const categorySelect = document.getElementById('categorySelect');
const brandList = document.getElementById('brandList');

function displayBrands(category) {
  brandList.innerHTML = brands[category].map(b => `
    <li class="list-group-item${
      b.rating === 1 ? ' list-group-item-danger' : 
      b.rating === 2 ? ' list-group-item-danger' : 
      b.rating === 3 ? ' list-group-item-danger' : 
      b.rating === 5 ? ' list-group-item-secondary' : 
      b.rating === 6 ? ' list-group-item-secondary' : 
      b.rating === 10 ? ' list-group-item-success' : 
      b.rating === 9 ? ' list-group-item-success' : 
      b.rating === 7 ? ' list-group-item-primary' :
      b.rating === 8 ? ' list-group-item-primary' :
      b.rating === 4 ? ' list-group-item-warning' : ''
    }">
      <strong>Brand:</strong> ${b.brand} <br>
      <strong>Type:</strong> ${b.type} <br>
      <strong>Notes:</strong> ${b.notes} <br>
      <strong>Owner:</strong> ${b.owner} <br>
      <strong>Availability:</strong> ${b.availability}
    </li>
  `).join('');
}

// Listen for dropdown changes
categorySelect.addEventListener('change', (e) => {
  displayBrands(e.target.value);
});

// Initialize with first category on page load
displayBrands(categorySelect.value);

</script>


</body>
</html>

