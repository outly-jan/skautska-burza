<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_moje_akce_url( string $akce, int $post_id ): string {
	$url = add_query_arg( [
		'burza_moje_akce' => $akce,
		'burza_moje_id'   => $post_id,
	], get_permalink() );
	return wp_nonce_url( $url, 'skaut_burza_moje_akce_' . $post_id );
}

/**
 * Zpracování akcí z [burza_moje] (rezervovat/prodáno/prodloužit/smazat/
 * znovu zveřejnit) — na template_redirect, stejný vzor jako formulář.
 */
function skaut_burza_handle_moje_akce(): void {
	if ( ! isset( $_GET['burza_moje_akce'], $_GET['burza_moje_id'], $_GET['_wpnonce'] ) ) return;
	if ( ! is_user_logged_in() ) return;

	$post_id = absint( $_GET['burza_moje_id'] );
	$akce    = sanitize_key( wp_unslash( $_GET['burza_moje_akce'] ) );

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'skaut_burza_moje_akce_' . $post_id ) ) {
		wp_die( esc_html__( 'Neplatný bezpečnostní token, zkuste to prosím znovu.', 'skaut-burza' ), '', [ 'response' => 403 ] );
	}
	if ( ! skaut_burza_je_autor_nebo_admin( $post_id ) ) {
		wp_die( esc_html__( 'Nemáte oprávnění tuto akci provést.', 'skaut-burza' ), '', [ 'response' => 403 ] );
	}

	$post = get_post( $post_id );
	if ( ! $post || 'burza_inzerat' !== $post->post_type ) {
		wp_die( esc_html__( 'Inzerát nenalezen.', 'skaut-burza' ), '', [ 'response' => 404 ] );
	}

	$navrat = remove_query_arg( [ 'burza_moje_akce', 'burza_moje_id', '_wpnonce', 'burza_chyba' ], wp_get_referer() ?: get_permalink() );
	$navrat = add_query_arg( 'burza_panel', 'moje', $navrat );

	switch ( $akce ) {
		case 'rezervovat':
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'burza_rezervovano' ] );
			break;

		case 'zrusit_rezervaci':
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
			break;

		case 'prodano':
			skaut_burza_archivovat( $post_id );
			break;

		case 'prodlouzit':
			update_post_meta( $post_id, '_burza_posledni_potvrzeni', time() );
			update_post_meta( $post_id, '_burza_pocet_vyzev', 0 );
			break;

		case 'znovu_zverejnit':
			$max = skaut_burza_max_inzeratu();
			if ( skaut_burza_pocet_aktivnich_inzeratu( get_current_user_id() ) >= $max ) {
				wp_safe_redirect( add_query_arg( 'burza_chyba', 'limit', $navrat ) );
				exit;
			}
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
			update_post_meta( $post_id, '_burza_posledni_potvrzeni', time() );
			update_post_meta( $post_id, '_burza_pocet_vyzev', 0 );
			break;

		case 'smazat':
			skaut_burza_smaz_fotky( $post_id );
			wp_delete_post( $post_id, true );
			break;

		default:
			return;
	}

	wp_safe_redirect( $navrat );
	exit;
}

