<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Maximální velikost jednoho nahrávaného souboru v bajtech (serverová pojistka —
 * hlavní ochranou je zmenšení na straně prohlížeče, viz assets/js/burza-upload.js).
 */
function skaut_burza_max_velikost_souboru(): int {
	return 3 * 1024 * 1024;
}

function skaut_burza_povolene_typy(): array {
	return [
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
	];
}

function skaut_burza_max_fotek(): int {
	return (int) get_option( 'skaut_burza_max_fotek', 3 );
}

/**
 * Přesměruje uploady z burzy do vlastní podsložky uploads/burza/, ať jsou
 * odlišitelné od ostatní medializace na webu. Volat jen dočasně kolem
 * wp_handle_upload() / wp_generate_attachment_metadata().
 */
function skaut_burza_upload_dir_filter( array $uploads ): array {
	$uploads['subdir'] = '/burza' . $uploads['subdir'];
	$uploads['path']   = $uploads['basedir'] . $uploads['subdir'];
	$uploads['url']    = $uploads['baseurl'] . $uploads['subdir'];
	return $uploads;
}

/**
 * Maximální šířka uložené fotky v px. Větší fotka se na serveru zmenší přímo
 * v originálu, který pak slouží jako detail.
 */
function skaut_burza_max_sirka_fotky(): int {
	return 1200;
}

/**
 * Jediná generovaná velikost pro uploady z burzy — čtvercový náhled — místo
 * standardních pěti a víc, ať se nezahltí hosting. Detail je samotný
 * (zmenšený) originál.
 */
function skaut_burza_intermediate_sizes_filter(): array {
	return [
		'burza_nahled' => [ 'width' => 400, 'height' => 400, 'crop' => true ],
	];
}

/**
 * Zpracuje jeden nahraný soubor ($_FILES['pole']) jako fotku inzerátu:
 * serverová validace, upload do uploads/burza/, zmenšení originálu na
 * max. šířku detailu a vygenerování čtvercového náhledu. Vrátí ID přílohy,
 * nebo WP_Error.
 *
 * @param array $file Jedna položka z $_FILES (ne vícerozměrné pole).
 */
function skaut_burza_zpracuj_upload( array $file, int $post_id ) {
	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	if ( ! function_exists( 'wp_crop_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	if ( empty( $file['name'] ) || ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE ) {
		return new WP_Error( 'skaut_burza_bez_souboru', __( 'Žádný soubor nebyl nahrán.', 'skaut-burza' ) );
	}

	if ( ( $file['error'] ?? 0 ) !== UPLOAD_ERR_OK ) {
		return new WP_Error( 'skaut_burza_chyba_uploadu', __( 'Nahrání souboru selhalo.', 'skaut-burza' ) );
	}

	if ( $file['size'] > skaut_burza_max_velikost_souboru() ) {
		return new WP_Error( 'skaut_burza_velky_soubor', __( 'Fotka je větší než 3 MB.', 'skaut-burza' ) );
	}

	$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], skaut_burza_povolene_typy() );
	if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) ) {
		return new WP_Error( 'skaut_burza_typ_souboru', __( 'Povolené typy fotek jsou JPG, PNG a WEBP.', 'skaut-burza' ) );
	}

	add_filter( 'upload_dir', 'skaut_burza_upload_dir_filter' );
	$sideload = wp_handle_upload( $file, [ 'test_form' => false ] );
	remove_filter( 'upload_dir', 'skaut_burza_upload_dir_filter' );

	if ( isset( $sideload['error'] ) ) {
		return new WP_Error( 'skaut_burza_upload_selhal', $sideload['error'] );
	}

	// Originál se nemaže, slouží jako detail — jen se zmenší na max. šířku.
	// Samostatnou velikost pro detail WordPress u užších fotek (typicky na
	// výšku, 1200×1600 po zmenšení v prohlížeči) vůbec nevygeneruje.
	$editor = wp_get_image_editor( $sideload['file'] );
	if ( ! is_wp_error( $editor ) ) {
		$rozmery = $editor->get_size();
		if ( $rozmery['width'] > skaut_burza_max_sirka_fotky() ) {
			$editor->resize( skaut_burza_max_sirka_fotky(), null, false );
			$editor->save( $sideload['file'] );
		}
	}

	$attachment_id = wp_insert_attachment( [
		'post_mime_type' => $sideload['type'],
		'post_title'     => sanitize_file_name( pathinfo( $sideload['file'], PATHINFO_FILENAME ) ),
		'post_status'    => 'inherit',
		'post_parent'    => $post_id,
	], $sideload['file'], $post_id );

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	update_post_meta( $attachment_id, '_burza_foto', 1 );

	add_filter( 'intermediate_image_sizes_advanced', 'skaut_burza_intermediate_sizes_filter' );
	$metadata = wp_generate_attachment_metadata( $attachment_id, $sideload['file'] );
	remove_filter( 'intermediate_image_sizes_advanced', 'skaut_burza_intermediate_sizes_filter' );
	wp_update_attachment_metadata( $attachment_id, $metadata );

	return $attachment_id;
}

/**
 * Smaže všechny fotky uložené v _burza_fotky (i s vygenerovanými velikostmi).
 */
function skaut_burza_smaz_fotky( int $post_id ): void {
	$fotky = get_post_meta( $post_id, '_burza_fotky', true );
	if ( ! is_array( $fotky ) ) return;
	foreach ( $fotky as $attachment_id ) {
		wp_delete_attachment( (int) $attachment_id, true );
	}
}
