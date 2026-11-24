// Site-wide behaviour, loaded on every page.
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
