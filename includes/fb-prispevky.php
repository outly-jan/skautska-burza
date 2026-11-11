<?php
/**
 * Příspěvky z facebookové stránky střediska — náhrada pluginu Custom Facebook Feed.
 *
 * Shortcode [skaut_fb_prispevky pocet="5"] vypíše poslední příspěvky stránky:
 * text, fotky (galerie s prohlížečem), videa (přímo přehrávatelná) a sdílené odkazy.
 * Data se berou z Facebook Graph API s trvalým tokenem stránky a ukládají se
 * do cache (transient); když Facebook neodpoví, ukáže se poslední úspěšná verze.
 *
 * Modul je samostatný (vlastní hooky, nastavení i assety) — dá se kdykoli
 * vyjmout do vlastního pluginu.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SKAUT_FB_VYCHOZI_STRANKA', '231860936668964' ); // Skaut Chlumec
define( 'SKAUT_FB_GRAPH', 'https://graph.facebook.com/' );

add_shortcode( 'skaut_fb_prispevky', 'skaut_fb_shortcode' );
add_action( 'wp_enqueue_scripts', 'skaut_fb_registrovat_assety' );
add_action( 'admin_menu', 'skaut_fb_admin_menu' );
add_action( 'admin_init', 'skaut_fb_registrovat_nastaveni' );
add_action( 'admin_post_skaut_fb_ziskat_token', 'skaut_fb_akce_ziskat_token' );
add_action( 'admin_post_skaut_fb_test', 'skaut_fb_akce_test' );

// ── KONFIGURACE ───────────────────────────────────────────────────────

function skaut_fb_graph_verze(): string {
	return (string) apply_filters( 'skaut_fb_graph_verze', 'v24.0' );
}

function skaut_fb_id_stranky(): string {
	$id = preg_replace( '/\D/', '', (string) get_option( 'skaut_fb_stranka_id', '' ) );
	return $id !== '' ? $id : SKAUT_FB_VYCHOZI_STRANKA;
}

function skaut_fb_token(): string {
	return trim( (string) get_option( 'skaut_fb_token', '' ) );
}

function skaut_fb_cache_minut(): int {
	return max( 5, (int) get_option( 'skaut_fb_cache_minut', 60 ) );
}

// ── GRAPH API ─────────────────────────────────────────────────────────

/**
 * GET na Graph API. Vrací dekódované JSON pole, nebo WP_Error se zprávou od Facebooku.
 */
function skaut_fb_graph_get( string $cesta, array $parametry ) {
	$url = SKAUT_FB_GRAPH . skaut_fb_graph_verze() . '/' . ltrim( $cesta, '/' );
	$url = add_query_arg( array_map( 'rawurlencode', $parametry ), $url );

	$odpoved = wp_remote_get( $url, [ 'timeout' => 15 ] );
	if ( is_wp_error( $odpoved ) ) {
		return $odpoved;
	}

	$data = json_decode( wp_remote_retrieve_body( $odpoved ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'skaut_fb', sprintf( 'Neplatná odpověď Facebooku (HTTP %d).', wp_remote_retrieve_response_code( $odpoved ) ) );
	}
	if ( isset( $data['error'] ) ) {
		$zprava = $data['error']['message'] ?? 'neznámá chyba';
		$kod    = $data['error']['code'] ?? 0;
		return new WP_Error( 'skaut_fb', sprintf( 'Facebook: %s (kód %s)', $zprava, $kod ) );
	}
	return $data;
}

/**
 * Stáhne stránku (jméno, profilovka) a posledních $pocet příspěvků v normalizované podobě.
 */
