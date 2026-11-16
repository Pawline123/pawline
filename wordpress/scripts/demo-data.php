<?php
/**
 * Eindeutig gekennzeichnete DEMODATEN – nur für die lokale Test-Instanz und GitHub Actions.
 * Alle Einträge tragen "[DEMO]" im Namen und die Markierung _pawline_demo=1.
 * Aufruf über scripts/demo-data.sh (wp eval-file). Entfernen: scripts/demo-remove.sh
 *
 * Nutzt die PHP-API von WooCommerce statt `wp wc …`, weil die REST-basierten CLI-Befehle
 * in WooCommerce 11.3 Parameter nicht zuverlässig übergeben (GitHub Actions, 16.11.2026).
 */

defined( 'ABSPATH' ) || exit;

$existing = get_posts( array( 'post_type' => 'product', 'meta_key' => '_pawline_demo', 'meta_value' => '1', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1 ) );
if ( $existing ) {
	echo "Demodaten existieren bereits.\n";
	return;
}

$term = term_exists( 'demo-kategorie', 'product_cat' );
if ( ! $term ) {
	$term = wp_insert_term( '[DEMO] Kategorie', 'product_cat', array( 'slug' => 'demo-kategorie' ) );
}
if ( is_wp_error( $term ) ) {
	WP_CLI::error( 'Demo-Kategorie: ' . $term->get_error_message() );
}
$cat = (int) $term['term_id'];
update_term_meta( $cat, '_pawline_demo', '1' );

$mark = static function ( WC_Product $p ) {
	$p->update_meta_data( '_pawline_demo', '1' );
	$p->update_meta_data( '_pawline_dropship', 'yes' );
};

$simple = new WC_Product_Simple();
$simple->set_name( '[DEMO] Einfaches Produkt' );
$simple->set_status( 'publish' );
$simple->set_regular_price( '19.90' );
$simple->set_sku( 'DEMO-001' );
$simple->set_manage_stock( false );
$simple->set_stock_status( 'instock' );
$simple->set_category_ids( array( $cat ) );
$simple->set_description( 'Demodaten – nicht für den Live-Import.' );
$mark( $simple );
$simple->save();

$attr = new WC_Product_Attribute();
$attr->set_name( 'Größe' );
$attr->set_options( array( 'S', 'M' ) );
$attr->set_visible( true );
$attr->set_variation( true );

$variable = new WC_Product_Variable();
$variable->set_name( '[DEMO] Variantenprodukt' );
$variable->set_status( 'publish' );
$variable->set_sku( 'DEMO-002' );
$variable->set_category_ids( array( $cat ) );
$variable->set_description( 'Demodaten mit zwei Varianten.' );
$variable->set_attributes( array( $attr ) );
$mark( $variable );
$variable->save();

foreach ( array( 'S', 'M' ) as $size ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $variable->get_id() );
	$v->set_attributes( array( sanitize_title( 'Größe' ) => $size ) );
	$v->set_regular_price( '24.90' );
	$v->set_sku( 'DEMO-002-' . $size );
	$v->set_manage_stock( false );
	$v->set_stock_status( 'instock' );
	$v->update_meta_data( '_pawline_demo', '1' );
	$v->save();
}
WC_Product_Variable::sync( $variable->get_id() );

printf( "Demodaten angelegt (Kategorie %d, Einfaches Produkt %d, Variantenprodukt %d).\n", $cat, $simple->get_id(), $variable->get_id() );
