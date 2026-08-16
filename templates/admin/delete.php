<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/admin/index.php">Admin</a></li>
    <li class="breadcrumb-item active" aria-current="page">Delete</li>
  </ol>
</nav>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $error): ?>
      <div><?= e($error) ?></div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<div class="card border-danger">
  <div class="card-body">
    <h4 class="card-title">Delete &ldquo;<?= e($brand["name"]) ?>&rdquo;?</h4>

    <p class="card-text">
      This removes the brand from the public site immediately. It cannot be undone.
    </p>

    <dl class="row">
      <dt class="col-sm-3">Rating</dt>
      <dd class="col-sm-9">
        <?php $rating = $brand["rating"]; require(TEMPLATE_DIR . "/partials/rating_badge.php"); ?>
      </dd>

      <dt class="col-sm-3">Type</dt>
      <dd class="col-sm-9"><?= e($brand["type"]) ?></dd>

      <dt class="col-sm-3">Notes</dt>
      <dd class="col-sm-9"><?= nl2br(e($brand["notes"])) ?></dd>
    </dl>

    <form action="/admin/delete.php" method="post" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $brand["id"] ?>">
      <button type="submit" class="btn btn-danger">Yes, delete it</button>
      <a class="btn btn-outline-secondary" href="/admin/index.php">Cancel</a>
    </form>
  </div>
</div>
