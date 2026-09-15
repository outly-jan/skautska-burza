<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_vypis_na_stranku(): int {
	return 12;
}

function skaut_burza_shortcode_vypis( $atts ): string {
	wp_enqueue_style( 'skaut-burza', SKAUT_BURZA_URL . 'assets/css/burza.css', [], SKAUT_BURZA_VERSION );

	$stranka   = isset( $_GET['burza_str'] ) ? max( 1, absint( $_GET['burza_str'] ) ) : 1;
	$kategorie = isset( $_GET['burza_kategorie'] ) ? absint( $_GET['burza_kategorie'] ) : 0;
	$hledat    = isset( $_GET['burza_hledat'] ) ? sanitize_text_field( wp_unslash( $_GET['burza_hledat'] ) ) : '';

	$args = [
		'post_type'      => 'burza_inzerat',
		'post_status'    => [ 'publish', 'burza_rezervovano' ],
		'posts_per_page' => skaut_burza_vypis_na_stranku(),
		'paged'          => $stranka,
		'orderby'        => 'date',
		'order'          => 'DESC',
	];

	if ( $hledat !== '' ) {
		$args['s'] = $hledat;
	}

	if ( $kategorie ) {
		$args['tax_query'] = [ [
			'taxonomy' => 'burza_kategorie',
			'field'    => 'term_id',
			'terms'    => $kategorie,
		] ];
	}

	$dotaz = new WP_Query( $args );

	ob_start();
	?>
	<div class="skaut-burza skaut-burza-vypis">
		<form method="get" class="skaut-burza-filtr">
			<?php foreach ( $_GET as $klic => $hodnota ) : ?>
				<?php if ( in_array( $klic, [ 'burza_kategorie', 'burza_hledat', 'burza_str' ], true ) ) continue; ?>
				<input type="hidden" name="<?php echo esc_attr( $klic ); ?>" value="<?php echo esc_attr( is_array( $hodnota ) ? '' : $hodnota ); ?>">
			<?php endforeach; ?>

			<label for="burza_hledat" class="screen-reader-text"><?php esc_html_e( 'Hledat', 'skaut-burza' ); ?></label>
			<input type="search" id="burza_hledat" name="burza_hledat" value="<?php echo esc_attr( $hledat ); ?>" placeholder="<?php esc_attr_e( 'Hledat…', 'skaut-burza' ); ?>">

			<?php
			wp_dropdown_categories( [
				'taxonomy'          => 'burza_kategorie',
				'hide_empty'        => false,
				'hierarchical'      => true,
				'name'              => 'burza_kategorie',
				'id'                => 'burza_kategorie_filtr',
				'selected'          => $kategorie,
				'show_option_all'   => __( 'Všechny kategorie', 'skaut-burza' ),
			] );
			?>

			<button type="submit"><?php esc_html_e( 'Filtrovat', 'skaut-burza' ); ?></button>
		</form>

		<?php if ( ! $dotaz->have_posts() ) : ?>
			<p class="skaut-burza-prazdno"><?php esc_html_e( 'Žádné inzeráty neodpovídají zadaným kritériím.', 'skaut-burza' ); ?></p>
		<?php else : ?>
			<div class="skaut-burza-mrizka">
				<?php while ( $dotaz->have_posts() ) : $dotaz->the_post(); ?>
					<?php
					$post_obj    = get_post();
					$fotky       = get_post_meta( $post_obj->ID, '_burza_fotky', true );
					$prvni_foto  = is_array( $fotky ) && ! empty( $fotky ) ? (int) $fotky[0] : 0;
					$cena        = get_post_meta( $post_obj->ID, '_burza_cena', true );
					$velikost    = get_post_meta( $post_obj->ID, '_burza_velikost', true );
					$rezervovano = 'burza_rezervovano' === $post_obj->post_status;
					?>
					<a class="skaut-burza-dlazdice" href="<?php the_permalink(); ?>">
						<?php if ( $rezervovano ) : ?>
							<span class="skaut-burza-stitek-rezervovano"><?php esc_html_e( 'Rezervováno', 'skaut-burza' ); ?></span>
						<?php endif; ?>
						<div class="skaut-burza-dlazdice-foto">
							<?php
							echo $prvni_foto
								? skaut_burza_foto_html( $prvni_foto, 'burza_nahled' )
								: '<span class="skaut-burza-bez-fotky">' . esc_html__( 'Bez fotky', 'skaut-burza' ) . '</span>';
							?>
						</div>
						<h3 class="skaut-burza-dlazdice-nazev"><?php the_title(); ?></h3>
						<?php if ( $velikost ) : ?>
							<p class="skaut-burza-dlazdice-velikost"><?php echo esc_html( $velikost ); ?></p>
						<?php endif; ?>
						<p class="skaut-burza-dlazdice-cena"><?php echo esc_html( $cena ); ?></p>
					</a>
				<?php endwhile; ?>
			</div>

			<div class="skaut-burza-strankovani">
				<?php
				echo paginate_links( [
					'total'     => $dotaz->max_num_pages,
					'current'   => $stranka,
					'format'    => '?burza_str=%#%',
					'add_args'  => array_filter( [
						'burza_kategorie' => $kategorie ?: false,
						'burza_hledat'    => $hledat ?: false,
					] ),
				] );
				?>
			</div>
		<?php endif; ?>

		<?php echo skaut_burza_disclaimer_html(); ?>
	</div>
	<?php
	wp_reset_postdata();
	return ob_get_clean();
}
