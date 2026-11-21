</main>

<footer class="site-footer">
  <div class="container">
    <p class="mb-1">
      <strong>Ethical Buy</strong> &middot; who really owns the brands in your basket.
      <a href="/about.php#how-scores-work">How scores work</a>
    </p>
    <p class="mb-0 small">
      Product data from <a href="https://world.openfoodfacts.org" rel="noopener noreferrer">Open Food Facts</a>,
      available under the Open Database Licence (ODbL).
    </p>
  </div>
</footer>

<script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
  // print the full working: open every collapsed section before printing
  window.addEventListener('beforeprint', function () {
    document.querySelectorAll('details:not([open])').forEach(function (d) {
      d.setAttribute('open', '');
      d.dataset.printOpened = '1';
    });
  });
  window.addEventListener('afterprint', function () {
    document.querySelectorAll('details[data-print-opened]').forEach(function (d) {
      d.removeAttribute('open');
      delete d.dataset.printOpened;
    });
  });
</script>
</body>
</html>
