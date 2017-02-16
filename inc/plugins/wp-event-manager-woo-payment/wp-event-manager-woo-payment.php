<?php
/*
  Plugin Name: Thim Events - WooCommerce Payment Methods Integration
  Plugin URI: http://thimpress.com/
  Description: Support paying for a booking with the payment methods provided by Woocommerce
  Author: ThimPress
  Version: 1.0
  Author URI: http://thimpress.com/
  Requires at least: 3.8
  Tested up to: 4.7.2

  Text Domain: tp-event-woo
  Domain Path: /languages/
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit;

/*
 * Class TP_Event_Woo
 */

class TP_Event_Woo {

	/**
	 * Hold the instance of TP_Event_Woo
	 *
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * Check woo payment activated
	 *
	 * @var bool
	 */
	protected static $_wc_loaded = false;

	/**
	 * Notice for error
	 *
	 * @var
	 */
	protected static $_notice;

	/**
	 * TP_Event_Woo constructor
	 */
	public function __construct() {

		// define constants
		$this->define_constants();
		// load text domain
		$this->load_text_domain();

		if ( self::$_wc_loaded ) {
			require_once WP_EVENT_WOO_INC . "/class-event-wc-settings.php";
			require_once WP_EVENT_WOO_INC . "/class-event-wc-product.php";
			require_once WP_EVENT_WOO_INC . "/class-event-wc-checkout.php";
			require_once WP_EVENT_WOO_INC . "/class-event-wc-payment.php";

			$this->init_hook();
		}

	}

	public function init_hook() {
		// add event as woocommerce product
		add_filter( 'woocommerce_product_class', array( $this, 'event_product_class' ), 10, 4 );
		// add event product to cart
		add_action( 'tp_event_register_event_action', array( $this, 'add_event_to_woo_cart' ), 1 );
		// update booking event status
		add_action( 'woocommerce_order_status_changed', array( $this, 'woocommerce_order_status_changed' ), 10, 3 );
		// disable paypal when activate woo
		add_filter( 'tp_event_enable_paypal_payment', array( $this, 'disable_paypal_checkout' ), 10, 1 );

		add_filter( 'tp_event_get_currency', array( $this, 'tp_event_get_currency' ), 50 );

		add_filter( 'thimpress_event_l18n', array( $this, 'tp_event_woo_l18n' ), 1 );


	}

	// filter woo currency
	public function tp_event_get_currency( $currency ) {
		return get_woocommerce_currency();
	}


	/**
	 * Disable paypal checkout
	 *
	 * @param $enable
	 *
	 * @return bool
	 */
	public function disable_paypal_checkout( $enable ) {
		if ( get_option( 'thimpress_events_woo_payment_enable' ) == 'yes' ) {
			return false;
		}
		return true;
	}


	/**
	 * Update l18n
	 *
	 * @param $args
	 *
	 * @return array
	 */
	public function tp_event_woo_l18n( $args ) {
		$l18n = array(
			'add_to_cart'  => __( ' has been added to your cart.', 'wp-event-woo' ),
			'woo_cart_url' => sprintf( '<a href="%s" class="button wc-forward">%s</a>', esc_url( wc_get_page_permalink( 'cart' ) ), esc_html__( 'View Cart', 'wp-event-woo' ) )
		);
		return array_merge( $args, $l18n );
	}

	/**
	 *  Add event to woo cart
	 *
	 * @param $args
	 */
	public function add_event_to_woo_cart( $args ) {
		WC()->cart->add_to_cart( $args['event_id'], $args['qty'] );
	}

	/**
	 * Add event product class to woocommerce
	 *
	 * @param $classname
	 * @param $product_type
	 * @param $post_type
	 * @param $product_id
	 *
	 * @return string
	 */
	public function event_product_class( $classname, $product_type, $post_type, $product_id ) {
		if ( $post_type == 'tp_event' ) {
			$classname = 'TP_Event_WC_Product';
		}
		return $classname;
	}

