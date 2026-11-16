<?php
/**
 * Produkt-Kennzeichnung "Dropshipping".
 *
 * Dropshipping-Artikel sollen OHNE eigene Bestandsführung angelegt werden, damit weder
 * WooCommerce noch ATUM einen eigenen Lagerzugang/-abgang vortäuschen. Lieferanten-
 * verfügbarkeit ist ohne Datenanbindung eine manuelle Angabe (Lagerstatus).
 * Lieferant, Lieferanten-Artikelnummer und Einkaufspreis pflegt ATUM.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Product_Integration {

	public const META = '_pawline_dropship';

	public static function init(): void {
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function is_dropship( \WC_Product $product ): bool {
		$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		return 'yes' === get_post_meta( $id, self::META, true );
	}

	public static function field(): void {
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META,
				'label'       => 'Dropshipping-Artikel',
				'description' => 'Wird direkt vom Lieferanten an den Kunden geschickt. Bestand hier NICHT verwalten (Lagerstatus manuell setzen).',
			)
		);
	}

	public static function save( \WC_Product $product ): void {
		// Nonce wird von WooCommerce beim Speichern des Produkts geprüft.
		$value = isset( $_POST[ self::META ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification
		$product->update_meta_data( self::META, $value );
	}

	/** Warnung, wenn ein Dropshipping-Artikel eigenen Bestand führt. */
	public static function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		global $post;
		$product = $post ? wc_get_product( $post->ID ) : null;
		if ( $product && self::is_dropship( $product ) && self::manages_stock_anywhere( $product ) ) {
			echo '<div class="notice notice-warning"><p><strong>Pawline:</strong> Dieser Dropshipping-Artikel (oder eine Variante) hat die Bestandsverwaltung aktiviert. Beim Direktversand entsteht kein eigener Lagerzugang – bitte „Lagerbestand verwalten“ deaktivieren und den Lagerstatus manuell pflegen.</p></div>';
		}
	}

	public static function manages_stock_anywhere( \WC_Product $product ): bool {
		if ( $product->get_manage_stock() ) {
			return true;
		}
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && 'parent' !== $child->get_manage_stock() && $child->get_manage_stock() ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Dropshipping-Artikel mit aktiver Bestandsführung (für den Überblick). */
	public static function conflicting_products(): array {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p && self::manages_stock_anywhere( $p ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}
}
