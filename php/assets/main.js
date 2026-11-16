document.querySelectorAll('form[data-confirm]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    var message = form.getAttribute('data-confirm') || 'Are you sure?';
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('input[type="number"][max]').forEach(function (input) {
  input.addEventListener('change', function () {
    var max = Number(input.getAttribute('max'));
    var min = Number(input.getAttribute('min') || 0);
    var value = Number(input.value);

    if (!Number.isFinite(value)) return;
    if (max >= 0 && value > max) input.value = max;
    if (value < min) input.value = min;
  });
});

document.querySelectorAll('.alert').forEach(function (alert) {
  window.setTimeout(function () {
    alert.classList.add('is-hiding');
    window.setTimeout(function () {
      alert.remove();
    }, 320);
  }, 5000);
});
