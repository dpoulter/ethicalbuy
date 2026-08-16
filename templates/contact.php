<h3>Contact</h3>

<?php if ($sent): ?>
  <div class="alert alert-success">
    Thanks &mdash; your message has been sent. We'll get back to you by email.
  </div>
<?php endif ?>

<?php
  // errors with numeric keys are form-wide rather than field-specific
  $general_errors = array_filter($errors, "is_int", ARRAY_FILTER_USE_KEY);
?>
<?php if ($general_errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($general_errors as $error): ?>
      <div><?= e($error) ?></div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<p class="text-muted">
  Spotted a brand we've rated wrongly, or one we're missing? Let us know.
</p>

<form action="contact.php" method="post" class="col-md-8" novalidate>
  <?= csrf_field() ?>

  <div class="mb-3">
    <label for="name" class="form-label">Your name</label>
    <input type="text" class="form-control<?= isset($errors["name"]) ? " is-invalid" : "" ?>"
           id="name" name="name" maxlength="100" required
           value="<?= e($values["name"]) ?>">
    <?php if (isset($errors["name"])): ?>
      <div class="invalid-feedback"><?= e($errors["name"]) ?></div>
    <?php endif ?>
  </div>

  <div class="mb-3">
    <label for="email" class="form-label">Your email</label>
    <input type="email" class="form-control<?= isset($errors["email"]) ? " is-invalid" : "" ?>"
           id="email" name="email" maxlength="255" required
           value="<?= e($values["email"]) ?>">
    <?php if (isset($errors["email"])): ?>
      <div class="invalid-feedback"><?= e($errors["email"]) ?></div>
    <?php endif ?>
    <div class="form-text">We'll only use this to reply to you.</div>
  </div>

  <div class="mb-3">
    <label for="message" class="form-label">Message</label>
    <textarea class="form-control<?= isset($errors["message"]) ? " is-invalid" : "" ?>"
              id="message" name="message" rows="6" maxlength="5000" required><?= e($values["message"]) ?></textarea>
    <?php if (isset($errors["message"])): ?>
      <div class="invalid-feedback"><?= e($errors["message"]) ?></div>
    <?php endif ?>
  </div>

  <button type="submit" class="btn btn-primary">Send message</button>
</form>
