<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_register_post_type(): void {
	register_post_type( 'burza_inzerat', [
		'label'               => __( 'Inzeráty burzy', 'skaut-burza' ),
		'labels'              => [
			'name'               => __( 'Inzeráty burzy', 'skaut-burza' ),
			'singular_name'      => __( 'Inzerát burzy', 'skaut-burza' ),
			'add_new'            => __( 'Přidat inzerát', 'skaut-burza' ),
			'add_new_item'       => __( 'Přidat nový inzerát', 'skaut-burza' ),
			'edit_item'          => __( 'Upravit inzerát', 'skaut-burza' ),
			'new_item'           => __( 'Nový inzerát', 'skaut-burza' ),
			'view_item'          => __( 'Zobrazit inzerát', 'skaut-burza' ),
			'search_items'       => __( 'Hledat inzeráty', 'skaut-burza' ),
			'not_found'          => __( 'Žádné inzeráty nenalezeny', 'skaut-burza' ),
			'not_found_in_trash' => __( 'Žádné inzeráty v koši', 'skaut-burza' ),
			'all_items'          => __( 'Všechny inzeráty', 'skaut-burza' ),
			'menu_name'          => __( 'Burza', 'skaut-burza' ),
		],
		'public'              => true,
		'has_archive'         => false,
		'show_in_rest'        => false,
		'exclude_from_search' => false,
		'supports'            => [ 'title', 'editor', 'author' ],
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_icon'           => 'dashicons-tag',
		'rewrite'             => [ 'slug' => 'inzerat' ],
	] );
}

function skaut_burza_register_taxonomy(): void {
	register_taxonomy( 'burza_kategorie', 'burza_inzerat', [
		'label'              => __( 'Kategorie burzy', 'skaut-burza' ),
		'labels'             => [
			'name'          => __( 'Kategorie burzy', 'skaut-burza' ),
			'singular_name' => __( 'Kategorie burzy', 'skaut-burza' ),
			'search_items'  => __( 'Hledat kategorie', 'skaut-burza' ),
			'all_items'     => __( 'Všechny kategorie', 'skaut-burza' ),
			'edit_item'     => __( 'Upravit kategorii', 'skaut-burza' ),
			'add_new_item'  => __( 'Přidat novou kategorii', 'skaut-burza' ),
		],
		'hierarchical'       => true,
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_admin_column'  => true,
		'show_in_nav_menus'  => false,
		'show_in_rest'       => false,
		'query_var'          => false,
		'rewrite'            => false,
	] );
}

function skaut_burza_register_post_statuses(): void {
	register_post_status( 'burza_rezervovano', [
		'label'                     => _x( 'Rezervováno', 'post status', 'skaut-burza' ),
		'public'                    => true,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Rezervováno <span class="count">(%s)</span>', 'Rezervováno <span class="count">(%s)</span>', 'skaut-burza' ),
	] );

	register_post_status( 'burza_archiv', [
		'label'                     => _x( 'Archiv', 'post status', 'skaut-burza' ),
		'public'                    => false,
		'private'                   => true,
		'exclude_from_search'       => true,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Archiv <span class="count">(%s)</span>', 'Archiv <span class="count">(%s)</span>', 'skaut-burza' ),
	] );
}

/**
 * Výchozí strom kategorií, seedovaný jen jednou (po vzoru migrací ve vlcci-odborky.php).
 */
function skaut_burza_seed_kategorie(): void {
	if ( get_option( 'skaut_burza_seeded_kategorie' ) ) return;
	if ( ! taxonomy_exists( 'burza_kategorie' ) ) return;

	$kategorie = [
		__( 'Kroj a doplňky', 'skaut-burza' ),
		__( 'Oblečení', 'skaut-burza' ),
		__( 'Obuv', 'skaut-burza' ),
		__( 'Spacáky a karimatky', 'skaut-burza' ),
		__( 'Batohy a krosny', 'skaut-burza' ),
		__( 'Vybavení do přírody', 'skaut-burza' ),
		__( 'Ostatní', 'skaut-burza' ),
	];

	foreach ( $kategorie as $nazev ) {
		if ( term_exists( $nazev, 'burza_kategorie' ) ) continue;
		wp_insert_term( $nazev, 'burza_kategorie' );
	}

	update_option( 'skaut_burza_seeded_kategorie', '1' );
}