function skaut_fb_stahnout( int $pocet ) {
	$token = skaut_fb_token();
	if ( $token === '' ) {
		return new WP_Error( 'skaut_fb', 'Není nastavený přístupový token (Nastavení → Facebook příspěvky).' );
	}
	$stranka_id = skaut_fb_id_stranky();

	$stranka = skaut_fb_graph_get( $stranka_id, [
		'fields'       => 'name,link,picture.width(120).height(120){url}',
		'access_token' => $token,
	] );
	if ( is_wp_error( $stranka ) ) {
		return $stranka;
	}

	$prispevky = skaut_fb_graph_get( $stranka_id . '/posts', [
		'fields'       => 'id,message,created_time,permalink_url,full_picture,'
			. 'attachments{media_type,type,url,unshimmed_url,title,description,media,'
			. 'subattachments.limit(50){media_type,type,url,media}}',
		'limit'        => (string) $pocet,
		'access_token' => $token,
	] );
	if ( is_wp_error( $prispevky ) ) {
		return $prispevky;
	}

	$vysledek = [
		'stranka'   => [
			'jmeno'  => (string) ( $stranka['name'] ?? '' ),
			'odkaz'  => (string) ( $stranka['link'] ?? 'https://www.facebook.com/' . $stranka_id ),
			'avatar' => (string) ( $stranka['picture']['data']['url'] ?? '' ),
		],
		'prispevky' => [],
	];
	foreach ( (array) ( $prispevky['data'] ?? [] ) as $p ) {
		$normalizovany = skaut_fb_normalizovat_prispevek( $p );
		if ( $normalizovany['text'] !== '' || $normalizovany['media'] || $normalizovany['sdileni'] ) {
			$vysledek['prispevky'][] = $normalizovany;
		}
	}
	return $vysledek;
}

/** Jeden obrázek/video z přílohy Graph API, nebo null. */
function skaut_fb_polozka_media( array $priloha ): ?array {
	$obrazek = $priloha['media']['image'] ?? null;
	$zdroj   = (string) ( $priloha['media']['source'] ?? '' );
	$typ     = (string) ( $priloha['type'] ?? '' );

	if ( ( $priloha['media_type'] ?? '' ) === 'video' && strpos( $typ, 'video_share' ) !== 0 ) {
		// Přímo přehrát jde jen video uložené na Facebooku (mp4 z fbcdn);
		// u ostatních se ukáže náhled s odkazem na příspěvek.
		$prehratelne = $zdroj !== '' && preg_match( '#^https://[^/]*(fbcdn\.net|facebook\.com)/#', $zdroj ) && strpos( $zdroj, '.mp4' ) !== false;
		if ( ! $prehratelne && empty( $obrazek['src'] ) ) {
			return null;
		}
		return [
			'typ'   => 'video',
			'src'   => (string) ( $obrazek['src'] ?? '' ),
			'w'     => (int) ( $obrazek['width'] ?? 0 ),
			'h'     => (int) ( $obrazek['height'] ?? 0 ),
			'video' => $prehratelne ? $zdroj : '',
			'odkaz' => (string) ( $priloha['url'] ?? '' ),
		];
	}

	if ( ! empty( $obrazek['src'] ) ) {
		return [
			'typ'   => 'foto',
			'src'   => (string) $obrazek['src'],
			'w'     => (int) ( $obrazek['width'] ?? 0 ),
			'h'     => (int) ( $obrazek['height'] ?? 0 ),
			'video' => '',
			'odkaz' => (string) ( $priloha['url'] ?? '' ),
		];
	}
	return null;
}

