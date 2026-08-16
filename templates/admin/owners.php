<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h3 class="mb-0">Admin &middot; Owners</h3>
  <a class="btn btn-outline-secondary" href="/admin/index.php">Brands</a>
</div>

<?php if ($flash !== ""): ?>
  <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $error): ?>
      <div><?= e($error) ?></div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<p class="text-muted">
  The importer only links a company automatically when the name matches exactly
  and one active company holds it. Everything else waits here, because guessing
  would publish a false claim about a real business.
</p>

<?php foreach ($owners as $owner): ?>
  <?php $confirmed = $owner["match_status"] === "confirmed"; ?>

  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h5 class="mb-1"><?= e($owner["name"]) ?></h5>
          <div class="text-muted small">
            <?= (int) $owner["brand_count"] ?> brand<?= (int) $owner["brand_count"] === 1 ? "" : "s" ?>
          </div>
        </div>

        <span class="badge <?= $confirmed ? "text-bg-success" : "text-bg-warning" ?>">
          <?= e(ucfirst($owner["match_status"])) ?>
        </span>
      </div>

      <?php if ($confirmed): ?>
        <dl class="row mt-3 mb-0">
          <dt class="col-sm-3">Registered as</dt>
          <dd class="col-sm-9">
            <?= e($owner["company_name"]) ?>
            <?php if (!empty($owner["source_url"])): ?>
              <a href="<?= e($owner["source_url"]) ?>" rel="noopener nofollow" target="_blank"
                 class="ms-1 small">#<?= e($owner["company_number"]) ?></a>
            <?php else: ?>
              <span class="text-muted small">#<?= e($owner["company_number"]) ?></span>
            <?php endif ?>
            <span class="text-muted small">(<?= e($owner["company_status"]) ?>)</span>
          </dd>

          <?php if (!empty($owner["parent_company_name"])): ?>
            <dt class="col-sm-3">Controlled by</dt>
            <dd class="col-sm-9"><?= e($owner["parent_company_name"]) ?></dd>
          <?php endif ?>
        </dl>

        <form action="/admin/owners.php" method="post" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="owner_id" value="<?= (int) $owner["id"] ?>">
          <input type="hidden" name="action" value="clear">
          <button type="submit" class="btn btn-sm btn-outline-danger">Clear match</button>
        </form>

      <?php else: ?>
        <?php if (!empty($owner["match_note"])): ?>
          <p class="text-muted small mt-2 mb-2"><?= e($owner["match_note"]) ?></p>
        <?php endif ?>

        <?php $list = $candidates[$owner["id"]] ?? []; ?>

        <?php if (!$list): ?>
          <p class="text-muted mb-0 mt-2">
            No suggestions yet. Run
            <code>php bin/import-companies-house.php --apply</code>.
          </p>
        <?php else: ?>
          <ul class="list-group list-group-flush mt-2">
            <?php foreach ($list as $c): ?>
              <li class="list-group-item px-0">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div>
                    <strong><?= e($c["company_name"]) ?></strong>
                    <span class="badge text-bg-light border ms-1"><?= (int) $c["score"] ?>%</span>
                    <span class="badge <?= ($c["company_status"] ?? "") === "active"
                        ? "text-bg-light border" : "text-bg-secondary" ?>">
                      <?= e($c["company_status"] ?? "unknown") ?>
                    </span>
                    <div class="text-muted small">
                      #<?= e($c["company_number"]) ?>
                      <?= empty($c["address_snippet"]) ? "" : "&middot; " . e($c["address_snippet"]) ?>
                    </div>
                  </div>

                  <form action="/admin/owners.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="owner_id" value="<?= (int) $owner["id"] ?>">
                    <input type="hidden" name="company_number" value="<?= e($c["company_number"]) ?>">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                      This one
                    </button>
                  </form>
                </div>
              </li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
      <?php endif ?>
    </div>
  </div>
<?php endforeach ?>

<p class="small text-muted">
  Company data from Companies House. Contains public sector information
  licensed under the Open Government Licence v3.0.
</p>
