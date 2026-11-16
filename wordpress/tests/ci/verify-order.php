<?php
/**
 * NUR für GitHub Actions: prüft die vom Browser-Test angelegte Bestellung in der Datenbank.
 * Schreibt die Ergebnisse nach /project/test-results/order.json.
 */

defined( 'ABSPATH' ) || exit;

$results = array();
$t       = static function ( string $name, bool $ok, string $detail = '' ) use ( &$results ) {
	$results[] = array( 'area' => 'Bestellung (Datenbank)', 'name' => $name, 'status' => $ok ? 'ok' : 'FAIL', 'detail' => $detail );
	echo ( $ok ? '  ok    ' : '  FAIL  ' ) . $name . ( $detail ? " – {$detail}" : '' ) . "\n";
};

$orders = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'billing_email' => 'ci-test@pawline.test' ) );
$order  = $orders[0] ?? null;
$t( 'Bestellung vorhanden', (bool) $order, $order ? '#' . $order->get_id() : 'keine Bestellung mit ci-test@pawline.test' );
if ( $order ) {
	$t( 'Status „In Wartestellung“ (Überweisung, unbezahlt)', 'on-hold' === $order->get_status(), $order->get_status() );
	$t( 'Summe 19,90 EUR', '19.90' === wc_format_decimal( $order->get_total(), 2 ), $order->get_total() . ' ' . $order->get_currency() );
	$t( 'Kein Steuerbetrag (§ 19 UStG)', 0.0 === (float) $order->get_total_tax(), (string) $order->get_total_tax() );
	$t( 'Keine Steuerposten', 0 === count( $order->get_items( 'tax' ) ) );
	$sku = '';
	foreach ( $order->get_items() as $item ) {
		$p   = $item->get_product();
		$sku = $p ? $p->get_sku() : '';
	}
	$t( 'Position DEMO-001', 'DEMO-001' === $sku, $sku );
	$t( 'Zahlart bacs (nur CI-Test)', 'bacs' === $order->get_payment_method(), $order->get_payment_method() );
}
file_put_contents( '/project/test-results/order.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
foreach ( $results as $r ) {
	if ( 'FAIL' === $r['status'] ) {
		exit( 1 );
	}
}
