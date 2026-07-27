/**
 * Sales & Service Office accordion on ARKRAY Group regional pages.
 * Mirrors the original arkray.co.jp group02–group05 inline behaviour.
 */
(function ($) {
	$(function () {
		var $answers = $('dd.answer');
		if (!$answers.length) {
			return;
		}

		$answers.hide();

		if (window.location.hash) {
			var $target = $(window.location.hash);
			$target.find('dd.answer').show();
			$target.find('div.tablecellbox2 img').addClass('rotate');
		}

		$('dt.question').on('click', function () {
			var $row = $(this);
			var $next = $row.next('dd.answer');

			$('div.tablecellbox2 img').removeClass('rotate');
			$answers.slideUp();

			if ($next.length && !$next.is(':visible')) {
				$row.find('div.tablecellbox2 img').addClass('rotate');
				$next.slideDown();
			}
		});
	});
})(window.ARKRAY_JQ || window.jQuery);
