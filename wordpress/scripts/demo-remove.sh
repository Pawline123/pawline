#!/bin/sh
# Entfernt alle mit _pawline_demo=1 markierten Produkte (inkl. Varianten) und die Demo-Kategorie.
set -eu
wp eval '
$ids = get_posts( array( "post_type" => array( "product", "product_variation" ), "meta_key" => "_pawline_demo", "meta_value" => "1", "post_status" => "any", "fields" => "ids", "posts_per_page" => -1 ) );
foreach ( $ids as $id ) {
	$p = wc_get_product( $id );
	if ( $p && $p->delete( true ) ) {
		echo "gelöscht: {$id}\n";
	}
}
$t = get_term_by( "slug", "demo-kategorie", "product_cat" );
if ( $t ) {
	wp_delete_term( $t->term_id, "product_cat" );
	echo "gelöscht: Demo-Kategorie\n";
}
echo "Demodaten entfernt.\n";
'
