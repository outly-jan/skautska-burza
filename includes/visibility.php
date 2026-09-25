<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Kontrola pro zobrazení kontaktů a dalších neveřejných údajů ke konkrétnímu
 * inzerátu: přihlášený uživatel, a pokud je inzerát v archivu, tak navíc
 * jen jeho autor nebo admin (archiv jinak není přes WP_Query nikomu jinému
 * ani dohledatelný, ale AJAX endpoint bere post_id přímo, takže se to musí
 * ověřit tady zvlášť).
 */
function skaut_burza_smi_videt_kontakt( int $post_id ): bool {
	if ( ! is_user_logged_in() ) return false;

	$post = get_post( $post_id );
	if ( ! $post || 'burza_inzerat' !== $post->post_type ) return false;

	if ( in_array( $post->post_status, [ 'publish', 'burza_rezervovano' ], true ) ) {
		return true;
	}

	if ( 'burza_archiv' === $post->post_status ) {
		return skaut_burza_je_autor_nebo_admin( $post_id );
	}

	return false;
}

/**
 * AJAX endpoint pro blok s popisem, dalšími fotkami a kontakty. Načítá se
 * až po vykreslení stránky, ať zacachovaná stránka nikdy neukáže kontakt
 * nepřihlášenému — viz poznámka o cache v README.
 */
function skaut_burza_ajax_kontakt(): void {
	check_ajax_referer( 'skaut_burza_kontakt', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

	if ( ! is_user_logged_in() ) {
		wp_send_json_success( [ 'html' => skaut_burza_prihlaseni_vyzva_html( $post_id ) ] );
	}

	if ( ! $post_id || ! skaut_burza_smi_videt_kontakt( $post_id ) ) {
		wp_send_json_error( [ 'html' => esc_html__( 'Inzerát nenalezen.', 'skaut-burza' ) ], 404 );
	}

	wp_send_json_success( [ 'html' => skaut_burza_gated_html( get_post( $post_id ) ) ] );
}

/**
 * Vyloučení z RSS feedů — popis inzerátu je vyhrazený přihlášeným, feed
 * ale nemá koncept přihlášení, takže nejjednodušší a nejjistější je burzu
 * z feedů úplně vynechat (na kontakty v postmeta by feed nesáhl nikdy,
 * ty se nikde neserializují, ale popis v post_content ano).
 */
function skaut_burza_vyloucit_z_feedu( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_feed() ) return;

	$post_type = $query->get( 'post_type' );
	if ( 'burza_inzerat' === $post_type || ( is_array( $post_type ) && in_array( 'burza_inzerat', $post_type, true ) ) ) {
		$query->set( 'post_type', 'skaut_burza_zadny_typ' );
	}
}

/**
 * Vyloučení z WP core XML sitemap (wp-sitemap.xml).
 */
function skaut_burza_vyloucit_ze_sitemapy( array $post_types ): array {
	unset( $post_types['burza_inzerat'] );
	return $post_types;
}
