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

/**
 * Celková doba zveřejnění nepotvrzeného inzerátu ve dnech: do první výzvy,
 * pak interval po každé výzvě (včetně poslední) a archivace.
 */
function skaut_burza_celkova_doba_zverejneni(): int {
	return skaut_burza_dny_do_prvni_vyzvy() + skaut_burza_max_vyzev() * skaut_burza_dny_mezi_vyzvami();
}

/**
 * Kdy denní kontrola inzerát přesune do archivu, pokud do té doby nikdo
 * nepotvrdí, že platí (timestamp). Stejná logika jako skaut_burza_denni_kontrola().
 */
function skaut_burza_konec_zverejneni( int $post_id ): int {
	$posledni = (int) get_post_meta( $post_id, '_burza_posledni_potvrzeni', true );
	$pocet    = (int) get_post_meta( $post_id, '_burza_pocet_vyzev', true );
	$prvni    = skaut_burza_dny_do_prvni_vyzvy() * DAY_IN_SECONDS;
	$dalsi    = skaut_burza_dny_mezi_vyzvami() * DAY_IN_SECONDS;
	$zbyva    = max( 0, skaut_burza_max_vyzev() - $pocet );

	return $posledni + ( 0 === $pocet ? $prvni : $dalsi ) + $zbyva * $dalsi;
}

/**
 * Kolik celých dní zbývá do archivace (0 = nejbližší denní kontrola).
 */
function skaut_burza_dni_do_archivace( int $post_id ): int {
	return max( 0, (int) ceil( ( skaut_burza_konec_zverejneni( $post_id ) - time() ) / DAY_IN_SECONDS ) );
}

/**
 * Kolik celých dní zbývá do smazání archivovaného inzerátu (viz
 * skaut_burza_mesicni_uklid() — 6 měsíců od přesunu do archivu).
 */
function skaut_burza_dni_do_smazani( WP_Post $post ): int {
	$smazani = strtotime( $post->post_modified_gmt . ' UTC +6 months' );
	return max( 0, (int) ceil( ( $smazani - time() ) / DAY_IN_SECONDS ) );
}

/**
 * "1 den", "3 dny", "5 dní" — české skloňování počtu dní.
 */
function skaut_burza_dny_text( int $pocet ): string {
	if ( 1 === $pocet ) {
		$tvar = __( 'den', 'skaut-burza' );
	} elseif ( $pocet >= 2 && $pocet <= 4 ) {
		$tvar = __( 'dny', 'skaut-burza' );
	} else {
		$tvar = __( 'dní', 'skaut-burza' );
	}
	return $pocet . ' ' . $tvar;
}

function skaut_burza_pridat_cron_interval( array $schedules ): array {
	if ( ! isset( $schedules['skaut_burza_mesicne'] ) ) {
		$schedules['skaut_burza_mesicne'] = [
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Jednou měsíčně (úklid burzy)', 'skaut-burza' ),
		];
	}
	return $schedules;
}

function skaut_burza_naplanovat_cron(): void {
	if ( ! wp_next_scheduled( 'skaut_burza_kontrola' ) ) {
		wp_schedule_event( time(), 'daily', 'skaut_burza_kontrola' );
	}
	if ( ! wp_next_scheduled( 'skaut_burza_uklid' ) ) {
		wp_schedule_event( time(), 'skaut_burza_mesicne', 'skaut_burza_uklid' );
	}
}

function skaut_burza_odplanovat_cron(): void {
	wp_clear_scheduled_hook( 'skaut_burza_kontrola' );
	wp_clear_scheduled_hook( 'skaut_burza_uklid' );
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

		// I na poslední výzvu musí být čas odpovědět — archivace až po
		// dalším intervalu, ne hned při nejbližší denní kontrole.
		if ( $pocet >= $max_vyzev && $stari >= $dalsi_limit ) {
			skaut_burza_archivovat( $post_id );
			skaut_burza_odeslat_info_archivace( $post_id );
		}
	}
}

/**
 * Měsíční úklid — archivované inzeráty starší 6 měsíců smazat i s fotkami.
 * "Starší" se počítá od post_modified, protože přesun do archivu (ať už
 * ruční přes [burza_moje], nebo cronem) vždy tenhle sloupec aktualizuje.
 */
function skaut_burza_mesicni_uklid(): void {
	$dotaz = new WP_Query( [
		'post_type'      => 'burza_inzerat',
		'post_status'    => 'burza_archiv',
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
