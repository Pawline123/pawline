<?php
/**
 * Auswertung: Umsatz, erfasste Einkaufsbeträge und klar benannte Lücken.
 *
 * Zuordnung: Bestellungen nach Bestelldatum im Zeitraum; Einkaufsbeträge über die
 * Zuordnung Lieferantenauftrag ↔ Bestellung (nicht nach Rechnungsdatum).
 * Erstattungen zählen bei der ursprünglichen Bestellung.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Reports {

	public const ORDER_STATUSES = array( 'processing', 'completed', 'refunded' );

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
	}

	public static function menu(): void {
		add_submenu_page( 'pawline', 'Auswertung', 'Auswertung', Capabilities::MANAGE, 'pawline-reports', array( __CLASS__, 'render' ) );
	}

	/** Zeitraum aus der Anfrage (Standard: aktueller Monat). */
	public static function range(): array {
		$from = Util::sanitize_date( wp_unslash( $_GET['from'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$to   = Util::sanitize_date( wp_unslash( $_GET['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		if ( '' === $from ) {
			$from = current_time( 'Y-m-01' );
		}
		if ( '' === $to ) {
			$to = current_time( 'Y-m-d' );
		}
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		return array( $from, $to );
	}

	public static function build( string $from, string $to ): array {
		$orders = wc_get_orders(
			array(
				'status'       => self::ORDER_STATUSES,
				'type'         => 'shop_order',
				'limit'        => -1,
				'date_created' => $from . '...' . $to,
			)
		);
		$map          = Supplier_Orders::linked_order_map();
		$in_range     = array();
		$order_rows   = array();
		$po_ids       = array();
		foreach ( $orders as $o ) {
			$in_range[ $o->get_id() ] = true;
			$order_rows[]             = array(
				'id'           => $o->get_id(),
				'total'        => wc_format_decimal( $o->get_total(), 2 ),
				'refunded'     => wc_format_decimal( $o->get_total_refunded(), 2 ),
				'currency'     => $o->get_currency(),
				'has_purchase' => ! empty( $map[ $o->get_id() ] ),
			);
			foreach ( $map[ $o->get_id() ] ?? array() as $po ) {
				$po_ids[ $po ] = $po;
			}
		}
		$purchase_rows = array();
		$straddling    = array();
		foreach ( $po_ids as $po ) {
			$d = Supplier_Orders::get( $po );
			foreach ( $d['order_ids'] as $oid ) {
				if ( empty( $in_range[ $oid ] ) ) {
					$straddling[] = $po;
					break;
				}
			}
			$purchase_rows[] = array( 'id' => $po, 'amount' => $d['amount'], 'currency' => $d['currency'], 'status' => $d['status'] );
		}
		$r               = Margin::compute( $order_rows, $purchase_rows, get_woocommerce_currency() );
		$r['straddling'] = $straddling;
		if ( $straddling ) {
			$r['complete'] = false;
		}
		return $r;
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		list( $from, $to ) = self::range();
		$r   = self::build( $from, $to );
		$fmt = static fn( int $c ) => Util::format_amount( Margin::cents_to_string( $c ), $r['currency'] );
		?>
		<div class="wrap pawline-wrap">
			<h1>Pawline – Auswertung</h1>
			<form method="get">
				<input type="hidden" name="page" value="pawline-reports">
				<label>Von <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
				<label>Bis <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
				<button class="button">Anzeigen</button>
			</form>

			<table class="widefat pawline-report">
				<tbody>
					<tr><th>Bestellungen im Zeitraum (Status: in Bearbeitung, abgeschlossen, erstattet)</th><td><?php echo (int) $r['order_count']; ?></td></tr>
					<tr><th>Verkaufserlöse (Endpreise, ohne USt-Ausweis nach § 19 UStG)</th><td><?php echo esc_html( $fmt( $r['sales_cents'] ) ); ?></td></tr>
					<tr><th>− Erstattungen (bei der ursprünglichen Bestellung erfasst)</th><td><?php echo esc_html( $fmt( $r['refunds_cents'] ) ); ?></td></tr>
					<tr><th>= Erlöse nach Erstattungen</th><td><?php echo esc_html( $fmt( $r['sales_after_refunds_cents'] ) ); ?></td></tr>
					<tr><th>− Erfasste Einkaufsbeträge brutto (inkl. nicht abziehbarer USt), <?php echo (int) $r['purchase_count']; ?> Lieferantenaufträge</th><td><?php echo esc_html( $fmt( $r['purchases_cents'] ) ); ?></td></tr>
					<tr class="pawline-total"><th>= Rohdifferenz (kein Gewinn)</th><td><?php echo esc_html( $fmt( $r['difference_cents'] ) ); ?></td></tr>
				</tbody>
			</table>
			<p class="description"><strong>Berechnungsgrundlage:</strong> <?php echo esc_html( $r['basis'] ); ?>.</p>

			<h2>Nicht enthalten</h2>
			<ul class="ul-disc"><?php foreach ( $r['not_included'] as $n ) : ?><li><?php echo esc_html( $n ); ?></li><?php endforeach; ?></ul>

			<h2>Vollständigkeit</h2>
			<?php if ( $r['complete'] ) : ?>
				<p>Für alle Bestellungen im Zeitraum sind Lieferantenaufträge mit Betrag in Shopwährung erfasst.</p>
			<?php else : ?>
				<ul class="ul-disc">
					<?php if ( $r['orders_without_purchase'] ) : ?><li><strong>Einkauf fehlt</strong> bei Bestellung(en) #<?php echo esc_html( implode( ', #', $r['orders_without_purchase'] ) ); ?></li><?php endif; ?>
					<?php if ( $r['purchases_missing_amount'] ) : ?><li><strong>Einkaufsbetrag nicht erfasst</strong> bei LA-<?php echo esc_html( implode( ', LA-', $r['purchases_missing_amount'] ) ); ?></li><?php endif; ?>
					<?php if ( $r['purchases_foreign_currency'] ) : ?><li><strong>Andere Währung</strong> (nicht umgerechnet, nicht enthalten): LA-<?php echo esc_html( implode( ', LA-', $r['purchases_foreign_currency'] ) ); ?></li><?php endif; ?>
					<?php if ( $r['orders_foreign_currency'] ) : ?><li><strong>Bestellungen in anderer Währung</strong> nicht enthalten: #<?php echo esc_html( implode( ', #', $r['orders_foreign_currency'] ) ); ?></li><?php endif; ?>
					<?php if ( $r['straddling'] ) : ?><li><strong>Lieferantenauftrag deckt auch Bestellungen außerhalb des Zeitraums ab</strong> (voll gezählt): LA-<?php echo esc_html( implode( ', LA-', $r['straddling'] ) ); ?></li><?php endif; ?>
				</ul>
			<?php endif; ?>

			<h2>Export</h2>
			<p>
				<a class="button" href="<?php echo esc_url( Export::url( 'invoices', $from, $to ) ); ?>">Eingangsbelege als CSV (Rechnungsdatum im Zeitraum)</a>
				<a class="button" href="<?php echo esc_url( Export::url( 'purchases', $from, $to ) ); ?>">Lieferantenaufträge als CSV (Bestelldatum im Zeitraum)</a>
			</p>
			<p class="description">CSV mit Semikolon, UTF-8 – zur Weiterverarbeitung (z. B. durch Steuerberatung). Kein DATEV-Format. Ausgangsrechnungen exportiert Germanized Pro.</p>
		</div>
		<?php
	}
}
