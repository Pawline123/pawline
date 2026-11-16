<?php
/**
 * NUR für GitHub Actions: Testbestellung aus dem Kaufablauf als „bezahlt“ markieren (Status „In Bearbeitung“),
 * damit sie im Pawline-Überblick unter „Beim Lieferanten bestellen“ erscheint. Simuliert den Zahlungseingang
 * der Offline-Zahlart „Überweisung (nur CI-Test)“ – kein Zahlungstest.
 */

defined( 'ABSPATH' ) || exit;

$orders = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'billing_email' => 'ci-test@pawline.test' ) );
if ( ! $orders ) {
	echo "FEHLER: keine Testbestellung gefunden\n";
	exit( 1 );
}
$order = $orders[0];
$order->update_status( 'processing', 'CI-Test: Zahlungseingang simuliert (Überweisung nur CI-Test).' );
$data = array(
	'order_id'   => $order->get_id(),
	'order_edit' => $order->get_edit_order_url(),
	'admin'      => admin_url(),
);
file_put_contents( '/project/test-results/admin-urls.json', wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . "\n";
