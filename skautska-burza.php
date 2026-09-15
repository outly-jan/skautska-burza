<?php
/**
 * Plugin Name: Skautská burza
 * Description: Burza použitého skautského oblečení a vybavení. Středisko Chlumec nad Cidlinou.
 * Version: 1.0.0
 * Text Domain: skaut-burza
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SKAUT_BURZA_VERSION', '1.0.0' );
define( 'SKAUT_BURZA_FILE', __FILE__ );
define( 'SKAUT_BURZA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SKAUT_BURZA_URL', plugin_dir_url( __FILE__ ) );
define( 'SKAUT_BURZA_RATE_LIMIT_SEKUND', 30 );

require_once SKAUT_BURZA_DIR . 'includes/cpt.php';
require_once SKAUT_BURZA_DIR . 'includes/meta.php';
require_once SKAUT_BURZA_DIR . 'includes/uploads.php';
require_once SKAUT_BURZA_DIR . 'includes/shortcode-formular.php';
require_once SKAUT_BURZA_DIR . 'includes/template.php';
require_once SKAUT_BURZA_DIR . 'includes/visibility.php';
require_once SKAUT_BURZA_DIR . 'includes/shortcode-vypis.php';
require_once SKAUT_BURZA_DIR . 'includes/shortcode-moje.php';
require_once SKAUT_BURZA_DIR . 'includes/email.php';
require_once SKAUT_BURZA_DIR . 'includes/cron.php';

final class SkautBurza {

	public function __construct() {
		register_activation_hook( SKAUT_BURZA_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( SKAUT_BURZA_FILE, [ $this, 'deactivate' ] );

		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'init', 'skaut_burza_register_post_type' );
		add_action( 'init', 'skaut_burza_register_taxonomy' );
		add_action( 'init', 'skaut_burza_register_post_statuses' );
		add_action( 'init', 'skaut_burza_seed_kategorie', 20 );
		add_action( 'init', 'skaut_burza_register_meta', 20 );

		add_shortcode( 'burza_formular', 'skaut_burza_shortcode_formular' );
		add_action( 'template_redirect', 'skaut_burza_handle_formular_submit' );

		add_shortcode( 'burza_vypis', 'skaut_burza_shortcode_vypis' );

		add_filter( 'template_include', 'skaut_burza_template_include' );
		add_action( 'wp_enqueue_scripts', 'skaut_burza_enqueue_detail_assets' );

		add_action( 'wp_ajax_skaut_burza_kontakt', 'skaut_burza_ajax_kontakt' );
		add_action( 'wp_ajax_nopriv_skaut_burza_kontakt', 'skaut_burza_ajax_kontakt' );

		add_action( 'pre_get_posts', 'skaut_burza_vyloucit_z_feedu' );
		add_filter( 'wp_sitemaps_post_types', 'skaut_burza_vyloucit_ze_sitemapy' );

		add_shortcode( 'burza_moje', 'skaut_burza_shortcode_moje' );
		add_action( 'template_redirect', 'skaut_burza_handle_moje_akce' );
		add_action( 'save_post', 'skaut_burza_vycistit_stranka_cache' );

		add_action( 'template_redirect', 'skaut_burza_handle_potvrzovaci_endpoint' );
		add_action( 'skaut_burza_kontrola', 'skaut_burza_denni_kontrola' );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'skaut-burza', false, dirname( plugin_basename( SKAUT_BURZA_FILE ) ) . '/languages' );
	}

	public function activate(): void {
		skaut_burza_register_post_type();
		skaut_burza_register_taxonomy();
		skaut_burza_register_post_statuses();
		skaut_burza_seed_kategorie();
		skaut_burza_naplanovat_cron();
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		skaut_burza_odplanovat_cron();
		flush_rewrite_rules();
	}
}

new SkautBurza();
