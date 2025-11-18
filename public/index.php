 <?php 
 // configuration
 require("../includes/config.php"); 
 
//echo "Welcome to Ethical Buy"; 
  //render("search_form.php", ["title" => "Search"]);
  render("category_form.php", ["title" => "Categories"]);
?>

<?php
$categories = [
  'Fruits' => ['Apple', 'Banana', 'Orange'],
  'Vegetables' => ['Carrot', 'Broccoli', 'Spinach'],
  'Dairy' => ['Milk Brand A', 'Cheese Brand B']
];
?>
