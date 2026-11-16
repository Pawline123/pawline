<?php
/**
 * Plugin-Bootstrap.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p><strong>Pawline Dropshipping & Belege</strong> benötigt WooCommerce.</p></div>';
				}
			);
			return;
		}
		Supplier_Orders::init();
		Supplier_Invoices::init();
		Order_Integration::init();
		Product_Integration::init();
		Admin_Dashboard::init();
		Reports::init();
		Export::init();
	}

	public static function activate(): void {
		Capabilities::grant();
		Private_Storage::instance()->ensure_protected();
	}

	/** Änderungsprotokoll-Eintrag anhängen (Zeit, Benutzer, geänderte Felder). */
	public static function append_log( int $post_id, string $meta_key, array $changes ): void {
		if ( ! $changes ) {
			return;
		}
		$log   = get_post_meta( $post_id, $meta_key, true );
		$log   = is_array( $log ) ? $log : array();
		$user  = wp_get_current_user();
		$log[] = array(
			'time'    => gmdate( 'c' ),
			'user'    => $user && $user->exists() ? $user->user_login : 'system',
			'changes' => array_values( $changes ),
		);
		update_post_meta( $post_id, $meta_key, $log );
	}

	public static function render_log( array $log ): void {
		$log = array_filter( $log, 'is_array' );
		if ( ! $log ) {
			echo '<p>Noch keine Änderungen protokolliert.</p>';
			return;
		}
		echo '<ul class="pawline-log">';
		foreach ( array_reverse( $log ) as $entry ) {
			printf(
				'<li><code>%s</code> – %s: %s</li>',
				esc_html( get_date_from_gmt( $entry['time'], 'd.m.Y H:i' ) ),
				esc_html( $entry['user'] ),
				esc_html( implode( ', ', (array) $entry['changes'] ) )
			);
		}
		echo '</ul>';
	}
}
