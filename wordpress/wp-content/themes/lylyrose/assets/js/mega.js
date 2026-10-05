/* Mega menu: tap-to-open for touch, since CSS :hover does not fire there.
   Pointer devices keep the CSS hover behaviour untouched — this only claims
   a click when the device reports no hover capability. */
( function () {
	var items = document.querySelectorAll( '.dk-mega' );
	if ( ! items.length ) { return; }

	var canHover = window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;

	items.forEach( function ( item ) {
		var trigger = item.querySelector( ':scope > a' );
		var panel = item.querySelector( '.dk-mega-panel' );
		if ( ! trigger || ! panel ) { return; }

		function setOpen( open ) {
			panel.style.display = open ? 'block' : '';
			trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		if ( ! canHover ) {
			trigger.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var open = trigger.getAttribute( 'aria-expanded' ) !== 'true';
				items.forEach( function ( other ) {
					var otherTrigger = other.querySelector( ':scope > a' );
					var otherPanel = other.querySelector( '.dk-mega-panel' );
					if ( other !== item && otherPanel ) {
						otherPanel.style.display = '';
						otherTrigger.setAttribute( 'aria-expanded', 'false' );
					}
				} );
				setOpen( open );
			} );
		}

		// Escape closes, and focus leaving the item closes too, so the panel
		// cannot be left hanging open with nothing focused inside it.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { setOpen( false ); }
		} );
		item.addEventListener( 'focusout', function ( e ) {
			if ( ! item.contains( e.relatedTarget ) ) { setOpen( false ); }
		} );
	} );

	document.addEventListener( 'click', function ( e ) {
		items.forEach( function ( item ) {
			if ( ! item.contains( e.target ) ) {
				var panel = item.querySelector( '.dk-mega-panel' );
				var trigger = item.querySelector( ':scope > a' );
				if ( panel ) { panel.style.display = ''; }
				trigger.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	} );
} )();
