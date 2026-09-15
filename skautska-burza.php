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

require_once SKAUT_BURZA_DIR . 'includes/cpt.php';
require_once SKAUT_BURZA_DIR . 'includes/meta.php';

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
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'skaut-burza', false, dirname( plugin_basename( SKAUT_BURZA_FILE ) ) . '/languages' );
	}

	public function activate(): void {
		skaut_burza_register_post_type();
		skaut_burza_register_taxonomy();
		skaut_burza_register_post_statuses();
		skaut_burza_seed_kategorie();
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}
}

new SkautBurza();
