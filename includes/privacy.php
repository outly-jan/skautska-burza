<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_register_privacy_exporter( array $exportery ): array {
	$exportery['skaut-burza'] = [
		'exporter_friendly_name' => __( 'Skautská burza', 'skaut-burza' ),
		'callback'               => 'skaut_burza_privacy_exporter',
	];
	return $exportery;
}

function skaut_burza_register_privacy_eraser( array $mazace ): array {
	$mazace['skaut-burza'] = [
		'eraser_friendly_name' => __( 'Skautská burza', 'skaut-burza' ),
		'callback'             => 'skaut_burza_privacy_eraser',
	];
	return $mazace;
}

function skaut_burza_privacy_uzivatelovy_inzeraty( int $user_id, int $stranka, int $na_stranku ): WP_Query {
	return new WP_Query( [
		'post_type'      => 'burza_inzerat',
		'author'         => $user_id,
		'post_status'    => [ 'publish', 'burza_rezervovano', 'burza_archiv' ],
		'posts_per_page' => $na_stranku,
		'paged'          => $stranka,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	] );
}

function skaut_burza_privacy_exporter( string $email_address, int $stranka = 1 ): array {
	$stranka    = max( 1, $stranka );
	$na_stranku = 20;

	$user = get_user_by( 'email', $email_address );
	if ( ! $user ) {
		return [ 'data' => [], 'done' => true ];
	}

	$dotaz         = skaut_burza_privacy_uzivatelovy_inzeraty( $user->ID, $stranka, $na_stranku );
	$export_polozky = [];

	foreach ( $dotaz->posts as $post ) {
		$export_polozky[] = [
			'group_id'    => 'skaut-burza-inzeraty',
			'group_label' => __( 'Inzeráty na burze', 'skaut-burza' ),
			'item_id'     => 'skaut-burza-inzerat-' . $post->ID,
			'data'        => [
				[ 'name' => __( 'Název', 'skaut-burza' ), 'value' => $post->post_title ],
				[ 'name' => __( 'Popis', 'skaut-burza' ), 'value' => $post->post_content ],
				[ 'name' => __( 'Velikost', 'skaut-burza' ), 'value' => (string) get_post_meta( $post->ID, '_burza_velikost', true ) ],
				[ 'name' => __( 'Stav věci', 'skaut-burza' ), 'value' => skaut_burza_stav_popisek( (string) get_post_meta( $post->ID, '_burza_stav', true ) ) ],
				[ 'name' => __( 'Cena', 'skaut-burza' ), 'value' => (string) get_post_meta( $post->ID, '_burza_cena', true ) ],
				[ 'name' => __( 'Telefon', 'skaut-burza' ), 'value' => (string) get_post_meta( $post->ID, '_burza_telefon', true ) ],
				[ 'name' => __( 'E-mail', 'skaut-burza' ), 'value' => (string) get_post_meta( $post->ID, '_burza_email', true ) ],
				[ 'name' => __( 'Datum vložení', 'skaut-burza' ), 'value' => $post->post_date ],
			],
		];
	}

	return [
		'data' => $export_polozky,
		'done' => count( $dotaz->posts ) < $na_stranku,
	];
}

/**
 * Maže jen kontaktní meta (telefon, e-mail) ze všech inzerátů uživatele,
 * samotné inzeráty (název, popis) zůstávají — stejný princip jako
 * anonymizace u vestavěného mazače komentářů v jádru WP. Úplné smazání
 * inzerátu včetně kontaktů dělá skaut_burza_smaz_fotky() + wp_delete_post()
 * v [burza_moje]/adminu, to je samostatná, uživatelem vyžádaná akce.
 */
function skaut_burza_privacy_eraser( string $email_address, int $stranka = 1 ): array {
	$stranka    = max( 1, $stranka );
	$na_stranku = 20;

	$user = get_user_by( 'email', $email_address );
	if ( ! $user ) {
		return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
	}

	$dotaz      = skaut_burza_privacy_uzivatelovy_inzeraty( $user->ID, $stranka, $na_stranku );
	$odstraneno = false;

	foreach ( $dotaz->posts as $post ) {
		if ( get_post_meta( $post->ID, '_burza_telefon', true ) || get_post_meta( $post->ID, '_burza_email', true ) ) {
			delete_post_meta( $post->ID, '_burza_telefon' );
			delete_post_meta( $post->ID, '_burza_email' );
			$odstraneno = true;
		}
	}

	return [
		'items_removed'  => $odstraneno,
		'items_retained' => false,
		'messages'       => $odstraneno ? [ __( 'Kontaktní údaje (telefon, e-mail) byly smazány ze všech inzerátů na burze. Samotné inzeráty (název, popis) zůstaly zachované.', 'skaut-burza' ) ] : [],
		'done'           => count( $dotaz->posts ) < $na_stranku,
	];
}
