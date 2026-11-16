<?php
/**
 * CSV-Export von Eingangsbelegen und Lieferantenaufträgen (kein DATEV-Format).
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Export {

	public static function init(): void {
		add_action( 'admin_post_pawline_export', array( __CLASS__, 'handle' ) );
	}

	public static function url( string $type, string $from, string $to ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=pawline_export&type=' . rawurlencode( $type ) . '&from=' . rawurlencode( $from ) . '&to=' . rawurlencode( $to ) ),
			'pawline_export'
		);
	}

	public static function handle(): void {
		check_admin_referer( 'pawline_export' );
		$type = sanitize_key( wp_unslash( $_GET['type'] ?? '' ) );
		$cap  = 'invoices' === $type ? Capabilities::DOCUMENTS : Capabilities::MANAGE;
		if ( ! current_user_can( $cap ) ) {
			wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) );
		}
		$from = Util::sanitize_date( wp_unslash( $_GET['from'] ?? '' ) );
		$to   = Util::sanitize_date( wp_unslash( $_GET['to'] ?? '' ) );
		if ( ! $from || ! $to ) {
			wp_die( 'Ungültiger Zeitraum.', '', array( 'response' => 400 ) );
		}
		$rows = 'invoices' === $type ? self::invoice_rows( $from, $to ) : self::purchase_rows( $from, $to );
		self::send( "pawline-{$type}-{$from}-{$to}.csv", $rows );
	}

	private static function date_query_ids( string $post_type, string $meta_key, string $from, string $to ): array {
		return get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'meta_value',
				'meta_key'       => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => $meta_key,
						'value'   => array( $from, $to ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);
	}

	public static function invoice_rows( string $from, string $to ): array {
		$rows = array( array( 'Beleg-ID', 'Lieferant', 'Rechnungsnummer', 'Rechnungsdatum', 'Netto', 'Umsatzsteuer', 'Gesamt', 'Währung', 'Steuerangabe', 'Lieferantenaufträge', 'Dateien', 'SHA-256' ) );
		foreach ( self::date_query_ids( Supplier_Invoices::POST_TYPE, Supplier_Invoices::META['invoice_date'], $from, $to ) as $id ) {
			$d      = Supplier_Invoices::get( $id );
			$rows[] = array(
				'ER-' . $id,
				$d['supplier_name'],
				$d['invoice_no'],
				$d['invoice_date'],
				self::num( $d['net'] ),
				self::num( $d['tax'] ),
				self::num( $d['gross'] ),
				$d['currency'],
				$d['tax_note'],
				$d['po_ids'] ? 'LA-' . implode( ' LA-', $d['po_ids'] ) : '',
				implode( ' | ', array_column( $d['files'], 'original_name' ) ),
				implode( ' | ', array_column( $d['files'], 'sha256' ) ),
			);
		}
		return $rows;
	}

	public static function purchase_rows( string $from, string $to ): array {
		$rows = array( array( 'Auftrag', 'Lieferant', 'Auftragsnr. Lieferant', 'Bestelldatum', 'Einkaufsbetrag brutto', 'Währung', 'Status', 'Kundenbestellungen', 'Eingangsbelege' ) );
		foreach ( self::date_query_ids( Supplier_Orders::POST_TYPE, Supplier_Orders::META['ordered_at'], $from, $to ) as $id ) {
			$d      = Supplier_Orders::get( $id );
			$inv    = Supplier_Invoices::for_po( $id );
			$rows[] = array(
				'LA-' . $id,
				$d['supplier_name'],
				$d['external_no'],
				$d['ordered_at'],
				self::num( $d['amount'] ),
				$d['currency'],
				Util::PO_STATUSES[ $d['status'] ],
				$d['order_ids'] ? '#' . implode( ' #', $d['order_ids'] ) : '',
				$inv ? 'ER-' . implode( ' ER-', $inv ) : '',
			);
		}
		return $rows;
	}

	private static function num( ?string $v ): string {
		return null === $v ? '' : str_replace( '.', ',', $v );
	}

	/** CSV als Zeichenkette (für Tests und Ausgabe). */
	public static function to_csv( array $rows ): string {
		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, "\xEF\xBB\xBF" );
		foreach ( $rows as $row ) {
			fputcsv( $fh, array_map( array( Util::class, 'csv_cell' ), $row ), ';', '"', '' );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return (string) $csv;
	}

	private static function send( string $filename, array $rows ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo self::to_csv( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
