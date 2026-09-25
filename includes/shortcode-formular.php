<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_max_inzeratu(): int {
	return (int) get_option( 'skaut_burza_max_inzeratu', 10 );
}

function skaut_burza_pocet_aktivnich_inzeratu( int $user_id ): int {
	$q = new WP_Query( [
		'post_type'      => 'burza_inzerat',
		'author'         => $user_id,
		'post_status'    => [ 'publish', 'burza_rezervovano' ],
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );
	return count( $q->posts );
}

function skaut_burza_prefill_telefon( int $user_id ): string {
	foreach ( [ 'telefon', 'phone', 'billing_phone' ] as $klic ) {
		$hodnota = get_user_meta( $user_id, $klic, true );
		if ( $hodnota ) return $hodnota;
	}
	return '';
}

function skaut_burza_formular_chyba( string $zprava ): void {
	global $skaut_burza_formular_chyby;
	$skaut_burza_formular_chyby[] = $zprava;
}

function skaut_burza_formular_ziskej_chyby(): array {
	global $skaut_burza_formular_chyby;
	return $skaut_burza_formular_chyby ?? [];
}

/**
 * Zpracování POSTu z [burza_formular] — na template_redirect, tedy před
 * jakýmkoli výstupem (stejný vzor jako handle_app_post() ve vlcci-odborky.php).
 */
function skaut_burza_handle_formular_submit(): void {
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) return;
	if ( ! isset( $_POST['skaut_burza_formular_nonce'] ) ) return;

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skaut_burza_formular_nonce'] ) ), 'skaut_burza_formular' ) ) {
		skaut_burza_formular_chyba( __( 'Neplatný bezpečnostní token, zkuste to prosím znovu.', 'skaut-burza' ) );
		return;
	}
	if ( ! is_user_logged_in() ) {
		skaut_burza_formular_chyba( __( 'Pro vložení inzerátu se musíte přihlásit.', 'skaut-burza' ) );
		return;
	}

	$user_id    = get_current_user_id();
	$post_id    = isset( $_POST['burza_post_id'] ) ? absint( $_POST['burza_post_id'] ) : 0;
	$je_editace = $post_id > 0;

	if ( $je_editace ) {
		if ( ! skaut_burza_je_autor_nebo_admin( $post_id ) ) {
			skaut_burza_formular_chyba( __( 'Nemáte oprávnění tento inzerát upravovat.', 'skaut-burza' ) );
			return;
		}
		$existujici = get_post( $post_id );
		if ( ! $existujici || 'burza_inzerat' !== $existujici->post_type ) {
			skaut_burza_formular_chyba( __( 'Inzerát nenalezen.', 'skaut-burza' ) );
			return;
		}
	}

	if ( ! $je_editace ) {
		$posledni = (int) get_user_meta( $user_id, '_skaut_burza_posledni_vlozeni', true );
		if ( $posledni && ( time() - $posledni ) < SKAUT_BURZA_RATE_LIMIT_SEKUND ) {
			skaut_burza_formular_chyba( __( 'Inzeráty lze vkládat jen jednou za chvíli, zkuste to prosím za okamžik znovu.', 'skaut-burza' ) );
			return;
		}

		$max = skaut_burza_max_inzeratu();
		if ( skaut_burza_pocet_aktivnich_inzeratu( $user_id ) >= $max ) {
			skaut_burza_formular_chyba( sprintf(
				/* translators: %d: maximální počet inzerátů */
				__( 'Máte už maximální počet aktivních inzerátů (%d). Než vložíte další, některý ukončete.', 'skaut-burza' ),
				$max
			) );
			return;
		}
	}

	$nazev     = sanitize_text_field( wp_unslash( $_POST['burza_nazev'] ?? '' ) );
	$popis     = sanitize_textarea_field( wp_unslash( $_POST['burza_popis'] ?? '' ) );
	$dodatecne = sanitize_textarea_field( wp_unslash( $_POST['burza_dodatecne_info'] ?? '' ) );
	$kategorie = absint( $_POST['burza_kategorie'] ?? 0 );
	$velikost  = sanitize_text_field( wp_unslash( $_POST['burza_velikost'] ?? '' ) );
	$stav      = skaut_burza_sanitize_stav( wp_unslash( $_POST['burza_stav'] ?? '' ) );
	$cena_typ  = sanitize_key( wp_unslash( $_POST['burza_cena_typ'] ?? 'castka' ) );
	$cena      = trim( sanitize_text_field( wp_unslash( $_POST['burza_cena'] ?? '' ) ) );
	$telefon   = sanitize_text_field( wp_unslash( $_POST['burza_telefon'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['burza_email'] ?? '' ) );
	$souhlas   = ! empty( $_POST['burza_souhlas'] );

	$chyby = [];
	if ( '' === $nazev ) $chyby[] = __( 'Vyplňte název věci.', 'skaut-burza' );
	if ( ! $kategorie || ! term_exists( $kategorie, 'burza_kategorie' ) ) $chyby[] = __( 'Vyberte kategorii.', 'skaut-burza' );
	if ( array_key_exists( $cena_typ, skaut_burza_cena_volby() ) ) {
		// Dohodou / za odvoz — případně vyplněná částka se ignoruje.
		$cena = $cena_typ;
	} elseif ( '' === $cena ) {
		$chyby[] = __( 'Vyplňte cenu v Kč (0 = zdarma), nebo zvolte „Dohodou“ či „Za odvoz“.', 'skaut-burza' );
	} elseif ( ! ctype_digit( $cena ) ) {
		$chyby[] = __( 'Cena musí být celé číslo v Kč, bez dalšího textu (0 = zdarma).', 'skaut-burza' );
	} else {
		$cena = (string) (int) $cena;
	}
	if ( '' === $telefon && '' === $email ) $chyby[] = __( 'Vyplňte telefon nebo e-mail, ať vás mohou zájemci kontaktovat.', 'skaut-burza' );
	if ( '' !== $email && ! is_email( $email ) ) $chyby[] = __( 'E-mail není platný.', 'skaut-burza' );
	if ( ! $souhlas ) $chyby[] = __( 'Je potřeba souhlasit se zveřejněním kontaktu přihlášeným uživatelům webu.', 'skaut-burza' );

	if ( $chyby ) {
		foreach ( $chyby as $c ) skaut_burza_formular_chyba( $c );
		return;
	}

	$post_data = [
		'post_type'    => 'burza_inzerat',
		'post_title'   => $nazev,
		'post_content' => $popis,
	];

	if ( $je_editace ) {
		// post_status se přes formulář nemění — o rezervaci/archivaci se starají
		// akce v [burza_moje], editace obsahu by je neměla tiše rušit.
		$post_data['ID'] = $post_id;
		$vysledek_id     = wp_update_post( $post_data, true );
	} else {
		$post_data['post_status'] = 'publish';
		$post_data['post_author'] = $user_id;
		$vysledek_id              = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $vysledek_id ) || ! $vysledek_id ) {
		skaut_burza_formular_chyba( __( 'Uložení inzerátu selhalo, zkuste to prosím znovu.', 'skaut-burza' ) );
		return;
	}

	wp_set_object_terms( $vysledek_id, [ $kategorie ], 'burza_kategorie', false );

	update_post_meta( $vysledek_id, '_burza_velikost', $velikost );
	update_post_meta( $vysledek_id, '_burza_dodatecne_info', $dodatecne );
	update_post_meta( $vysledek_id, '_burza_stav', $stav );
	update_post_meta( $vysledek_id, '_burza_cena', $cena );
	update_post_meta( $vysledek_id, '_burza_telefon', $telefon );
	update_post_meta( $vysledek_id, '_burza_email', $email );

	if ( ! $je_editace ) {
		update_post_meta( $vysledek_id, '_burza_posledni_potvrzeni', time() );
		update_post_meta( $vysledek_id, '_burza_pocet_vyzev', 0 );
		skaut_burza_novy_token( $vysledek_id );
		update_user_meta( $user_id, '_skaut_burza_posledni_vlozeni', time() );
	}

	$fotky = get_post_meta( $vysledek_id, '_burza_fotky', true );
	if ( ! is_array( $fotky ) ) $fotky = [];

	$odebrat = array_map( 'absint', (array) ( $_POST['burza_odebrat_foto'] ?? [] ) );
	foreach ( $odebrat as $attachment_id ) {
		if ( in_array( $attachment_id, $fotky, true ) ) {
			wp_delete_attachment( $attachment_id, true );
			$fotky = array_values( array_diff( $fotky, [ $attachment_id ] ) );
		}
	}

	$max_fotek  = skaut_burza_max_fotek();
	$foto_chyby = 0;
	if ( ! empty( $_FILES['burza_fotky'] ) && is_array( $_FILES['burza_fotky']['name'] ) ) {
		$pocet = count( $_FILES['burza_fotky']['name'] );
		for ( $i = 0; $i < $pocet; $i++ ) {
			if ( count( $fotky ) >= $max_fotek ) break;
			if ( ( $_FILES['burza_fotky']['error'][ $i ] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE ) continue;

			$jeden = [
				'name'     => $_FILES['burza_fotky']['name'][ $i ],
				'type'     => $_FILES['burza_fotky']['type'][ $i ],
				'tmp_name' => $_FILES['burza_fotky']['tmp_name'][ $i ],
				'error'    => $_FILES['burza_fotky']['error'][ $i ],
				'size'     => $_FILES['burza_fotky']['size'][ $i ],
			];

			$nahrano = skaut_burza_zpracuj_upload( $jeden, $vysledek_id );
			if ( is_wp_error( $nahrano ) ) {
				$foto_chyby++;
				continue;
			}
			$fotky[] = $nahrano;
		}
	}

	update_post_meta( $vysledek_id, '_burza_fotky', array_slice( $fotky, 0, $max_fotek ) );

	$cil = add_query_arg( 'burza_ulozeno', '1', get_permalink( $vysledek_id ) );
	if ( $foto_chyby > 0 ) {
		$cil = add_query_arg( 'burza_foto_chyby', $foto_chyby, $cil );
	}
	wp_safe_redirect( $cil );
	exit;
}

