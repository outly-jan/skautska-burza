( function () {
	'use strict';

	var MAX_STRANA = 1600;
	var JPEG_KVALITA = 0.8;

	/**
	 * Přečte EXIF orientaci (1–8) z JPEG souboru, nebo 1 (bez rotace),
	 * pokud EXIF chybí nebo nejde o JPEG.
	 */
	function zjistiOrientaci( soubor ) {
		return soubor.slice( 0, 65536 ).arrayBuffer().then( function ( buffer ) {
			var view = new DataView( buffer );
			if ( view.byteLength < 2 || view.getUint16( 0 ) !== 0xffd8 ) return 1;

			var offset = 2;
			while ( offset < view.byteLength - 1 ) {
				var marker = view.getUint16( offset );
				offset += 2;

				if ( marker === 0xffe1 ) {
					if ( view.getUint32( offset + 2 ) !== 0x45786966 ) return 1;

					var tiffOffset = offset + 8;
					var little = view.getUint16( tiffOffset ) === 0x4949;
					var ifdOffset = view.getUint32( tiffOffset + 4, little );
					var tagsCount = view.getUint16( tiffOffset + ifdOffset, little );

					for ( var i = 0; i < tagsCount; i++ ) {
						var tagOffset = tiffOffset + ifdOffset + 2 + i * 12;
						if ( view.getUint16( tagOffset, little ) === 0x0112 ) {
							return view.getUint16( tagOffset + 8, little );
						}
					}
					return 1;
				} else if ( ( marker & 0xff00 ) !== 0xff00 ) {
					break;
				} else {
					offset += view.getUint16( offset );
				}
			}
			return 1;
		} ).catch( function () {
			return 1;
		} );
	}

	function nacistObrazek( soubor ) {
		return new Promise( function ( resolve, reject ) {
			var img = new Image();
			var url = URL.createObjectURL( soubor );
			img.onload = function () {
				URL.revokeObjectURL( url );
				resolve( img );
			};
			img.onerror = function () {
				URL.revokeObjectURL( url );
				reject( new Error( 'obrazek' ) );
			};
			img.src = url;
		} );
	}

	/**
	 * Zmenší delší stranu na MAX_STRANA, narovná podle EXIF orientace
	 * a vrátí nový JPEG soubor v kvalitě 0.8.
	 */
	function zmensiSoubor( soubor ) {
		return Promise.all( [ nacistObrazek( soubor ), zjistiOrientaci( soubor ) ] ).then( function ( vysledky ) {
			var img = vysledky[ 0 ];
			var orientace = vysledky[ 1 ];

			var sirka = img.naturalWidth;
			var vyska = img.naturalHeight;
			var pomer = Math.min( 1, MAX_STRANA / Math.max( sirka, vyska ) );
			var cilSirka = Math.round( sirka * pomer );
			var cilVyska = Math.round( vyska * pomer );

			var otocenoODevadesat = orientace >= 5 && orientace <= 8;
			var canvas = document.createElement( 'canvas' );
			canvas.width = otocenoODevadesat ? cilVyska : cilSirka;
			canvas.height = otocenoODevadesat ? cilSirka : cilVyska;

			var ctx = canvas.getContext( '2d' );
			switch ( orientace ) {
				case 2: ctx.transform( -1, 0, 0, 1, cilSirka, 0 ); break;
				case 3: ctx.transform( -1, 0, 0, -1, cilSirka, cilVyska ); break;
				case 4: ctx.transform( 1, 0, 0, -1, 0, cilVyska ); break;
				case 5: ctx.transform( 0, 1, 1, 0, 0, 0 ); break;
				case 6: ctx.transform( 0, 1, -1, 0, cilVyska, 0 ); break;
				case 7: ctx.transform( 0, -1, -1, 0, cilVyska, cilSirka ); break;
				case 8: ctx.transform( 0, -1, 1, 0, 0, cilSirka ); break;
				default: break;
			}
			ctx.drawImage( img, 0, 0, cilSirka, cilVyska );

			return new Promise( function ( resolve ) {
				canvas.toBlob( function ( blob ) {
					if ( ! blob ) {
						resolve( soubor );
						return;
					}
					var novyNazev = soubor.name.replace( /\.[^.]+$/, '' ) + '.jpg';
					resolve( new File( [ blob ], novyNazev, { type: 'image/jpeg' } ) );
				}, 'image/jpeg', JPEG_KVALITA );
			} );
		} ).catch( function () {
			return soubor;
		} );
	}

	function zpracujInput( input ) {
		if ( ! input.files || ! input.files.length ) return;

		var maxFotek = parseInt( input.dataset.maxFotek || '3', 10 );
		var jizFotek = parseInt( input.dataset.jizFotek || '0', 10 );
		var volno = Math.max( 0, maxFotek - jizFotek );
		var vybrane = Array.prototype.slice.call( input.files, 0, volno );

		var info = input.parentElement.querySelector( '.skaut-burza-fotky-info' );
		var tlacitko = input.closest( 'form' ).querySelector( 'button[type="submit"]' );

		if ( input.files.length > volno ) {
			if ( info ) info.textContent = 'Vybráno bude jen prvních ' + volno + ' fotek (limit ' + maxFotek + ').';
		} else if ( info ) {
			info.textContent = '';
		}

		if ( tlacitko ) tlacitko.disabled = true;
		if ( info ) info.textContent += ' Zmenšuji fotky…';

		Promise.all( vybrane.map( zmensiSoubor ) ).then( function ( zmensene ) {
			var data = new DataTransfer();
			zmensene.forEach( function ( soubor ) {
				data.items.add( soubor );
			} );
			input.files = data.files;
			if ( info ) info.textContent = zmensene.length + ' fotka(y) připravena(y) k odeslání.';
			if ( tlacitko ) tlacitko.disabled = false;
		} ).catch( function () {
			if ( tlacitko ) tlacitko.disabled = false;
		} );
	}

	document.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.matches( 'input[type="file"][name="burza_fotky[]"]' ) ) {
			zpracujInput( e.target );
		}
	} );
} )();
