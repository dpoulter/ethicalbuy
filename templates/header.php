<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= isset($title) ? e($title) . " | Ethical Buy" : "Ethical Buy" ?></title>
  <link href="/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/site.css" rel="stylesheet">
</head>
<body>
<?php
  // which menu item is the current page, for the active state only
  $current_page = basename((string) ($_SERVER["SCRIPT_NAME"] ?? ""));
  $nav_items = [
      "index.php"      => "Home",
      "search.php"     => "Search",
      "priorities.php" => has_chosen_priorities() ? "Your Priorities &#10003;" : "Your Priorities",
      "about.php"      => "About",
      "contact.php"    => "Contact",
  ];
?>

<a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>

<nav class="navbar navbar-expand-lg navbar-dark site-nav">
  <div class="container">
    <a class="navbar-brand site-wordmark" href="/index.php">
      <svg class="site-wordmark__mark" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
        <path d="M20 4c-7.5 0-13 3.6-13 10.2 0 1.6.4 3 1.1 4.1C9.6 13.9 12.8 10.8 17 9c-3.6 2.4-6.2 5.6-7.6 10.4 1 .4 2 .6 3.2.6C18.4 20 20 13.4 20 4z" fill="currentColor"/>
        <path d="M4.5 20.5c1.4-2.6 3-4.6 4.7-6.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" fill="none"/>
      </svg>
      <span>Ethical Buy</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu"
      aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarMenu">
      <div class="navbar-nav ms-auto">
        <?php foreach ($nav_items as $href => $label): ?>
          <a class="nav-link<?= $current_page === $href ? " active" : "" ?>" href="/<?= $href ?>"<?= $current_page === $href ? ' aria-current="page"' : "" ?>><?= $label ?></a>
        <?php endforeach ?>
      </div>
    </div>
  </div>
</nav>

<main class="container site-main" id="main">
