/**
 * Front-end enhancements. Without JavaScript the site stays fully usable.
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------
	 * Tabs: stacked <section>s with headings become an ARIA tablist with
	 * arrow-key navigation. Found by class, since core/group keeps no data
	 * attributes.
	 * ------------------------------------------------------------------ */
	function initTabs( root ) {
		var panels = Array.prototype.filter.call( root.children, function ( child ) {
			return child.tagName === 'SECTION';
		} );

		if ( panels.length < 2 ) {
			return;
		}

		var list = document.createElement( 'ul' );
		list.className = 'nabu-tabs__list';
		list.setAttribute( 'role', 'tablist' );

		var tabs = [];

		panels.forEach( function ( panel, index ) {
			var heading = panel.querySelector( 'h2, h3' );
			if ( ! heading ) {
				return;
			}

			var id = panel.id || 'nabu-tab-panel-' + index + '-' + Math.random().toString( 36 ).slice( 2, 7 );
			panel.id = id;

			var tab = document.createElement( 'button' );
			tab.type = 'button';
			tab.className = 'nabu-tabs__tab';
			tab.id = id + '-tab';
			tab.textContent = heading.textContent;
			tab.setAttribute( 'role', 'tab' );
			tab.setAttribute( 'aria-controls', id );
			tab.setAttribute( 'aria-selected', index === 0 ? 'true' : 'false' );
			tab.tabIndex = index === 0 ? 0 : -1;

			var li = document.createElement( 'li' );
			li.setAttribute( 'role', 'presentation' );
			li.appendChild( tab );
			list.appendChild( li );

			heading.hidden = true;
			panel.classList.add( 'nabu-tabs__panel' );
			panel.setAttribute( 'role', 'tabpanel' );
			panel.setAttribute( 'aria-labelledby', tab.id );
			panel.tabIndex = 0;
			panel.hidden = index !== 0;

			tabs.push( { tab: tab, panel: panel } );
		} );

		if ( tabs.length < 2 ) {
			return;
		}

		root.insertBefore( list, root.firstChild );
		root.classList.add( 'nabu-tabs--ready' );

		function select( index, focus ) {
			tabs.forEach( function ( entry, i ) {
				var active = i === index;
				entry.tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				entry.tab.tabIndex = active ? 0 : -1;
				entry.panel.hidden = ! active;
			} );
			if ( focus ) {
				tabs[ index ].tab.focus();
			}
		}

		tabs.forEach( function ( entry, index ) {
			entry.tab.addEventListener( 'click', function () {
				select( index, false );
			} );

			entry.tab.addEventListener( 'keydown', function ( event ) {
				var next = null;
				if ( event.key === 'ArrowRight' ) {
					next = ( index + 1 ) % tabs.length;
				} else if ( event.key === 'ArrowLeft' ) {
					next = ( index - 1 + tabs.length ) % tabs.length;
				} else if ( event.key === 'Home' ) {
					next = 0;
				} else if ( event.key === 'End' ) {
					next = tabs.length - 1;
				}
				if ( next !== null ) {
					event.preventDefault();
					select( next, true );
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Donate bar: dismissible, and the choice is remembered.
	 * ------------------------------------------------------------------ */
	function initDonateBar( bar ) {
		var key = 'nabu-donatebar-dismissed';
		var stored = null;

		try {
			stored = window.localStorage.getItem( key );
		} catch ( e ) {
			stored = null;
		}

		if ( stored === '1' ) {
			bar.remove();
			return;
		}

		bar.hidden = false;

		var close = bar.querySelector( '.nabu-donatebar__close' );
		if ( ! close ) {
			return;
		}

		close.addEventListener( 'click', function () {
			bar.remove();
			try {
				window.localStorage.setItem( key, '1' );
			} catch ( e ) {
				// Not stored: the bar comes back on the next visit.
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Mobile menu: on narrow screens the sidebar becomes the menu, shown as
	 * an overlay by a toggle labelled "Menü" in the blue bar, and the main
	 * navigation steps back — the sidebar's navigation box carries the same
	 * items, plus the open branch. Nothing moves in the DOM.
	 * ------------------------------------------------------------------ */
	function initMobileMenu() {
		var sidebar = document.querySelector( '.nabu-sidebar' );
		var bar = document.querySelector( '.nabu-navbar__inner' );

		if ( ! sidebar || ! bar ) {
			return;
		}

		if ( ! sidebar.id ) {
			sidebar.id = 'nabu-menu';
		}

		var toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'nabu-menu-toggle';
		toggle.setAttribute( 'aria-expanded', 'false' );
		toggle.setAttribute( 'aria-controls', sidebar.id );
		toggle.innerHTML = '<span class="nabu-menu-toggle__icon" aria-hidden="true"></span><span class="nabu-menu-toggle__text">Menü</span>';
		bar.appendChild( toggle );

		var label = toggle.querySelector( '.nabu-menu-toggle__text' );

		/*
		 * The overlay starts below the header, so the toggle stays in view
		 * and closes it at the same spot. Where the bar ends and how far the
		 * logo tab hangs below it only the browser knows.
		 */
		function measure() {
			var barBox = bar.getBoundingClientRect();
			var logo = document.querySelector( '.nabu-logo-tab' );
			var overhang = logo
				? Math.max( 0, logo.getBoundingClientRect().bottom - barBox.bottom )
				: 0;

			document.documentElement.style.setProperty( '--nabu-menu-top', barBox.bottom + 'px' );
			document.documentElement.style.setProperty( '--nabu-menu-overhang', overhang + 'px' );
		}

		measure();
		window.addEventListener( 'resize', measure );

		// Only now may the stylesheet hide the main navigation.
		document.documentElement.classList.add( 'nabu-has-menu' );

		var open = false;

		function focusable() {
			return Array.prototype.filter.call(
				sidebar.querySelectorAll( 'a[href], button:not([disabled]), input, select, textarea' ),
				function ( el ) {
					return el.offsetParent !== null;
				}
			);
		}

		function setOpen( state ) {
			open = state;
			toggle.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
			document.documentElement.classList.toggle( 'nabu-menu-open', state );

			if ( label ) {
				label.textContent = state ? 'Schließen' : 'Menü';
			}

			if ( state ) {
				measure();
				var first = focusable()[ 0 ];
				if ( first ) {
					first.focus();
				}
			} else {
				toggle.focus();
			}
		}

		toggle.addEventListener( 'click', function () {
			setOpen( ! open );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! open ) {
				return;
			}

			if ( event.key === 'Escape' ) {
				setOpen( false );
				return;
			}

			if ( event.key !== 'Tab' ) {
				return;
			}

			// The toggle is part of the chain: it stays visible and is the
			// way out. Everything else behind the overlay is not.
			var chain = [ toggle ].concat( focusable() );

			var first = chain[ 0 ];
			var last = chain[ chain.length - 1 ];

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );

		// A click on the area next to the menu closes it.
		sidebar.addEventListener( 'click', function ( event ) {
			if ( event.target === sidebar ) {
				setOpen( false );
			}
		} );

		// Once the window is wide enough, the column is a column again.
		var mql = window.matchMedia ? window.matchMedia( '(min-width: 782px)' ) : null;

		if ( mql && typeof mql.addEventListener === 'function' ) {
			mql.addEventListener( 'change', function ( mql ) {
				if ( mql.matches && open ) {
					setOpen( false );
				}
			} );
		}
	}

	function init() {
		document.querySelectorAll( '.nabu-tabs' ).forEach( initTabs );
		document.querySelectorAll( '[data-nabu-donatebar]' ).forEach( initDonateBar );
		initMobileMenu();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
