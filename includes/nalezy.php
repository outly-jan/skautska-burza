<?php
/**
 * Nálezy — věci nalezené na skautských akcích. Vkládají je jen vedoucí
 * (role author a vyšší, tj. kdo má publish_posts), vidí je všichni,
 * kontakt na vedoucího jen přihlášení. Nález je zveřejněný, dokud ho
 * vedoucí neoznačí jako vrácený majiteli (nebo nesmaže).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_register_nalez_post_type(): void {
	register_post_type( 'burza_nalez', [
		'label'               => __( 'Nálezy', 'skaut-burza' ),
		'labels'              => [
			'name'               => __( 'Nálezy', 'skaut-burza' ),
			'singular_name'      => __( 'Nález', 'skaut-burza' ),
			'add_new'            => __( 'Přidat nález', 'skaut-burza' ),
			'add_new_item'       => __( 'Přidat nový nález', 'skaut-burza' ),
			'edit_item'          => __( 'Upravit nález', 'skaut-burza' ),
			'new_item'           => __( 'Nový nález', 'skaut-burza' ),
			'view_item'          => __( 'Zobrazit nález', 'skaut-burza' ),
			'search_items'       => __( 'Hledat nálezy', 'skaut-burza' ),
			'not_found'          => __( 'Žádné nálezy nenalezeny', 'skaut-burza' ),
			'not_found_in_trash' => __( 'Žádné nálezy v koši', 'skaut-burza' ),
			'all_items'          => __( 'Nálezy', 'skaut-burza' ),
		],
		'public'              => true,
		'has_archive'         => false,
		'show_in_rest'        => false,
		'exclude_from_search' => false,
		'supports'            => [ 'title', 'editor', 'author' ],
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=burza_inzerat',
		'rewrite'             => [ 'slug' => 'nalez' ],
	] );
}

function skaut_burza_register_nalez_status(): void {
	register_post_status( 'burza_vraceno', [
		'label'                     => _x( 'Vráceno majiteli', 'post status', 'skaut-burza' ),
		'public'                    => false,
		'private'                   => true,
		'exclude_from_search'       => true,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Vráceno majiteli <span class="count">(%s)</span>', 'Vráceno majiteli <span class="count">(%s)</span>', 'skaut-burza' ),
	] );
}

function skaut_burza_register_nalez_meta(): void {
	$pole = [
		'_burza_velikost'       => [ 'string', 'sanitize_text_field', 'skaut_burza_auth_meta' ],
		'_burza_dodatecne_info' => [ 'string', 'sanitize_textarea_field', 'skaut_burza_auth_meta' ],
		'_burza_akce'           => [ 'string', 'sanitize_text_field', 'skaut_burza_auth_meta' ],
		'_burza_datum_nalezu'   => [ 'string', 'skaut_burza_sanitize_datum', 'skaut_burza_auth_meta' ],
		'_burza_telefon'        => [ 'string', 'sanitize_text_field', 'skaut_burza_auth_kontakt' ],
		'_burza_email'          => [ 'string', 'sanitize_email', 'skaut_burza_auth_kontakt' ],
		'_burza_fotky'          => [ 'array', 'skaut_burza_sanitize_fotky', 'skaut_burza_auth_meta' ],
	];
	foreach ( $pole as $klic => [ $typ, $sanitize, $auth ] ) {
		register_post_meta( 'burza_nalez', $klic, [
			'type'              => $typ,
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => $sanitize,
			'auth_callback'     => $auth,
		] );
	}
}

/**
 * Datum ve formátu RRRR-MM-DD (z <input type="date">), jinak prázdný řetězec.
 */
function skaut_burza_sanitize_datum( $hodnota ): string {
	$hodnota = trim( (string) $hodnota );
	$datum   = DateTime::createFromFormat( '!Y-m-d', $hodnota );
	return ( $datum && $datum->format( 'Y-m-d' ) === $hodnota ) ? $hodnota : '';
}

/**
 * Vedoucí = uživatel, který smí publikovat příspěvky (role author a vyšší,
 * i když má vedle toho další role).
 */
function skaut_burza_je_vedouci(): bool {
	return is_user_logged_in() && current_user_can( 'publish_posts' );
}

/**
 * Adresa stránky nálezů (Burza → Nastavení). Výchozí je stránka burzy —
 * nálezy jsou na ní jako další panely. $panel (nalezy|nalez_formular)
 * se předá jako ?burza_panel= pro burza-panely.js.
 */
function skaut_burza_url_nalezu( string $panel = '' ): string {
	$url = trim( (string) get_option( 'skaut_burza_url_nalezy', '' ) );
	if ( '' === $url ) return skaut_burza_url_stranky( $panel );
	if ( 0 === strpos( $url, '/' ) ) $url = home_url( $url );

	return $panel ? add_query_arg( 'burza_panel', $panel, $url ) : $url;
}

/**
 * Jméno vedoucího, který nález vložil — přezdívka z WP profilu.
 */
function skaut_burza_prezdivka_autora( WP_Post $post ): string {
	$autor = get_userdata( (int) $post->post_author );
	if ( ! $autor ) return '';
	return trim( (string) ( $autor->nickname ?: $autor->display_name ) );
}

