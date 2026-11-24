<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= isset($title) ? e($title) . " | Ethical Buy" : "Ethical Buy" ?></title>
  <link href="<?= e(asset_url("/vendor/bootstrap/bootstrap.min.css")) ?>" rel="stylesheet">
  <link href="<?= e(asset_url("/assets/site.css")) ?>" rel="stylesheet">
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
        <circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.8"/>
        <path d="M7.8 12.3l2.8 2.8 5.6-5.9" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
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