function skaut_fb_normalizovat_prispevek( array $p ): array {
	$media   = [];
	$sdileni = null;
	$popis   = '';

	foreach ( (array) ( $p['attachments']['data'] ?? [] ) as $priloha ) {
		$podprilohy = (array) ( $priloha['subattachments']['data'] ?? [] );
		if ( $podprilohy ) {
			foreach ( $podprilohy as $pod ) {
				$polozka = skaut_fb_polozka_media( (array) $pod );
				if ( $polozka ) $media[] = $polozka;
			}
			continue;
		}

		$typ_media = (string) ( $priloha['media_type'] ?? '' );
		$typ       = (string) ( $priloha['type'] ?? '' );
		if ( in_array( $typ_media, [ 'link', 'event' ], true ) || strpos( $typ, 'video_share' ) === 0 ) {
			$url = (string) ( $priloha['unshimmed_url'] ?? $priloha['url'] ?? '' );
			if ( $url !== '' ) {
				$sdileni = [
					'url'     => $url,
					'titulek' => (string) ( $priloha['title'] ?? '' ),
					'popis'   => (string) ( $priloha['description'] ?? '' ),
					'obrazek' => (string) ( $priloha['media']['image']['src'] ?? '' ),
				];
			}
			continue;
		}

		if ( $popis === '' ) {
			$popis = trim( (string) ( $priloha['description'] ?? '' ) );
		}
		$polozka = skaut_fb_polozka_media( (array) $priloha );
		if ( $polozka ) $media[] = $polozka;
	}

	if ( ! $media && ! $sdileni && ! empty( $p['full_picture'] ) ) {
		$media[] = [ 'typ' => 'foto', 'src' => (string) $p['full_picture'], 'w' => 0, 'h' => 0, 'video' => '', 'odkaz' => '' ];
	}

	// Sdílený příspěvek jiné stránky bez vlastního komentáře nemá message —
	// jeho text je v popisu přílohy, zobrazí se jako citace.
	$text   = trim( (string) ( $p['message'] ?? '' ) );
	$citace = $text === '' && $popis !== '';

	return [
		'id'      => (string) ( $p['id'] ?? '' ),
		'text'    => $citace ? $popis : $text,
		'citace'  => $citace,
		'cas'     => strtotime( (string) ( $p['created_time'] ?? '' ) ) ?: 0,
		'odkaz'   => (string) ( $p['permalink_url'] ?? '' ),
		'media'   => $media,
		'sdileni' => $sdileni,
	];
}

/**
 * Data pro výpis: z cache, jinak čerstvě z Facebooku; při chybě poslední
 * úspěšná verze (záloha v options), aby výpis z webu nezmizel.
 */
function skaut_fb_data( int $pocet ) {
	$klic = 'skaut_fb_' . md5( skaut_fb_id_stranky() . '|' . $pocet . '|' . skaut_fb_graph_verze() );
	$data = get_transient( $klic );
	if ( is_array( $data ) ) {
		return $data;
	}

	$data = skaut_fb_stahnout( $pocet );
	if ( ! is_wp_error( $data ) ) {
		set_transient( $klic, $data, skaut_fb_cache_minut() * MINUTE_IN_SECONDS );
		update_option( $klic . '_zaloha', $data, false );
		delete_option( 'skaut_fb_posledni_chyba' );
		return $data;
	}

	update_option( 'skaut_fb_posledni_chyba', [ 'cas' => time(), 'zprava' => $data->get_error_message() ], false );
	$zaloha = get_option( $klic . '_zaloha' );
	if ( is_array( $zaloha ) ) {
		// Nezkoušet Facebook při každém zobrazení stránky, dokud je nedostupný.
		set_transient( $klic, $zaloha, 5 * MINUTE_IN_SECONDS );
		return $zaloha;
	}
	return $data;
}

function skaut_fb_smazat_cache(): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_skaut\\_fb\\_%' OR option_name LIKE '\\_transient\\_timeout\\_skaut\\_fb\\_%'" );
}

// ── VÝPIS ─────────────────────────────────────────────────────────────

function skaut_fb_registrovat_assety(): void {
	wp_register_style( 'skaut-fb', SKAUT_BURZA_URL . 'assets/css/fb-prispevky.css', [], SKAUT_BURZA_VERSION );
	wp_register_script( 'skaut-fb', SKAUT_BURZA_URL . 'assets/js/fb-prispevky.js', [], SKAUT_BURZA_VERSION, true );
}

function skaut_fb_shortcode( $atts ): string {
	$atts  = shortcode_atts( [ 'pocet' => 5 ], $atts, 'skaut_fb_prispevky' );
	$pocet = min( 25, max( 1, (int) $atts['pocet'] ) );

	$data = skaut_fb_data( $pocet );
	if ( is_wp_error( $data ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p class="skaut-fb-chyba">' . esc_html( $data->get_error_message() ) . '</p>';
		}
		return '';
	}

	wp_enqueue_style( 'skaut-fb' );
	wp_enqueue_script( 'skaut-fb' );

	$html = '<div class="skaut-fb">';
	foreach ( $data['prispevky'] as $prispevek ) {
		$html .= skaut_fb_render_prispevek( $prispevek, $data['stranka'] );
	}
	$html .= '</div>';
	return $html;
}

