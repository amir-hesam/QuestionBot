(function () {
  'use strict';

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || form.tagName !== 'FORM') {
      return;
    }

    if (form.getAttribute('data-confirm')) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
        return;
      }
    }

    if (form.getAttribute('data-busy') === '1') {
      var buttons = form.querySelectorAll('button[type="submit"]');
      for (var i = 0; i < buttons.length; i++) {
        buttons[i].disabled = true;
        if (!buttons[i].getAttribute('data-label')) {
          buttons[i].setAttribute('data-label', buttons[i].textContent);
        }
        buttons[i].textContent = 'در حال ارسال...';
      }
    }
  });
})();
