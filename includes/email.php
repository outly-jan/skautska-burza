<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_kontaktni_email(): string {
	$nastaveny = get_option( 'skaut_burza_kontaktni_email' );
	return $nastaveny ?: get_option( 'admin_email' );
}

function skaut_burza_email_paticka(): string {
	return "\n\n---\n" . sprintf(
		/* translators: %s: kontaktní e-mail střediska */
		__( 'Burza použitých věcí — středisko. Dotazy na provoz burzy: %s', 'skaut-burza' ),
		skaut_burza_kontaktni_email()
	);
}

function skaut_burza_odeslat_vyzvu( int $post_id ): void {
	$post = get_post( $post_id );
	if ( ! $post ) return;

	$autor = get_userdata( (int) $post->post_author );
	if ( ! $autor || ! $autor->user_email ) return;

	$token = get_post_meta( $post_id, '_burza_token', true );
	if ( ! $token ) $token = skaut_burza_novy_token( $post_id );

	$odkaz_potvrdit = add_query_arg( [
		'burza_akce' => 'potvrdit',
		'id'         => $post_id,
		'token'      => $token,
	], home_url( '/' ) );

	$odkaz_ukoncit = add_query_arg( [
		'burza_akce' => 'ukoncit',
		'id'         => $post_id,
		'token'      => $token,
	], home_url( '/' ) );

	$fotky      = get_post_meta( $post_id, '_burza_fotky', true );
	$prvni_foto = is_array( $fotky ) && ! empty( $fotky ) ? (int) $fotky[0] : 0;
	$nahled_url = $prvni_foto ? wp_get_attachment_image_url( $prvni_foto, 'burza_nahled' ) : '';

	$predmet = sprintf(
		/* translators: %s: název inzerátu */
		__( 'Je inzerát „%s“ na burze stále aktuální?', 'skaut-burza' ),
		$post->post_title
	);

	$telo  = sprintf( __( 'Ahoj, na burze máš už nějakou dobu inzerát „%s“.', 'skaut-burza' ), esc_html( $post->post_title ) ) . "\n\n";
	if ( $nahled_url ) {
		$telo .= '<img src="' . esc_url( $nahled_url ) . '" alt=""><br><br>';
	}
	$telo .= __( 'Platí ještě? Stačí kliknout na jednu z voleb:', 'skaut-burza' ) . "\n\n";
	$telo .= '<a href="' . esc_url( $odkaz_potvrdit ) . '">' . esc_html__( 'Ano, stále prodávám', 'skaut-burza' ) . '</a><br>';
	$telo .= '<a href="' . esc_url( $odkaz_ukoncit ) . '">' . esc_html__( 'Už je pryč', 'skaut-burza' ) . '</a><br><br>';
	$telo .= __( 'Pokud nezareaguješ, po třech nezodpovězených výzvách inzerát automaticky přesuneme do archivu.', 'skaut-burza' );
	$telo .= skaut_burza_email_paticka();

	add_filter( 'wp_mail_content_type', 'skaut_burza_html_email' );
	wp_mail( $autor->user_email, $predmet, nl2br( $telo ) );
	remove_filter( 'wp_mail_content_type', 'skaut_burza_html_email' );
}

function skaut_burza_odeslat_info_archivace( int $post_id ): void {
	$post = get_post( $post_id );
	if ( ! $post ) return;

	$autor = get_userdata( (int) $post->post_author );
	if ( ! $autor || ! $autor->user_email ) return;

	$stranka_moje = skaut_burza_stranka_s_shortcode( 'burza_moje' );
	$odkaz        = $stranka_moje ? get_permalink( $stranka_moje ) : home_url( '/' );

	$predmet = sprintf(
		/* translators: %s: název inzerátu */
		__( 'Inzerát „%s“ byl přesunut do archivu', 'skaut-burza' ),
		$post->post_title
	);

	$telo  = sprintf( __( 'Ahoj, tvůj inzerát „%s“ jsme na burze přesunuli do archivu, protože jsme se na třikrát nedočkali odpovědi, jestli ještě platí.', 'skaut-burza' ), esc_html( $post->post_title ) ) . "\n\n";
	$telo .= sprintf(
		/* translators: %s: odkaz na přehled vlastních inzerátů */
		__( 'Pokud věc pořád nabízíš, můžeš ho kdykoli znovu zveřejnit ve svém přehledu inzerátů: %s', 'skaut-burza' ),
		esc_url( $odkaz )
	);
	$telo .= skaut_burza_email_paticka();

	wp_mail( $autor->user_email, $predmet, $telo );
}

function skaut_burza_html_email(): string {
	return 'text/html';
}

/**
 * Frontend endpoint ?burza_akce=potvrdit|ukoncit&id=&token= z e-mailu
 * s výzvou. Token je po použití vždy regenerován, ať nejde odkaz použít
 * podruhé.
 */
function skaut_burza_handle_potvrzovaci_endpoint(): void {
	if ( ! isset( $_GET['burza_akce'] ) ) return;

	$akce = sanitize_key( wp_unslash( $_GET['burza_akce'] ) );
	if ( ! in_array( $akce, [ 'potvrdit', 'ukoncit' ], true ) ) return;

	$post_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

	$post          = get_post( $post_id );
	$ulozeny_token = $post ? get_post_meta( $post_id, '_burza_token', true ) : '';
	$platny        = $post && 'burza_inzerat' === $post->post_type && $token && $ulozeny_token && hash_equals( (string) $ulozeny_token, $token );

	if ( $platny ) {
		if ( 'potvrdit' === $akce ) {
			update_post_meta( $post_id, '_burza_posledni_potvrzeni', time() );
			update_post_meta( $post_id, '_burza_pocet_vyzev', 0 );
			$zprava = __( 'Děkujeme, inzerát jsme označili jako stále aktuální.', 'skaut-burza' );
		} else {
			skaut_burza_archivovat( $post_id );
			$zprava = __( 'Inzerát jsme přesunuli do archivu. Díky za info.', 'skaut-burza' );
		}
		skaut_burza_novy_token( $post_id );
	} else {
		$zprava = __( 'Odkaz už není platný — buď byl použitý dřív, nebo inzerát mezitím zanikl.', 'skaut-burza' );
	}

	skaut_burza_zobraz_potvrzovaci_stranku( $zprava );
	exit;
}

function skaut_burza_zobraz_potvrzovaci_stranku( string $zprava ): void {
	status_header( 200 );
	get_header();
	echo '<div class="skaut-burza skaut-burza-potvrzeni"><p>' . esc_html( $zprava ) . '</p></div>';
	get_footer();
}