function skaut_burza_datum_text( string $datum ): string {
	return $datum ? date_i18n( get_option( 'date_format' ), strtotime( $datum ) ) : '';
}

function skaut_burza_nalez_chyba( string $zprava ): void {
	global $skaut_burza_nalez_chyby;
	$skaut_burza_nalez_chyby[] = $zprava;
}

function skaut_burza_nalez_ziskej_chyby(): array {
	global $skaut_burza_nalez_chyby;
	return $skaut_burza_nalez_chyby ?? [];
}

/**
 * Zpracování POSTu z [burza_nalez_formular] na template_redirect.
 */
function skaut_burza_handle_nalez_submit(): void {
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) return;
	if ( ! isset( $_POST['skaut_burza_nalez_nonce'] ) ) return;

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skaut_burza_nalez_nonce'] ) ), 'skaut_burza_nalez_formular' ) ) {
		skaut_burza_nalez_chyba( __( 'Neplatný bezpečnostní token, zkuste to prosím znovu.', 'skaut-burza' ) );
		return;
	}
	if ( ! skaut_burza_je_vedouci() ) {
		skaut_burza_nalez_chyba( __( 'Nálezy mohou vkládat jen vedoucí.', 'skaut-burza' ) );
		return;
	}

	$post_id    = isset( $_POST['burza_post_id'] ) ? absint( $_POST['burza_post_id'] ) : 0;
	$je_editace = $post_id > 0;

	if ( $je_editace ) {
		$existujici = get_post( $post_id );
		if ( ! $existujici || 'burza_nalez' !== $existujici->post_type ) {
			skaut_burza_nalez_chyba( __( 'Nález nenalezen.', 'skaut-burza' ) );
			return;
		}
		if ( ! skaut_burza_je_autor_nebo_admin( $post_id ) ) {
			skaut_burza_nalez_chyba( __( 'Nemáte oprávnění tento nález upravovat.', 'skaut-burza' ) );
			return;
		}
	}

	$nazev     = sanitize_text_field( wp_unslash( $_POST['burza_nazev'] ?? '' ) );
	$popis     = sanitize_textarea_field( wp_unslash( $_POST['burza_popis'] ?? '' ) );
	$kategorie = absint( $_POST['burza_kategorie'] ?? 0 );
	$velikost  = sanitize_text_field( wp_unslash( $_POST['burza_velikost'] ?? '' ) );
	$akce      = sanitize_text_field( wp_unslash( $_POST['burza_akce'] ?? '' ) );
	$datum     = skaut_burza_sanitize_datum( wp_unslash( $_POST['burza_datum_nalezu'] ?? '' ) );
	$dodatecne = sanitize_textarea_field( wp_unslash( $_POST['burza_dodatecne_info'] ?? '' ) );
	$telefon   = sanitize_text_field( wp_unslash( $_POST['burza_telefon'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['burza_email'] ?? '' ) );
	$souhlas   = ! empty( $_POST['burza_souhlas'] );

	$chyby = [];
	if ( '' === $nazev ) $chyby[] = __( 'Vyplňte, co jste našli.', 'skaut-burza' );
	if ( ! $kategorie || ! term_exists( $kategorie, 'burza_kategorie' ) ) $chyby[] = __( 'Vyberte kategorii.', 'skaut-burza' );
	if ( '' === $datum ) {
		$chyby[] = __( 'Vyplňte datum nálezu.', 'skaut-burza' );
	} elseif ( $datum > current_time( 'Y-m-d' ) ) {
		$chyby[] = __( 'Datum nálezu nemůže být v budoucnosti.', 'skaut-burza' );
	}
	if ( '' === $telefon && '' === $email ) $chyby[] = __( 'Vyplňte telefon nebo e-mail, ať se vám majitel může ozvat.', 'skaut-burza' );
	if ( '' !== $email && ! is_email( $email ) ) $chyby[] = __( 'E-mail není platný.', 'skaut-burza' );
	if ( ! $souhlas ) $chyby[] = __( 'Je potřeba souhlasit se zveřejněním kontaktu přihlášeným uživatelům webu.', 'skaut-burza' );

	if ( $chyby ) {
		foreach ( $chyby as $c ) skaut_burza_nalez_chyba( $c );
		return;
	}

	$post_data = [
		'post_type'    => 'burza_nalez',
		'post_title'   => $nazev,
		'post_content' => $popis,
	];

	if ( $je_editace ) {
		$post_data['ID'] = $post_id;
		$vysledek_id     = wp_update_post( $post_data, true );
	} else {
		$post_data['post_status'] = 'publish';
		$post_data['post_author'] = get_current_user_id();
		$vysledek_id              = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $vysledek_id ) || ! $vysledek_id ) {
		skaut_burza_nalez_chyba( __( 'Uložení nálezu selhalo, zkuste to prosím znovu.', 'skaut-burza' ) );
		return;
	}

	wp_set_object_terms( $vysledek_id, [ $kategorie ], 'burza_kategorie', false );

	update_post_meta( $vysledek_id, '_burza_velikost', $velikost );
	update_post_meta( $vysledek_id, '_burza_akce', $akce );
	update_post_meta( $vysledek_id, '_burza_datum_nalezu', $datum );
	update_post_meta( $vysledek_id, '_burza_dodatecne_info', $dodatecne );
	update_post_meta( $vysledek_id, '_burza_telefon', $telefon );
	update_post_meta( $vysledek_id, '_burza_email', $email );

	$foto_chyby = skaut_burza_ulozit_fotky_z_formulare( $vysledek_id );

	// Nový nález: zpět na prázdný formulář s potvrzením, ať jde po akci
	// zadávat nálezy jeden za druhým. Úprava: na detail upraveného nálezu.
	$cil = $je_editace
		? add_query_arg( 'burza_ulozeno', '1', get_permalink( $vysledek_id ) )
		: add_query_arg( 'burza_nalez_ulozeno', $vysledek_id, skaut_burza_url_nalezu( 'nalez_formular' ) );
	if ( $foto_chyby > 0 ) {
		$cil = add_query_arg( 'burza_foto_chyby', $foto_chyby, $cil );
	}
	wp_safe_redirect( $cil );
	exit;
}

