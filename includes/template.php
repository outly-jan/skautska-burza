<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_disclaimer_html(): string {
	return '<p class="skaut-burza-disclaimer">' . esc_html__(
		'Středisko je pouze provozovatelem této nástěnky, ne prodejcem — za stav věcí ani průběh předání neodpovídá. Kupující a prodávající se domlouvají přímo mezi sebou.',
		'skaut-burza'
	) . '</p>';
}

function skaut_burza_stav_popisek( string $klic ): string {
	$stavy = skaut_burza_stavy();
	return $stavy[ $klic ] ?? '';
}

function skaut_burza_foto_html( int $attachment_id, string $velikost ): string {
	if ( ! $attachment_id ) return '';
	return wp_get_attachment_image( $attachment_id, $velikost, false, [ 'loading' => 'lazy' ] );
}

/**
 * Veřejná část detailu — vidí i nepřihlášený návštěvník.
 */
function skaut_burza_verejna_data_html( WP_Post $post ): string {
	$fotky      = get_post_meta( $post->ID, '_burza_fotky', true );
	$prvni_foto = is_array( $fotky ) && ! empty( $fotky ) ? (int) $fotky[0] : 0;
	$velikost   = get_post_meta( $post->ID, '_burza_velikost', true );
	$stav       = get_post_meta( $post->ID, '_burza_stav', true );
	$cena       = get_post_meta( $post->ID, '_burza_cena', true );
	$kategorie  = get_the_terms( $post->ID, 'burza_kategorie' );
	$rezervovano = 'burza_rezervovano' === $post->post_status;

	ob_start();
	?>
	<div class="skaut-burza-verejne">
		<?php if ( $rezervovano ) : ?>
			<p class="skaut-burza-stitek-rezervovano"><?php esc_html_e( 'Rezervováno', 'skaut-burza' ); ?></p>
		<?php endif; ?>

		<?php if ( $prvni_foto ) : ?>
			<div class="skaut-burza-foto-hlavni"><?php echo skaut_burza_foto_html( $prvni_foto, 'full' ); ?></div>
		<?php else : ?>
			<div class="skaut-burza-bez-fotky"><?php esc_html_e( 'Bez fotky', 'skaut-burza' ); ?></div>
		<?php endif; ?>

		<ul class="skaut-burza-udaje">
			<?php if ( $kategorie && ! is_wp_error( $kategorie ) ) : ?>
				<li><strong><?php esc_html_e( 'Kategorie:', 'skaut-burza' ); ?></strong> <?php echo esc_html( $kategorie[0]->name ); ?></li>
			<?php endif; ?>
			<?php if ( $velikost ) : ?>
				<li><strong><?php esc_html_e( 'Velikost:', 'skaut-burza' ); ?></strong> <?php echo esc_html( $velikost ); ?></li>
			<?php endif; ?>
			<?php if ( $stav ) : ?>
				<li><strong><?php esc_html_e( 'Stav:', 'skaut-burza' ); ?></strong> <?php echo esc_html( skaut_burza_stav_popisek( $stav ) ); ?></li>
			<?php endif; ?>
			<li><strong><?php esc_html_e( 'Cena:', 'skaut-burza' ); ?></strong> <?php echo esc_html( $cena ); ?></li>
			<li><strong><?php esc_html_e( 'Vloženo:', 'skaut-burza' ); ?></strong> <?php echo esc_html( get_the_date( '', $post ) ); ?></li>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Blok jen pro přihlášené — popis, zbývající fotky, kontakty. Nekontroluje
 * oprávnění, to je na volajícím (AJAX handler ve visibility.php).
 */
function skaut_burza_gated_html( WP_Post $post ): string {
	$fotky           = get_post_meta( $post->ID, '_burza_fotky', true );
	$fotky           = is_array( $fotky ) ? $fotky : [];
	$zbyvajici_fotky = array_slice( $fotky, 1 );
	$telefon         = get_post_meta( $post->ID, '_burza_telefon', true );
	$email           = get_post_meta( $post->ID, '_burza_email', true );

	ob_start();
	?>
	<div class="skaut-burza-gated">
		<?php if ( $post->post_content ) : ?>
			<div class="skaut-burza-popis"><?php echo wpautop( esc_html( $post->post_content ) ); ?></div>
		<?php endif; ?>

		<?php if ( $zbyvajici_fotky ) : ?>
			<div class="skaut-burza-dalsi-fotky">
				<?php foreach ( $zbyvajici_fotky as $attachment_id ) : ?>
					<?php echo skaut_burza_foto_html( (int) $attachment_id, 'full' ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<ul class="skaut-burza-kontakty">
			<li><strong><?php esc_html_e( 'Telefon:', 'skaut-burza' ); ?></strong> <?php echo $telefon ? esc_html( $telefon ) : esc_html__( 'neuvedeno', 'skaut-burza' ); ?></li>
			<li><strong><?php esc_html_e( 'E-mail:', 'skaut-burza' ); ?></strong> <?php echo $email ? esc_html( $email ) : esc_html__( 'neuvedeno', 'skaut-burza' ); ?></li>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

function skaut_burza_prihlaseni_vyzva_html(): string {
	return '<p class="skaut-burza-vyzva">' . sprintf(
		/* translators: %s: odkaz na přihlášení */
		esc_html__( 'Popis, další fotky a kontakt na prodávajícího uvidíte po %s.', 'skaut-burza' ),
		'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'přihlášení', 'skaut-burza' ) . '</a>'
	) . '</p>';
}

/**
 * Najde stránku, na které je vložený daný shortcode (např. [burza_formular]),
 * ať se na ni dá z [burza_moje] odkázat na úpravu bez nutnosti ji ručně
 * nastavovat. Výsledek se cachuje přes transient.
 */
function skaut_burza_stranka_s_shortcode( string $shortcode ): int {
	$cache_key = 'skaut_burza_stranka_' . $shortcode;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) return (int) $cached;

	global $wpdb;
	$like = '%' . $wpdb->esc_like( '[' . $shortcode ) . '%';
	$id   = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s LIMIT 1",
		$like
	) );

	set_transient( $cache_key, $id, HOUR_IN_SECONDS );
	return $id;
}

