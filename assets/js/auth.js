/* Client-side checks for sign-in, registration, profile and change password (FR-AUTH-01).
   These are a convenience. The server repeats every rule and is the authority. */
(function () {
  'use strict';
  var MIN = window.PW_MIN || 10;

  function passwordProblems(pw) {
    var p = [];
    if (pw.length < MIN) { p.push('Use at least ' + MIN + ' characters.'); }
    if (pw.length > 72) { p.push('Use at most 72 characters.'); }
    if (!/[A-Za-z]/.test(pw) || !/\d/.test(pw)) { p.push('Include at least one letter and one number.'); }
    return p;
  }

  function mark(el, ok, msg) {
    el.classList.toggle('is-invalid', !ok);
    var fb = el.parentNode.querySelector('.invalid-feedback');
    if (fb && msg && !ok) { fb.textContent = msg; }
  }

  function check(form, only) {
    var ok = true;
    form.querySelectorAll('input[required]').forEach(function (el) {
      if (el.type === 'radio' || (only && el !== only)) { return; }
      var valid = el.checkValidity() && el.value.trim() !== '';
      var msg = null;
      if (valid && el.name === 'password' && form.querySelector('[name=password_confirm]')) {
        var p = passwordProblems(el.value);
        if (p.length) { valid = false; msg = p.join(' '); }
      }
      if (valid && el.name === 'password_confirm') {
        var pw = form.querySelector('[name=password]');
        if (pw && pw.value !== el.value) { valid = false; msg = 'The two passwords do not match.'; }
      }
      mark(el, valid, msg);
      if (!valid) { ok = false; }
    });
    if (!only) {
      var radios = form.querySelectorAll('input[type=radio][required]');
      if (radios.length && !form.querySelector('input[type=radio]:checked')) { ok = false; }
    }
    return ok;
  }

  ['login-form', 'register-form', 'password-form', 'profile-form'].forEach(function (id) {
    var form = document.getElementById(id);
    if (!form) { return; }
    form.addEventListener('submit', function (ev) {
      if (!check(form)) {
        ev.preventDefault();
        var bad = form.querySelector('.is-invalid');
        if (bad) { bad.focus(); }
      }
    });
    // Errors appear when you submit and clear as soon as the field becomes valid. Nothing is checked on blur,
    // because an error message that appears or disappears under the pointer moves the button and swallows the click.
    form.querySelectorAll('input').forEach(function (el) {
      el.addEventListener('input', function () {
        form.querySelectorAll('input.is-invalid').forEach(function (bad) { check(form, bad); });
      });
    });
  });
})();