/**
 * Pravidla burzy nad formulářem pro nový inzerát. Čísla se berou
 * z nastavení, ať nápověda vždy odpovídá skutečnému chování.
 */
function skaut_burza_formular_napoveda_html( int $max_fotek ): string {
	$moje_odkaz = '<a href="' . esc_url( skaut_burza_url_stranky( 'moje' ) ) . '">' . esc_html__( 'Moje inzeráty', 'skaut-burza' ) . '</a>';

	$body = [
		sprintf(
			/* translators: 1: celková doba zveřejnění, 2: dny do první výzvy, 3: počet výzev */
			esc_html__( 'Inzerát je zveřejněný %1$s. %2$s po vložení vám přijde e-mail s dotazem, jestli je stále aktuální — stačí kliknout a doba se počítá znovu od začátku. Když na %3$s takové e-maily nezareagujete, inzerát se přesune do archivu.', 'skaut-burza' ),
			esc_html( skaut_burza_dny_text( skaut_burza_celkova_doba_zverejneni() ) ),
			esc_html( skaut_burza_dny_text( skaut_burza_dny_do_prvni_vyzvy() ) ),
			(int) skaut_burza_max_vyzev()
		),
		sprintf(
			/* translators: %s: odkaz na stránku Moje inzeráty */
			esc_html__( 'V přehledu %s vidíte, kolik dní zbývá, a platnost tam můžete kdykoli prodloužit. Najdete tam i úpravu a smazání inzerátu.', 'skaut-burza' ),
			$moje_odkaz
		),
		esc_html__( 'Když se s někým domluvíte, označte věc jako rezervovanou — ve výpisu zůstane se štítkem „Rezervováno“ a rezervaci jde zase zrušit. Po předání ji označte jako prodanou, přesune se do archivu.', 'skaut-burza' ),
		esc_html__( 'Z archivu můžete inzerát kdykoli znovu zveřejnit. Archivované inzeráty se po 6 měsících i s fotkami smažou.', 'skaut-burza' ),
		sprintf(
			/* translators: 1: max. počet fotek, 2: max. velikost souboru v MB */
			esc_html__( 'Fotky: nejvýš %1$d, formát JPG, PNG nebo WEBP. Velké fotky z mobilu se před odesláním automaticky zmenší, velikost souboru tedy řešit nemusíte (limit %2$d MB na fotku platí jen v případě, že by to prohlížeč nezvládl).', 'skaut-burza' ),
			$max_fotek,
			(int) ( skaut_burza_max_velikost_souboru() / ( 1024 * 1024 ) )
		),
		sprintf(
			/* translators: %d: max. počet aktivních inzerátů */
			esc_html__( 'Popis, další fotky a kontakt uvidí jen přihlášení uživatelé webu. Najednou můžete mít nejvýš %d aktivních inzerátů.', 'skaut-burza' ),
			skaut_burza_max_inzeratu()
		),
	];

	return '<div class="skaut-burza-napoveda"><p><strong>' . esc_html__( 'Jak burza funguje', 'skaut-burza' ) . '</strong></p><ul><li>'
		. implode( '</li><li>', $body )
		. '</li></ul></div>';
}

