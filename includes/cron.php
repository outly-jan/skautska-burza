<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function skaut_burza_dny_do_prvni_vyzvy(): int {
	return (int) get_option( 'skaut_burza_dny_prvni_vyzva', 30 );
}

function skaut_burza_dny_mezi_vyzvami(): int {
	return (int) get_option( 'skaut_burza_dny_interval_vyzev', 14 );
}

function skaut_burza_max_vyzev(): int {
	return (int) get_option( 'skaut_burza_max_vyzev', 3 );
}

function skaut_burza_naplanovat_cron(): void {
	if ( ! wp_next_scheduled( 'skaut_burza_kontrola' ) ) {
		wp_schedule_event( time(), 'daily', 'skaut_burza_kontrola' );
	}
}

function skaut_burza_odplanovat_cron(): void {
	wp_clear_scheduled_hook( 'skaut_burza_kontrola' );
}

/**
 * Denní kontrola aktivních inzerátů — výzvy a archivace po SPEC.md:
 * 30 dní bez potvrzení → výzva č. 1, pak každých dalších 14 dní další
 * výzva, po třech nezodpovězených archivace. _burza_posledni_potvrzeni
 * slouží jako společné "hodiny" pro obojí — nastavuje ho jak skutečné
 * potvrzení (formulář, potvrzovací e-mailový odkaz, prodloužení
 * v [burza_moje]), tak odeslání každé další výzvy tady v cronu, protože
 * SPEC.md pro "od poslední výzvy" žádné samostatné meta pole nedefinuje.
 */
function skaut_burza_denni_kontrola(): void {
	$prvni_limit = skaut_burza_dny_do_prvni_vyzvy() * DAY_IN_SECONDS;
	$dalsi_limit = skaut_burza_dny_mezi_vyzvami() * DAY_IN_SECONDS;
	$max_vyzev   = skaut_burza_max_vyzev();
	$ted         = time();

	$dotaz = new WP_Query( [
		'post_type'      => 'burza_inzerat',
		'post_status'    => [ 'publish', 'burza_rezervovano' ],
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );

	foreach ( $dotaz->posts as $post_id ) {
		$posledni = (int) get_post_meta( $post_id, '_burza_posledni_potvrzeni', true );
		$pocet    = (int) get_post_meta( $post_id, '_burza_pocet_vyzev', true );
		$stari    = $ted - $posledni;

		if ( 0 === $pocet && $stari >= $prvni_limit ) {
			skaut_burza_odeslat_vyzvu( $post_id );
			update_post_meta( $post_id, '_burza_pocet_vyzev', 1 );
			update_post_meta( $post_id, '_burza_posledni_potvrzeni', $ted );
			continue;
		}

		if ( $pocet > 0 && $pocet < $max_vyzev && $stari >= $dalsi_limit ) {
			skaut_burza_odeslat_vyzvu( $post_id );
			update_post_meta( $post_id, '_burza_pocet_vyzev', $pocet + 1 );
			update_post_meta( $post_id, '_burza_posledni_potvrzeni', $ted );
			continue;
		}

		if ( $pocet >= $max_vyzev ) {
			skaut_burza_archivovat( $post_id );
			skaut_burza_odeslat_info_archivace( $post_id );
		}
	}
}
