<?php
/**
 * Title: Startbereich (großes Bild mit Text)
 * Slug: pawline/hero
 * Categories: pawline, banner
 * Keywords: hero, start, banner, titelbild
 * Description: Großer Einstieg mit Hintergrund (Bild im Editor wählbar), Überschrift und Button.
 *
 * @package Pawline
 */
?>
<!-- wp:cover {"overlayColor":"sand","isUserOverlayColor":true,"minHeight":520,"isDark":false,"align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull is-light" style="min-height:520px"><span aria-hidden="true" class="wp-block-cover__background has-sand-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">Willkommen bei Pawline</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">[Platzhalter] Kurzer Satz, wofür Pawline steht – im Editor ersetzen.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">Zum Shop</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->