function skaut_burza_shortcode_formular( $atts ): string {
	if ( ! is_user_logged_in() ) {
		return '<p class="skaut-burza-vyzva">' . sprintf(
			/* translators: %s: odkaz na přihlášení */
			esc_html__( 'Pro vložení inzerátu do burzy se musíte %s.', 'skaut-burza' ),
			'<a href="' . esc_url( skaut_burza_prihlaseni_url( add_query_arg( 'burza_panel', 'formular', get_permalink() ) ) ) . '">' . esc_html__( 'přihlásit', 'skaut-burza' ) . '</a>'
		) . '</p>';
	}

	wp_enqueue_style( 'skaut-burza', SKAUT_BURZA_URL . 'assets/css/burza.css', [], SKAUT_BURZA_VERSION );
	wp_enqueue_script( 'skaut-burza-upload', SKAUT_BURZA_URL . 'assets/js/burza-upload.js', [], SKAUT_BURZA_VERSION, true );
	wp_enqueue_script( 'skaut-burza-formular', SKAUT_BURZA_URL . 'assets/js/burza-formular.js', [], SKAUT_BURZA_VERSION, true );
	skaut_burza_enqueue_panely();

	$user_id    = get_current_user_id();
	$post_id    = isset( $_GET['burza_uprava'] ) ? absint( $_GET['burza_uprava'] ) : 0;
	$je_editace = $post_id > 0;
	$existujici = null;

	if ( $je_editace ) {
		if ( ! skaut_burza_je_autor_nebo_admin( $post_id ) ) {
			return '<p class="skaut-burza-chyba">' . esc_html__( 'Nemáte oprávnění tento inzerát upravovat.', 'skaut-burza' ) . '</p>';
		}
		$existujici = get_post( $post_id );
		if ( ! $existujici || 'burza_inzerat' !== $existujici->post_type ) {
			return '<p class="skaut-burza-chyba">' . esc_html__( 'Inzerát nenalezen.', 'skaut-burza' ) . '</p>';
		}
	}

	$po_postu = ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' );
	$hodnota  = static function ( string $klic, string $vychozi = '' ) use ( $po_postu ) {
		if ( $po_postu && isset( $_POST[ $klic ] ) ) {
			return sanitize_text_field( wp_unslash( $_POST[ $klic ] ) );
		}
		return $vychozi;
	};

	if ( $je_editace ) {
		$nazev     = $hodnota( 'burza_nazev', $existujici->post_title );
		$popis     = $po_postu && isset( $_POST['burza_popis'] ) ? sanitize_textarea_field( wp_unslash( $_POST['burza_popis'] ) ) : $existujici->post_content;
		$dodatecne = $po_postu && isset( $_POST['burza_dodatecne_info'] ) ? sanitize_textarea_field( wp_unslash( $_POST['burza_dodatecne_info'] ) ) : (string) get_post_meta( $post_id, '_burza_dodatecne_info', true );
		$velikost  = $hodnota( 'burza_velikost', (string) get_post_meta( $post_id, '_burza_velikost', true ) );
		$stav      = $hodnota( 'burza_stav', (string) get_post_meta( $post_id, '_burza_stav', true ) );
		$ulozena   = (string) get_post_meta( $post_id, '_burza_cena', true );
		$cena_typ  = $hodnota( 'burza_cena_typ', skaut_burza_cena_typ( $ulozena ) );
		// Starší inzeráty mají cenu jako text ("5 Kč") — do číselného pole jen číslice.
		$cena      = $hodnota( 'burza_cena', 'castka' === skaut_burza_cena_typ( $ulozena ) ? preg_replace( '/\D+/', '', $ulozena ) : '' );
		$telefon   = $hodnota( 'burza_telefon', (string) get_post_meta( $post_id, '_burza_telefon', true ) );
		$email     = $hodnota( 'burza_email', (string) get_post_meta( $post_id, '_burza_email', true ) );
		$terms     = wp_get_post_terms( $post_id, 'burza_kategorie', [ 'fields' => 'ids' ] );
		$kategorie = $po_postu && isset( $_POST['burza_kategorie'] ) ? absint( $_POST['burza_kategorie'] ) : (int) ( $terms[0] ?? 0 );
		$fotky     = get_post_meta( $post_id, '_burza_fotky', true );
		if ( ! is_array( $fotky ) ) $fotky = [];
	} else {
		$nazev     = $hodnota( 'burza_nazev' );
		$popis     = $po_postu && isset( $_POST['burza_popis'] ) ? sanitize_textarea_field( wp_unslash( $_POST['burza_popis'] ) ) : '';
		$dodatecne = $po_postu && isset( $_POST['burza_dodatecne_info'] ) ? sanitize_textarea_field( wp_unslash( $_POST['burza_dodatecne_info'] ) ) : '';
		$velikost  = $hodnota( 'burza_velikost' );
		$stav      = $hodnota( 'burza_stav' );
		$cena_typ  = $hodnota( 'burza_cena_typ', 'castka' );
		$cena      = $hodnota( 'burza_cena' );
		$telefon   = $hodnota( 'burza_telefon', skaut_burza_prefill_telefon( $user_id ) );
		$email     = $hodnota( 'burza_email', wp_get_current_user()->user_email );
		$kategorie = $po_postu && isset( $_POST['burza_kategorie'] ) ? absint( $_POST['burza_kategorie'] ) : 0;
		$fotky     = [];
	}

	$max_fotek = skaut_burza_max_fotek();
	$chyby     = skaut_burza_formular_ziskej_chyby();

	ob_start();
	?>
	<div class="skaut-burza skaut-burza-formular" data-skaut-burza-panel="formular"<?php echo ( $po_postu || $je_editace || isset( $_GET['burza_ulozeno'] ) ) ? ' data-skaut-burza-aktivni' : ''; ?>>
		<?php if ( isset( $_GET['burza_ulozeno'] ) ) : ?>
			<p class="skaut-burza-ok"><?php esc_html_e( 'Inzerát byl uložen.', 'skaut-burza' ); ?></p>
		<?php endif; ?>

		<?php if ( $chyby ) : ?>
			<ul class="skaut-burza-chyby">
				<?php foreach ( $chyby as $c ) : ?>
					<li><?php echo esc_html( $c ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! $je_editace ) echo skaut_burza_formular_napoveda_html( $max_fotek ); ?>

		<form method="post" enctype="multipart/form-data" class="skaut-burza-form">
			<?php wp_nonce_field( 'skaut_burza_formular', 'skaut_burza_formular_nonce' ); ?>
			<input type="hidden" name="burza_post_id" value="<?php echo esc_attr( $post_id ); ?>">

			<p>
				<label for="burza_nazev"><?php esc_html_e( 'Název věci', 'skaut-burza' ); ?></label>
				<input type="text" id="burza_nazev" name="burza_nazev" value="<?php echo esc_attr( $nazev ); ?>" required>
			</p>

			<p>
				<label for="burza_kategorie"><?php esc_html_e( 'Kategorie', 'skaut-burza' ); ?></label>
				<?php
				wp_dropdown_categories( [
					'taxonomy'         => 'burza_kategorie',
					'hide_empty'       => false,
					'hierarchical'     => true,
					'name'             => 'burza_kategorie',
					'id'               => 'burza_kategorie',
					'selected'         => $kategorie,
					'show_option_none' => __( '— vyberte kategorii —', 'skaut-burza' ),
					'option_none_value' => '0',
				] );
				?>
			</p>

			<p>
				<label for="burza_popis"><?php esc_html_e( 'Popis', 'skaut-burza' ); ?></label>
				<textarea id="burza_popis" name="burza_popis" rows="5"><?php echo esc_textarea( $popis ); ?></textarea>
			</p>

			<p>
				<label for="burza_velikost"><?php esc_html_e( 'Velikost', 'skaut-burza' ); ?></label>
				<input type="text" id="burza_velikost" name="burza_velikost" value="<?php echo esc_attr( $velikost ); ?>" placeholder="<?php esc_attr_e( 'např. 128, M, 42…', 'skaut-burza' ); ?>">
			</p>

			<p>
				<label for="burza_stav"><?php esc_html_e( 'Stav', 'skaut-burza' ); ?></label>
				<select id="burza_stav" name="burza_stav">
					<?php foreach ( skaut_burza_stavy() as $klic => $popisek ) : ?>
						<option value="<?php echo esc_attr( $klic ); ?>" <?php selected( $stav, $klic ); ?>><?php echo esc_html( $popisek ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<span class="skaut-burza-label"><?php esc_html_e( 'Cena', 'skaut-burza' ); ?></span>
				<span class="skaut-burza-cena-volby">
					<label class="skaut-burza-cena-volba">
						<input type="radio" name="burza_cena_typ" value="castka" <?php checked( ! array_key_exists( $cena_typ, skaut_burza_cena_volby() ) ); ?>>
						<span class="skaut-burza-cena-pole">
							<input type="number" id="burza_cena" name="burza_cena" value="<?php echo esc_attr( $cena ); ?>" min="0" max="1000000" step="1" inputmode="numeric" placeholder="<?php esc_attr_e( 'celé číslo, 0 = zdarma', 'skaut-burza' ); ?>" aria-label="<?php esc_attr_e( 'Cena v Kč', 'skaut-burza' ); ?>">
							<span class="skaut-burza-cena-mena">Kč</span>
						</span>
					</label>
					<?php foreach ( skaut_burza_cena_volby() as $klic => $popisek ) : ?>
						<label class="skaut-burza-cena-volba">
							<input type="radio" name="burza_cena_typ" value="<?php echo esc_attr( $klic ); ?>" <?php checked( $cena_typ, $klic ); ?>>
							<?php echo esc_html( $popisek ); ?>
						</label>
					<?php endforeach; ?>
				</span>
			</p>

			<p>
				<label for="burza_dodatecne_info"><?php esc_html_e( 'Dodatečné informace (nepovinné)', 'skaut-burza' ); ?></label>
				<textarea id="burza_dodatecne_info" name="burza_dodatecne_info" rows="3" placeholder="<?php esc_attr_e( 'např. kde a kdy je možné věc vyzvednout, možnost poslat poštou…', 'skaut-burza' ); ?>"><?php echo esc_textarea( $dodatecne ); ?></textarea>
			</p>

			<p>
				<label for="burza_telefon"><?php esc_html_e( 'Telefon', 'skaut-burza' ); ?></label>
				<input type="tel" id="burza_telefon" name="burza_telefon" value="<?php echo esc_attr( $telefon ); ?>">
			</p>

			<p>
				<label for="burza_email"><?php esc_html_e( 'E-mail', 'skaut-burza' ); ?></label>
				<input type="email" id="burza_email" name="burza_email" value="<?php echo esc_attr( $email ); ?>">
			</p>

			<?php if ( $je_editace && $fotky ) : ?>
				<p><?php esc_html_e( 'Stávající fotky', 'skaut-burza' ); ?></p>
				<ul class="skaut-burza-fotky-stavajici">
					<?php foreach ( $fotky as $attachment_id ) : ?>
						<li>
							<?php echo wp_get_attachment_image( $attachment_id, 'burza_nahled' ); ?>
							<label>
								<input type="checkbox" name="burza_odebrat_foto[]" value="<?php echo esc_attr( $attachment_id ); ?>">
								<?php esc_html_e( 'odebrat', 'skaut-burza' ); ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p>
				<label for="burza_fotky"><?php echo esc_html( sprintf(
					/* translators: %d: maximální počet fotek */
					__( 'Fotky (max. %d, JPG/PNG/WEBP)', 'skaut-burza' ),
					$max_fotek
				) ); ?></label>
				<input type="file" id="burza_fotky" name="burza_fotky[]" accept="image/jpeg,image/png,image/webp" multiple data-max-fotek="<?php echo esc_attr( $max_fotek ); ?>" data-jiz-fotek="<?php echo esc_attr( $je_editace ? count( $fotky ) : 0 ); ?>">
				<span class="skaut-burza-fotky-info"></span>
			</p>

			<p>
				<label>
					<input type="checkbox" name="burza_souhlas" value="1" required>
					<?php esc_html_e( 'Souhlasím se zveřejněním telefonu a e-mailu přihlášeným uživatelům webu.', 'skaut-burza' ); ?>
				</label>
			</p>

			<p>
				<button type="submit"><?php echo $je_editace ? esc_html__( 'Uložit změny', 'skaut-burza' ) : esc_html__( 'Vložit inzerát', 'skaut-burza' ); ?></button>
			</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
