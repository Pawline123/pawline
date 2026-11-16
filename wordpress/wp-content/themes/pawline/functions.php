<?php
/**
 * Pawline Theme – Funktionen.
 *
 * Bewusst klein gehalten: Shop-Logik gehört in Plugins (WooCommerce,
 * Germanized, ATUM, pawline-dropship), nicht ins Theme.
 *
 * @package Pawline
 */

defined( 'ABSPATH' ) || exit;

define( 'PAWLINE_THEME_VERSION', '0.1.0' );

add_action( 'after_setup_theme', 'pawline_setup' );
/**
 * Theme-Unterstützungen.
 */
function pawline_setup() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
	load_theme_textdomain( 'pawline', get_template_directory() . '/languages' );
}

add_action( 'wp_enqueue_scripts', 'pawline_enqueue_styles' );
/**
 * Stylesheet laden (nur lokal, keine externen Quellen).
 */
function pawline_enqueue_styles() {
	wp_enqueue_style(
		'pawline-style',
		get_stylesheet_uri(),
		array(),
		PAWLINE_THEME_VERSION
	);
}

add_action( 'init', 'pawline_register_pattern_categories' );
/**
 * Eigene Kategorie für Pawline-Abschnitte im Block-Inserter.
 */
function pawline_register_pattern_categories() {
	register_block_pattern_category(
		'pawline',
		array(
			'label'       => __( 'Pawline-Abschnitte', 'pawline' ),
			'description' => __( 'Wiederverwendbare Abschnitte für Pawline-Seiten.', 'pawline' ),
		)
	);
}

add_filter( 'render_block_woocommerce/order-confirmation-totals', 'pawline_order_confirmation_small_business_notice', 10, 1 );
/**
 * Bestellbestätigung (Block-Theme): Germanized-Kleinunternehmerhinweis direkt unter den Summen.
 *
 * Germanized gibt den Hinweis im Warenkorb, in der Kasse und in E-Mails selbst aus, im Block
 * „Bestellbestätigung – Summen“ aber nicht. Hier wird nur Germanizeds eigene Vorlage
 * (global/small-business-info.php, Text aus den Germanized-Einstellungen) angehängt –
 * keine eigene Steuerlogik, und nur wenn Germanized die Kleinunternehmerregelung meldet.
 *
 * @param string $content Gerendertes Block-HTML.
 * @return string
 */
function pawline_order_confirmation_small_business_notice( $content ) {
	if ( '' === trim( (string) $content ) || ! function_exists( 'wc_gzd_is_small_business' ) || ! wc_gzd_is_small_business() ) {
		return $content;
	}
	$notice = wc_get_template_html( 'global/small-business-info.php' ); // Germanized löst die Vorlage selbst auf (wie im Mini-Warenkorb).
	if ( '' === trim( $notice ) ) {
		return $content;
	}
	return $content . '<div class="pawline-order-total-notice">' . $notice . '</div>';
}

add_filter( 'render_block', 'pawline_checkout_mobile_small_business_notice', 1100, 2 );
/**
 * Kasse (Block-Kasse mit Germanized): Kleinunternehmerhinweis auch auf dem Handy direkt beim Gesamtbetrag.
 *
 * Germanized setzt vor „Jetzt kaufen“ eine Bestellübersicht ein, die den Hinweis enthält. Auf schmalen
 * Bildschirmen klappt WooCommerce diese Übersicht zu („Bestellübersicht 19,90 €“) – der Hinweis ist dann
 * nicht sichtbar. Hier wird Germanizeds eigene Vorlage (global/small-business-info.php) zwischen Übersicht
 * und Schaltfläche gesetzt; per CSS nur bei schmaler Ansicht sichtbar (breit steht er in der Übersicht).
 * Keine eigene Steuerlogik; nur wenn Germanized die Kleinunternehmerregelung meldet.
 *
 * @param string $content Gerendertes Block-HTML.
 * @param array  $block   Block-Daten.
 * @return string
 */
function pawline_checkout_mobile_small_business_notice( $content, $block ) {
	if ( 'woocommerce/checkout' !== ( $block['blockName'] ?? '' ) || ! function_exists( 'wc_gzd_is_small_business' ) || ! wc_gzd_is_small_business() ) {
		return $content;
	}
	$summary = '<div data-block-name="woocommerce/checkout-order-summary-block" class="wp-block-woocommerce-checkout-order-summary-block"></div>';
	$pos     = strpos( (string) $content, '<div class="wc-gzd-checkout-submit">' . $summary );
	if ( false === $pos || false !== strpos( (string) $content, 'pawline-checkout-tax-notice' ) ) {
		return $content;
	}
	$notice = wc_get_template_html( 'global/small-business-info.php' );
	if ( '' === trim( $notice ) ) {
		return $content;
	}
	$insert_at = $pos + strlen( '<div class="wc-gzd-checkout-submit">' . $summary );
	return substr( $content, 0, $insert_at ) . '<div class="pawline-checkout-tax-notice">' . $notice . '</div>' . substr( $content, $insert_at );
}
