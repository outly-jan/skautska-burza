<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Povolené hodnoty _burza_stav.
 */
function skaut_burza_stavy(): array {
	return [
		'nove'        => __( 'Nové', 'skaut-burza' ),
		'velmi-dobry' => __( 'Velmi dobrý', 'skaut-burza' ),
		'nosene'      => __( 'Nošené', 'skaut-burza' ),
	];
}

function skaut_burza_sanitize_stav( $value ) {
	$stavy = array_keys( skaut_burza_stavy() );
	return in_array( $value, $stavy, true ) ? $value : $stavy[0];
}

function skaut_burza_sanitize_fotky( $value ): array {
	if ( ! is_array( $value ) ) return [];
	$ids = array_map( 'absint', $value );
	$ids = array_filter( $ids );
	return array_slice( array_values( $ids ), 0, 3 );
}

/**
 * auth_callback pro veřejná (nekontaktní) meta pole.
 * show_in_rest je u všech false, takže tohle gatuje jen REST/registrovanou meta
 * autorizaci, ne přímé volání update_post_meta() z vlastních formulářových handlerů.
 */
function skaut_burza_auth_meta( $allowed, $meta_key, $post_id, $user_id, $cap, $caps ) {
	return current_user_can( 'edit_posts' );
}

/**
 * auth_callback pro kontaktní pole — nepřihlášený uživatel nemá nikdy přístup.
 */
function skaut_burza_auth_kontakt( $allowed, $meta_key, $post_id, $user_id, $cap, $caps ) {
	return is_user_logged_in();
}

function skaut_burza_register_meta(): void {
	register_post_meta( 'burza_inzerat', '_burza_velikost', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => 'skaut_burza_auth_meta',
	] );

	register_post_meta( 'burza_inzerat', '_burza_stav', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'skaut_burza_sanitize_stav',
		'auth_callback'     => 'skaut_burza_auth_meta',
	] );

	register_post_meta( 'burza_inzerat', '_burza_cena', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => 'skaut_burza_auth_meta',
	] );

	register_post_meta( 'burza_inzerat', '_burza_telefon', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => 'skaut_burza_auth_kontakt',
	] );

	register_post_meta( 'burza_inzerat', '_burza_email', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_email',
		'auth_callback'     => 'skaut_burza_auth_kontakt',
	] );

	register_post_meta( 'burza_inzerat', '_burza_fotky', [
		'type'              => 'array',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'skaut_burza_sanitize_fotky',
		'auth_callback'     => 'skaut_burza_auth_meta',
	] );

	register_post_meta( 'burza_inzerat', '_burza_posledni_potvrzeni', [
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'absint',
		'auth_callback'     => 'skaut_burza_auth_meta',
	] );

	register_post_meta( 'burza_inzerat', '_burza_pocet_vyzev', [
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'absint',
		'auth_callback'     => 'skaut_burza_auth_meta',
	] );

	register_post_meta( 'burza_inzerat', '_burza_token', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => 'skaut_burza_auth_meta',
	] );
}

/**
 * Nový 32znakový hex token pro odkazy v e-mailu. Volá se při vložení
 * inzerátu a znovu po každém použití odkazu, aby nešel použít podruhé.
 */
function skaut_burza_novy_token( int $post_id ): string {
	$token = bin2hex( random_bytes( 16 ) );
	update_post_meta( $post_id, '_burza_token', $token );
	return $token;
}

/**
 * Autor inzerátu, nebo administrátor. Používá se u všech akcí
 * (editace, mazání, změna stavu), místo spoléhání na WP capabilities,
 * protože rodiče typicky mají jen roli subscriber bez edit_posts.
 */
function skaut_burza_je_autor_nebo_admin( int $post_id ): bool {
	if ( ! is_user_logged_in() ) return false;
	if ( current_user_can( 'manage_options' ) ) return true;
	$post = get_post( $post_id );
	return $post && (int) $post->post_author === get_current_user_id();
}
