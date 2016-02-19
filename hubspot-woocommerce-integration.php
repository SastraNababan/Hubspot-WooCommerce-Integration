<?php
/* 
  Plugin Name: Hubspot Woocommerce Integration
  Plugin URI: https://sastranababan.com
  Description: Woocommerce Hubspot Integration
  Version: 1.0.0
  Author: Sastra Nababan
  Author URI: http://www.sastranababan.com
  License: GPL V3
 */

class Hubspot_Woocommerce_Integration {
	private static $instance = null;
	private $plugin_path;
	private $plugin_url;
    private $text_domain = 'hubspot-woocommerce-integration';
    private $woo_activated;
    protected $plugin_name;
    protected $version;
    public $hubspot_api;
    public $client_id;

	/**
	 * Creates or returns an instance of this class.
	 */
	public static function get_instance() {
		// If an instance hasn't been created and set to $instance create an instance and set it to $instance.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Initializes the plugin by setting localization, hooks, filters, and administrative functions.
	 */
	private function __construct() {
		$this->plugin_name = 'hubspot-woocommerce-integration';
		$this->version = '1.0.0';
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->plugin_url  = plugin_dir_url( __FILE__ );

		$this->client_id 		  ='3765607d-c88e-11e5-a2bf-bd028721085c';	

		$this->load_dependencies();

		load_plugin_textdomain( $this->text_domain, false, $this->plugin_path . '\lang' );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_register_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_register_styles' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ) );

		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

		$this->run_plugin();
	}

	private function load_dependencies() {
		include_once ($this->plugin_path.'includes/class-hubspot-api.php');
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}	

	public function init() {
		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			// Include our integration class.
			include_once 'includes/class-hubspot-settings.php';

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_hubspot_settings' ) );
		} else {
			echo 'Hubspot Woocommerce Integration plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugins to be active!';
		}
	}

	public function add_hubspot_settings( $integrations ) {
		$integrations[] = 'hubspot_settings';
		return $integrations;
	}

	public function woo_not_installed(){
 	?>
    <div class="error">
        <p><?php _e( 'Hubspot Woocommerce Integration plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugins to be active!', 'my-text-domain' ); ?></p>
    </div>
    <?php
	}

	public function get_plugin_url() {
		return $this->plugin_url;
	}

	public function get_plugin_path() {
		return $this->plugin_path;
	}


    public function activation() {
			/**
			* Check if WooCommerce are active
			**/
			if (!in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) {
				
				// Deactivate the plugin
				deactivate_plugins(__FILE__);
				
				// Throw an error in the wordpress admin console
				$error_message = __('Hubspot Woocommerce Integration plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugins to be active!', 'woocommerce');
				die($error_message);
				
			}    	
	}


    public function deactivation() {

	}


    public function admin_register_scripts() {
		wp_enqueue_script( $this->plugin_name, $this->plugin_url. 'admin/admin.js', array( 'jquery' ), $this->version, false );
		wp_localize_script( $this->plugin_name, 'hubwooAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}


    public function admin_register_styles() {
    	wp_enqueue_style( $this->plugin_name, $this->plugin_url. 'admin/admin.css');

	}


    public function register_scripts() {

	}


    public function register_styles() {

	}


    private function run_plugin() {

	}
}
Hubspot_Woocommerce_Integration::get_instance();