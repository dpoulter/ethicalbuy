<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/admin/index.php">Admin</a></li>
    <li class="breadcrumb-item active" aria-current="page">
      <?= $id === null ? "New brand" : "Edit " . e($values["name"]) ?>
    </li>
  </ol>
</nav>

<h3><?= $id === null ? "Add a brand" : "Edit brand" ?></h3>

<?php $general_errors = array_filter($errors, "is_int", ARRAY_FILTER_USE_KEY); ?>
<?php if ($general_errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($general_errors as $error): ?>
      <div><?= e($error) ?></div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<form action="/admin/edit.php<?= $id === null ? "" : "?id=" . (int) $id ?>" method="post"
      class="col-lg-8" novalidate>
  <?= csrf_field() ?>

  <div class="mb-3">
    <label for="name" class="form-label">Brand name</label>
    <input type="text" class="form-control<?= isset($errors["name"]) ? " is-invalid" : "" ?>"
           id="name" name="name" maxlength="150" required value="<?= e($values["name"]) ?>">
    <?php if (isset($errors["name"])): ?>
      <div class="invalid-feedback"><?= e($errors["name"]) ?></div>
    <?php endif ?>
  </div>

  <div class="row">
    <div class="col-md-6 mb-3">
      <label for="category_id" class="form-label">Category</label>
      <select class="form-select" id="category_id" name="category_id">
        <option value="">Uncategorised</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category["id"] ?>"
            <?= $values["category_id"] === (string) $category["id"] ? " selected" : "" ?>>
            <?= e($category["name"]) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-6 mb-3">
      <label for="owner_id" class="form-label">Owner</label>
      <select class="form-select" id="owner_id" name="owner_id">
        <option value="">Unknown</option>
        <?php foreach ($owners as $owner): ?>
          <option value="<?= (int) $owner["id"] ?>"
            <?= $values["owner_id"] === (string) $owner["id"] ? " selected" : "" ?>>
            <?= e($owner["name"]) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4 mb-3">
      <label for="type" class="form-label">Type</label>
      <input type="text" class="form-control<?= isset($errors["type"]) ? " is-invalid" : "" ?>"
             id="type" name="type" maxlength="100" value="<?= e($values["type"]) ?>">
      <?php if (isset($errors["type"])): ?>
        <div class="invalid-feedback"><?= e($errors["type"]) ?></div>
      <?php endif ?>
    </div>

    <div class="col-md-4 mb-3">
      <label for="availability" class="form-label">Availability</label>
      <input type="text" class="form-control<?= isset($errors["availability"]) ? " is-invalid" : "" ?>"
             id="availability" name="availability" maxlength="100"
             value="<?= e($values["availability"]) ?>">
      <?php if (isset($errors["availability"])): ?>
        <div class="invalid-feedback"><?= e($errors["availability"]) ?></div>
      <?php endif ?>
    </div>

    <div class="col-md-4 mb-3">
      <label for="rating" class="form-label">Rating</label>
      <input type="number" step="0.5" min="1" max="10"
             class="form-control<?= isset($errors["rating"]) ? " is-invalid" : "" ?>"
             id="rating" name="rating" value="<?= e($values["rating"]) ?>">
      <?php if (isset($errors["rating"])): ?>
        <div class="invalid-feedback"><?= e($errors["rating"]) ?></div>
      <?php endif ?>
      <div class="form-text">1&ndash;10, or leave blank for not rated.</div>
    </div>
  </div>

  <div class="mb-3">
    <label for="notes" class="form-label">Notes</label>
    <textarea class="form-control<?= isset($errors["notes"]) ? " is-invalid" : "" ?>"
              id="notes" name="notes" rows="6" maxlength="5000"><?= e($values["notes"]) ?></textarea>
    <?php if (isset($errors["notes"])): ?>
      <div class="invalid-feedback"><?= e($errors["notes"]) ?></div>
    <?php endif ?>
    <div class="form-text">Shown on the public brand page. Explain what drove the rating.</div>
  </div>

  <button type="submit" class="btn btn-primary">
    <?= $id === null ? "Create brand" : "Save changes" ?>
  </button>
  <a class="btn btn-outline-secondary" href="/admin/index.php">Cancel</a>
</form>
