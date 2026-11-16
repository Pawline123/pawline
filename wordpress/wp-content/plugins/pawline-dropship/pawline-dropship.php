<?php
/**
 * Plugin Name:       Pawline Dropshipping & Belege
 * Description:       Manuelle Lieferantenaufträge zu WooCommerce-Bestellungen, Versandinfos, geschützte Eingangsbelege, Überblick und transparente Auswertung. Bucht keinen Lagerbestand und bestellt nichts automatisch.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Pawline
 * License:           GPL-2.0-or-later
 * Text Domain:       pawline-dropship
 *
 * WC requires at least: 9.0
 *
 * @package Pawline\Dropship
 */

defined( 'ABSPATH' ) || exit;

define( 'PAWLINE_DS_VERSION', '0.1.0' );
define( 'PAWLINE_DS_FILE', __FILE__ );
define( 'PAWLINE_DS_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-util.php';
require_once __DIR__ . '/includes/class-margin.php';
require_once __DIR__ . '/includes/class-capabilities.php';
require_once __DIR__ . '/includes/class-private-storage.php';
require_once __DIR__ . '/includes/class-supplier-orders.php';
require_once __DIR__ . '/includes/class-supplier-invoices.php';
require_once __DIR__ . '/includes/class-order-integration.php';
require_once __DIR__ . '/includes/class-product-integration.php';
require_once __DIR__ . '/includes/class-admin-dashboard.php';
require_once __DIR__ . '/includes/class-reports.php';
require_once __DIR__ . '/includes/class-export.php';
require_once __DIR__ . '/includes/class-plugin.php';

register_activation_hook( __FILE__, array( \Pawline\Dropship\Plugin::class, 'activate' ) );

// HPOS-Kompatibilität (High-Performance Order Storage) erklären.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( \Pawline\Dropship\Plugin::class, 'boot' ) );
