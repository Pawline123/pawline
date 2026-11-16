<?php
/**
 * NUR für die automatische Prüfung in GitHub Actions (wp eval-file).
 *
 * Macht eine Testbestellung ohne echte Zahlungsdaten möglich:
 * - Offline-Zahlart „Überweisung“ (bacs) als „nur CI-Test“ aktivieren. Das ist KEIN Zahlungstest;
 *   Stripe wird mangels Testschlüssel nicht geprüft.
 * - Versandzone Deutschland mit kostenlosem Versand „nur CI-Test“, weil noch keine echten
 *   Versandregeln festgelegt sind. Das sind keine Versandkosten des Shops.
 * Wird NICHT von scripts/setup-wp.sh ausgeführt und darf nie auf den Server übertragen werden.
 */

defined( 'ABSPATH' ) || exit;

update_option(
	'woocommerce_bacs_settings',
	array(
		'enabled'      => 'yes',
		'title'        => 'Überweisung (nur CI-Test)',
		'description'  => 'Nur automatischer Test – keine echte Zahlart.',
		'instructions' => '',
	)
);
echo "Zahlart bacs (nur CI-Test) aktiviert\n";

$exists = false;
foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
	if ( 'CI-Test Deutschland' === $zone['zone_name'] ) {
		$exists = true;
	}
}
if ( ! $exists ) {
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( 'CI-Test Deutschland' );
	$zone->add_location( 'DE', 'country' );
	$zone->save();
	$instance = $zone->add_shipping_method( 'free_shipping' );
	update_option( "woocommerce_free_shipping_{$instance}_settings", array( 'title' => 'Versand (nur CI-Test)', 'requires' => '' ) );
	echo "Versandzone CI-Test Deutschland angelegt\n";
}

// Seiten-URLs für den Browser-Test.
$product_id = wc_get_product_id_by_sku( 'DEMO-001' );
$urls       = array(
	'product'  => $product_id ? get_permalink( $product_id ) : '',
	'cart'     => wc_get_cart_url(),
	'checkout' => wc_get_checkout_url(),
	'shop'     => wc_get_page_permalink( 'shop' ),
);
wp_mkdir_p( '/project/test-results' );
file_put_contents( '/project/test-results/urls.json', wp_json_encode( $urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo wp_json_encode( $urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

// Seitentypen dokumentieren (Block- oder klassische Kasse).
foreach ( array( 'cart', 'checkout' ) as $p ) {
	$content = (string) get_post_field( 'post_content', wc_get_page_id( $p ) );
	echo "{$p}-Seite: " . ( str_contains( $content, 'wp:woocommerce/' . $p ) ? 'Block' : ( str_contains( $content, '[woocommerce_' ) ? 'Shortcode' : 'unbekannt' ) ) . "\n";
}
if ( ! $product_id ) {
	echo "FEHLER: Demoprodukt DEMO-001 fehlt\n";
	exit( 1 );
}
