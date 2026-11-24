<div class="prose">
<h1 class="h3">Contact</h1>

<?php if ($sent): ?>
  <div class="alert alert-success">
    Thanks, your message has been sent. We'll reply by email.
  </div>
<?php endif ?>

<?php
  // errors with numeric keys are form-wide rather than field-specific
  $general_errors = array_filter($errors, "is_int", ARRAY_FILTER_USE_KEY);
  $reporting = ($values["about"] ?? "") !== "" && ($values["item"] ?? "") !== "";
?>
<?php if ($general_errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($general_errors as $error): ?>
      <div><?= e($error) ?></div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<?php if ($reporting): ?>
  <p>
    You're reporting a problem with the <?= e($values["about"]) ?>
    <strong><?= e($values["item"]) ?></strong>. Tell us what is wrong and, if you can,
    where the correct information can be found. See <a href="/corrections.php">how we handle corrections</a>.
  </p>
<?php else: ?>
  <p class="text-muted">
    Spotted a brand we've rated wrongly, or one we're missing? Let us know.
    See <a href="/corrections.php">how we handle corrections</a>.
  </p>
<?php endif ?>

<form action="contact.php" method="post" class="col-md-8" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="form_stamp" value="<?= e($stamp ?? "") ?>">
  <?php if ($reporting): ?>
    <input type="hidden" name="about" value="<?= e($values["about"]) ?>">
    <input type="hidden" name="item" value="<?= e($values["item"]) ?>">
  <?php endif ?>

  <div class="mb-3">
    <label for="name" class="form-label">Your name</label>
    <input type="text" class="form-control<?= isset($errors["name"]) ? " is-invalid" : "" ?>"
           id="name" name="name" maxlength="100" required autocomplete="name"
           value="<?= e($values["name"]) ?>">
    <?php if (isset($errors["name"])): ?>
      <div class="invalid-feedback"><?= e($errors["name"]) ?></div>
    <?php endif ?>
  </div>

  <div class="mb-3">
    <label for="email" class="form-label">Your email</label>
    <input type="email" class="form-control<?= isset($errors["email"]) ? " is-invalid" : "" ?>"
           id="email" name="email" maxlength="254" required autocomplete="email"
           value="<?= e($values["email"]) ?>">
    <?php if (isset($errors["email"])): ?>
      <div class="invalid-feedback"><?= e($errors["email"]) ?></div>
    <?php endif ?>
    <div class="form-text">We'll only use this to reply to you. See our <a href="/privacy.php">privacy policy</a>.</div>
  </div>

  <!-- left empty by people; the field is hidden from view and from screen readers -->
  <div class="contact-trap" aria-hidden="true">
    <label for="<?= CONTACT_HONEYPOT ?>">Leave this empty</label>
    <input type="text" id="<?= CONTACT_HONEYPOT ?>" name="<?= CONTACT_HONEYPOT ?>" tabindex="-1" autocomplete="off" value="">
  </div>

  <div class="mb-3">
    <label for="message" class="form-label">Message</label>
    <textarea class="form-control<?= isset($errors["message"]) ? " is-invalid" : "" ?>"
              id="message" name="message" rows="6" maxlength="5000" required><?= e($values["message"]) ?></textarea>
    <?php if (isset($errors["message"])): ?>
      <div class="invalid-feedback"><?= e($errors["message"]) ?></div>
    <?php endif ?>
    <div class="form-text">Plain text only, up to 5,000 characters.</div>
  </div>

  <?php if (!empty($turnstile_site_key)): ?>
    <div class="cf-turnstile mb-3" data-sitekey="<?= e($turnstile_site_key) ?>"></div>
  <?php endif ?>

  <button type="submit" class="btn btn-primary">Send message</button>
</form>
</div>
<?php if (!empty($turnstile_site_key)): ?>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif ?>
