<?php
  $product_name = $product["name"] !== null && $product["name"] !== "" ? $product["name"] : "Product " . $product["code"];
  $off_url = (string) ($product["product_url"] ?? "");
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php">Categories</a></li>
    <li class="breadcrumb-item">
      <a href="brand.php?brand=<?= urlencode($product["brand"]) ?>"><?= e($product["brand"]) ?></a>
    </li>
    <li class="breadcrumb-item active" aria-current="page"><?= e($product_name) ?></li>
  </ol>
</nav>

<div class="card mb-4 page-hero">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div class="d-flex align-items-start gap-3 hero-with-photo">
        <figure class="product-photo mb-0">
          <?php $thumb_url = $product["image_url"] ?? null; $thumb_alt = $product_name; $thumb_size = 160;
                require(TEMPLATE_DIR . "/partials/thumb.php"); ?>
          <?php if (is_product_image_url($product["image_url"] ?? null)): ?>
            <figcaption class="photo-credit">
              Photo:
              <?php if (preg_match('~^https?://~i', $off_url)): ?>
                <a href="<?= e($off_url) ?>" target="_blank" rel="noopener noreferrer">Open Food Facts contributors</a>,
              <?php else: ?>
                Open Food Facts contributors,
              <?php endif ?>
              CC BY-SA 4.0
            </figcaption>
          <?php endif ?>
        </figure>
        <div>
          <h3 class="card-title mb-0"><?= e($product_name) ?></h3>
          <div class="text-muted">
            by <a href="brand.php?brand=<?= urlencode($product["brand"]) ?>"><?= e($product["brand"]) ?></a>
          </div>
        </div>
      </div>
      <div class="text-end score-hero">
        <div class="small text-muted mb-1">
          <?= $chosen ? "Your rating" : "Balanced rating" ?>
        </div>
        <?php require(TEMPLATE_DIR . "/partials/personal_score.php"); ?>
      </div>
    </div>

    <dl class="row mt-3 mb-0">
      <dt class="col-sm-3">Brand owner</dt>
      <dd class="col-sm-9"><?= e($product["owner"] ?? "Unknown") ?></dd>

      <dt class="col-sm-3">Brand category</dt>
      <dd class="col-sm-9"><?= e($product["category"] ?? "Uncategorised") ?></dd>

      <?php if ($product["product_category"] !== null && $product["product_category"] !== ""): ?>
        <dt class="col-sm-3">Product type</dt>
        <dd class="col-sm-9"><?= e($product["product_category"]) ?></dd>
      <?php endif ?>

      <dt class="col-sm-3">Certifications</dt>
      <dd class="col-sm-9"><?= e($product["labels"] !== null && $product["labels"] !== "" ? $product["labels"] : "None recorded") ?></dd>

      <dt class="col-sm-3">Barcode</dt>
      <dd class="col-sm-9"><?= e($product["code"]) ?></dd>
    </dl>

    <p class="small text-muted mb-0 mt-3">
      Product facts from
      <?php if (preg_match('~^https?://~i', $off_url)): ?>
        <a href="<?= e($off_url) ?>" target="_blank" rel="noopener noreferrer">Open Food Facts</a>,
      <?php else: ?>
        Open Food Facts,
      <?php endif ?>
      available under the Open Database Licence (ODbL).
      <?php if (!empty($product["product_retrieved_at"]) && strtotime($product["product_retrieved_at"])): ?>
        Retrieved <?= e(date("j M Y", strtotime($product["product_retrieved_at"]))) ?>.
      <?php endif ?>
    </p>
  </div>
</div>

<?php
  $subject_label = "This product";
  $row_notes = [
      "editorial" => "The brand's, not this product's",
      "ownership" => "The brand's, not this product's",
  ];
  require(TEMPLATE_DIR . "/partials/score_card.php");
?>

<?php require(TEMPLATE_DIR . "/partials/rating_legend.php"); ?>