function skaut_burza_nalez_akce_url( string $akce, int $post_id ): string {
	$url = add_query_arg( [
		'burza_nalez_akce' => $akce,
		'burza_nalez_id'   => $post_id,
	], skaut_burza_url_nalezu( 'nalezy' ) );
	return wp_nonce_url( $url, 'skaut_burza_nalez_akce_' . $post_id );
}

/**
 * Akce u nálezu (vráceno majiteli / znovu zveřejnit / smazat) — autor nebo admin.
 */
function skaut_burza_handle_nalez_akce(): void {
	if ( ! isset( $_GET['burza_nalez_akce'], $_GET['burza_nalez_id'], $_GET['_wpnonce'] ) ) return;
	if ( ! is_user_logged_in() ) return;

	$post_id = absint( $_GET['burza_nalez_id'] );
	$akce    = sanitize_key( wp_unslash( $_GET['burza_nalez_akce'] ) );

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'skaut_burza_nalez_akce_' . $post_id ) ) {
		wp_die( esc_html__( 'Neplatný bezpečnostní token, zkuste to prosím znovu.', 'skaut-burza' ), '', [ 'response' => 403 ] );
	}

	$post = get_post( $post_id );
	if ( ! $post || 'burza_nalez' !== $post->post_type ) {
		wp_die( esc_html__( 'Nález nenalezen.', 'skaut-burza' ), '', [ 'response' => 404 ] );
	}
	if ( ! skaut_burza_je_autor_nebo_admin( $post_id ) ) {
		wp_die( esc_html__( 'Nemáte oprávnění tuto akci provést.', 'skaut-burza' ), '', [ 'response' => 403 ] );
	}

	switch ( $akce ) {
		case 'vraceno':
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'burza_vraceno' ] );
			break;

		case 'znovu':
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
			break;

		case 'smazat':
			skaut_burza_smaz_fotky( $post_id );
			wp_delete_post( $post_id, true );
			break;

		default:
			return;
	}

	wp_safe_redirect( skaut_burza_url_nalezu( 'nalezy' ) );
	exit;
}

/**
 * Odkazy pro správu nálezu (autor nebo admin), oddělené svislítkem.
 */
