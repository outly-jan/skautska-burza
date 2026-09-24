<?php
/**
 * Fallback šablona pro detail inzerátu burzy. Přebije ji šablona
 * single-burza_inzerat.php v aktivním tématu, pokud existuje.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) :
	the_post();
	$post_obj = get_post();
	?>
	<article <?php post_class( 'skaut-burza-detail' ); ?>>
		<?php echo skaut_burza_zpet_na_vypis_html(); ?>

		<h1><?php the_title(); ?></h1>

		<?php echo skaut_burza_verejna_data_html( $post_obj ); ?>

		<div class="skaut-burza-kontakt-container" data-post-id="<?php echo esc_attr( $post_obj->ID ); ?>">
			<p class="skaut-burza-nacitam"><?php esc_html_e( 'Načítám…', 'skaut-burza' ); ?></p>
		</div>

		<?php echo skaut_burza_disclaimer_html(); ?>

		<?php echo skaut_burza_zpet_na_vypis_html(); ?>
	</article>
	<?php
endwhile;

get_footer();
