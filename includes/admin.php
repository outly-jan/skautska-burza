<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── SEZNAM V ADMINU: SLOUPCE ─────────────────────────────────────────

function skaut_burza_admin_sloupce( array $sloupce ): array {
	$vysledek = [];
	foreach ( $sloupce as $klic => $popisek ) {
		if ( 'date' === $klic ) {
			$vysledek['skaut_burza_potvrzeni'] = __( 'Poslední potvrzení', 'skaut-burza' );
			$vysledek['skaut_burza_vyzvy']     = __( 'Počet výzev', 'skaut-burza' );
		}
		$vysledek[ $klic ] = $popisek;
	}
	return $vysledek;
}

function skaut_burza_admin_sloupec_obsah( string $sloupec, int $post_id ): void {
	if ( 'skaut_burza_potvrzeni' === $sloupec ) {
		$cas = (int) get_post_meta( $post_id, '_burza_posledni_potvrzeni', true );
		echo $cas ? esc_html( date_i18n( get_option( 'date_format' ), $cas ) ) : '—';
	} elseif ( 'skaut_burza_vyzvy' === $sloupec ) {
		echo esc_html( (string) (int) get_post_meta( $post_id, '_burza_pocet_vyzev', true ) );
	}
}

function skaut_burza_admin_sortable_sloupce( array $sloupce ): array {
	$sloupce['skaut_burza_potvrzeni'] = 'skaut_burza_potvrzeni';
	$sloupce['skaut_burza_vyzvy']     = 'skaut_burza_vyzvy';
	return $sloupce;
}

// ── SEZNAM V ADMINU: FILTR PODLE KATEGORIE ───────────────────────────
// Filtr podle stavu má WP v adminu vestavěný (odkazy Vše/Publikováno/
// Rezervováno/Archiv nad seznamem — díky show_in_admin_all_list
// a show_in_admin_status_list u statusů z části 1).

function skaut_burza_admin_filtr_kategorie(): void {
	global $typenow;
	if ( 'burza_inzerat' !== $typenow ) return;

	wp_dropdown_categories( [
		'taxonomy'         => 'burza_kategorie',
		'hide_empty'       => false,
		'hierarchical'     => true,
		'name'             => 'burza_kategorie',
		'selected'         => isset( $_GET['burza_kategorie'] ) ? absint( $_GET['burza_kategorie'] ) : 0,
		'show_option_all'  => __( 'Všechny kategorie', 'skaut-burza' ),
	] );
}

function skaut_burza_admin_filtr_a_razeni_dotaz( WP_Query $query ): void {
	global $pagenow, $typenow;
	if ( ! is_admin() || ! $query->is_main_query() ) return;
	if ( 'edit.php' !== $pagenow || 'burza_inzerat' !== $typenow ) return;

	if ( ! empty( $_GET['burza_kategorie'] ) ) {
		$query->set( 'tax_query', [ [
			'taxonomy' => 'burza_kategorie',
			'field'    => 'term_id',
			'terms'    => absint( $_GET['burza_kategorie'] ),
		] ] );
	}

	$orderby = $query->get( 'orderby' );
	if ( 'skaut_burza_potvrzeni' === $orderby ) {
		$query->set( 'meta_key', '_burza_posledni_potvrzeni' );
		$query->set( 'orderby', 'meta_value_num' );
	} elseif ( 'skaut_burza_vyzvy' === $orderby ) {
		$query->set( 'meta_key', '_burza_pocet_vyzev' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}

// ── SEZNAM V ADMINU: HROMADNÉ AKCE ───────────────────────────────────

function skaut_burza_admin_bulk_actions( array $akce ): array {
	$akce['skaut_burza_archivovat'] = __( 'Archivovat', 'skaut-burza' );
	$akce['skaut_burza_obnovit']    = __( 'Obnovit (zveřejnit)', 'skaut-burza' );
	$akce['skaut_burza_smazat']     = __( 'Trvale smazat i s fotkami', 'skaut-burza' );
	return $akce;
}

function skaut_burza_admin_handle_bulk_actions( string $presmerovani, string $akce, array $ids ): string {
	if ( ! in_array( $akce, [ 'skaut_burza_archivovat', 'skaut_burza_obnovit', 'skaut_burza_smazat' ], true ) ) {
		return $presmerovani;
	}
	if ( ! current_user_can( 'manage_options' ) ) return $presmerovani;

	$pocet = 0;
	foreach ( $ids as $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post || 'burza_inzerat' !== $post->post_type ) continue;

		switch ( $akce ) {
			case 'skaut_burza_archivovat':
				skaut_burza_archivovat( $post_id );
				$pocet++;
				break;
			case 'skaut_burza_obnovit':
				wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
				$pocet++;
				break;
			case 'skaut_burza_smazat':
				skaut_burza_smaz_fotky( $post_id );
				wp_delete_post( $post_id, true );
				$pocet++;
				break;
		}
	}

	return add_query_arg( 'skaut_burza_hromadne', $pocet, $presmerovani );
}

function skaut_burza_admin_bulk_notice(): void {
	if ( empty( $_REQUEST['skaut_burza_hromadne'] ) ) return;
	$pocet = absint( $_REQUEST['skaut_burza_hromadne'] );
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( sprintf(
			/* translators: %d: počet upravených inzerátů */
			_n( 'Upraven %d inzerát.', 'Upraveno %d inzerátů.', $pocet, 'skaut-burza' ),
			$pocet
		) )
	);
}

