
/* Iteration 5: progressive enhancement. Regular GET submission still works if JS fails. */
(function ($) {
  'use strict';
  $(function () {
    const $form = $('#scholarship-search');
    const $results = $('#search-results');
    const $status = $('#search-status');
    if (!$form.length || !$results.length || !$.ajax) return;

    let currentRequest = null;
    let debounce = null;

    function loadResults(url, push) {
      if (currentRequest) currentRequest.abort();
      $results.attr('aria-busy', 'true');
      $status.text('Loading scholarships...');

      const separator = url.includes('?') ? '&' : '?';
      currentRequest = $.ajax({
        url: url + separator + 'ajax=1',
        method: 'GET',
        dataType: 'html'
      })
        .done(function (html) {
          $results.html(html);
          $status.text(
            $results.find('[role="status"]').first().text() || 'Results updated'
          );
          if (push) window.history.pushState({}, '', url);
        })
        .fail(function (_xhr, status) {
          if (status !== 'abort') {
            $status.text('Could not refresh results. Press Search to try again.');
          }
        })
        .always(function () {
          $results.attr('aria-busy', 'false');
        });
    }

    function formUrl() {
      const parts = $form.serializeArray().filter(function (part) {
        return part.value !== '';
      });
      return $form.attr('action') + '?' + $.param(parts);
    }

    function submitSearch() {
      const url = formUrl();
      loadResults(url, true);
      $.get(
        $form.attr('action'),
        $.param($form.serializeArray()) + '&log_only=1'
      );
    }

    $form.on('submit', function (event) {
      event.preventDefault();
      clearTimeout(debounce);
      submitSearch();
    });

    $form.on(
      'change',
      'select,input[type="checkbox"],input[type="number"]',
      function () {
        clearTimeout(debounce);
        submitSearch();
      }
    );

    $form.on('input', 'input[name="keyword"]', function () {
      clearTimeout(debounce);
      debounce = setTimeout(submitSearch, 400);
    });

    $results.on('click', '.pagination a', function (event) {
      event.preventDefault();
      loadResults(this.href, true);
      $results.get(0).scrollIntoView({
        behavior: 'auto',
        block: 'start'
      });
    });

    window.addEventListener('popstate', function () {
      window.location.reload();
    });
  });
})(jQuery);
