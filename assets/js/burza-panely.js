( function () {
	'use strict';

	/**
	 * Burza běží na jedné stránce se třemi shortcody v panelech (Elementor
	 * Panely / Tabs apod.). Podle URL otevře panel s požadovaným shortcodem:
	 * ?burza_panel=vypis|formular|moje, ?burza_uprava=ID → formulář, jinak
	 * panel, který server označil data-skaut-burza-aktivni (např. formulář
	 * s chybami po odeslání). Bez panelů jen posune stránku k shortcodu.
	 */
	function najdiCil() {
		var parametry = new URLSearchParams( window.location.search );
		var panel = parametry.get( 'burza_panel' );
		if ( ! panel && parametry.has( 'burza_uprava' ) ) panel = 'formular';

		if ( panel ) {
			return document.querySelector( '.skaut-burza[data-skaut-burza-panel="' + panel.replace( /[^a-z]/g, '' ) + '"]' );
		}
		return document.querySelector( '.skaut-burza[data-skaut-burza-aktivni]' );
	}

	function otevriPanel( cil ) {
		var tabpanel = cil.closest( '[role="tabpanel"]' );
		if ( tabpanel && tabpanel.id ) {
			var titulky = document.querySelectorAll( '[aria-controls="' + tabpanel.id + '"]' );
			for ( var i = 0; i < titulky.length; i++ ) {
				// Elementor má titulek panelu zvlášť pro desktop a mobil — kliknout na viditelný.
				if ( titulky[ i ].offsetParent !== null ) {
					if ( titulky[ i ].getAttribute( 'aria-selected' ) !== 'true' ) titulky[ i ].click();
					break;
				}
			}
		}

		var detaily = cil.closest( 'details' );
		if ( detaily ) detaily.open = true;
	}

	function spust() {
		var cil = najdiCil();
		if ( ! cil ) return;

		otevriPanel( cil );
		window.setTimeout( function () {
			cil.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}, 150 );
	}

	// Elementor inicializuje panely až po načtení svých skriptů.
	if ( document.readyState === 'complete' ) {
		spust();
	} else {
		window.addEventListener( 'load', spust );
	}
} )();