function skaut_burza_nalez_akce_html( WP_Post $post ): string {
	if ( ! skaut_burza_je_autor_nebo_admin( $post->ID ) ) return '';

	$odkazy = [
		'<a href="' . esc_url( add_query_arg( 'burza_nalez_uprava', $post->ID, skaut_burza_url_nalezu( 'nalez_formular' ) ) ) . '">' . esc_html__( 'upravit', 'skaut-burza' ) . '</a>',
	];
	if ( 'burza_vraceno' === $post->post_status ) {
		$odkazy[] = '<a href="' . esc_url( skaut_burza_nalez_akce_url( 'znovu', $post->ID ) ) . '">' . esc_html__( 'znovu zveřejnit', 'skaut-burza' ) . '</a>';
	} else {
		$odkazy[] = '<a href="' . esc_url( skaut_burza_nalez_akce_url( 'vraceno', $post->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Označit jako vrácené majiteli? Nález zmizí z přehledu.', 'skaut-burza' ) ) . '\');">' . esc_html__( 'vráceno majiteli', 'skaut-burza' ) . '</a>';
	}
	$odkazy[] = '<a href="' . esc_url( skaut_burza_nalez_akce_url( 'smazat', $post->ID ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Opravdu nález nenávratně smazat i s fotkami?', 'skaut-burza' ) ) . '\');">' . esc_html__( 'smazat', 'skaut-burza' ) . '</a>';

	return '<p class="skaut-burza-nalez-akce">' . implode( ' | ', $odkazy ) . '</p>';
}

/**
 * Vyhledávání v nálezech prohledává kromě názvu a popisu i akci / místo
 * nálezu — WP 's' sám o sobě meta pole neprohledává.
 */
function skaut_burza_nalezy_posts_search( string $search, WP_Query $query ): string {
	if ( ! $query->get( 'skaut_burza_nalezy_hledat' ) ) return $search;

	global $wpdb;
	$like = '%' . $wpdb->esc_like( (string) $query->get( 's' ) ) . '%';

	return $wpdb->prepare(
		" AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '_burza_akce' AND pm.meta_value LIKE %s
		) ) ",
		$like,
		$like,
		$like
	);
}

function skaut_burza_shortcode_nalezy( $atts ): string {
	wp_enqueue_style( 'skaut-burza', SKAUT_BURZA_URL . 'assets/css/burza.css', [], SKAUT_BURZA_VERSION );
	skaut_burza_enqueue_panely();

	$stranka   = isset( $_GET['burza_nstr'] ) ? max( 1, absint( $_GET['burza_nstr'] ) ) : 1;
	$kategorie = isset( $_GET['burza_nkategorie'] ) ? absint( $_GET['burza_nkategorie'] ) : 0;
	$hledat    = isset( $_GET['burza_nhledat'] ) ? sanitize_text_field( wp_unslash( $_GET['burza_nhledat'] ) ) : '';

	$args = [
		'post_type'      => 'burza_nalez',
		'post_status'    => 'publish',
		'posts_per_page' => skaut_burza_vypis_na_stranku(),
		'paged'          => $stranka,
		'orderby'        => 'date',
		'order'          => 'DESC',
	];
	if ( '' !== $hledat ) {
		$args['s']                         = $hledat;
		$args['skaut_burza_nalezy_hledat'] = true;
	}
	if ( $kategorie ) {
		$args['tax_query'] = [ [
			'taxonomy' => 'burza_kategorie',
			'field'    => 'term_id',
			'terms'    => $kategorie,
		] ];
	}

	add_filter( 'posts_search', 'skaut_burza_nalezy_posts_search', 10, 2 );
	$dotaz = new WP_Query( $args );
	remove_filter( 'posts_search', 'skaut_burza_nalezy_posts_search', 10 );

	// Vedoucí vidí pod přehledem i své nálezy označené jako vrácené.
	$vracene = null;
	if ( skaut_burza_je_vedouci() ) {
		$vracene = new WP_Query( [
			'post_type'      => 'burza_nalez',
			'post_status'    => 'burza_vraceno',
			'author'         => current_user_can( 'manage_options' ) ? '' : get_current_user_id(),
			'posts_per_page' => 50,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		] );
	}

	ob_start();
	?>
	<div class="skaut-burza skaut-burza-nalezy" data-skaut-burza-panel="nalezy">
		<form method="get" class="skaut-burza-filtr">
			<input type="hidden" name="burza_panel" value="nalezy">
			<?php foreach ( $_GET as $klic => $hodnota ) : ?>
				<?php if ( 0 === strpos( (string) $klic, 'burza_' ) ) continue; ?>
				<input type="hidden" name="<?php echo esc_attr( $klic ); ?>" value="<?php echo esc_attr( is_array( $hodnota ) ? '' : $hodnota ); ?>">
			<?php endforeach; ?>

			<label for="burza_nhledat" class="screen-reader-text"><?php esc_html_e( 'Hledat', 'skaut-burza' ); ?></label>
			<input type="search" id="burza_nhledat" name="burza_nhledat" value="<?php echo esc_attr( $hledat ); ?>" placeholder="<?php esc_attr_e( 'Hledat (i podle akce)…', 'skaut-burza' ); ?>">

			<?php
			wp_dropdown_categories( [
				'taxonomy'        => 'burza_kategorie',
				'hide_empty'      => false,
				'hierarchical'    => true,
				'name'            => 'burza_nkategorie',
				'id'              => 'burza_nkategorie',
				'selected'        => $kategorie,
				'show_option_all' => __( 'Všechny kategorie', 'skaut-burza' ),
			] );
			?>

			<button type="submit"><?php esc_html_e( 'Filtrovat', 'skaut-burza' ); ?></button>
		</form>

		<?php if ( ! $dotaz->have_posts() ) : ?>
			<p class="skaut-burza-prazdno"><?php esc_html_e( 'Žádné nálezy neodpovídají zadaným kritériím.', 'skaut-burza' ); ?></p>
		<?php else : ?>
			<div class="skaut-burza-mrizka">
				<?php while ( $dotaz->have_posts() ) : $dotaz->the_post(); ?>
					<?php
					$post_obj   = get_post();
					$fotky      = get_post_meta( $post_obj->ID, '_burza_fotky', true );
					$prvni_foto = is_array( $fotky ) && ! empty( $fotky ) ? (int) $fotky[0] : 0;
					$akce       = (string) get_post_meta( $post_obj->ID, '_burza_akce', true );
					$datum      = (string) get_post_meta( $post_obj->ID, '_burza_datum_nalezu', true );
					?>
					<div class="skaut-burza-polozka">
						<a class="skaut-burza-dlazdice" href="<?php the_permalink(); ?>">
							<h3 class="skaut-burza-dlazdice-nazev"><?php the_title(); ?></h3>
							<div class="skaut-burza-dlazdice-foto">
								<?php
								echo $prvni_foto
									? skaut_burza_foto_html( $prvni_foto, 'burza_nahled' )
									: '<span class="skaut-burza-bez-fotky">' . esc_html__( 'Bez fotky', 'skaut-burza' ) . '</span>';
								?>
							</div>
							<?php if ( $akce ) : ?>
								<p class="skaut-burza-dlazdice-velikost"><strong><?php esc_html_e( 'Akce:', 'skaut-burza' ); ?></strong> <?php echo esc_html( $akce ); ?></p>
							<?php endif; ?>
							<p class="skaut-burza-dlazdice-cena"><strong><?php esc_html_e( 'Nalezeno:', 'skaut-burza' ); ?></strong> <?php echo esc_html( skaut_burza_datum_text( $datum ) ); ?></p>
							<span class="skaut-burza-dlazdice-tlacitko"><?php esc_html_e( 'Detail', 'skaut-burza' ); ?></span>
						</a>
						<?php echo skaut_burza_nalez_akce_html( $post_obj ); ?>
					</div>
				<?php endwhile; ?>
			</div>

			<div class="skaut-burza-strankovani">
				<?php
				echo paginate_links( [
					'total'    => $dotaz->max_num_pages,
					'current'  => $stranka,
					'format'   => '?burza_nstr=%#%',
					'add_args' => array_filter( [
						'burza_panel'      => 'nalezy',
						'burza_nkategorie' => $kategorie ?: false,
						'burza_nhledat'    => $hledat ?: false,
					] ),
				] );
				?>
			</div>
		<?php endif; ?>
		<?php wp_reset_postdata(); ?>

		<?php if ( $vracene && $vracene->have_posts() ) : ?>
			<h2><?php esc_html_e( 'Vrácené nálezy', 'skaut-burza' ); ?></h2>
			<p class="skaut-burza-napoveda-kratka"><?php esc_html_e( 'Vidíte jen vy. Po 6 měsících se vrácené nálezy samy smažou.', 'skaut-burza' ); ?></p>
			<ul class="skaut-burza-moje-seznam skaut-burza-moje-archiv">
				<?php while ( $vracene->have_posts() ) : $vracene->the_post(); ?>
					<li class="skaut-burza-moje-polozka">
						<strong><?php the_title(); ?></strong>
						<span class="skaut-burza-moje-akce"><?php echo skaut_burza_nalez_akce_html( get_post() ); ?></span>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php wp_reset_postdata(); ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function skaut_burza_shortcode_nalez_formular( $atts ): string {
	if ( ! is_user_logged_in() ) {
		return '<p class="skaut-burza-vyzva">' . sprintf(
			/* translators: %s: odkaz na přihlášení */
			esc_html__( 'Nálezy vkládají vedoucí. Pro vložení se musíte %s.', 'skaut-burza' ),
			'<a href="' . esc_url( skaut_burza_prihlaseni_url( add_query_arg( 'burza_panel', 'nalez_formular', get_permalink() ) ) ) . '">' . esc_html__( 'přihlásit', 'skaut-burza' ) . '</a>'
		) . '</p>';
	}
	if ( ! skaut_burza_je_vedouci() ) {
		return '<p class="skaut-burza-vyzva">' . esc_html__( 'Nálezy mohou vkládat jen vedoucí. Pokud jste něco našli, předejte to prosím vedoucímu.', 'skaut-burza' ) . '</p>';
	}

	wp_enqueue_style( 'skaut-burza', SKAUT_BURZA_URL . 'assets/css/burza.css', [], SKAUT_BURZA_VERSION );
	wp_enqueue_script( 'skaut-burza-upload', SKAUT_BURZA_URL . 'assets/js/burza-upload.js', [], SKAUT_BURZA_VERSION, true );
	skaut_burza_enqueue_panely();

	$post_id    = isset( $_GET['burza_nalez_uprava'] ) ? absint( $_GET['burza_nalez_uprava'] ) : 0;
	$je_editace = $post_id > 0;
	$existujici = null;

	if ( $je_editace ) {
		$existujici = get_post( $post_id );
		if ( ! $existujici || 'burza_nalez' !== $existujici->post_type ) {
			return '<p class="skaut-burza-chyba">' . esc_html__( 'Nález nenalezen.', 'skaut-burza' ) . '</p>';
		}
		if ( ! skaut_burza_je_autor_nebo_admin( $post_id ) ) {
			return '<p class="skaut-burza-chyba">' . esc_html__( 'Nemáte oprávnění tento nález upravovat.', 'skaut-burza' ) . '</p>';
		}
	}

	$po_postu = ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' ) && isset( $_POST['skaut_burza_nalez_nonce'] );
	$hodnota  = static function ( string $klic, string $vychozi = '' ) use ( $po_postu ) {
		if ( $po_postu && isset( $_POST[ $klic ] ) ) {
			return sanitize_text_field( wp_unslash( $_POST[ $klic ] ) );
		}
		return $vychozi;
	};
	$text = static function ( string $klic, string $vychozi = '' ) use ( $po_postu ) {
		if ( $po_postu && isset( $_POST[ $klic ] ) ) {
			return sanitize_textarea_field( wp_unslash( $_POST[ $klic ] ) );
		}
		return $vychozi;
	};
	$meta = static function ( string $klic ) use ( $post_id, $je_editace ) {
		return $je_editace ? (string) get_post_meta( $post_id, $klic, true ) : '';
	};

	$nazev     = $hodnota( 'burza_nazev', $je_editace ? $existujici->post_title : '' );
	$popis     = $text( 'burza_popis', $je_editace ? $existujici->post_content : '' );
	$velikost  = $hodnota( 'burza_velikost', $meta( '_burza_velikost' ) );
	$akce      = $hodnota( 'burza_akce', $meta( '_burza_akce' ) );
	$datum     = $hodnota( 'burza_datum_nalezu', $je_editace ? $meta( '_burza_datum_nalezu' ) : current_time( 'Y-m-d' ) );
	$dodatecne = $text( 'burza_dodatecne_info', $meta( '_burza_dodatecne_info' ) );
	$telefon   = $hodnota( 'burza_telefon', $je_editace ? $meta( '_burza_telefon' ) : skaut_burza_prefill_telefon( get_current_user_id() ) );
	$email     = $hodnota( 'burza_email', $je_editace ? $meta( '_burza_email' ) : wp_get_current_user()->user_email );
	if ( $je_editace ) {
		$terms     = wp_get_post_terms( $post_id, 'burza_kategorie', [ 'fields' => 'ids' ] );
		$kategorie = $po_postu && isset( $_POST['burza_kategorie'] ) ? absint( $_POST['burza_kategorie'] ) : (int) ( $terms[0] ?? 0 );
		$fotky     = get_post_meta( $post_id, '_burza_fotky', true );
		if ( ! is_array( $fotky ) ) $fotky = [];
	} else {
		$kategorie = $po_postu && isset( $_POST['burza_kategorie'] ) ? absint( $_POST['burza_kategorie'] ) : 0;
		$fotky     = [];
	}

	$max_fotek = skaut_burza_max_fotek();
	$chyby     = skaut_burza_nalez_ziskej_chyby();

	ob_start();
	?>
	<div class="skaut-burza skaut-burza-formular" data-skaut-burza-panel="nalez_formular"<?php echo ( $po_postu || $je_editace ) ? ' data-skaut-burza-aktivni' : ''; ?>>
		<?php if ( $je_editace ) : ?>
			<h2><?php esc_html_e( 'Úprava nálezu', 'skaut-burza' ); ?></h2>
		<?php endif; ?>

		<?php
		$ulozeny = isset( $_GET['burza_nalez_ulozeno'] ) ? get_post( absint( $_GET['burza_nalez_ulozeno'] ) ) : null;
		if ( ! $je_editace && $ulozeny && 'burza_nalez' === $ulozeny->post_type && skaut_burza_je_autor_nebo_admin( $ulozeny->ID ) ) :
			?>
			<p class="skaut-burza-ok">
				<?php echo esc_html( sprintf(
					/* translators: %s: název nálezu */
					__( 'Nález „%s“ byl uložen.', 'skaut-burza' ),
					$ulozeny->post_title
				) ); ?>
				<a href="<?php echo esc_url( get_permalink( $ulozeny ) ); ?>"><?php esc_html_e( 'Zobrazit nález', 'skaut-burza' ); ?></a>
				<?php if ( ! empty( $_GET['burza_foto_chyby'] ) ) : ?>
					<br><?php echo esc_html( sprintf(
						/* translators: %d: počet fotek */
						__( 'Některé fotky (%d) se nepodařilo nahrát.', 'skaut-burza' ),
						absint( $_GET['burza_foto_chyby'] )
					) ); ?>
				<?php endif; ?>
				<br><?php esc_html_e( 'Další nález můžete vložit rovnou níže.', 'skaut-burza' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $chyby ) : ?>
			<ul class="skaut-burza-chyby">
				<?php foreach ( $chyby as $c ) : ?>
					<li><?php echo esc_html( $c ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! $je_editace ) : ?>
			<div class="skaut-burza-napoveda">
				<p><strong><?php esc_html_e( 'Jak nálezy fungují', 'skaut-burza' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Nález vidí na webu všichni, kontakt na vás jen přihlášení uživatelé. U nálezu se zobrazí vaše přezdívka.', 'skaut-burza' ); ?></li>
					<li><?php esc_html_e( 'Nález zůstane zveřejněný, dokud ho v přehledu nálezů neoznačíte jako „vráceno majiteli“ (nebo nesmažete). Vrácené nálezy se po 6 měsících samy smažou.', 'skaut-burza' ); ?></li>
					<li><?php echo esc_html( sprintf(
						/* translators: %d: max. počet fotek */
						__( 'Fotky: nejvýš %d, JPG, PNG nebo WEBP. Velké fotky z mobilu se před odesláním samy zmenší.', 'skaut-burza' ),
						$max_fotek
					) ); ?></li>
				</ul>
			</div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" class="skaut-burza-form" autocomplete="off">
			<?php wp_nonce_field( 'skaut_burza_nalez_formular', 'skaut_burza_nalez_nonce' ); ?>
			<input type="hidden" name="burza_post_id" value="<?php echo esc_attr( $post_id ); ?>">

			<p>
				<label for="burza_nalez_nazev"><?php esc_html_e( 'Co jste našli', 'skaut-burza' ); ?></label>
				<input type="text" id="burza_nalez_nazev" name="burza_nazev" value="<?php echo esc_attr( $nazev ); ?>" placeholder="<?php esc_attr_e( 'např. modrá mikina s kapucí', 'skaut-burza' ); ?>" required>
			</p>

			<p>
				<label for="burza_nalez_kategorie"><?php esc_html_e( 'Kategorie', 'skaut-burza' ); ?></label>
				<?php
				wp_dropdown_categories( [
					'taxonomy'          => 'burza_kategorie',
					'hide_empty'        => false,
					'hierarchical'      => true,
					'name'              => 'burza_kategorie',
					'id'                => 'burza_nalez_kategorie',
					'selected'          => $kategorie,
					'show_option_none'  => __( '— vyberte kategorii —', 'skaut-burza' ),
					'option_none_value' => '0',
				] );
				?>
			</p>

			<p>
				<label for="burza_nalez_akce"><?php esc_html_e( 'Akce / místo nálezu', 'skaut-burza' ); ?></label>
				<input type="text" id="burza_nalez_akce" name="burza_akce" value="<?php echo esc_attr( $akce ); ?>" placeholder="<?php esc_attr_e( 'např. Letní tábor 2026, Sázava', 'skaut-burza' ); ?>">
			</p>

			<p>
				<label for="burza_nalez_datum"><?php esc_html_e( 'Datum nálezu', 'skaut-burza' ); ?></label>
				<input type="date" id="burza_nalez_datum" name="burza_datum_nalezu" value="<?php echo esc_attr( $datum ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
			</p>

			<p>
				<label for="burza_nalez_popis"><?php esc_html_e( 'Popis', 'skaut-burza' ); ?></label>
				<textarea id="burza_nalez_popis" name="burza_popis" rows="4" placeholder="<?php esc_attr_e( 'barva, značka, podepsáno…', 'skaut-burza' ); ?>"><?php echo esc_textarea( $popis ); ?></textarea>
			</p>

			<p>
				<label for="burza_nalez_velikost"><?php esc_html_e( 'Velikost (nepovinné)', 'skaut-burza' ); ?></label>
				<input type="text" id="burza_nalez_velikost" name="burza_velikost" value="<?php echo esc_attr( $velikost ); ?>" placeholder="<?php esc_attr_e( 'např. 128, M, 42…', 'skaut-burza' ); ?>">
			</p>

			<p>
				<label for="burza_nalez_dodatecne"><?php esc_html_e( 'Dodatečné informace (nepovinné)', 'skaut-burza' ); ?></label>
				<textarea id="burza_nalez_dodatecne" name="burza_dodatecne_info" rows="3" placeholder="<?php esc_attr_e( 'např. kde a kdy je možné věc vyzvednout', 'skaut-burza' ); ?>"><?php echo esc_textarea( $dodatecne ); ?></textarea>
			</p>

			<p>
				<label for="burza_nalez_telefon"><?php esc_html_e( 'Telefon', 'skaut-burza' ); ?></label>
				<input type="tel" id="burza_nalez_telefon" name="burza_telefon" value="<?php echo esc_attr( $telefon ); ?>">
			</p>

			<p>
				<label for="burza_nalez_email"><?php esc_html_e( 'E-mail', 'skaut-burza' ); ?></label>
				<input type="email" id="burza_nalez_email" name="burza_email" value="<?php echo esc_attr( $email ); ?>">
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
				<label for="burza_nalez_fotky"><?php echo esc_html( sprintf(
					/* translators: %d: maximální počet fotek */
					__( 'Fotky (max. %d, JPG/PNG/WEBP)', 'skaut-burza' ),
					$max_fotek
				) ); ?></label>
				<input type="file" id="burza_nalez_fotky" name="burza_fotky[]" accept="image/jpeg,image/png,image/webp" multiple data-max-fotek="<?php echo esc_attr( $max_fotek ); ?>" data-jiz-fotek="<?php echo esc_attr( count( $fotky ) ); ?>">
				<span class="skaut-burza-fotky-info"></span>
			</p>

			<p>
				<label>
					<input type="checkbox" name="burza_souhlas" value="1" required>
					<?php esc_html_e( 'Souhlasím se zveřejněním telefonu a e-mailu přihlášeným uživatelům webu.', 'skaut-burza' ); ?>
				</label>
			</p>

			<p>
				<button type="submit"><?php echo $je_editace ? esc_html__( 'Uložit změny', 'skaut-burza' ) : esc_html__( 'Vložit nález', 'skaut-burza' ); ?></button>
			</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Veřejná část detailu nálezu — fotky, údaje, popis i dodatečné informace
 * vidí všichni (ať majitel věc snadno pozná). Kontakt jde přes AJAX.
 */
function skaut_burza_nalez_verejna_data_html( WP_Post $post ): string {
	$fotky     = get_post_meta( $post->ID, '_burza_fotky', true );
	$fotky     = is_array( $fotky ) ? array_map( 'intval', $fotky ) : [];
	$kategorie = get_the_terms( $post->ID, 'burza_kategorie' );
	$velikost  = (string) get_post_meta( $post->ID, '_burza_velikost', true );
	$akce      = (string) get_post_meta( $post->ID, '_burza_akce', true );
	$datum     = (string) get_post_meta( $post->ID, '_burza_datum_nalezu', true );
	$dodatecne = (string) get_post_meta( $post->ID, '_burza_dodatecne_info', true );

	ob_start();
	?>
	<div class="skaut-burza-verejne">
		<?php if ( $fotky ) : ?>
			<div class="skaut-burza-foto-hlavni"><?php echo skaut_burza_foto_html( $fotky[0], 'full' ); ?></div>
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
			<?php if ( $akce ) : ?>
				<li><strong><?php esc_html_e( 'Akce / místo:', 'skaut-burza' ); ?></strong> <?php echo esc_html( $akce ); ?></li>
			<?php endif; ?>
			<?php if ( $datum ) : ?>
				<li><strong><?php esc_html_e( 'Nalezeno:', 'skaut-burza' ); ?></strong> <?php echo esc_html( skaut_burza_datum_text( $datum ) ); ?></li>
			<?php endif; ?>
			<li><strong><?php esc_html_e( 'Vloženo:', 'skaut-burza' ); ?></strong> <?php echo esc_html( get_the_date( '', $post ) ); ?></li>
		</ul>
	</div>

	<?php if ( $post->post_content ) : ?>
		<div class="skaut-burza-popis"><?php echo wpautop( esc_html( $post->post_content ) ); ?></div>
	<?php endif; ?>

	<?php if ( '' !== trim( $dodatecne ) ) : ?>
		<div class="skaut-burza-dodatecne">
			<strong><?php esc_html_e( 'Dodatečné informace:', 'skaut-burza' ); ?></strong>
			<?php echo wpautop( esc_html( $dodatecne ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( count( $fotky ) > 1 ) : ?>
		<div class="skaut-burza-dalsi-fotky">
			<?php foreach ( array_slice( $fotky, 1 ) as $attachment_id ) : ?>
				<?php echo skaut_burza_foto_html( $attachment_id, 'full' ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

/**
 * Kontakt na vedoucího — jen pro přihlášené (AJAX handler ve visibility.php).
 */
function skaut_burza_nalez_gated_html( WP_Post $post ): string {
	$telefon   = (string) get_post_meta( $post->ID, '_burza_telefon', true );
	$email     = (string) get_post_meta( $post->ID, '_burza_email', true );
	$prezdivka = skaut_burza_prezdivka_autora( $post );

	ob_start();
	?>
	<div class="skaut-burza-gated">
		<ul class="skaut-burza-kontakty">
			<?php if ( $prezdivka ) : ?>
				<li><strong><?php esc_html_e( 'Našel/našla:', 'skaut-burza' ); ?></strong> <?php echo esc_html( $prezdivka ); ?></li>
			<?php endif; ?>
			<li><strong><?php esc_html_e( 'Telefon:', 'skaut-burza' ); ?></strong> <?php echo $telefon ? esc_html( $telefon ) : esc_html__( 'neuvedeno', 'skaut-burza' ); ?></li>
			<li><strong><?php esc_html_e( 'E-mail:', 'skaut-burza' ); ?></strong> <?php echo $email ? esc_html( $email ) : esc_html__( 'neuvedeno', 'skaut-burza' ); ?></li>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

function skaut_burza_nalez_prihlaseni_vyzva_html( int $post_id ): string {
	return '<p class="skaut-burza-vyzva">' . sprintf(
		/* translators: %s: odkaz na přihlášení */
		esc_html__( 'Kontakt na vedoucího, který věc našel, uvidíte po %s.', 'skaut-burza' ),
		'<a href="' . esc_url( skaut_burza_prihlaseni_url( (string) get_permalink( $post_id ) ) ) . '">' . esc_html__( 'přihlášení', 'skaut-burza' ) . '</a>'
	) . '</p>';
}

function skaut_burza_nalez_zpet_html(): string {
	return '<p class="skaut-burza-zpet"><a href="' . esc_url( skaut_burza_url_nalezu( 'nalezy' ) ) . '" data-skaut-burza-zpet>'
		. esc_html__( '← Zpět na přehled nálezů', 'skaut-burza' ) . '</a></p>';
}

/**
 * Měsíční úklid — nálezy vrácené majiteli před víc než 6 měsíci smazat i s fotkami.
 */
function skaut_burza_uklid_vracenych_nalezu(): void {
	$dotaz = new WP_Query( [
		'post_type'      => 'burza_nalez',
		'post_status'    => 'burza_vraceno',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'date_query'     => [ [
			'column' => 'post_modified',
			'before' => '6 months ago',
		] ],
	] );

	foreach ( $dotaz->posts as $post_id ) {
		skaut_burza_smaz_fotky( $post_id );
		wp_delete_post( $post_id, true );
	}
}

/**
 * Nový typ obsahu potřebuje přegenerovat přepisovací pravidla (/nalez/…).
 * Aktivační hook se při deployi nespouští, proto jednorázově podle verze.
 */
function skaut_burza_flush_rewrite_po_aktualizaci(): void {
	if ( get_option( 'skaut_burza_rewrite_verze' ) === 'nalezy-1' ) return;
	flush_rewrite_rules();
	update_option( 'skaut_burza_rewrite_verze', 'nalezy-1' );
}
