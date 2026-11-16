<?php
/**
 * NUR für GitHub Actions: prüft in der Datenbank, was tests/e2e/admin.mjs über die Oberfläche angelegt hat.
 * Ergebnis: /project/test-results/admin-db.json
 */

defined( 'ABSPATH' ) || exit;

use Pawline\Dropship\Supplier_Orders;
use Pawline\Dropship\Supplier_Invoices;
use Pawline\Dropship\Order_Integration;
use Pawline\Dropship\Private_Storage;

$results = array();
$t       = static function ( string $name, bool $ok, string $detail = '' ) use ( &$results ) {
	$results[] = array( 'area' => 'Verwaltung (Datenbank)', 'name' => $name, 'status' => $ok ? 'ok' : 'FAIL', 'detail' => $detail );
	echo ( $ok ? '  ok    ' : '  FAIL  ' ) . $name . ( $detail ? " – {$detail}" : '' ) . "\n";
};

$ids     = json_decode( (string) file_get_contents( '/project/test-results/admin-urls.json' ), true );
$created = json_decode( (string) @file_get_contents( '/project/test-results/admin-created.json' ), true ) ?: array(); // phpcs:ignore
$order   = wc_get_order( (int) $ids['order_id'] );
$po      = (int) ( $created['po_id'] ?? 0 );
$inv     = (int) ( $created['invoice_id'] ?? 0 );

$t( 'Lieferantenauftrag gespeichert', $po > 0 && Supplier_Orders::POST_TYPE === get_post_type( $po ), "LA-{$po}" );
$linked = array_map( 'intval', (array) get_post_meta( $po, Supplier_Orders::ORDER_LINK_META, false ) );
$t( 'Lieferantenauftrag ↔ Kundenbestellung verknüpft', in_array( (int) $ids['order_id'], $linked, true ), 'Bestellungen: ' . implode( ', ', $linked ) );
$t( 'Eingangsbeleg gespeichert', $inv > 0 && Supplier_Invoices::POST_TYPE === get_post_type( $inv ), "ER-{$inv}" );
$po_links = array_map( 'intval', (array) get_post_meta( $inv, Supplier_Invoices::PO_LINK_META, false ) );
$t( 'Eingangsbeleg ↔ Lieferantenauftrag verknüpft', in_array( $po, $po_links, true ) );
$files = (array) get_post_meta( $inv, Supplier_Invoices::FILES_META, true );
$t( 'Zwei Originaldateien abgelegt (PDF + XML)', 2 === count( $files ), count( $files ) . ' Datei(en)' );
$store = Private_Storage::instance();
foreach ( $files as $f ) {
	$path = $store->path_for( (string) ( $f['key'] ?? '' ) );
	$t( 'Datei in geschützter Ablage, Prüfsumme ok: ' . ( $f['original_name'] ?? '?' ), $path && $store->verify( $f ) && 0 !== strpos( $path, wp_normalize_path( ABSPATH ) ), (string) $path );
}
$uploads = wp_upload_dir( null, false );
$t( 'Keine Belegdatei im öffentlichen Uploadordner', ! file_exists( $uploads['basedir'] . '/pawline-private' ) );
$ship = $order ? Order_Integration::get_shipments( $order ) : array();
$t( 'Tracking an der Bestellung gespeichert', ! empty( $ship ) && 'DEMO00012345' === ( $ship[0]['tracking'] ?? '' ), wp_json_encode( $ship ) );
$notes = $order ? wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) : array();
$t( 'Bestellnotiz zum Lieferantenauftrag/Tracking vorhanden', (bool) array_filter( $notes, static fn( $n ) => false !== stripos( $n->content, 'Lieferantenauftrag' ) || false !== stripos( $n->content, 'DEMO00012345' ) ), count( $notes ) . ' Notizen' );
$customer_notes = array_filter( $notes, static fn( $n ) => $n->customer_note );
$t( 'Kunde nicht automatisch benachrichtigt (keine Kundennotiz)', 0 === count( $customer_notes ) );

file_put_contents( '/project/test-results/admin-db.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
foreach ( $results as $r ) {
	if ( 'FAIL' === $r['status'] ) {
		exit( 1 );
	}
}
