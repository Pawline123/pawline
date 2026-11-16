<?php
/**
 * Reine Hilfsfunktionen ohne WordPress-Abhängigkeit (unit-testbar).
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Util {

	/** Status eines Lieferantenauftrags. */
	public const PO_STATUSES = array(
		'offen'      => 'Offen (noch nicht beim Lieferanten bestellt)',
		'bestellt'   => 'Beim Lieferanten bestellt',
		'versendet'  => 'Vom Lieferanten versendet',
		'zugestellt' => 'Zugestellt',
		'problem'    => 'Problem / Klärung nötig',
		'storniert'  => 'Storniert',
	);

	/** Erlaubte Belegdateien: Endung => zulässige MIME-Typen (per finfo ermittelt). */
	public const ALLOWED_FILES = array(
		'pdf'  => array( 'application/pdf' ),
		'xml'  => array( 'application/xml', 'text/xml' ),
		'jpg'  => array( 'image/jpeg' ),
		'jpeg' => array( 'image/jpeg' ),
		'png'  => array( 'image/png' ),
	);

	public const MAX_FILE_BYTES = 20971520; // 20 MB.

	/**
	 * Betrag aus Eingabe normalisieren ("1.234,56", "1234.56", "12,5") zu "1234.56".
	 * Gibt null zurück, wenn die Eingabe leer oder ungültig ist.
	 */
	public static function parse_amount( $raw ): ?string {
		if ( null === $raw ) {
			return null;
		}
		$s = trim( (string) $raw );
		$s = str_replace( array( ' ', "\u{00A0}", '€', 'EUR' ), '', $s );
		if ( '' === $s ) {
			return null;
		}
		$negative = false;
		if ( str_starts_with( $s, '-' ) ) {
			$negative = true;
			$s        = substr( $s, 1 );
		}
		$has_comma = str_contains( $s, ',' );
		$has_dot   = str_contains( $s, '.' );
		if ( $has_comma && $has_dot ) {
			// Das zuletzt vorkommende Zeichen ist das Dezimaltrennzeichen.
			if ( strrpos( $s, ',' ) > strrpos( $s, '.' ) ) {
				$s = str_replace( '.', '', $s );
				$s = str_replace( ',', '.', $s );
			} else {
				$s = str_replace( ',', '', $s );
			}
		} elseif ( $has_comma ) {
			if ( substr_count( $s, ',' ) > 1 ) {
				return null;
			}
			$s = str_replace( ',', '.', $s );
		} elseif ( $has_dot && substr_count( $s, '.' ) > 1 ) {
			// "1.234.567" als Tausendertrennung.
			$s = str_replace( '.', '', $s );
		}
		if ( ! preg_match( '/^\d+(\.\d{1,4})?$/', $s ) ) {
			return null;
		}
		$value = number_format( (float) $s, 2, '.', '' );
		return $negative ? '-' . $value : $value;
	}

	/** Betrag deutsch formatieren. */
	public static function format_amount( ?string $amount, string $currency = 'EUR' ): string {
		if ( null === $amount || '' === $amount ) {
			return '–';
		}
		return number_format( (float) $amount, 2, ',', '.' ) . ' ' . $currency;
	}

	/** Gültigen Status liefern oder Standard. */
	public static function sanitize_status( $status ): string {
		$status = is_string( $status ) ? strtolower( trim( $status ) ) : '';
		return array_key_exists( $status, self::PO_STATUSES ) ? $status : 'offen';
	}

	/** ISO-4217-Währungscode (3 Großbuchstaben) oder EUR. */
	public static function sanitize_currency( $currency ): string {
		$c = strtoupper( trim( (string) $currency ) );
		return preg_match( '/^[A-Z]{3}$/', $c ) ? $c : 'EUR';
	}

	/**
	 * Liste von IDs aus Text ("12, 15 #18") oder Array.
	 *
	 * @return int[]
	 */
	public static function parse_id_list( $raw ): array {
		if ( is_array( $raw ) ) {
			$parts = $raw;
		} else {
			$parts = preg_split( '/[^0-9]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
		}
		$ids = array();
		foreach ( (array) $parts as $p ) {
			$id = (int) $p;
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}
		return array_values( $ids );
	}

	/** Datum im Format JJJJ-MM-TT prüfen. */
	public static function sanitize_date( $raw ): string {
		$raw = trim( (string) $raw );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $raw;
		}
		return '';
	}

	/**
	 * Prüft Dateiendung und tatsächlichen Inhaltstyp.
	 *
	 * @return string|null Normalisierte Endung oder null, wenn nicht erlaubt.
	 */
	public static function validate_file_type( string $original_name, string $detected_mime ): ?string {
		$ext = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		if ( ! isset( self::ALLOWED_FILES[ $ext ] ) ) {
			return null;
		}
		return in_array( $detected_mime, self::ALLOWED_FILES[ $ext ], true ) ? $ext : null;
	}

	/** Dateinamen für Anzeige und Content-Disposition entschärfen. */
	public static function safe_display_name( string $name ): string {
		$name = basename( str_replace( '\\', '/', $name ) );
		$name = preg_replace( '/[^\p{L}\p{N}._\- ]+/u', '_', $name );
		$name = trim( (string) $name, ' .' );
		return '' === $name ? 'beleg' : mb_substr( $name, 0, 150 );
	}

	/** Gespeicherten Dateischlüssel prüfen (nur von uns erzeugte Namen). */
	public static function is_valid_storage_key( string $key ): bool {
		return (bool) preg_match( '#^\d{4}/[a-f0-9]{32}\.(pdf|xml|jpg|jpeg|png)$#', $key );
	}

	/** Felder vergleichen und geänderte Schlüssel liefern (für das Änderungsprotokoll). */
	public static function changed_fields( array $before, array $after ): array {
		$changed = array();
		foreach ( $after as $k => $v ) {
			$old = $before[ $k ] ?? null;
			if ( (string) ( is_array( $old ) ? implode( ',', $old ) : $old ) !== (string) ( is_array( $v ) ? implode( ',', $v ) : $v ) ) {
				$changed[] = $k;
			}
		}
		return $changed;
	}

	/** CSV-Zelle gegen Formel-Injektion in Tabellenprogrammen absichern. */
	public static function csv_cell( $value ): string {
		$v = (string) $value;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! preg_match( '/^-?\d+([.,]\d+)?$/', $v ) ) {
			$v = "'" . $v;
		}
		return $v;
	}
}
