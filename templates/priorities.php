<h3>Your Priorities</h3>

<?php if ($saved): ?>
  <div class="alert alert-success">
    Saved. Ratings across the site now reflect what matters to you.
    <a href="/search.php?submitted=1&amp;sort=personal">See brands ranked your way</a>.
  </div>
<?php endif ?>

<p class="lead">
  Ethics aren't one number. Tell us what matters to you and every rating on the
  site is recalculated from your priorities.
</p>

<?php if (!$chosen): ?>
  <div class="alert alert-secondary">
    You haven't set anything yet, so everything is currently weighted equally.
  </div>
<?php endif ?>

<form action="/priorities.php" method="post" class="col-lg-9">
  <?= csrf_field() ?>

  <?php foreach (score_dimensions() as $key => $meta): ?>
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <h5 class="mb-1"><?= e($meta["label"]) ?></h5>
            <p class="text-muted mb-2"><?= e($meta["blurb"]) ?></p>
            <p class="small text-muted mb-0"><?= e($meta["sourced"]) ?></p>
          </div>
          <span class="badge text-bg-light border align-self-center"
                id="label_<?= e($key) ?>">
            <?= e(weight_label($weights[$key])) ?>
          </span>
        </div>

        <label for="w_<?= e($key) ?>" class="form-label visually-hidden">
          How much does <?= e($meta["label"]) ?> matter?
        </label>
        <input type="range" class="form-range mt-3" id="w_<?= e($key) ?>"
               name="w_<?= e($key) ?>" min="<?= WEIGHT_MIN ?>" max="<?= WEIGHT_MAX ?>"
               step="1" value="<?= (int) $weights[$key] ?>"
               data-label="label_<?= e($key) ?>">

        <div class="d-flex justify-content-between small text-muted">
          <span>Ignore</span>
          <span>Normal</span>
          <span>Critical</span>
        </div>
      </div>
    </div>
  <?php endforeach ?>

  <button type="submit" class="btn btn-primary">Save priorities</button>
  <button type="submit" name="action" value="reset" class="btn btn-outline-secondary">
    Reset to equal
  </button>
</form>

<p class="small text-muted mt-4 col-lg-9">
  Your priorities are kept in a cookie on this device. There's no account, we
  don't store them on our servers, and they aren't shared with anyone. Setting a
  dimension to <em>Ignore</em> removes it from your ratings entirely.
</p>

<script type="application/json" id="weightLabels"><?= json_for_html(
    array_map("weight_label", range(WEIGHT_MIN, WEIGHT_MAX))
) ?></script>

<script>
(function () {
  // labels come from PHP so the wording cannot drift from weight_label()
  const labels = JSON.parse(document.getElementById('weightLabels').textContent);

  for (const slider of document.querySelectorAll('input[type=range][data-label]')) {
    const badge = document.getElementById(slider.dataset.label);
    if (!badge) continue;

    slider.addEventListener('input', () => {
      badge.textContent = labels[Number(slider.value)] ?? '';
    });
  }
})();
</script>