function skaut_fb_render_prispevek( array $p, array $stranka ): string {
	ob_start();
	?>
	<article class="skaut-fb-prispevek">
		<header class="skaut-fb-hlavicka">
			<?php if ( $stranka['avatar'] ) : ?>
				<img class="skaut-fb-avatar" src="<?php echo esc_url( $stranka['avatar'] ); ?>" alt="" width="44" height="44" loading="lazy">
			<?php endif; ?>
			<div>
				<a class="skaut-fb-jmeno" href="<?php echo esc_url( $stranka['odkaz'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $stranka['jmeno'] ); ?></a>
				<?php if ( $p['cas'] ) : ?>
					<a class="skaut-fb-datum" href="<?php echo esc_url( $p['odkaz'] ); ?>" target="_blank" rel="noopener"><time datetime="<?php echo esc_attr( gmdate( 'c', $p['cas'] ) ); ?>"><?php echo esc_html( wp_date( 'j. n. Y', $p['cas'] ) ); ?></time></a>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( $p['text'] !== '' ) : ?>
			<div class="skaut-fb-text<?php echo empty( $p['citace'] ) ? '' : ' je-citace'; ?>"><?php echo skaut_fb_format_text( $p['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno uvnitř ?></div>
		<?php endif; ?>

		<?php echo skaut_fb_render_media( $p ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<?php if ( $p['sdileni'] ) : $s = $p['sdileni']; ?>
			<a class="skaut-fb-sdileni" href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener">
				<?php if ( $s['obrazek'] ) : ?>
					<img src="<?php echo esc_url( $s['obrazek'] ); ?>" alt="" loading="lazy">
				<?php endif; ?>
				<span class="skaut-fb-sdileni-text">
					<span class="skaut-fb-sdileni-domena"><?php echo esc_html( preg_replace( '/^www\./', '', (string) wp_parse_url( $s['url'], PHP_URL_HOST ) ) ); ?></span>
					<?php if ( $s['titulek'] ) : ?><strong><?php echo esc_html( $s['titulek'] ); ?></strong><?php endif; ?>
					<?php if ( $s['popis'] ) : ?><span class="skaut-fb-sdileni-popis"><?php echo esc_html( wp_trim_words( $s['popis'], 30 ) ); ?></span><?php endif; ?>
				</span>
			</a>
		<?php endif; ?>

		<?php if ( $p['odkaz'] ) : ?>
			<footer class="skaut-fb-paticka">
				<a href="<?php echo esc_url( $p['odkaz'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Zobrazit na Facebooku', 'skaut-burza' ); ?></a>
			</footer>
		<?php endif; ?>
	</article>
	<?php
	return (string) ob_get_clean();
}

/** Text příspěvku: escapovat, proklikávací odkazy, zachovat odstavce. */
function skaut_fb_format_text( string $text ): string {
	$html = make_clickable( esc_html( $text ) );
	$html = str_replace( '<a href=', '<a target="_blank" rel="noopener nofollow" href=', $html );
	return nl2br( $html );
}

function skaut_fb_render_media( array $p ): string {
	$media = $p['media'];
	$pocet = count( $media );
	if ( ! $pocet ) return '';

	// Jediné video: rovnou přehrávač.
	if ( $pocet === 1 && $media[0]['typ'] === 'video' ) {
		$m = $media[0];
		if ( $m['video'] ) {
			return sprintf(
				'<div class="skaut-fb-media skaut-fb-media-1"><video class="skaut-fb-video" controls playsinline preload="none"%s%s src="%s"></video></div>',
				$m['src'] ? ' poster="' . esc_url( $m['src'] ) . '"' : '',
				( $m['w'] && $m['h'] ) ? ' style="aspect-ratio:' . (int) $m['w'] . '/' . (int) $m['h'] . '"' : '',
				esc_url( $m['video'] )
			);
		}
		if ( ! $m['src'] ) return '';
		return sprintf(
			'<div class="skaut-fb-media skaut-fb-media-1"><a class="skaut-fb-dlazdice je-video" href="%s" target="_blank" rel="noopener"><img src="%s" alt="" loading="lazy"%s></a></div>',
			esc_url( $m['odkaz'] ?: $p['odkaz'] ),
			esc_url( $m['src'] ),
			skaut_fb_rozmery_attr( $m )
		);
	}

	$viditelnych = min( $pocet, 4 );
	$html        = '<div class="skaut-fb-media skaut-fb-media-' . $viditelnych . '">';
	foreach ( $media as $i => $m ) {
		$skryta = $i >= $viditelnych;
		$tridy  = 'skaut-fb-dlazdice' . ( $m['typ'] === 'video' ? ' je-video' : '' );
		if ( $m['typ'] === 'video' && ! $m['video'] ) {
			// Video, které nejde přehrát přímo: odkaz na Facebook, mimo prohlížeč.
			$atributy = sprintf(
				' class="%s" href="%s" target="_blank" rel="noopener"%s',
				esc_attr( $tridy ),
				esc_url( $m['odkaz'] ?: $p['odkaz'] ),
				$skryta ? ' hidden' : ''
			);
		} else {
			$atributy = sprintf(
				' class="%s" href="%s" data-skaut-fb-typ="%s"%s%s',
				esc_attr( $tridy ),
				esc_url( $m['video'] ?: $m['src'] ),
				esc_attr( $m['video'] ? 'video' : 'foto' ),
				( $m['video'] && $m['src'] ) ? ' data-skaut-fb-poster="' . esc_url( $m['src'] ) . '"' : '',
				$skryta ? ' hidden' : ''
			);
		}
		$html .= '<a' . $atributy . '>';
		if ( ! $skryta && $m['src'] ) {
			$html .= '<img src="' . esc_url( $m['src'] ) . '" alt="" loading="lazy"' . skaut_fb_rozmery_attr( $m ) . '>';
		}
		if ( ! $skryta && $i === $viditelnych - 1 && $pocet > $viditelnych ) {
			$html .= '<span class="skaut-fb-dalsi">+' . ( $pocet - $viditelnych ) . '</span>';
		}
		$html .= '</a>';
	}
	$html .= '</div>';
	return $html;
}

function skaut_fb_rozmery_attr( array $m ): string {
	return ( $m['w'] && $m['h'] ) ? sprintf( ' width="%d" height="%d"', $m['w'], $m['h'] ) : '';
}

// ── NASTAVENÍ ─────────────────────────────────────────────────────────

function skaut_fb_admin_menu(): void {
	add_options_page(
		__( 'Facebook příspěvky', 'skaut-burza' ),
		__( 'Facebook příspěvky', 'skaut-burza' ),
		'manage_options',
		'skaut-fb-prispevky',
		'skaut_fb_render_nastaveni'
	);
}

function skaut_fb_registrovat_nastaveni(): void {
	register_setting( 'skaut_fb_nastaveni', 'skaut_fb_stranka_id', [
		'type'              => 'string',
		'sanitize_callback' => static fn( $v ) => preg_replace( '/\D/', '', (string) $v ),
		'default'           => '',
	] );
	register_setting( 'skaut_fb_nastaveni', 'skaut_fb_token', [
		'type'              => 'string',
		// Prázdné pole = token se nemění (uložený token se do formuláře nevypisuje).
		'sanitize_callback' => static function ( $v ) {
			$v = trim( (string) $v );
			return $v === '' ? skaut_fb_token() : sanitize_text_field( $v );
		},
		'default'           => '',
	] );
	register_setting( 'skaut_fb_nastaveni', 'skaut_fb_cache_minut', [
		'type'              => 'integer',
		'sanitize_callback' => static fn( $v ) => max( 5, (int) $v ),
		'default'           => 60,
	] );
	add_action( 'update_option_skaut_fb_token', 'skaut_fb_smazat_cache' );
	add_action( 'update_option_skaut_fb_stranka_id', 'skaut_fb_smazat_cache' );
}

function skaut_fb_presmerovat_zpet( string $typ, string $zprava ): void {
	set_transient( 'skaut_fb_zprava_' . get_current_user_id(), [ 'typ' => $typ, 'zprava' => $zprava ], 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=skaut-fb-prispevky' ) );
	exit;
}

/**
 * Z krátkodobého uživatelského tokenu (Graph API Explorer) udělá trvalý token
 * stránky: výměna za dlouhodobý uživatelský token → /me/accounts → token stránky,
 * který nevyprší. ID a tajný klíč aplikace se nikam neukládají.
 */
function skaut_fb_akce_ziskat_token(): void {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nedostatečná oprávnění.' );
	check_admin_referer( 'skaut_fb_ziskat_token' );

	$app_id     = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['app_id'] ?? '' ) );
	$app_secret = trim( sanitize_text_field( wp_unslash( $_POST['app_secret'] ?? '' ) ) );
	$user_token = trim( sanitize_text_field( wp_unslash( $_POST['user_token'] ?? '' ) ) );
	if ( $app_id === '' || $app_secret === '' || $user_token === '' ) {
		skaut_fb_presmerovat_zpet( 'error', 'Vyplň ID aplikace, tajný klíč i uživatelský token.' );
	}

	$dlouhy = skaut_fb_graph_get( 'oauth/access_token', [
		'grant_type'        => 'fb_exchange_token',
		'client_id'         => $app_id,
		'client_secret'     => $app_secret,
		'fb_exchange_token' => $user_token,
	] );
	if ( is_wp_error( $dlouhy ) ) {
		skaut_fb_presmerovat_zpet( 'error', 'Výměna tokenu selhala — ' . $dlouhy->get_error_message() );
	}

	$ucty = skaut_fb_graph_get( 'me/accounts', [
		'fields'       => 'id,name,access_token',
		'limit'        => '100',
		'access_token' => (string) ( $dlouhy['access_token'] ?? '' ),
	] );
	if ( is_wp_error( $ucty ) ) {
		skaut_fb_presmerovat_zpet( 'error', 'Načtení stránek selhalo — ' . $ucty->get_error_message() );
	}

	$stranka_id = skaut_fb_id_stranky();
	$nazvy      = [];
	foreach ( (array) ( $ucty['data'] ?? [] ) as $ucet ) {
		if ( (string) ( $ucet['id'] ?? '' ) === $stranka_id && ! empty( $ucet['access_token'] ) ) {
			update_option( 'skaut_fb_token', sanitize_text_field( $ucet['access_token'] ), false );
			skaut_fb_smazat_cache();
			skaut_fb_presmerovat_zpet( 'success', sprintf( 'Trvalý token stránky „%s“ je uložený.', $ucet['name'] ?? $stranka_id ) );
		}
		$nazvy[] = ( $ucet['name'] ?? '?' ) . ' (' . ( $ucet['id'] ?? '?' ) . ')';
	}
	skaut_fb_presmerovat_zpet( 'error', sprintf(
		'Token nemá přístup ke stránce %s. Dostupné stránky: %s. Při generování tokenu v Graph API Exploreru je potřeba stránku povolit.',
		$stranka_id,
		$nazvy ? implode( ', ', $nazvy ) : 'žádné'
	) );
}

function skaut_fb_akce_test(): void {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nedostatečná oprávnění.' );
	check_admin_referer( 'skaut_fb_test' );

	skaut_fb_smazat_cache();
	$data = skaut_fb_stahnout( 5 );
	if ( is_wp_error( $data ) ) {
		skaut_fb_presmerovat_zpet( 'error', $data->get_error_message() );
	}
	delete_option( 'skaut_fb_posledni_chyba' );
	skaut_fb_presmerovat_zpet( 'success', sprintf(
		'Funguje: stránka „%s“, načteno %d příspěvků. Cache je vymazaná.',
		$data['stranka']['jmeno'],
		count( $data['prispevky'] )
	) );
}

function skaut_fb_render_nastaveni(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$zprava = get_transient( 'skaut_fb_zprava_' . get_current_user_id() );
	if ( $zprava ) delete_transient( 'skaut_fb_zprava_' . get_current_user_id() );
	$chyba = get_option( 'skaut_fb_posledni_chyba' );
	$token = skaut_fb_token();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Facebook příspěvky', 'skaut-burza' ); ?></h1>

		<?php if ( $zprava ) : ?>
			<div class="notice notice-<?php echo esc_attr( $zprava['typ'] ); ?> is-dismissible"><p><?php echo esc_html( $zprava['zprava'] ); ?></p></div>
		<?php endif; ?>
		<?php if ( is_array( $chyba ) ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( sprintf( 'Poslední chyba (%s): %s', wp_date( 'j. n. Y H:i', $chyba['cas'] ), $chyba['zprava'] ) ); ?></p></div>
		<?php endif; ?>

		<p>Na stránku vlož shortcode <code>[skaut_fb_prispevky pocet="5"]</code> (nejvýš 25 příspěvků).</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'skaut_fb_nastaveni' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="skaut_fb_stranka_id">ID facebookové stránky</label></th>
					<td>
						<input type="text" class="regular-text" id="skaut_fb_stranka_id" name="skaut_fb_stranka_id" value="<?php echo esc_attr( get_option( 'skaut_fb_stranka_id', '' ) ); ?>" placeholder="<?php echo esc_attr( SKAUT_FB_VYCHOZI_STRANKA ); ?>">
						<p class="description">Prázdné = <?php echo esc_html( SKAUT_FB_VYCHOZI_STRANKA ); ?> (Skaut Chlumec).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_fb_token">Přístupový token stránky</label></th>
					<td>
						<input type="password" class="regular-text" id="skaut_fb_token" name="skaut_fb_token" value="" autocomplete="off"
							placeholder="<?php echo esc_attr( $token !== '' ? 'uložený (…' . substr( $token, -6 ) . ')' : 'není nastavený' ); ?>">
						<p class="description">Prázdné pole = ponechat uložený token. Pohodlněji ho získáš formulářem níže.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_fb_cache_minut">Obnovovat po (minutách)</label></th>
					<td><input type="number" min="5" id="skaut_fb_cache_minut" name="skaut_fb_cache_minut" value="<?php echo esc_attr( skaut_fb_cache_minut() ); ?>"></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="skaut_fb_test">
			<?php wp_nonce_field( 'skaut_fb_test' ); ?>
			<?php submit_button( 'Otestovat spojení a vymazat cache', 'secondary', 'submit', false ); ?>
		</form>

		<hr>
		<h2>Získat trvalý token stránky</h2>
		<ol>
			<li>Na <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com/apps</a> vytvoř aplikaci (případ použití „Spravovat vše na stránce“ / typ Firma). Může zůstat v režimu vývoje — stačí, že jsi správce stránky i aplikace.</li>
			<li>V aplikaci v <em>Nastavení aplikace → Základní</em> zkopíruj <strong>ID aplikace</strong> a <strong>Tajný klíč aplikace</strong>.</li>
			<li>Otevři <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a>, vpravo vyber svou aplikaci a „User Token“, přidej oprávnění <code>pages_show_list</code>, <code>pages_read_engagement</code> a <code>pages_read_user_content</code> a klikni na <em>Generate Access Token</em>. V okně Facebooku povol stránku Skaut Chlumec.</li>
			<li>Vygenerovaný token vlož sem i s ID a klíčem aplikace. Plugin z něj získá token stránky, který nevyprší (dokud nezměníš heslo k Facebooku nebo neodebereš aplikaci). ID a tajný klíč se neukládají.</li>
		</ol>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="skaut_fb_ziskat_token">
			<?php wp_nonce_field( 'skaut_fb_ziskat_token' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="skaut_fb_app_id">ID aplikace</label></th>
					<td><input type="text" class="regular-text" id="skaut_fb_app_id" name="app_id" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_fb_app_secret">Tajný klíč aplikace</label></th>
					<td><input type="password" class="regular-text" id="skaut_fb_app_secret" name="app_secret" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skaut_fb_user_token">Uživatelský token z Exploreru</label></th>
					<td><input type="password" class="regular-text" id="skaut_fb_user_token" name="user_token" autocomplete="off"></td>
				</tr>
			</table>
			<?php submit_button( 'Získat a uložit token stránky', 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