// ── STRÁNKA NASTAVENÍ ─────────────────────────────────────────────────

function skaut_burza_admin_menu(): void {
	add_submenu_page(
		'edit.php?post_type=burza_inzerat',
		__( 'Nastavení burzy', 'skaut-burza' ),
		__( 'Nastavení', 'skaut-burza' ),
		'manage_options',
		'skaut-burza-nastaveni',
		'skaut_burza_render_nastaveni_stranka'
	);
}

function skaut_burza_register_settings(): void {
	register_setting( 'skaut_burza_nastaveni', 'skaut_burza_dny_prvni_vyzva', [
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'default'           => 30,
	] );
	register_setting( 'skaut_burza_nastaveni', 'skaut_burza_dny_interval_vyzev', [
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'default'           => 14,
	] );
	register_setting( 'skaut_burza_nastaveni', 'skaut_burza_max_vyzev', [
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'default'           => 3,
	] );
	register_setting( 'skaut_burza_nastaveni', 'skaut_burza_max_fotek', [
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'default'           => 3,
	] );
	register_setting( 'skaut_burza_nastaveni', 'skaut_burza_url_stranky', [
		'type'              => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default'           => '',
	] );
	register_setting( 'skaut_burza_nastaveni', 'skaut_burza_kontaktni_email', [
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_email',
		'default'           => get_option( 'admin_email' ),
	] );
}

function skaut_burza_render_nastaveni_stranka(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Nastavení burzy', 'skaut-burza' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'skaut_burza_nastaveni' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="skaut_burza_dny_prvni_vyzva"><?php esc_html_e( 'Počet dní do první výzvy', 'skaut-burza' ); ?></label></th>
					<td><input type="number" min="1" id="skaut_burza_dny_prvni_vyzva" name="skaut_burza_dny_prvni_vyzva" value="<?php echo esc_attr( get_option( 'skaut_burza_dny_prvni_vyzva', 30 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_burza_dny_interval_vyzev"><?php esc_html_e( 'Interval dalších výzev (dny)', 'skaut-burza' ); ?></label></th>
					<td><input type="number" min="1" id="skaut_burza_dny_interval_vyzev" name="skaut_burza_dny_interval_vyzev" value="<?php echo esc_attr( get_option( 'skaut_burza_dny_interval_vyzev', 14 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_burza_max_vyzev"><?php esc_html_e( 'Počet výzev před archivací', 'skaut-burza' ); ?></label></th>
					<td><input type="number" min="1" id="skaut_burza_max_vyzev" name="skaut_burza_max_vyzev" value="<?php echo esc_attr( get_option( 'skaut_burza_max_vyzev', 3 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_burza_max_fotek"><?php esc_html_e( 'Maximální počet fotek na inzerát', 'skaut-burza' ); ?></label></th>
					<td><input type="number" min="1" max="10" id="skaut_burza_max_fotek" name="skaut_burza_max_fotek" value="<?php echo esc_attr( get_option( 'skaut_burza_max_fotek', 3 ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_burza_url_stranky"><?php esc_html_e( 'Adresa stránky burzy', 'skaut-burza' ); ?></label></th>
					<td>
						<input type="url" class="regular-text" id="skaut_burza_url_stranky" name="skaut_burza_url_stranky" value="<?php echo esc_attr( get_option( 'skaut_burza_url_stranky', '' ) ); ?>" placeholder="<?php echo esc_attr( home_url( '/bazar/' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Stránka se shortcody [burza_vypis], [burza_formular] a [burza_moje]. Prázdné = /bazar/. Sem vedou odkazy „Zpět na přehled“, „upravit“, „Moje inzeráty“ i odkaz v e-mailu.', 'skaut-burza' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_burza_kontaktni_email"><?php esc_html_e( 'Kontaktní e-mail střediska (do patičky e-mailů)', 'skaut-burza' ); ?></label></th>
					<td><input type="email" id="skaut_burza_kontaktni_email" name="skaut_burza_kontaktni_email" value="<?php echo esc_attr( skaut_burza_kontaktni_email() ); ?>"></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
