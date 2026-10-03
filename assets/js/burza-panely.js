( function () {
	'use strict';

	/**
	 * Burza běží na jedné stránce se shortcody v panelech (Elementor Panely /
	 * Tabs apod.). Podle URL otevře panel s požadovaným shortcodem:
	 * ?burza_panel=vypis|formular|moje|nalezy|nalez_formular, ?burza_uprava=ID
	 * → formulář, ?burza_nalez_uprava=ID → formulář nálezu, jinak
	 * panel, který server označil data-skaut-burza-aktivni (např. formulář
	 * s chybami po odeslání). Bez panelů jen posune stránku k shortcodu.
	 */
	var INTERVAL_MS = 250;
	var MAX_POKUSU = 40; // ~10 s
	var STABILNI_KONTROLY = 4; // panel musí zůstat otevřený ~1 s

	function najdiCil() {
		var parametry = new URLSearchParams( window.location.search );
		var panel = parametry.get( 'burza_panel' );
		if ( ! panel && parametry.has( 'burza_uprava' ) ) panel = 'formular';
		if ( ! panel && parametry.has( 'burza_nalez_uprava' ) ) panel = 'nalez_formular';

		if ( panel ) {
			return document.querySelector( '.skaut-burza[data-skaut-burza-panel="' + panel.replace( /[^a-z_]/g, '' ) + '"]' );
		}
		return document.querySelector( '.skaut-burza[data-skaut-burza-aktivni]' );
	}

	function jeViditelny( el ) {
		return el.getClientRects().length > 0;
	}

	/**
	 * Titulek panelu, který obsahuje shortcode. Elementor může mít titulek
	 * zvlášť pro desktop a mobil — přednost má viditelný.
	 */
	function najdiTitulek( tabpanel ) {
		var titulky = document.querySelectorAll( '[aria-controls="' + tabpanel.id + '"]' );
		for ( var i = 0; i < titulky.length; i++ ) {
			if ( jeViditelny( titulky[ i ] ) ) return titulky[ i ];
		}
		return titulky[ 0 ] || null;
	}

	function posun( cil ) {
		cil.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function spust() {
		var cil = najdiCil();
		if ( ! cil ) return;

		var detaily = cil.closest( 'details' );
		if ( detaily ) detaily.open = true;

		var tabpanel = cil.closest( '[role="tabpanel"]' );
		if ( ! tabpanel || ! tabpanel.id ) {
			posun( cil );
			return;
		}

		// Elementor si obsluhu panelů načítá líně a při inicializaci aktivuje
		// první panel — jednorázové kliknutí by přišlo moc brzy nebo by ho
		// přebil. Proto opakovat, dokud panel s cílem nezůstane otevřený.
		// Jakmile uživatel sám klikne nebo píše, přestat (nebojovat s ním).
		var pokusy = 0;
		var stabilni = 0;
		var zastaveno = false;

		function zastav() {
			zastaveno = true;
			document.removeEventListener( 'pointerdown', uzivatel, true );
			document.removeEventListener( 'keydown', uzivatel, true );
		}

		function uzivatel( e ) {
			if ( e.isTrusted ) zastav();
		}

		document.addEventListener( 'pointerdown', uzivatel, true );
		document.addEventListener( 'keydown', uzivatel, true );

		( function kontrola() {
			if ( zastaveno ) return;

			if ( jeViditelny( cil ) ) {
				stabilni++;
				if ( stabilni === 1 ) posun( cil );
				if ( stabilni >= STABILNI_KONTROLY ) {
					zastav();
					return;
				}
			} else {
				stabilni = 0;
				var titulek = najdiTitulek( tabpanel );
				if ( titulek ) titulek.click();
			}

			if ( ++pokusy >= MAX_POKUSU ) {
				zastav();
				return;
			}
			window.setTimeout( kontrola, INTERVAL_MS );
		} )();
	}

	// Návrat tlačítkem Zpět může stránku obnovit z paměti prohlížeče (bfcache)
	// i s formulářem vyplněným údaji předchozího inzerátu/nálezu — načíst znovu.
	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted && document.querySelector( '.skaut-burza form' ) ) {
			window.location.reload();
		}
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', spust );
	} else {
		spust();
	}
} )();
