<?php
/**
 * Plugin Name: Skautská burza
 * Description: Burza použitého skautského oblečení a vybavení. Středisko Chlumec nad Cidlinou.
 * Version: 1.0.8
 * Text Domain: skaut-burza
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SKAUT_BURZA_VERSION', '1.0.8' );
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
require_once SKAUT_BURZA_DIR . 'includes/admin.php';
require_once SKAUT_BURZA_DIR . 'includes/privacy.php';

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

		add_action( 'template_redirect', 'skaut_burza_handle_potvrzovaci_endpoint' );
		add_filter( 'cron_schedules', 'skaut_burza_pridat_cron_interval' );
		add_action( 'skaut_burza_kontrola', 'skaut_burza_denni_kontrola' );
		add_action( 'skaut_burza_uklid', 'skaut_burza_mesicni_uklid' );

		add_filter( 'manage_burza_inzerat_posts_columns', 'skaut_burza_admin_sloupce' );
		add_action( 'manage_burza_inzerat_posts_custom_column', 'skaut_burza_admin_sloupec_obsah', 10, 2 );
		add_filter( 'manage_edit-burza_inzerat_sortable_columns', 'skaut_burza_admin_sortable_sloupce' );

		add_action( 'restrict_manage_posts', 'skaut_burza_admin_filtr_kategorie' );
		add_action( 'pre_get_posts', 'skaut_burza_admin_filtr_a_razeni_dotaz' );

		add_filter( 'bulk_actions-edit-burza_inzerat', 'skaut_burza_admin_bulk_actions' );
		add_filter( 'handle_bulk_actions-edit-burza_inzerat', 'skaut_burza_admin_handle_bulk_actions', 10, 3 );
		add_action( 'admin_notices', 'skaut_burza_admin_bulk_notice' );

		add_action( 'admin_menu', 'skaut_burza_admin_menu' );
		add_action( 'admin_init', 'skaut_burza_register_settings' );

		add_filter( 'wp_privacy_personal_data_exporters', 'skaut_burza_register_privacy_exporter' );
		add_filter( 'wp_privacy_personal_data_erasers', 'skaut_burza_register_privacy_eraser' );
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