	/**
	 * Change event booking status when change woocommerce order status
	 *
	 * @param $order_id
	 * @param $old_status
	 * @param $new_status
	 */
	public function woocommerce_order_status_changed( $order_id, $old_status, $new_status ) {
		$event_booking_id = get_post_meta( $order_id, '_tp_event_event_order', true );
		if ( $event_booking_id ) {
			if ( in_array( $new_status, array( 'completed', 'pending', 'processing', 'cancelled' ) ) ) {
				TP_Event_Booking::instance( $event_booking_id )->update_status( 'ea-' . $new_status );
			} else {
				TP_Event_Booking::instance( $event_booking_id )->update_status( 'ea-pending' );
			}
		}
	}

	/**
	 * Define Plugins Constants
	 */
	public function define_constants() {
		define( 'WP_EVENT_WOO_PATH', plugin_dir_path( __FILE__ ) );
		define( 'WP_EVENT_WOO_URI', plugin_dir_url( __FILE__ ) );
		define( 'WP_EVENT_WOO_INC', WP_EVENT_WOO_PATH . 'inc/' );
		define( 'WP_EVENT_WOO_INC_URI', WP_EVENT_WOO_URI . 'inc/' );
		define( 'WP_EVENT_WOO_VER', '1.0' );
		define( 'WP_EVENT_WOO_REQUIRE_VER', '2.0' );
		define( 'WP_EVENT_WOO_MAIN_FILE', __FILE__ );
	}

	/**
	 * Load text domain
	 */
	public function load_text_domain() {
		// Get mo file
		$text_domain = 'wp-event-woo';
		$locale      = apply_filters( 'plugin_locale', get_locale(), $text_domain );
		$mo_file     = $text_domain . '-' . $locale . '.mo';
		// Check mo file global
		$mo_global = WP_LANG_DIR . '/plugins/' . $mo_file;
		// Load translate file
		if ( file_exists( $mo_global ) ) {
			load_textdomain( $text_domain, $mo_global );
		} else {
			load_textdomain( $text_domain, WP_EVENT_WOO_PATH . '/languages/' . $mo_file );
		}
	}

	/**
	 * Plugin load
	 */
	public static function load() {

		if ( !function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		// check TP Event plugin activated
		if ( class_exists( 'TP_Event' ) && is_plugin_active( 'wp-event-manager/wp-event-manager.php' ) ) {
			if ( TP_EVENT_VER < 2 || !TP_EVENT_VER ) {
				self::$_wc_loaded = false;
				self::$_notice    = 'required_update_tp_event';
			} else {
				self::$_wc_loaded = true;
			}
		} else {
			self::$_notice = 'required_active_tp_event';
		}

		// check Woocommerce activated
		if ( self::$_wc_loaded && is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			self::$_wc_loaded = true;
		} else {
			self::$_wc_loaded = false;
			if ( !self::$_notice ) {
				self::$_notice = 'required_active_woo';
			}
		}

		TP_Event_Woo::instance();

		if ( !self::$_wc_loaded ) {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		}

	}


	/*
	 * Show admin notice when active plugin
	 */
	public static function admin_notice() {
		?>
        <div class="error">
			<?php
			switch ( self::$_notice ) {
				case 'required_active_tp_event':
					echo '<p>' . __( wp_kses( '<strong>Thim Events - WooCommerce Payment Methods Integration</strong> requires <strong>Thim Events</strong> is activated. Please install and active it before you can using this add-on.', array( 'strong' => array() ) ), 'wp-event-woo' ) . '</p>';
					break;
				case 'required_update_tp_event':
					echo '<p>' . sprintf( __( wp_kses( '<strong>Thim Events - WooCommerce Payment Methods Integration</strong> requires <strong>Thim Event</strong> version <strong>%s</strong> or higher.', array( 'strong' => array() ), 'wp-event-woo' ) ), WP_EVENT_WOO_REQUIRE_VER ) . '</p>';
					break;
				case'required_active_woo':
					echo '<p>' . sprintf( __( wp_kses( 'Thim Events - WooCommerce Payment Methods Integration requires <a href="%s">WooCommerce</a> is activated. Please install and active it before you can using this add-on.', array( 'a' => array( 'href' => array() ) ) ), 'wp-event-woo' ), 'http://wordpress.org/plugins/woocommerce' ) . '</p>';
					break;
			} ?>
        </div>
		<?php
	}

	/**
	 * TP_Event_Woo instance
	 *
	 * @return null|TP_Event_Woo
	 */
	public static function instance() {
		if ( !self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

}

add_action( 'plugins_loaded', array( 'TP_Event_Woo', 'load' ) );