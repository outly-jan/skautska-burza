( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var kontejnery = document.querySelectorAll( '.skaut-burza-kontakt-container[data-post-id]' );
		if ( ! kontejnery.length || typeof skautBurzaKontakt === 'undefined' ) return;

		kontejnery.forEach( function ( kontejner ) {
			var postId = kontejner.getAttribute( 'data-post-id' );
			var data = new URLSearchParams();
			data.set( 'action', 'skaut_burza_kontakt' );
			data.set( 'post_id', postId );
			data.set( 'nonce', skautBurzaKontakt.nonce );

			fetch( skautBurzaKontakt.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: data.toString(),
			} )
				.then( function ( odpoved ) { return odpoved.json(); } )
				.then( function ( vysledek ) {
					if ( vysledek && vysledek.success && vysledek.data && vysledek.data.html ) {
						kontejner.innerHTML = vysledek.data.html;
					} else {
						kontejner.innerHTML = '<p class="skaut-burza-chyba">' + skautBurzaKontakt.chyba + '</p>';
					}
				} )
				.catch( function () {
					kontejner.innerHTML = '<p class="skaut-burza-chyba">' + skautBurzaKontakt.chyba + '</p>';
				} );
		} );
	} );
} )();
