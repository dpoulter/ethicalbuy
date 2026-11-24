<?php
/**
 * A product or brand photo, hotlinked from Open Food Facts, in a fixed-size
 * box so the layout never jumps. Expects $thumb_url (may be null) and
 * optionally $thumb_alt (default "", decorative) and $thumb_size (default 96).
 *
 * With no usable URL, or if the image fails to load, the box shows a neutral
 * placeholder: the img is drawn over the box's own background, and a broken
 * image with empty alt text draws nothing. No script involved.
 */
// sizes are CSS classes (thumb--56 etc.), never inline styles, for the CSP
$thumb_size = in_array((int) ($thumb_size ?? 96), [56, 96, 160], true) ? (int) ($thumb_size ?? 96) : 96;
$thumb_ok = is_product_image_url($thumb_url ?? null);
?>
<span class="thumb thumb--<?= $thumb_size ?><?= $thumb_ok ? "" : " thumb--empty" ?>">
  <?php if ($thumb_ok): ?>
    <img src="<?= e($thumb_url) ?>" alt="<?= e($thumb_alt ?? "") ?>" loading="lazy" decoding="async"
         width="96" height="96" referrerpolicy="no-referrer">
  <?php endif ?>
</span>
<?php unset($thumb_url, $thumb_alt, $thumb_size, $thumb_ok); ?>
