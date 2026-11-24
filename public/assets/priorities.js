// Live labels for the priority sliders (templates/priorities.php).
(function () {
  // labels come from PHP so the wording cannot drift from weight_label()
  const labels = JSON.parse(document.getElementById('weightLabels').textContent);

  for (const slider of document.querySelectorAll('input[type=range][data-label]')) {
    const badge = document.getElementById(slider.dataset.label);
    if (!badge) continue;

    slider.addEventListener('input', () => {
      badge.textContent = labels[Number(slider.value)] ?? '';
    });
  }
})();