/**
 * Zruší cache z skaut_burza_stranka_s_shortcode() při uložení jakékoli
 * stránky, ať se hned projeví přesun [burza_formular]/[burza_moje] na
 * jinou stránku.
 */
function skaut_burza_vycistit_stranka_cache(): void {
	delete_transient( 'skaut_burza_stranka_burza_formular' );
	delete_transient( 'skaut_burza_stranka_burza_moje' );
}

function skaut_burza_template_include( string $template ): string {
	if ( ! is_singular( 'burza_inzerat' ) ) return $template;

	$tema_sablona = locate_template( [ 'single-burza_inzerat.php' ] );
	if ( $tema_sablona ) return $tema_sablona;

	return SKAUT_BURZA_DIR . 'templates/single-burza_inzerat.php';
}

function skaut_burza_enqueue_detail_assets(): void {
	if ( ! is_singular( 'burza_inzerat' ) ) return;

	wp_enqueue_style( 'skaut-burza', SKAUT_BURZA_URL . 'assets/css/burza.css', [], SKAUT_BURZA_VERSION );
	wp_enqueue_script( 'skaut-burza-kontakt', SKAUT_BURZA_URL . 'assets/js/burza-kontakt.js', [], SKAUT_BURZA_VERSION, true );
	wp_localize_script( 'skaut-burza-kontakt', 'skautBurzaKontakt', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'skaut_burza_kontakt' ),
		'nacitam' => __( 'Načítám…', 'skaut-burza' ),
		'chyba'   => __( 'Kontakt se nepodařilo načíst, zkuste stránku obnovit.', 'skaut-burza' ),
	] );
}
