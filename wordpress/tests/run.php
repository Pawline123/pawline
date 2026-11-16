<?php
/**
 * Eigenständige Tests ohne WordPress (reine Logik + Ladeprüfung des Plugins).
 *
 * Aufruf: php tests/run.php
 * Prüft NICHT: Verhalten innerhalb von WordPress/WooCommerce (dafür docs/TESTPLAN.md).
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

// --- Minimale Stubs, damit die Plugin-Datei ohne WordPress geladen werden kann. ---
$GLOBALS['pawline_hooks'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['pawline_hooks'][] = array( 'action', $h ); return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['pawline_hooks'][] = array( 'filter', $h ); return true; }
function register_activation_hook( $f, $cb ) { $GLOBALS['pawline_hooks'][] = array( 'activation', $f ); }

$plugin = dirname( __DIR__ ) . '/wp-content/plugins/pawline-dropship/pawline-dropship.php';
require $plugin;

use Pawline\Dropship\Util;
use Pawline\Dropship\Margin;
use Pawline\Dropship\Private_Storage;
use Pawline\Dropship\Export;
use Pawline\Dropship\Plugin;
use Pawline\Dropship\Capabilities;

$pass = 0;
$fail = 0;
function check( string $name, $actual, $expected ): void {
	global $pass, $fail;
	if ( $actual === $expected ) {
		++$pass;
		echo "  ok   {$name}\n";
	} else {
		++$fail;
		echo "  FAIL {$name}\n       erwartet: " . var_export( $expected, true ) . "\n       erhalten: " . var_export( $actual, true ) . "\n";
	}
}

echo "Laden des Plugins\n";
check( 'Aktivierungs-Hook registriert', in_array( array( 'activation', realpath( $plugin ) ?: $plugin ), $GLOBALS['pawline_hooks'], true ) || count( array_filter( $GLOBALS['pawline_hooks'], fn( $h ) => 'activation' === $h[0] ) ) === 1, true );
check( 'plugins_loaded registriert', in_array( array( 'action', 'plugins_loaded' ), $GLOBALS['pawline_hooks'], true ), true );
check( 'HPOS-Erklärung registriert', in_array( array( 'action', 'before_woocommerce_init' ), $GLOBALS['pawline_hooks'], true ), true );
$GLOBALS['pawline_hooks'] = array();
Plugin::boot(); // Ohne WooCommerce: nur Hinweis.
check( 'Ohne WooCommerce nur Admin-Hinweis', $GLOBALS['pawline_hooks'], array( array( 'action', 'admin_notices' ) ) );
eval( 'class WooCommerce {}' );
$GLOBALS['pawline_hooks'] = array();
Plugin::boot();
$hooks = array_column( $GLOBALS['pawline_hooks'], 1 );
foreach ( array( 'init', 'admin_menu', 'save_post_pawline_po', 'save_post_pawline_invoice', 'admin_post_pawline_download', 'admin_post_pawline_export', 'pre_delete_post', 'woocommerce_process_shop_order_meta', 'woocommerce_admin_process_product_object', 'manage_woocommerce_page_wc-orders_columns' ) as $h ) {
	check( "Hook {$h}", in_array( $h, $hooks, true ), true );
}
check( 'Capabilities enthalten Beleg-Recht', in_array( Capabilities::DOCUMENTS, Capabilities::all_caps(), true ), true );
check( 'Capabilities Anzahl', count( Capabilities::all_caps() ), 22 );

echo "\nBeträge\n";
$cases = array(
	'24,90' => '24.90', '1.234,56' => '1.234,56', '1,234.56' => '1234.56', '1234.5' => '1234.50',
	'12' => '12.00', ' 7,5 € ' => '7.50', '-3,20' => '-3.20', '1.234.567' => '1234567.00',
	'' => null, 'abc' => null, '1,2,3' => null, '12,345678' => null,
);
$cases['1.234,56'] = '1234.56';
foreach ( $cases as $in => $exp ) {
	check( "parse_amount('" . $in . "')", Util::parse_amount( (string) $in ), $exp );
}
check( 'format_amount', Util::format_amount( '1234.5', 'EUR' ), '1.234,50 EUR' );
check( 'format_amount leer', Util::format_amount( null ), '–' );

echo "\nEingaben\n";
check( 'Status gültig', Util::sanitize_status( 'Bestellt' ), 'bestellt' );
check( 'Status ungültig => offen', Util::sanitize_status( 'hack' ), 'offen' );
check( 'Währung', Util::sanitize_currency( 'usd' ), 'USD' );
check( 'Währung ungültig', Util::sanitize_currency( 'EURO' ), 'EUR' );
check( 'ID-Liste', Util::parse_id_list( '#1042, 1043 1042;x' ), array( 1042, 1043 ) );
check( 'ID-Liste leer', Util::parse_id_list( '' ), array() );
check( 'Datum gültig', Util::sanitize_date( '2026-02-28' ), '2026-02-28' );
check( 'Datum ungültig', Util::sanitize_date( '2026-02-30' ), '' );
check( 'Geänderte Felder', Util::changed_fields( array( 'a' => '1', 'b' => array( 1 ) ), array( 'a' => '1', 'b' => array( 1, 2 ), 'c' => 'x' ) ), array( 'b', 'c' ) );
check( 'CSV-Formelschutz', Util::csv_cell( '=HYPERLINK("x")' ), "'=HYPERLINK(\"x\")" );
check( 'CSV negative Zahl bleibt', Util::csv_cell( '-12,50' ), '-12,50' );
check( 'Anzeigename entschärft', Util::safe_display_name( '../../etc/pa"ss<wd>.pdf' ), 'pa_ss_wd_.pdf' );
check( 'Speicherschlüssel gültig', Util::is_valid_storage_key( '2026/' . str_repeat( 'a', 32 ) . '.pdf' ), true );
check( 'Speicherschlüssel Traversal', Util::is_valid_storage_key( '2026/../../wp-config.php' ), false );
check( 'Dateityp PDF ok', Util::validate_file_type( 'Rechnung.PDF', 'application/pdf' ), 'pdf' );
check( 'Dateityp falscher Inhalt', Util::validate_file_type( 'shell.pdf', 'text/x-php' ), null );
check( 'Dateityp nicht erlaubt', Util::validate_file_type( 'x.php', 'text/x-php' ), null );

echo "\nRohdifferenz\n";
check( 'to_cents 12.345', Margin::to_cents( '12.345' ), 1235 );
check( 'to_cents -0.5', Margin::to_cents( '-0.5' ), -50 );
check( 'cents_to_string', Margin::cents_to_string( -1205 ), '-12.05' );
$r = Margin::compute(
	array(
		array( 'id' => 1, 'total' => '59.90', 'refunded' => '10.00', 'currency' => 'EUR', 'has_purchase' => true ),
		array( 'id' => 2, 'total' => '20.00', 'refunded' => '0', 'currency' => 'EUR', 'has_purchase' => false ),
		array( 'id' => 3, 'total' => '99.00', 'refunded' => '0', 'currency' => 'CHF', 'has_purchase' => true ),
	),
	array(
		array( 'id' => 10, 'amount' => '30.10', 'currency' => 'EUR', 'status' => 'bestellt' ),
		array( 'id' => 11, 'amount' => null, 'currency' => 'EUR', 'status' => 'offen' ),
		array( 'id' => 12, 'amount' => '5.00', 'currency' => 'EUR', 'status' => 'storniert' ),
		array( 'id' => 13, 'amount' => '8.00', 'currency' => 'USD', 'status' => 'bestellt' ),
	),
	'EUR'
);
check( 'Erlöse', $r['sales_cents'], 7990 );
check( 'Erstattungen', $r['refunds_cents'], 1000 );
check( 'Einkauf (ohne storniert/fremd/leer)', $r['purchases_cents'], 3010 );
check( 'Rohdifferenz (79,90 − 10,00 − 30,10)', $r['difference_cents'], 3980 );
check( 'Bestellung ohne Einkauf markiert', $r['orders_without_purchase'], array( 2 ) );
check( 'Fehlender Betrag markiert', $r['purchases_missing_amount'], array( 11 ) );
check( 'Fremdwährung Einkauf markiert', $r['purchases_foreign_currency'], array( 13 ) );
check( 'Fremdwährung Bestellung markiert', $r['orders_foreign_currency'], array( 3 ) );
check( 'Als unvollständig gekennzeichnet', $r['complete'], false );
check( 'Grundlage benannt', str_contains( $r['basis'], '§ 19 UStG' ), true );
$r2 = Margin::compute( array( array( 'id' => 1, 'total' => '10', 'refunded' => '0', 'currency' => 'EUR', 'has_purchase' => true ) ), array( array( 'id' => 5, 'amount' => '4.00', 'currency' => 'EUR', 'status' => 'zugestellt' ) ) );
check( 'Vollständig', $r2['complete'], true );

echo "\nGeschützte Ablage\n";
$tmp = sys_get_temp_dir() . '/pawline-test-' . bin2hex( random_bytes( 4 ) );
mkdir( $tmp );
$store = new Private_Storage( $tmp . '/private' );
file_put_contents( $tmp . '/rechnung.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" );
file_put_contents( $tmp . '/erechnung.xml', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rsm:CrossIndustryInvoice xmlns:rsm=\"urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100\"/>\n" );
file_put_contents( $tmp . '/boese.pdf', "<?php echo 'x'; ?>" );
$rec = $store->store( $tmp . '/rechnung.pdf', 'Rechnung 4711.pdf', false );
check( 'PDF gespeichert', is_array( $rec ), true );
check( 'Zufallsname', is_array( $rec ) && Util::is_valid_storage_key( $rec['key'] ), true );
check( 'Originalname erhalten', $rec['original_name'] ?? null, 'Rechnung 4711.pdf' );
check( 'SHA-256 korrekt', $rec['sha256'] ?? null, hash_file( 'sha256', $tmp . '/rechnung.pdf' ) );
check( 'Prüfsumme verifiziert', $store->verify( $rec ), true );
check( '.htaccess angelegt', str_contains( (string) file_get_contents( $tmp . '/private/.htaccess' ), 'Require all denied' ), true );
check( 'index.php angelegt', is_file( $tmp . '/private/index.php' ), true );
check( 'Dateirechte 0640', substr( sprintf( '%o', fileperms( $tmp . '/private/' . $rec['key'] ) ), -4 ), '0640' );
$xml = $store->store( $tmp . '/erechnung.xml', 'erechnung.xml', false );
check( 'XML (E-Rechnung) gespeichert', is_array( $xml ), true );
check( 'PHP als PDF getarnt abgelehnt', $store->store( $tmp . '/boese.pdf', 'boese.pdf', false ) instanceof RuntimeException, true );
check( 'Pfad-Traversal abgelehnt', $store->path_for( '../../../etc/passwd' ), null );
check( 'Unbekannter Schlüssel', $store->path_for( '2026/' . str_repeat( 'b', 32 ) . '.pdf' ), null );
file_put_contents( $tmp . '/private/' . $rec['key'], 'manipuliert' );
check( 'Manipulation erkannt', $store->verify( $rec ), false );

echo "\nAblageort (kein Rückfall auf öffentliche Ordner)\n";
$web = $tmp . '/web';
mkdir( $web . '/wp-content/uploads', 0777, true );
$pdf = $tmp . '/rechnung.pdf';
$none = new Private_Storage( '', array( $web ) );
check( 'Nicht konfiguriert: als unsicher erkannt', $none->is_secure(), false );
check( 'Nicht konfiguriert: Upload blockiert', $none->store( $pdf, 'r.pdf', false ) instanceof RuntimeException, true );
check( 'Nicht konfiguriert: keine Sperrdateien angelegt', $none->ensure_protected(), false );
check( 'Nicht konfiguriert: kein Download-Pfad', $none->path_for( '2026/' . str_repeat( 'a', 32 ) . '.pdf' ), null );
$inside = new Private_Storage( $web . '/wp-content/uploads/pawline-private', array( $web, $web . '/wp-content/uploads' ) );
$res = $inside->store( $pdf, 'r.pdf', false );
check( 'Im Uploadordner: Upload blockiert', $res instanceof RuntimeException && str_contains( $res->getMessage(), 'öffentlich' ), true );
check( 'Im Uploadordner: Ordner wird nicht angelegt', is_dir( $web . '/wp-content/uploads/pawline-private' ), false );
check( 'Im Uploadordner: keine Datei gespeichert', glob( $web . '/wp-content/uploads/*' ), array() );
$dots = new Private_Storage( $tmp . '/aussen/../web/belege', array( $web ) );
check( 'Pfad mit ".." in den Webroot blockiert', $dots->is_secure(), false );
check( 'Webroot selbst blockiert', ( new Private_Storage( $web, array( $web ) ) )->is_secure(), false );
check( 'Relativer Pfad blockiert', ( new Private_Storage( 'belege', array( $web ) ) )->is_secure(), false );
check( 'Windows-Pfad ohne Rücksicht auf Groß-/Kleinschreibung blockiert', ( new Private_Storage( 'C:\\InetPub\\wwwroot\\belege', array( 'c:/inetpub/WWWROOT' ) ) )->location_error() !== null, true );
mkdir( $web . '/versteckt' );
symlink( $web . '/versteckt', $tmp . '/link' );
$sym = new Private_Storage( $tmp . '/link/belege', array( $web ) );
check( 'Symlink in den Webroot blockiert', $sym->is_secure(), false );
check( 'Symlink: Ordner im Webroot nicht angelegt', is_dir( $web . '/versteckt/belege' ), false );
unlink( $tmp . '/link' );
$outside = new Private_Storage( $tmp . '/aussen/belege', array( $web ) );
check( 'Außerhalb des Webroots: sicher', $outside->is_secure(), true );
check( 'Außerhalb des Webroots: Upload erlaubt', is_array( $outside->store( $pdf, 'r.pdf', false ) ), true );
check( 'Webroot, der nur denselben Namensanfang hat, ist kein Treffer', ( new Private_Storage( $tmp . '/web-belege', array( $web ) ) )->is_secure(), true );

echo "\nCSV-Export\n";
$csv = Export::to_csv( array( array( 'Lieferant', 'Betrag' ), array( '=cmd', '12,50' ) ) );
check( 'BOM vorhanden', substr( $csv, 0, 3 ), "\xEF\xBB\xBF" );
check( 'Semikolon & Formelschutz', str_contains( $csv, "'=cmd;12,50" ), true );

// Aufräumen.
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $it as $f ) {
	$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
}
rmdir( $tmp );

echo "\nErgebnis: {$pass} bestanden, {$fail} fehlgeschlagen\n";
exit( $fail > 0 ? 1 : 0 );
