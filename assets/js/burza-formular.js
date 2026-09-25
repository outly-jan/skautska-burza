( function () {
	'use strict';

	/**
	 * Přepínač ceny: pole s částkou je povinné jen při volbě částky; při
	 * „Dohodou“ / „Za odvoz“ je zašedlé a server ho ignoruje. Pole není
	 * disabled, aby šlo kliknutím do něj rovnou přepnout zpět na částku.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		var castka = document.getElementById( 'burza_cena' );
		var volby = document.querySelectorAll( 'input[name="burza_cena_typ"]' );
		if ( ! castka || ! volby.length ) return;

		function aktualizuj() {
			var zvoleno = document.querySelector( 'input[name="burza_cena_typ"]:checked' );
			var jeCastka = ! zvoleno || zvoleno.value === 'castka';
			castka.required = jeCastka;
			castka.classList.toggle( 'skaut-burza-neaktivni', ! jeCastka );
		}

		volby.forEach( function ( volba ) {
			volba.addEventListener( 'change', function () {
				aktualizuj();
				if ( volba.checked && volba.value === 'castka' ) castka.focus();
			} );
		} );

		// Kliknutí do pole s částkou zvolí rovnou i přepínač „částka“.
		castka.addEventListener( 'focus', function () {
			var radio = document.querySelector( 'input[name="burza_cena_typ"][value="castka"]' );
			if ( radio && ! radio.checked ) {
				radio.checked = true;
				aktualizuj();
			}
		} );

		aktualizuj();
	} );
} )();
