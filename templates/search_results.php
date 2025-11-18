<body>
<form action="search.php" method="get">
<h3>Search Results</h3>
<div>
<table class="table" id="tbl">
     <tr>
		<th>Brand</th>
        <th>Category</th>
        <th>Type</th>
        <th>Owner</th>
        <th>Notes</th>
        <th>Availability</th>
     </tr>

<?php
function getRatingClass($rating) {
  if ($rating <= 3) return 'table-danger';
  if ($rating == 4) return 'table-warning';
  if ($rating >= 5 && $rating <= 6) return 'table-secondary';
  if ($rating >= 7 && $rating <= 8) return 'table-primary';
  if ($rating >= 9) return 'table-success';
  return '';
}
?>

<?php foreach ($results as $result): ?>
   <tr class="<?= getRatingClass($result['rating']) ?>">
    	<td><?= $result["brand"] ?></td>
        <td><?= $result["category"] ?></td>
        <td><?= $result["type"] ?></td>
        <td><?= $result["owner"] ?></td>
        <td><?= $result["notes"] ?></td>
        <td><?= $result["availability"] ?></td>
   </tr>
<?php endforeach ?>
</table>
</div>

<button id="btnSearch" type="submit" name="btnSearch" class="btn btn-outline-primary">Search Again</button>
</form>
</body>


		
      