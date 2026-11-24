/**
 * Save / un-save (FR-USER-04) and application status (FR-USER-05) without reloading the page.
 * Works on search results (which are replaced by AJAX, so events are delegated), the detail page, My Matches and Saved.
 * Every request carries the CSRF token; the server decides whether the profile is yours.
 */
(function ($) {
  'use strict';
  function say(msg) {
    var $r = $('#status-live');
    if (!$r.length) { $r = $('<div id="status-live" class="visually-hidden" role="status" aria-live="polite"></div>').appendTo('body'); }
    $r.text('').text(msg);
  }

  $(document).on('click', '.js-save-toggle', function () {
    var $b = $(this);
    if ($b.prop('disabled')) { return; }
    var wantSave = $b.attr('aria-pressed') !== 'true';
    $b.prop('disabled', true);
    $.ajax({
      url: $b.data('url'), method: 'POST', dataType: 'json',
      headers: { 'X-CSRF-Token': $b.data('csrf'), 'X-Requested-With': 'XMLHttpRequest' },
      data: { profile: $b.data('profile'), scholarship: $b.data('scholarship'), save: wantSave ? '1' : '0' }
    }).done(function (r) {
      $('.js-save-toggle[data-scholarship="' + $b.data('scholarship') + '"]').each(function () {
        $(this).attr('aria-pressed', r.saved ? 'true' : 'false')
          .toggleClass('btn-success', r.saved).toggleClass('btn-outline-success', !r.saved)
          .find('.js-save-label').text(r.saved ? 'Saved' : 'Save');
      });
      say((r.saved ? 'Saved ' : 'Removed ') + $b.data('name'));
    }).fail(function (xhr) {
      say(xhr.status === 401 ? 'Please sign in again.' : 'Could not update that. Reload the page and try again.');
      if (xhr.status === 401) { window.location.href = $b.data('url').replace(/my\/save\.php$/, 'login.php'); }
    }).always(function () { $b.prop('disabled', false); });
  });

  // Saved page: change status as soon as the choice changes. The Update button stays as the no-JavaScript fallback.
  var $t = $('#saved-table');
  if ($t.length) {
    $t.find('.js-status-button').addClass('d-none');
    $t.on('change', '.js-status-select', function () {
      var $s = $(this), $f = $s.closest('form');
      $s.prop('disabled', true);
      $.ajax({
        url: $t.data('status-url'), method: 'POST', dataType: 'json',
        headers: { 'X-CSRF-Token': $t.data('csrf'), 'X-Requested-With': 'XMLHttpRequest' },
        data: { profile_id: $f.find('[name=profile_id]').val(), scholarship_id: $f.find('[name=scholarship_id]').val(), status: $s.val() }
      }).done(function (r) { say('Status changed to ' + r.status); })
        .fail(function () { say('Could not change the status. Reload the page and try again.'); })
        .always(function () { $s.prop('disabled', false); });
    });
  }
}(jQuery));
