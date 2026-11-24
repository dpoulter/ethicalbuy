<div class="prose">
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

<h4 class="mt-4" id="how-scores-work">How scores work</h4>

<?php $dims = score_dimensions(); ?>

<p>
  A brand's overall score is a weighted average of the dimensions we hold data
  for. You set each weight from <?= (int) WEIGHT_MIN ?> to <?= (int) WEIGHT_MAX ?>
  on the <a href="priorities.php">Priorities</a> page; if you haven't, every
  dimension is weighted <?= (int) WEIGHT_DEFAULT ?>. Each score is multiplied by
  its weight, the results are added up, and the total is divided by the sum of
  the weights used. A dimension with no data is left out of both sums, never
  counted as zero. A dimension weighted 0 is ignored. If the dimensions with
  data make up less than <?= (int) round(COVERAGE_LOW * 100) ?>% of the weight
  you asked for, the score is flagged as thin. Each brand page shows the
  calculation.
</p>

<p>
  Products are scored the same way, with the same weights. A product has its
  own nutrition, environment and welfare scores from Open Food Facts; our
  overall view and ownership are the brand's, applied to each of its
  products. Each product page shows the calculation.
</p>

<h5 class="mt-3"><?= e($dims["nutrition"]["label"]) ?></h5>
<p class="mb-1">Open Food Facts Nutri-Score, mapped to a score:</p>
<ul class="grade-map">
  <?php foreach (off_grade_map() as $grade => $score): ?>
    <li><?= e(strtoupper($grade)) ?> = <?= e(rating_score($score)) ?></li>
  <?php endforeach ?>
</ul>

<h5><?= e($dims["environment"]["label"]) ?></h5>
<p>
  Open Food Facts Eco-Score (now called Green-Score), which also has A+ and F:
  <?= e(off_grade_map_text(off_eco_grade_map(), " = ")) ?>, out of 10. F scores 1, the lowest
  score we use. "Unknown", "not applicable" and blank grades are left unscored.
</p>

<h5><?= e($dims["welfare"]["label"]) ?></h5>
<p class="mb-1">
  Starts at 5 and adds credit for each recognised label on the product,
  capped at 10. It never goes below 5, because a missing label is missing
  evidence, not evidence of harm. A product with no recognised label is left
  unassessed. Label credits:
</p>
<ul class="welfare-labels">
  <?php foreach (off_welfare_labels() as $tag => $credit): ?>
    <li><?= e(off_label_name($tag)) ?>: +<?= e(rtrim(rtrim(number_format($credit, 1), "0"), ".")) ?></li>
  <?php endforeach ?>
</ul>

<h5><?= e($dims["ownership"]["label"]) ?></h5>
<p class="mb-1">
  Scored from the type of owner we have recorded for the brand. Each recorded
  type counts as one of the categories below; a type not in the list is left
  unscored rather than guessed. A score we enter by hand replaces this.
  Business size is not scored yet.
</p>
<table class="table table-sm ownership-table">
  <thead><tr><th>Category</th><th>Points</th><th>Meaning</th></tr></thead>
  <tbody>
    <?php foreach (ownership_categories() as $c): ?>
      <tr>
        <td><?= e($c["label"]) ?></td>
        <td><?= (int) $c["points"] ?></td>
        <td><?= e($c["meaning"]) ?></td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<h5><?= e($dims["editorial"]["label"]) ?></h5>
<p>Entered by hand by us. It is not imported.</p>

<h5>Brands with several products</h5>
<p>
  A brand's nutrition, environment and welfare scores are the mean of its
  products' scores, rounded to one decimal place. They reflect the products
  Open Food Facts lists for the brand, not necessarily the brand's whole
  range. A product is filed under a brand only when Open Food Facts tags it
  with exactly that brand name.
</p>

<p class="small text-muted">
  Product data from Open Food Facts, available under the Open Database
  Licence (ODbL).
</p>

<h4 class="mt-4">Where the information comes from</h4>

<p>
  Ratings are compiled from public sources: company reports and ownership
  filings, published campaign and watchdog research, and press coverage. Each
  brand's notes explain what drove its score.
</p>

<p>
  Product facts and photos come from Open Food Facts, and company details from
  Companies House. Each source and its licence, including how product photos
  are loaded, is listed on <a href="/sources.php">data sources and licences</a>.
  What that means for your privacy is in the <a href="/privacy.php">privacy policy</a>.
</p>

<p>
  Ownership changes often, and a rating reflects what was known when it was
  written. If something here looks out of date or wrong, we'd genuinely like to
  know: see <a href="/corrections.php">corrections</a> and we'll look at it again.
</p>

<h4 class="mt-4">What this isn't</h4>

<p>
  This is a research aid, not a verdict. A single number can't capture
  everything that matters about a company, and reasonable people weigh these
  things differently. Read the notes, follow the sources, and draw your own
  conclusions.
</p>

<p class="mt-4">
  <a class="btn btn-primary" href="search.php">Search brands and products</a>
  <a class="btn btn-outline-secondary" href="index.php">Browse by category</a>
</p>
</div>
