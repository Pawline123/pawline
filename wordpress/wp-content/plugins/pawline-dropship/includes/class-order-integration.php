<?php
/**
 * Integration in WooCommerce-Bestellungen (HPOS und klassische Speicherung).
 *
 * - Meta-Box "Dropshipping": zugeordnete Lieferantenaufträge, Schnellanlage, Rohdifferenz.
 * - Versandinformationen: mehrere Pakete mit Trackingnummer, manuell gepflegt.
 *   Hinweis: Germanized bringt eigene "Sendungen" mit. Vor dem Live-Gang festlegen,
 *   welche Stelle führend ist (siehe docs/ERWEITERUNGEN.md), um Doppelpflege zu vermeiden.
 * - Spalte "Lieferant" in der Bestellübersicht.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Order_Integration {

	public const SHIPMENTS_META = '_pawline_shipments';
	public const NONCE          = 'pawline_order_save';

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save' ), 50 );
		// Spalten: HPOS.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_hpos' ), 10, 2 );
		// Spalten: klassisch.
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_legacy' ), 10, 2 );
	}

	private static function screen_id(): string {
		return function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	}

	public static function meta_boxes(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}
		add_meta_box( 'pawline_dropship', 'Dropshipping (Pawline)', array( __CLASS__, 'render' ), self::screen_id(), 'normal', 'high' );
	}

	/** @param \WP_Post|\WC_Order $post_or_order */
	public static function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$po_ids = Supplier_Orders::for_order( $order->get_id() );
		wp_nonce_field( self::NONCE, '_pawline_order_nonce' );

		echo '<h4>Lieferantenaufträge</h4>';
		if ( $order->is_paid() || $order->has_status( array( 'processing', 'completed' ) ) ) {
			echo '<p>Zahlungsstatus: <strong>bezahlt</strong> – kann beim Lieferanten bestellt werden.</p>';
		} else {
			printf( '<p>Zahlungsstatus: <strong>%s</strong> – vor der Lieferantenbestellung Zahlung prüfen.</p>', esc_html( wc_get_order_status_name( $order->get_status() ) ) );
		}
		if ( $po_ids ) {
			echo '<table class="widefat striped"><thead><tr><th>Auftrag</th><th>Status</th><th>Einkaufsbetrag</th><th>Deckt Bestellungen</th></tr></thead><tbody>';
			foreach ( $po_ids as $po ) {
				$d = Supplier_Orders::get( $po );
				printf(
					'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_url( (string) get_edit_post_link( $po ) ),
					esc_html( get_the_title( $po ) ),
					esc_html( Util::PO_STATUSES[ $d['status'] ] ),
					null === $d['amount'] ? '<em>nicht erfasst</em>' : esc_html( Util::format_amount( $d['amount'], $d['currency'] ) ),
					esc_html( '#' . implode( ', #', $d['order_ids'] ) )
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p><em>Noch kein Lieferantenauftrag erfasst.</em></p>';
		}
		printf(
			'<p><a class="button" href="%s">Lieferantenauftrag erfassen</a></p>',
			esc_url( admin_url( 'post-new.php?post_type=' . Supplier_Orders::POST_TYPE . '&wc_order=' . $order->get_id() ) )
		);

		self::render_difference( $order, $po_ids );
		self::render_shipments( $order );
	}

	private static function render_difference( \WC_Order $order, array $po_ids ): void {
		$purchases = array();
		$shared    = false;
		foreach ( $po_ids as $po ) {
			$d = Supplier_Orders::get( $po );
			if ( count( $d['order_ids'] ) > 1 ) {
				$shared = true;
			}
			$purchases[] = array( 'id' => $po, 'amount' => $d['amount'], 'currency' => $d['currency'], 'status' => $d['status'] );
		}
		$r = Margin::compute(
			array(
				array(
					'id'           => $order->get_id(),
					'total'        => wc_format_decimal( $order->get_total(), 2 ),
					'refunded'     => wc_format_decimal( $order->get_total_refunded(), 2 ),
					'currency'     => $order->get_currency(),
					'has_purchase' => ! empty( $po_ids ),
				),
			),
			$purchases,
			$order->get_currency()
		);
		echo '<h4>Rohdifferenz dieser Bestellung</h4>';
		if ( $shared ) {
			echo '<p><strong>Nicht berechnet:</strong> Ein Lieferantenauftrag deckt mehrere Bestellungen ab; der Einkaufsbetrag wird nicht automatisch aufgeteilt. Siehe Pawline › Auswertung für den Zeitraum.</p>';
			return;
		}
		printf(
			'<p>%s − Erstattungen %s − Einkauf %s = <strong>%s</strong>%s</p><p class="description">Grundlage: %s. Keine Gewinnangabe – nicht enthalten: %s.</p>',
			esc_html( Util::format_amount( Margin::cents_to_string( $r['sales_cents'] ), $r['currency'] ) ),
			esc_html( Util::format_amount( Margin::cents_to_string( $r['refunds_cents'] ), $r['currency'] ) ),
			esc_html( Util::format_amount( Margin::cents_to_string( $r['purchases_cents'] ), $r['currency'] ) ),
			esc_html( Util::format_amount( Margin::cents_to_string( $r['difference_cents'] ), $r['currency'] ) ),
			$r['complete'] ? '' : ' <span style="color:#b32d2e">(unvollständig: Einkaufsbetrag fehlt oder andere Währung)</span>',
			esc_html( Margin::BASIS_LABEL ),
			esc_html( implode( ', ', Margin::NOT_INCLUDED ) )
		);
	}

	private static function render_shipments( \WC_Order $order ): void {
		$rows   = self::get_shipments( $order );
		$rows[] = array( 'carrier' => '', 'tracking' => '', 'url' => '', 'date' => '' ); // Leere Zeile für neues Paket.
		echo '<h4>Versand (manuell gepflegt)</h4><p class="description">Eine Zeile je Paket. Wird nicht automatisch beim Lieferanten abgefragt. Zum Entfernen alle Felder einer Zeile leeren.</p>';
		echo '<table class="widefat"><thead><tr><th>Versanddienst</th><th>Trackingnummer</th><th>Tracking-Link (optional)</th><th>Versanddatum</th></tr></thead><tbody>';
		foreach ( $rows as $i => $r ) {
			printf(
				'<tr><td><input type="text" name="pawline_ship[%1$d][carrier]" value="%2$s"></td><td><input type="text" name="pawline_ship[%1$d][tracking]" value="%3$s"></td><td><input type="url" name="pawline_ship[%1$d][url]" value="%4$s" placeholder="https://"></td><td><input type="date" name="pawline_ship[%1$d][date]" value="%5$s"></td></tr>',
				(int) $i,
				esc_attr( $r['carrier'] ),
				esc_attr( $r['tracking'] ),
				esc_attr( $r['url'] ),
				esc_attr( $r['date'] )
			);
		}
		echo '</tbody></table><p class="description">Speichern über „Aktualisieren“ der Bestellung. Der Kunde wird dadurch nicht automatisch benachrichtigt.</p>';
	}

	public static function get_shipments( \WC_Order $order ): array {
		$rows = $order->get_meta( self::SHIPMENTS_META );
		return is_array( $rows ) ? $rows : array();
	}

	public static function sanitize_shipments( array $input ): array {
		$out = array();
		foreach ( $input as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$row = array(
				'carrier'  => sanitize_text_field( $r['carrier'] ?? '' ),
				'tracking' => sanitize_text_field( $r['tracking'] ?? '' ),
				'url'      => esc_url_raw( $r['url'] ?? '', array( 'https', 'http' ) ),
				'date'     => Util::sanitize_date( $r['date'] ?? '' ),
			);
			if ( '' !== implode( '', $row ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	public static function save( int $order_id ): void {
		if ( ! isset( $_POST['_pawline_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pawline_order_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$input  = isset( $_POST['pawline_ship'] ) && is_array( $_POST['pawline_ship'] ) ? wp_unslash( $_POST['pawline_ship'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$before = self::get_shipments( $order );
		$after  = self::sanitize_shipments( $input );
		if ( $before === $after ) {
			return;
		}
		$order->update_meta_data( self::SHIPMENTS_META, $after );
		$new = array_diff( array_column( $after, 'tracking' ), array_column( $before, 'tracking' ) );
		foreach ( array_filter( $new ) as $tracking ) {
			$order->add_order_note( 'Versandinfo erfasst: Tracking ' . $tracking . ' (manuell).' );
		}
		$order->save();
	}

	public static function columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$new['pawline_supplier'] = 'Lieferant';
			}
		}
		if ( ! isset( $new['pawline_supplier'] ) ) {
			$new['pawline_supplier'] = 'Lieferant';
		}
		return $new;
	}

	public static function column_hpos( string $column, $order ): void {
		if ( 'pawline_supplier' === $column && $order instanceof \WC_Order ) {
			self::print_supplier_cell( $order );
		}
	}

	public static function column_legacy( string $column, int $post_id ): void {
		if ( 'pawline_supplier' === $column ) {
			$order = wc_get_order( $post_id );
			if ( $order ) {
				self::print_supplier_cell( $order );
			}
		}
	}

	private static function print_supplier_cell( \WC_Order $order ): void {
		$pos = Supplier_Orders::for_order( $order->get_id() );
		if ( ! $pos ) {
			echo $order->has_status( 'processing' ) ? '<mark class="order-status status-on-hold"><span>Bestellen!</span></mark>' : '–';
			return;
		}
		$labels = array();
		foreach ( $pos as $po ) {
			$labels[] = Util::PO_STATUSES[ Supplier_Orders::get( $po )['status'] ];
		}
		echo esc_html( implode( ' / ', array_unique( $labels ) ) );
		if ( self::get_shipments( $order ) ) {
			echo '<br><small>Tracking erfasst</small>';
		}
	}
}
