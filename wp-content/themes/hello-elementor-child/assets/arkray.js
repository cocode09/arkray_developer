jQuery(document).ready(function($){

	// ── Responsive viewport classes (matches live skel breakpoints) ───────
	// large: max-width 1199px → .pc | middle: 990px → .tablet | small: 767px → .sp
	var mqSp     = window.matchMedia('(max-width: 767px)');
	var mqTablet = window.matchMedia('(max-width: 990px)');
	var mqPc     = window.matchMedia('(max-width: 1199px)');

	function updateResponsiveClasses(){
		var $html = $('html');
		$html.toggleClass('sp', mqSp.matches);
		$html.toggleClass('tablet', mqTablet.matches);
		$html.toggleClass('pc', mqPc.matches);
	}

	function applyViewportLayoutState(){
		if ( ! mqSp.matches ) {
			$('.product_index_line').prev('.product_index_line').css('border-bottom', '1px solid #e6e6e6');
			$('.top_left .top_newsarea').show();
			$('.top_left .top_newsarea').next('.right_more').show();
		} else {
			$('.product_index_line').prev('.product_index_line').css('border-bottom', 'none');
		}
		if ( mqPc.matches ) {
			$('.top_left .top_newsarea').show();
			$('.top_left .top_newsarea').next('.right_more').show();
		}
		if ( mqTablet.matches ) {
			$('.top_left .top_newsarea').hide();
			$('.top_left .top_newsarea').next('.right_more').hide();
			$('.top_left h2.news').removeClass('open');
		}

		$('#sp_menu').hide();
		$('#sp_menubtn').removeClass('open');
	}

	function onBreakpointChange(){
		updateResponsiveClasses();
		applyViewportLayoutState();

		var slider = $('#mainvisual ul').data('bxSlider');
		if ( slider && typeof slider.reloadSlider === 'function' ) {
			slider.reloadSlider();
		}
	}

	var lastViewportWidth = $(window).innerWidth();
	function onWindowResize(){
		var thisWidth = $(window).innerWidth();
		updateResponsiveClasses();
		if ( thisWidth !== lastViewportWidth ) {
			lastViewportWidth = thisWidth;
			applyViewportLayoutState();
		}
	}

	function bindMq(mql){
		if ( mql.addEventListener ) {
			mql.addEventListener('change', onBreakpointChange);
		} else if ( mql.addListener ) {
			mql.addListener(onBreakpointChange);
		}
	}

	bindMq(mqSp);
	bindMq(mqTablet);
	bindMq(mqPc);
	$(window).on('resize orientationchange', onWindowResize);
	if ( window.visualViewport ) {
		window.visualViewport.addEventListener('resize', onWindowResize);
	}

	// ── Fix Google CSE form margin injected at runtime ────────────────────
	function fixGcsemargin() {
		$('.header_right .search form.gsc-search-box, #sp_menu .search form.gsc-search-box').css({ margin: '0', padding: '0' });
	}
	// Run immediately and watch for CSE lazy render
	fixGcsemargin();
	var gceObserver = new MutationObserver(fixGcsemargin);
	var searchEls = document.querySelectorAll('.header_right .search, #sp_menu .search');
	searchEls.forEach(function(searchEl) {
		gceObserver.observe(searchEl, { childList: true, subtree: true });
	});
	// Disconnect after 5s — CSE will have rendered by then
	setTimeout(function(){ gceObserver.disconnect(); }, 5000);

	// ── Hero slider — matches original: fade, auto, 6s pause, 1s speed ──
	$('#mainvisual ul').bxSlider({
		auto:   true,
		pause:  6000,
		speed:  1000,
		mode:   'fade'
	});
	onBreakpointChange();

	// ── Page-top button ───────────────────────────────────────────────────
	var $pageTop = $('#pagetop').hide();

	$(window).on('scroll', function(){
		if ( $(this).scrollTop() > 100 ) {
			$pageTop.fadeIn();
		} else {
			$pageTop.fadeOut();
		}
	});

	$pageTop.on('click', function(){
		$('html, body').animate({ scrollTop: 0 }, 500);
		return false;
	});

	// Match the reference site when the browser restores a non-zero scroll position.
	$(window).trigger('scroll');
	$(window).on('load', function(){
		$(window).trigger('scroll');
	});

	// ── Mobile hamburger menu (#sp_menubtn) ───────────────────────────────
	$('#sp_menubtn').on('click', function(){
		$(this).toggleClass('open');
		$('#sp_menu').toggle();
	});

	// Filter Events & Gallery items by year without changing the page URL.
	var $eventYearLinks = $('#g_menu a[data-filter-year]');
	var $eventAreas     = $('#content_area .content_eventarea');
	var pendingYearKey  = 'arkrayEventsPendingYear';

	function filterEventsByYear(selectedYear){
		selectedYear = String(selectedYear);
		$eventYearLinks.removeClass('ac').removeAttr('aria-current');
		$eventYearLinks.filter(function(){
			return String($(this).data('filter-year')) === selectedYear;
		}).addClass('ac').attr('aria-current', 'true');

		$eventAreas.each(function(){
			$(this).children('.box').each(function(){
				$(this).toggle(String($(this).data('year')) === selectedYear);
			});
		});
	}

	if ( $eventYearLinks.length ) {
		$eventYearLinks.on('click', function(event){
			var selectedYear = String($(this).data('filter-year'));

			if ( ! $eventAreas.length ) {
				try {
					window.sessionStorage.setItem(pendingYearKey, selectedYear);
				} catch (storageError) {}
				return;
			}

			event.preventDefault();
			filterEventsByYear(selectedYear);
		});
	}

	if ( $eventAreas.length ) {
		try {
			var pendingYear = window.sessionStorage.getItem(pendingYearKey);
			window.sessionStorage.removeItem(pendingYearKey);
			if ( pendingYear ) {
				filterEventsByYear(pendingYear);
			}
		} catch (storageError) {}
	}

	// ── Global region gateway modal ────────────────────────────────────────
	// Port of arkray.com's region gateway: shown on first visit, hidden once
	// the visitor closes it or selects a region (cookie-controlled).
	(function(){
		var COOKIE_NAME = 'arkray_gateway';
		var COOKIE_DAYS = 30;

		function setCookie(name, value, days){
			var expires = '';
			if ( days ) {
				var d = new Date();
				d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
				expires = '; expires=' + d.toUTCString();
			}
			document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/';
		}

		function getCookie(name){
			var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'));
			return match ? decodeURIComponent(match[1]) : null;
		}

		var $modal   = $('#modal-content');
		var $modalSp = $('#modal-content_sp');

		// Nothing to do if the markup is absent or the visitor already dismissed.
		if ( ( ! $modal.length && ! $modalSp.length ) || getCookie(COOKIE_NAME) ) {
			return;
		}

		function isSp(){
			return mqTablet.matches;
		}

		function centerModal(){
			var w = $(window).width();
			var h = $(window).height();
			if ( $modal.length ) {
				$modal.css({
					left: Math.max(0, (w - $modal.outerWidth(true)) / 2) + 'px',
					top:  Math.max(0, (h - $modal.outerHeight(true)) / 2) + 'px'
				});
			}
			if ( $modalSp.length ) {
				$modalSp.css({
					top: Math.max(0, (h - $modalSp.outerHeight(true)) / 2) + 'px'
				});
			}
		}

		function dismiss(){
			setCookie(COOKIE_NAME, '1', COOKIE_DAYS);
			$('#modal-content, #modal-content_sp, #modal-overlay').fadeOut('slow', function(){
				if ( this.id === 'modal-overlay' ) {
					$(this).remove();
				}
			});
		}

		function openModal(){
			$('body').append('<div id="modal-overlay"></div>');
			$('#modal-overlay').fadeTo('slow', 0.7);
			centerModal();
			if ( isSp() && $modalSp.length ) {
				$modalSp.fadeIn('slow');
			} else if ( $modal.length ) {
				$modal.fadeIn('slow');
			} else {
				$modalSp.fadeIn('slow');
			}
		}

		// Close buttons dismiss without navigating.
		$('#modal-close, #modal-close-sp').on('click', function(e){
			e.preventDefault();
			dismiss();
		});

		// Current/local site link (Vietnam) closes the modal without leaving.
		$('#modal-content a.current').on('click', function(e){
			e.preventDefault();
			dismiss();
		});

		// Mobile select: navigate for external regions, close for the current site.
		$modalSp.find('#sp_region').on('change', function(){
			var $opt = $('option:selected', this);
			setCookie(COOKIE_NAME, '1', COOKIE_DAYS);
			if ( $opt.attr('current') === 'on' ) {
				$('#modal-content_sp, #modal-overlay').fadeOut('slow', function(){
					if ( this.id === 'modal-overlay' ) {
						$(this).remove();
					}
				});
			} else {
				window.location.href = $(this).val();
			}
		});

		// External desktop region links: persist dismissal before navigating so
		// the modal does not reappear when the visitor returns.
		$('#modal-content a').not('.current').on('click', function(){
			setCookie(COOKIE_NAME, '1', COOKIE_DAYS);
		});

		$(window).on('resize', centerModal);

		openModal();
	})();

	// ── News section accordion on tablet (matches original script.js) ─────
	$('.top_left h2.news').on('click', function(){
		$(this).toggleClass('open');
		$(this).siblings('.top_newsarea').slideToggle(200);
	});

});
