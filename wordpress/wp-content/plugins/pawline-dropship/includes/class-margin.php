<?php
/**
 * Transparente Rohdifferenz-Berechnung (ohne WordPress-Abhängigkeit, unit-testbar).
 *
 * Grundlage (Kleinunternehmer nach § 19 UStG):
 * - Verkaufsseite: tatsächlich gezahlte Endpreise der Bestellungen (keine USt enthalten/ausgewiesen),
 *   abzüglich erfasster Erstattungen.
 * - Einkaufsseite: erfasste Einkaufsbeträge brutto, d. h. inklusive der nicht abziehbaren
 *   Umsatzsteuer der Lieferanten.
 * Es werden nie Netto- und Bruttowerte gemischt. Ergebnis ist KEIN Unternehmensgewinn.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Margin {

	public const BASIS_LABEL = 'Verkaufserlöse (Endpreise, ohne USt-Ausweis nach § 19 UStG) abzüglich Erstattungen, minus erfasste Einkaufsbeträge brutto (inkl. nicht abziehbarer Vorsteuer)';

	public const NOT_INCLUDED = array(
		'Zahlungsgebühren (z. B. Stripe, PayPal)',
		'Versandkosten, soweit nicht im erfassten Einkaufsbetrag enthalten',
		'Rücksendekosten und Ersatzlieferungen',
		'Hosting, Lizenzen, Werbung und sonstige Betriebsausgaben',
	);

	/**
	 * @param array  $orders    Liste: ['id'=>int,'total'=>string,'refunded'=>string,'currency'=>string,'has_purchase'=>bool].
	 * @param array  $purchases Liste: ['id'=>int,'amount'=>?string,'currency'=>string,'status'=>string].
	 * @param string $shop_currency Shopwährung.
	 */
	public static function compute( array $orders, array $purchases, string $shop_currency = 'EUR' ): array {
		$r = array(
			'basis'                     => self::BASIS_LABEL,
			'currency'                  => $shop_currency,
			'sales_cents'               => 0,
			'refunds_cents'             => 0,
			'sales_after_refunds_cents' => 0,
			'purchases_cents'           => 0,
			'difference_cents'          => 0,
			'order_count'               => 0,
			'orders_without_purchase'   => array(),
			'purchase_count'            => 0,
			'purchases_missing_amount'  => array(),
			'purchases_foreign_currency' => array(),
			'orders_foreign_currency'   => array(),
			'not_included'              => self::NOT_INCLUDED,
			'complete'                  => true,
		);

		foreach ( $orders as $o ) {
			if ( ( $o['currency'] ?? $shop_currency ) !== $shop_currency ) {
				$r['orders_foreign_currency'][] = (int) $o['id'];
				continue;
			}
			++$r['order_count'];
			$r['sales_cents']   += self::to_cents( $o['total'] ?? '0' );
			$r['refunds_cents'] += abs( self::to_cents( $o['refunded'] ?? '0' ) );
			if ( empty( $o['has_purchase'] ) ) {
				$r['orders_without_purchase'][] = (int) $o['id'];
			}
		}

		foreach ( $purchases as $p ) {
			if ( 'storniert' === ( $p['status'] ?? '' ) ) {
				continue;
			}
			if ( ( $p['currency'] ?? $shop_currency ) !== $shop_currency ) {
				$r['purchases_foreign_currency'][] = (int) $p['id'];
				continue;
			}
			if ( null === ( $p['amount'] ?? null ) || '' === $p['amount'] ) {
				$r['purchases_missing_amount'][] = (int) $p['id'];
				continue;
			}
			++$r['purchase_count'];
			$r['purchases_cents'] += self::to_cents( $p['amount'] );
		}

		$r['sales_after_refunds_cents'] = $r['sales_cents'] - $r['refunds_cents'];
		$r['difference_cents']          = $r['sales_after_refunds_cents'] - $r['purchases_cents'];
		$r['complete']                  = empty( $r['orders_without_purchase'] )
			&& empty( $r['purchases_missing_amount'] )
			&& empty( $r['purchases_foreign_currency'] )
			&& empty( $r['orders_foreign_currency'] );

		return $r;
	}

	/** Dezimalstring exakt in Cent umrechnen (ohne Float-Rundungsfehler). */
	public static function to_cents( $amount ): int {
		$s = trim( (string) $amount );
		if ( '' === $s || ! preg_match( '/^(-)?(\d+)(?:\.(\d+))?$/', $s, $m ) ) {
			return 0;
		}
		$frac  = str_pad( substr( $m[3] ?? '', 0, 3 ), 3, '0' );
		$cents = (int) $m[2] * 100 + intdiv( (int) $frac + 5, 10 );
		return '-' === $m[1] ? -$cents : $cents;
	}

	public static function cents_to_string( int $cents ): string {
		$sign = $cents < 0 ? '-' : '';
		$abs  = abs( $cents );
		return sprintf( '%s%d.%02d', $sign, intdiv( $abs, 100 ), $abs % 100 );
	}
}
