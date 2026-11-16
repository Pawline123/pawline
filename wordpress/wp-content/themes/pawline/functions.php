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
