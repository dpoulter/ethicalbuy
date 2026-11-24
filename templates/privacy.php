<?php
/*
 * Privacy policy. Built from what the site actually does, so keep it in step
 * with the code:
 *   - retention comes from MESSAGE_RETENTION_DAYS (message_retention_days())
 *   - the advertising section is partials/privacy_ads.php, included only
 *     while ADS_ENABLED=1, and must be written before ads are switched on
 *   - the Turnstile paragraph appears only while Turnstile is configured
 */
$days = message_retention_days();
?>
<div class="prose">
<h1 class="h3">Privacy policy</h1>

<p class="text-muted">Last updated <?= e(long_date(LEGAL_LAST_UPDATED)) ?>.</p>

<p>
  This policy explains what information Ethical Buy collects when you use
  <?= e(preg_replace('#^https?://#', "", SITE_URL)) ?>, why, how long we keep it and
  what your rights are. We have tried to keep it short because we collect
  very little.
</p>

<h2 class="h5 mt-4">Who we are</h2>
<p>
  The data controller is <?= legal_value(controller_name(), "CONTROLLER_NAME") ?>.
  You can contact us about anything in this policy at <?= legal_email_link() ?>.
</p>

<h2 class="h5 mt-4">The short version</h2>
<ul>
  <li>You can use the site without giving us any personal information.</li>
  <li>
    There is no analytics, no tracking and no advertising<?= ads_enabled() ? " other than as described below" : "" ?>.
    We do not sell or share your information with anyone for marketing.
  </li>
  <li>If you contact us, we keep your message for <?= (int) $days ?> days and then delete it.</li>
</ul>

<h2 class="h5 mt-4">What we collect and why</h2>

<h3 class="h6 mt-3">Messages you send us</h3>
<p>
  When you use the <a href="/contact.php">contact form</a> we store your name,
  your email address and your message, so that we can read it, reply and fix
  anything you report. Our lawful basis is our legitimate interest in
  answering people who write to us and keeping the information on the site
  accurate. We do not add you to any mailing list.
</p>

<h3 class="h6 mt-3">Records that protect the site</h3>
<p>
  To stop the contact form and the site being abused, we keep a short log
  entry when a message is received and when someone fails to sign in to our
  admin pages. These entries record only a shortened form of the IP address
  the request came from (the first three parts of an IPv4 address, or the
  first 48 bits of an IPv6 address), which identifies a network rather than
  a person or household.
</p>
<p>
  We also count how many requests each visitor makes in a short period, so
  that we can slow down automated scraping. These counters store a one-way,
  keyed scramble of your IP address rather than the address itself, and are
  deleted within two days. Our lawful basis for both is our legitimate
  interest in keeping the site secure and available.
</p>
<p>
  Like any website, the servers that deliver these pages record technical
  details of each request, including the IP address, for security and to
  diagnose faults.
</p>

<h3 class="h6 mt-3">Cookies</h3>
<p>We use at most two cookies, both of them strictly necessary for something you have asked for:</p>
<ul>
  <li>
    <strong>priorities</strong>: set only if you choose
    <a href="/priorities.php">your priorities</a>. It holds the five weights you
    picked, so the scores you see reflect what matters to you. It contains no
    identifier, is not used for tracking and lasts a year unless you reset it.
  </li>
  <li>
    <strong>PHPSESSID</strong>: set only on pages with a form, such as the contact
    form. It holds a random identifier that lets us check the form was really
    sent from our site. It is deleted when you close your browser.
  </li>
</ul>
<p>Because both are necessary for a service you requested, we do not ask for consent to them.</p>

<h3 class="h6 mt-3">Product photos</h3>
<p>
  Product photos are loaded directly from Open Food Facts' servers
  (images.openfoodfacts.org), not ours. When a page with a photo is shown,
  your browser asks Open Food Facts for the image, so Open Food Facts sees
  your IP address and browser details. We send them no other information
  about you. Their use of that information is covered by the
  <a href="https://world.openfoodfacts.org/privacy" rel="noopener noreferrer">Open Food Facts privacy policy</a>.
</p>

<?php if (turnstile_enabled()): ?>
<h3 class="h6 mt-3">Checking the contact form is used by a person</h3>
<p>
  The contact form uses Cloudflare Turnstile to tell people from automated
  programs. It runs in your browser when you open the form and sends
  Cloudflare technical information about your browser and device. It does
  not set advertising cookies. See
  <a href="https://www.cloudflare.com/privacypolicy/" rel="noopener noreferrer">Cloudflare's privacy policy</a>.
</p>
<?php endif ?>

<?php if (ads_enabled()) { require(TEMPLATE_DIR . "/partials/privacy_ads.php"); } ?>

<h2 class="h5 mt-4">Who else is involved</h2>
<p>
  The site is reached through Cloudflare, which carries traffic between your
  browser and our server and protects it from attack. Cloudflare processes
  your IP address and request details to do that, on our behalf. The site
  itself runs on a private server that we operate; your messages are stored
  there and nowhere else. We do not share your information with anyone else
  unless the law requires us to.
</p>

<h2 class="h5 mt-4">How long we keep it</h2>
<ul>
  <li>Contact messages and the log entries described above: <?= (int) $days ?> days, then deleted automatically.</li>
  <li>Request counters: up to two days.</li>
  <li>Cookies: as described above; you can delete them in your browser at any time.</li>
</ul>

<h2 class="h5 mt-4">Your rights</h2>
<p>
  Under UK data protection law (the UK GDPR and the Data Protection Act 2018)
  you have the right to ask for a copy of the information we hold about you,
  to have it corrected or deleted, to restrict or object to how we use it, and
  to receive it in a portable form. To use any of these rights, email
  <?= legal_email_link() ?>. We will reply within one month. There is no charge.
</p>
<p>
  If you are unhappy with how we have handled your information, please tell us
  first so we can try to put it right. You also have the right to complain to
  the Information Commissioner's Office:
  <a href="https://ico.org.uk/make-a-complaint/" rel="noopener noreferrer">ico.org.uk/make-a-complaint</a>,
  telephone 0303 123 1113.
</p>

<h2 class="h5 mt-4">Changes</h2>
<p>
  If we change what we collect, we will update this page and the date at the
  top before the change takes effect.
</p>
</div>
