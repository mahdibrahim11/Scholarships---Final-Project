/**
 * Scholarship search: AJAX filtering with jQuery.
 * The form is a normal GET form first. This file only upgrades it so results update without a page reload,
 * and keeps the address bar in sync so a search can be bookmarked or shared.
 */
(function ($) {
  'use strict';
  var $form = $('#search-form');
  var $results = $('#results');
  if (!$form.length || !$results.length) { return; }

  var endpoint = $form.attr('action').replace(/search\.php.*$/, 'search_results.php');
  var xhr = null;
  var timer = null;

  // Everything the person chose, without empty values, as a query string (keeps addresses short).
  function formQuery(page) {
    var pairs = $form.serializeArray().filter(function (f) { return f.value !== ''; });
    if (page && page > 1) { pairs.push({ name: 'page', value: page }); }
    return $.param(pairs);
  }

  // log = true only when state, grade or field changed: keyword typing, sorting and paging are not new searches.
  function load(query, opts) {
    opts = opts || {};
    if (xhr) { xhr.abort(); }
    $results.attr('aria-busy', 'true').addClass('is-loading');
    xhr = $.ajax({
      url: endpoint + '?' + query + (opts.log ? '' : (query ? '&' : '') + 'nolog=1'),
      method: 'GET',
      dataType: 'html'
    }).done(function (html) {
      $results.html(html);
      if (opts.push !== false) {
        var url = $form.attr('action') + (query ? '?' + query : '');
        try { window.history.pushState({ q: query }, '', url); } catch (e) { /* older browsers: results still update */ }
      }
      if (opts.scroll) { $results[0].scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }).fail(function (jq, status) {
      if (status === 'abort') { return; }
      $results.html('<div class="alert alert-danger" role="alert">The search could not be completed. Check your connection and try again.</div>');
    }).always(function () {
      $results.attr('aria-busy', 'false').removeClass('is-loading');
    });
  }

  // Plain selects, checkboxes: update straight away. State, grade and field count as a new search.
  $form.on('change', 'select, input[type=checkbox]', function () {
    load(formQuery(1), { log: $(this).data('mandatory') === 1 || $(this).data('mandatory') === '1' });
  });

  // Typing: wait for a short pause so we do not search on every key.
  $form.on('input', 'input[type=search], input[name=gpa]', function () {
    clearTimeout(timer);
    timer = setTimeout(function () { load(formQuery(1)); }, 350);
  });

  // The Search button (and Enter) searches now, without reloading the page.
  $form.on('submit', function (ev) {
    ev.preventDefault();
    clearTimeout(timer);
    load(formQuery(1), { log: true });
  });

  // Page links inside the results: stay on the page.
  $results.on('click', '.pagination a.page-link', function (ev) {
    var href = $(this).attr('href');
    if (!href || $(this).closest('.page-item').hasClass('disabled')) { ev.preventDefault(); return; }
    ev.preventDefault();
    load(href.indexOf('?') >= 0 ? href.split('?')[1] : '', { scroll: true });
  });

  // Back and Forward buttons: restore the form from the address and reload the results.
  $(window).on('popstate', function () {
    var params = new URLSearchParams(window.location.search);
    $form[0].reset();
    $form.find('select, input[type=search], input[name=gpa]').each(function () { $(this).val(params.get(this.name) || (this.tagName === 'SELECT' && this.name === 'sort' ? 'deadline' : '')); });
    $form.find('input[type=checkbox]').each(function () { this.checked = params.get(this.name) === '1'; });
    load(window.location.search.replace(/^\?/, ''), { push: false });
  });
})(jQuery);
