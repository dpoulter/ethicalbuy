<!--
  The prose on this page is a starting point. Edit it to describe your own
  sourcing and methodology before going public with it.
-->

<h3>About Ethical Buy</h3>

<p class="lead">
  Ethical Buy helps you find out who really owns the brands in your basket,
  and how they behave.
</p>

<p>
  Supermarket shelves are full of brands that look independent but belong to a
  handful of large groups. We collect what's publicly known about each brand
  &mdash; its parent company, where you can buy it, and the concerns raised
  about it &mdash; and boil that down to a single rating out of ten, so you can
  make a call in the aisle rather than after an evening of research.
</p>

<h4 class="mt-4">How the ratings work</h4>

<p>
  Every brand gets a score from 1 to 10. Higher is better. Brands we haven't
  assessed yet are shown as <em>Not rated</em> rather than being given a
  neutral score, so an unrated brand is never mistaken for an average one.
</p>

<ul class="list-group mb-3">
  <?php foreach (rating_bands() as $band): ?>
    <li class="list-group-item<?= $band["class"] === "" ? "" : " list-group-item-" . $band["class"] ?>">
      <strong><?= e($band["label"]) ?></strong>
      <span class="text-muted">(<?= e($band["range"]) ?>)</span>
      &mdash; <?= e($band["blurb"]) ?>
    </li>
  <?php endforeach ?>
</ul>

<p>
  The colour you see on a brand's row is derived from its score, so the key
  above always matches what's on screen.
</p>

<h4 class="mt-4">Where the information comes from</h4>

<p>
  Ratings are compiled from public sources: company reports and ownership
  filings, published campaign and watchdog research, and press coverage. Each
  brand's notes explain what drove its score.
</p>

<p>
  Ownership changes often, and a rating reflects what was known when it was
  written. If something here looks out of date or wrong, we'd genuinely like to
  know &mdash; <a href="contact.php">tell us</a> and we'll look at it again.
</p>

<h4 class="mt-4">What this isn't</h4>

<p>
  This is a research aid, not a verdict. A single number can't capture
  everything that matters about a company, and reasonable people weigh these
  things differently. Read the notes, follow the sources, and draw your own
  conclusions.
</p>

<p class="mt-4">
  <a class="btn btn-primary" href="search.php">Search brands</a>
  <a class="btn btn-outline-secondary" href="index.php">Browse by category</a>
</p>
