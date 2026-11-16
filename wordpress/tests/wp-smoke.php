<?php
/**
 * Integrationsprüfung INNERHALB von WordPress (wp eval-file).
 * Aufruf: .\scripts\pawline.ps1 test   (führt auch tests/run.php aus)
 *
 * Läuft automatisch in GitHub Actions (.github/workflows/wordpress.yml) und lokal über `pawline.ps1 test`.
 * Ergänzt die manuellen Prüfungen aus docs/TESTPLAN.md, ersetzt sie nicht.
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$pass = 0;
$fail = 0;
$warn = 0;
$t    = static function ( string $name, bool $ok, bool $warning_only = false ) use ( &$pass, &$fail, &$warn ) {
	if ( $ok ) {
		++$pass;
		echo "  ok    {$name}\n";
	} elseif ( $warning_only ) {
		++$warn;
		echo "  WARN  {$name}\n";
	} else {
		++$fail;
		echo "  FAIL  {$name}\n";
	}
};

echo "Umgebung\n";
$t( 'Umgebung ist local', 'local' === wp_get_environment_type() );
$t( 'Theme pawline aktiv', 'pawline' === get_stylesheet() );
$t( 'Theme ist Block-Theme', wp_is_block_theme() );
foreach ( array( 'woocommerce/woocommerce.php', 'woocommerce-germanized/woocommerce-germanized.php', 'atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php', 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php', 'pawline-dropship/pawline-dropship.php' ) as $plugin ) {
	$t( "Plugin aktiv: {$plugin}", is_plugin_active( $plugin ), false !== strpos( $plugin, 'stripe' ) );
}
$gzd_pro = false;
foreach ( array_keys( get_plugins() ) as $file ) {
	if ( false !== strpos( $file, 'germanized-pro' ) && is_plugin_active( $file ) ) {
		$gzd_pro = true;
	}
}
$t( 'Germanized Pro aktiv (Voraussetzung für Rechnungstests)', $gzd_pro, true );

echo "\nWooCommerce-Einstellungen\n";
$t( 'Land DE', 0 === strpos( (string) get_option( 'woocommerce_default_country' ), 'DE' ) );
$t( 'Währung EUR', 'EUR' === get_woocommerce_currency() );
$t( 'Steuerberechnung aus (§ 19 UStG)', 'no' === get_option( 'woocommerce_calc_taxes' ) );
$t( 'Gastbestellung erlaubt', 'yes' === get_option( 'woocommerce_enable_guest_checkout' ) );
$t( 'Germanized Kleinunternehmer-Option gesetzt (Optionsname im Backend prüfen!)', 'yes' === get_option( 'woocommerce_gzd_small_enterprise' ), true );
foreach ( array( 'cart', 'checkout', 'myaccount', 'shop' ) as $page ) {
	$t( "Shopseite vorhanden: {$page}", wc_get_page_id( $page ) > 0 );
}

echo "\nPawline-Plugin\n";
$t( 'Post-Typ Lieferantenauftrag', post_type_exists( 'pawline_po' ) );
$t( 'Post-Typ Eingangsbeleg', post_type_exists( 'pawline_invoice' ) );
$t( 'Lieferantenaufträge nicht öffentlich / nicht in REST', ! get_post_type_object( 'pawline_po' )->public && ! get_post_type_object( 'pawline_po' )->show_in_rest );
$t( 'Eingangsbelege nicht öffentlich / nicht in REST', ! get_post_type_object( 'pawline_invoice' )->public && ! get_post_type_object( 'pawline_invoice' )->show_in_rest );
$admin = get_role( 'administrator' );
$t( 'Admin hat manage_pawline_documents', $admin && $admin->has_cap( 'manage_pawline_documents' ) );
$customer = get_role( 'customer' );
$t( 'Kunde hat KEIN Belegrecht', $customer && ! $customer->has_cap( 'manage_pawline_documents' ) && ! $customer->has_cap( 'edit_pawline_invoices' ) );
$store = \Pawline\Dropship\Private_Storage::instance();
$err   = $store->location_error();
$t( 'Belegablage sicher konfiguriert' . ( $err ? " ({$err})" : ': ' . $store->base_dir() ), null === $err );
$t( 'Belegablage außerhalb von ABSPATH', '' !== $store->base_dir() && 0 !== strpos( wp_normalize_path( $store->base_dir() ) . '/', trailingslashit( wp_normalize_path( ABSPATH ) ) ) );
$uploads = wp_upload_dir( null, false );
$t( 'Kein Belegordner im Uploadordner (kein Rückfall)', ! file_exists( $uploads['basedir'] . '/pawline-private' ) );
// Simulierte Fehlkonfigurationen mit den echten öffentlichen Verzeichnissen dieser Installation.
$roots = array( ABSPATH, WP_CONTENT_DIR, $uploads['basedir'] );
$t( 'Ohne PAWLINE_PRIVATE_DIR: Upload gesperrt', ! ( new \Pawline\Dropship\Private_Storage( '', $roots ) )->is_secure() );
$t( 'PAWLINE_PRIVATE_DIR im Uploadordner: Upload gesperrt', ! ( new \Pawline\Dropship\Private_Storage( $uploads['basedir'] . '/pawline-private', $roots ) )->is_secure() );
$t( 'Uploadordner wurde dabei nicht angelegt', ! file_exists( $uploads['basedir'] . '/pawline-private' ) );
// Echte Ablage einer Testdatei in der konfigurierten Ablage.
if ( null === $err ) {
	$tmp = wp_tempnam( 'pawline-smoke.pdf' );
	file_put_contents( $tmp, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" );
	$rec = $store->store( $tmp, 'smoke-test.pdf', false );
	$t( 'Testbeleg in sicherer Ablage gespeichert', is_array( $rec ) );
	if ( is_array( $rec ) ) {
		$path = $store->path_for( $rec['key'] );
		$t( 'Testbeleg liegt außerhalb von ABSPATH', $path && 0 !== strpos( $path, wp_normalize_path( ABSPATH ) ) );
		$t( 'Testbeleg Prüfsumme ok', $store->verify( $rec ) );
		if ( $path ) {
			unlink( $path );
		}
	}
	@unlink( $tmp ); // phpcs:ignore
}

// Download ohne Anmeldung muss abgewiesen werden (Container-intern über den Dienstnamen).
$resp = wp_remote_get( 'http://wordpress/wp-admin/admin-post.php?action=pawline_download&invoice=1&file=0', array( 'redirection' => 0, 'timeout' => 10 ) );
if ( is_wp_error( $resp ) ) {
	$t( 'Download ohne Anmeldung abgewiesen (Anfrage nicht möglich: ' . $resp->get_error_message() . ')', false, true );
} else {
	$code = wp_remote_retrieve_response_code( $resp );
	$t( "Download ohne Anmeldung abgewiesen (HTTP {$code})", in_array( $code, array( 302, 400, 403 ), true ) && false === strpos( wp_remote_retrieve_body( $resp ), '%PDF' ) );
}

echo "\nTheme\n";
foreach ( array( 'front-page', 'single-product', 'archive-product', 'page-cart', 'page-checkout' ) as $slug ) {
	$tpl = get_block_template( get_stylesheet() . '//' . $slug );
	$t( "Template {$slug} geladen", $tpl && ! empty( $tpl->content ) );
	if ( $tpl ) {
		$t( "Template {$slug} ohne Editor-Anpassung in der DB (Quelle: Theme)", 'theme' === $tpl->source, true );
	}
}
$registry = WP_Block_Type_Registry::get_instance();
foreach ( array( 'woocommerce/product-collection', 'woocommerce/product-template', 'woocommerce/product-image-gallery', 'woocommerce/add-to-cart-form', 'woocommerce/product-categories', 'woocommerce/mini-cart', 'woocommerce/breadcrumbs', 'woocommerce/catalog-sorting', 'woocommerce/product-results-count', 'woocommerce/product-sku', 'woocommerce/product-details' ) as $block ) {
	$t( "Block registriert: {$block}", $registry->is_registered( $block ) );
}
foreach ( array( 'pawline/hero', 'pawline/kategorien', 'pawline/neue-produkte', 'pawline/vorteile', 'pawline/text-bild', 'pawline/kontakt', 'pawline/rechtstext-platzhalter' ) as $pattern ) {
	$t( "Muster registriert: {$pattern}", WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern ) );
}

echo "\nDemodaten\n";
$demo = get_posts( array( 'post_type' => 'product', 'meta_key' => '_pawline_demo', 'meta_value' => '1', 'fields' => 'ids', 'posts_per_page' => -1, 'post_status' => 'any' ) );
foreach ( $demo as $id ) {
	$t( "Demo-Produkt {$id} als [DEMO] benannt", 0 === strpos( get_the_title( $id ), '[DEMO]' ) );
}
if ( ! $demo ) {
	echo "  (keine Demodaten vorhanden)\n";
}

echo "\nErgebnis: {$pass} ok, {$warn} Warnungen, {$fail} Fehler\n";
if ( $fail > 0 ) {
	exit( 1 );
}
