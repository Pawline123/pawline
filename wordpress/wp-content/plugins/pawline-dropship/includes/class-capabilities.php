<?php
/**
 * Rechte: eigene Capabilities für Lieferantenaufträge und Eingangsbelege.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	/** Zugriff auf Pawline-Verwaltung (Überblick, Aufträge, Auswertung). */
	public const MANAGE = 'manage_pawline';

	/** Zugriff auf Eingangsbelege (Dateien herunterladen, hochladen). */
	public const DOCUMENTS = 'manage_pawline_documents';

	/** Rollen, die die Rechte bei Aktivierung erhalten. */
	public const ROLES = array( 'administrator', 'shop_manager' );

	/**
	 * Primitive Capabilities eines Post-Typs mit capability_type = [singular, plural].
	 */
	public static function post_type_caps( string $plural ): array {
		return array(
			"edit_{$plural}",
			"edit_others_{$plural}",
			"edit_published_{$plural}",
			"edit_private_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_others_{$plural}",
			"delete_published_{$plural}",
			"delete_private_{$plural}",
		);
	}

	public static function all_caps(): array {
		return array_merge(
			array( self::MANAGE, self::DOCUMENTS ),
			self::post_type_caps( 'pawline_pos' ),
			self::post_type_caps( 'pawline_invoices' )
		);
	}

	public static function grant(): void {
		foreach ( self::ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::all_caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}
}
