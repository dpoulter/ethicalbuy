<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/admin/index.php">Admin</a></li>
    <li class="breadcrumb-item active" aria-current="page">
      Scores for <?= e($brand["name"]) ?>
    </li>
  </ol>
</nav>

<h3>Dimension scores</h3>

<?php if ($flash !== ""): ?>
  <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif ?>

<?php $general = array_filter($errors, "is_int", ARRAY_FILTER_USE_KEY); ?>
<?php if ($general): ?>
  <div class="alert alert-danger">
    <?php foreach ($general as $error): ?><div><?= e($error) ?></div><?php endforeach ?>
  </div>
<?php endif ?>

<p class="text-muted">
  Readers weight these themselves, so each one is scored on its own merits
  rather than rolled into a single verdict. <strong>Leave a score blank if you
  haven't assessed it</strong> &mdash; blank means "we don't know" and is
  excluded from a reader's average, which is not the same as scoring it low.
</p>

<p class="text-muted">
  The editorial rating (<?= e(rating_score($brand["rating"])) ?>) is edited on
  the <a href="/admin/edit.php?id=<?= (int) $brand["id"] ?>">brand form</a> and
  counts as its own dimension.
</p>

<form action="/admin/scores.php" method="post" class="col-lg-9">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $brand["id"] ?>">

  <?php foreach (score_dimensions() as $key => $meta): ?>
    <?php if ($key === "editorial") continue; ?>
    <?php $existing = $scores[$key] ?? null; ?>

    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title"><?= e($meta["label"]) ?></h5>
        <p class="text-muted small"><?= e($meta["blurb"]) ?></p>

        <div class="row g-2">
          <div class="col-md-3">
            <label for="score_<?= e($key) ?>" class="form-label">Score</label>
            <input type="number" step="0.5" min="1" max="10"
                   class="form-control<?= isset($errors[$key]) ? " is-invalid" : "" ?>"
                   id="score_<?= e($key) ?>" name="score_<?= e($key) ?>"
                   placeholder="Not assessed"
                   value="<?= $existing === null ? "" : e(rtrim(rtrim((string) $existing["score"], "0"), ".")) ?>">
            <?php if (isset($errors[$key])): ?>
              <div class="invalid-feedback"><?= e($errors[$key]) ?></div>
            <?php endif ?>
          </div>

          <div class="col-md-9">
            <label for="note_<?= e($key) ?>" class="form-label">Why</label>
            <input type="text" class="form-control" id="note_<?= e($key) ?>"
                   name="note_<?= e($key) ?>" maxlength="255"
                   placeholder="Shown to readers alongside the score"
                   value="<?= $existing === null ? "" : e($existing["note"]) ?>">
          </div>
        </div>

        <?php if ($existing !== null && !empty($existing["source"])): ?>
          <p class="small text-muted mt-2 mb-0">
            Currently from <?= e($existing["source"]) ?><?php
              if (!empty($existing["retrieved_at"])): ?>,
              <?= e(substr((string) $existing["retrieved_at"], 0, 10)) ?><?php
              endif ?>.
            Saving here marks it as curated by you.
          </p>
        <?php endif ?>
      </div>
    </div>
  <?php endforeach ?>

  <button type="submit" class="btn btn-primary">Save scores</button>
  <a class="btn btn-outline-secondary" href="/admin/index.php">Back</a>
</form>