function skaut_burza_shortcode_moje( $atts ): string {
	if ( ! is_user_logged_in() ) {
		return '<p class="skaut-burza-vyzva">' . sprintf(
			/* translators: %s: odkaz na přihlášení */
			esc_html__( 'Pro přehled vlastních inzerátů se musíte %s.', 'skaut-burza' ),
			'<a href="' . esc_url( skaut_burza_prihlaseni_url( add_query_arg( 'burza_panel', 'moje', get_permalink() ) ) ) . '">' . esc_html__( 'přihlásit', 'skaut-burza' ) . '</a>'
		) . '</p>';
	}

	wp_enqueue_style( 'skaut-burza', SKAUT_BURZA_URL . 'assets/css/burza.css', [], SKAUT_BURZA_VERSION );
	skaut_burza_enqueue_panely();

	$user_id = get_current_user_id();

	$aktivni = new WP_Query( [
		'post_type'      => 'burza_inzerat',
		'author'         => $user_id,
		'post_status'    => [ 'publish', 'burza_rezervovano' ],
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );

	$archivovane = new WP_Query( [
		'post_type'      => 'burza_inzerat',
		'author'         => $user_id,
		'post_status'    => 'burza_archiv',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );

	ob_start();
	?>
	<div class="skaut-burza skaut-burza-moje" data-skaut-burza-panel="moje">
		<?php if ( isset( $_GET['burza_chyba'] ) && 'limit' === $_GET['burza_chyba'] ) : ?>
			<p class="skaut-burza-chyby"><?php echo esc_html( sprintf(
				/* translators: %d: maximální počet inzerátů */
				__( 'Máte už maximální počet aktivních inzerátů (%d). Než tenhle znovu zveřejníte, jiný ukončete.', 'skaut-burza' ),
				skaut_burza_max_inzeratu()
			) ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Moje inzeráty', 'skaut-burza' ); ?></h2>

		<p class="skaut-burza-napoveda-kratka"><?php echo esc_html( sprintf(
			/* translators: 1: počet dní do první výzvy, 2: celková doba zveřejnění */
			__( 'Inzerát je zveřejněný %2$s. Kliknutím na „prodloužit platnost“ (nebo potvrzením v e-mailu, který vám přijde %1$s po vložení) se doba počítá znovu od začátku. Po jejím uplynutí se inzerát přesune do archivu, odkud ho můžete znovu zveřejnit; archiv se po 6 měsících maže.', 'skaut-burza' ),
			skaut_burza_dny_text( skaut_burza_dny_do_prvni_vyzvy() ),
			skaut_burza_dny_text( skaut_burza_celkova_doba_zverejneni() )
		) ); ?></p>

		<?php if ( ! $aktivni->have_posts() ) : ?>
			<p><?php esc_html_e( 'Zatím nemáte žádný aktivní inzerát.', 'skaut-burza' ); ?></p>
		<?php else : ?>
			<ul class="skaut-burza-moje-seznam">
				<?php while ( $aktivni->have_posts() ) : $aktivni->the_post(); ?>
					<?php
					$id          = get_the_ID();
					$rezervovano = 'burza_rezervovano' === get_post_status();
					$cena        = get_post_meta( $id, '_burza_cena', true );
					$zbyva_dni   = skaut_burza_dni_do_archivace( $id );
					$brzy        = $zbyva_dni <= skaut_burza_dny_mezi_vyzvami();
					?>
					<li class="skaut-burza-moje-polozka">
						<a href="<?php the_permalink(); ?>"><strong><?php the_title(); ?></strong></a>
						<?php if ( $rezervovano ) : ?>
							<span class="skaut-burza-stitek-rezervovano"><?php esc_html_e( 'Rezervováno', 'skaut-burza' ); ?></span>
						<?php endif; ?>
						<span class="skaut-burza-moje-cena"><?php echo esc_html( skaut_burza_cena_text( $cena ) ); ?></span>
						<span class="skaut-burza-moje-platnost<?php echo $brzy ? ' skaut-burza-moje-platnost-brzy' : ''; ?>">
							<?php
							echo esc_html( $zbyva_dni > 0
								/* translators: %s: počet dní, např. "5 dní" */
								? sprintf( __( 'zveřejněno ještě %s', 'skaut-burza' ), skaut_burza_dny_text( $zbyva_dni ) )
								: __( 'v nejbližších hodinách se přesune do archivu', 'skaut-burza' )
							);
							?>
						</span>

						<span class="skaut-burza-moje-akce">
							<?php
							$odkazy = [];

							$odkazy[] = '<a href="' . esc_url( add_query_arg( 'burza_uprava', $id, skaut_burza_url_stranky( 'formular' ) ) ) . '">' . esc_html__( 'upravit', 'skaut-burza' ) . '</a>';
							if ( ! $rezervovano ) {
								$odkazy[] = '<a href="' . esc_url( skaut_burza_moje_akce_url( 'rezervovat', $id ) ) . '">' . esc_html__( 'označit jako rezervované', 'skaut-burza' ) . '</a>';
							} else {
								$odkazy[] = '<a href="' . esc_url( skaut_burza_moje_akce_url( 'zrusit_rezervaci', $id ) ) . '">' . esc_html__( 'zrušit rezervaci', 'skaut-burza' ) . '</a>';
							}
							$odkazy[] = '<a href="' . esc_url( skaut_burza_moje_akce_url( 'prodano', $id ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Opravdu označit jako prodané a přesunout do archivu?', 'skaut-burza' ) ) . '\');">' . esc_html__( 'označit jako prodané', 'skaut-burza' ) . '</a>';
							$odkazy[] = '<a href="' . esc_url( skaut_burza_moje_akce_url( 'prodlouzit', $id ) ) . '">' . esc_html__( 'prodloužit platnost', 'skaut-burza' ) . '</a>';
							$odkazy[] = '<a href="' . esc_url( skaut_burza_moje_akce_url( 'smazat', $id ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Opravdu inzerát nenávratně smazat i s fotkami?', 'skaut-burza' ) ) . '\');">' . esc_html__( 'smazat', 'skaut-burza' ) . '</a>';

							echo implode( ' | ', $odkazy );
							?>
						</span>
					</li>
				<?php endwhile; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $archivovane->have_posts() ) : ?>
			<h2><?php esc_html_e( 'Archivované inzeráty', 'skaut-burza' ); ?></h2>
			<ul class="skaut-burza-moje-seznam skaut-burza-moje-archiv">
				<?php while ( $archivovane->have_posts() ) : $archivovane->the_post(); ?>
					<?php $id = get_the_ID(); ?>
					<li class="skaut-burza-moje-polozka">
						<strong><?php the_title(); ?></strong>
						<span class="skaut-burza-moje-platnost">
							<?php
							/* translators: %s: počet dní, např. "5 dní" */
							echo esc_html( sprintf( __( 'smaže se za %s', 'skaut-burza' ), skaut_burza_dny_text( skaut_burza_dni_do_smazani( get_post() ) ) ) );
							?>
						</span>
						<span class="skaut-burza-moje-akce">
							<a href="<?php echo esc_url( skaut_burza_moje_akce_url( 'znovu_zverejnit', $id ) ); ?>"><?php esc_html_e( 'znovu zveřejnit', 'skaut-burza' ); ?></a>
						</span>
					</li>
				<?php endwhile; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	wp_reset_postdata();
	return ob_get_clean();
}
