<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= isset($title) ? e($title) . " | Ethical Buy" : "Ethical Buy" ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Ethical Buy</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu"
      aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarMenu">
      <div class="navbar-nav ms-auto">
        <a class="nav-link btn btn-outline-primary me-2" href="index.php">Home</a>
        <a class="nav-link btn btn-outline-primary me-2" href="search.php">Product Search</a>
        <a class="nav-link btn btn-outline-primary me-2" href="about.php">About</a>
        <a class="nav-link btn btn-outline-primary" href="contact.php">Contact</a>
      </div>
    </div>
  </div>
</nav>

<main class="container my-4">
