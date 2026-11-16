#!/bin/sh
# Pawline – Einrichtung der lokalen Test-Instanz (läuft im WP-CLI-Container).
# Aufruf über: .\scripts\pawline.ps1 setup
# Wiederholbar: bereits erledigte Schritte werden übersprungen.
set -eu

say() { printf '\n==> %s\n' "$1"; }

say "WordPress-Kern"
if ! wp core is-installed 2>/dev/null; then
  wp core install --url="$WP_URL" --title="Pawline (TESTUMGEBUNG)" \
    --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" --skip-email
fi
wp language core install de_DE --activate || echo "WARNUNG: Deutsches Sprachpaket nicht installiert (Download blockiert?)."
wp option update timezone_string "Europe/Berlin"
wp option update date_format "d.m.Y"
wp option update time_format "H:i"
wp option update blog_public 0            # Testumgebung nicht indexieren
wp rewrite structure '/%postname%/' --hard

say "Plugins von wordpress.org (aktuelle stabile Versionen)"
for p in woocommerce woocommerce-germanized atum-stock-manager-for-woocommerce woocommerce-gateway-stripe; do
  if wp plugin is-installed "$p"; then
    wp plugin activate "$p"
  else
    wp plugin install "$p" --activate
  fi
done
wp language plugin install de_DE --all || true

say "Lizenzpflichtige Plugins aus private-plugins/ (z. B. Germanized Pro)"
found=0
for zip in /project/private-plugins/*.zip; do
  [ -e "$zip" ] || continue
  found=1
  wp plugin install "$zip" --activate --force
done
[ "$found" -eq 1 ] || echo "HINWEIS: Keine ZIP in private-plugins/. Germanized Pro fehlt -> Rechnungs-/Stornotests nicht möglich."

say "Pawline-Theme und -Plugin"
wp theme activate pawline
wp plugin activate pawline-dropship

say "WooCommerce-Grundeinstellungen (Deutschland, EUR, Kleinunternehmer ohne USt)"
wp option update woocommerce_default_country "DE"
wp option update woocommerce_currency "EUR"
wp option update woocommerce_currency_pos "right_space"
wp option update woocommerce_price_thousand_sep "."
wp option update woocommerce_price_decimal_sep ","
wp option update woocommerce_price_num_decimals 2
wp option update woocommerce_weight_unit "kg"
wp option update woocommerce_dimension_unit "cm"
# § 19 UStG: keine Steuerberechnung in WooCommerce.
wp option update woocommerce_calc_taxes "no"
wp option update woocommerce_enable_guest_checkout "yes"
wp option update woocommerce_enable_checkout_login_reminder "yes"
wp option update woocommerce_coming_soon "no" || true
wp wc tool run install_pages --user="$WP_ADMIN_USER" >/dev/null || echo "WARNUNG: Shopseiten konnten nicht angelegt werden."

say "Deutsche Titel und Adressen der Shopseiten, Beispielseite entfernen"
wp eval '
foreach ( array( "cart" => array( "Warenkorb", "warenkorb" ), "checkout" => array( "Kasse", "kasse" ), "myaccount" => array( "Mein Konto", "mein-konto" ), "shop" => array( "Shop", "shop" ) ) as $key => $t ) {
	$id = wc_get_page_id( $key );
	if ( $id > 0 ) {
		wp_update_post( array( "ID" => $id, "post_title" => $t[0], "post_name" => $t[1] ) );
		echo "Seite {$key}: {$t[0]} (/{$t[1]}/)\n";
	}
}
foreach ( array( "sample-page", "beispiel-seite" ) as $slug ) {
	$p = get_page_by_path( $slug );
	if ( $p ) {
		wp_delete_post( $p->ID, true );
		echo "Beispielseite gelöscht: {$slug}\n";
	}
}
'
wp rewrite flush --hard

say "Germanized: Kleinunternehmerregelung"
# ACHTUNG: Optionsname aus der Germanized-Codebasis übernommen, in dieser Umgebung NICHT
# gegen die installierte Version geprüft. Nach dem Setup im Backend kontrollieren:
# WooCommerce > Einstellungen > Germanized > Allgemein > Kleinunternehmerregelung.
wp option update woocommerce_gzd_small_enterprise "yes"
# Einheitlicher Wortlaut des Kleinunternehmerhinweises (Germanized nutzt ihn auf Produktseiten, in Listen,
# Warenkorb, Kasse, Bestellbestätigung und E-Mails). Backend: Germanized › Allgemein › Kleinunternehmerregelung.
wp option update woocommerce_gzd_small_enterprise_text "Umsatzsteuerbefreit gemäß § 19 UStG."
# Preisauszeichnung in Produktlisten: Das Theme setzt die Germanized-Blöcke „Steuerhinweis“ und
# „Versandkostenhinweis“ direkt unter den Preis. Germanizeds automatische Ausgabe unter der
# Warenkorb-Schaltfläche wird abgeschaltet, sonst erscheinen beide Hinweise doppelt.
# (Backend: WooCommerce › Einstellungen › Germanized › Preisauszeichnung › Produktliste)
wp option update woocommerce_gzd_display_product_loop_tax "no"
wp option update woocommerce_gzd_display_product_loop_shipping_costs "no"

say "Seiten (Kontakt, Rechtstext-Entwürfe)"
create_page() { # slug, titel, status, inhalt
  if [ -z "$(wp post list --post_type=page --name="$1" --post_status=any --field=ID)" ]; then
    wp post create --post_type=page --post_name="$1" --post_title="$2" --post_status="$3" --post_content="$4" --porcelain >/dev/null
    echo "angelegt: $2 ($3)"
  else
    echo "vorhanden: $2"
  fi
}
create_page kontakt "Kontakt" publish '<!-- wp:pattern {"slug":"pawline/kontakt"} /-->'
for entry in "impressum|Impressum" "datenschutz|Datenschutzerklärung" "agb|AGB" "widerruf|Widerrufsbelehrung" "versand-zahlung|Versand & Zahlung"; do
  slug=${entry%%|*}; title=${entry#*|}
  create_page "$slug" "$title" draft '<!-- wp:pattern {"slug":"pawline/rechtstext-platzhalter"} /-->'
done

say "Belegablage"
wp eval '$s = \Pawline\Dropship\Private_Storage::instance(); $e = $s->location_error(); echo null === $e ? "Belegablage bereit: " . $s->base_dir() . "\n" : "WARNUNG: " . $e . "\n";'

say "Versionen dokumentieren -> docs/VERSIONEN.md"
{
  echo "# Installierte Versionen (automatisch erzeugt)"
  echo
  echo "Erzeugt: $(date -u +%Y-%m-%dT%H:%MZ) durch scripts/setup-wp.sh"
  echo
  echo "- WordPress: $(wp core version)"
  echo "- PHP: $(php -r 'echo PHP_VERSION;')"
  echo "- Datenbank: $(wp db query 'SELECT VERSION();' --skip-column-names)"
  echo
  echo "| Plugin | Version | Status |"
  echo "|---|---|---|"
  wp plugin list --fields=name,version,status --format=csv | tail -n +2 | awk -F, '{print "| "$1" | "$2" | "$3" |"}'
  echo
  echo "Theme: $(wp theme list --status=active --field=name) $(wp theme list --status=active --field=version)"
} > /project/docs/VERSIONEN.md || echo "WARNUNG: docs/VERSIONEN.md nicht schreibbar."

say "Fertig. Shop: $WP_URL  Verwaltung: $WP_URL/wp-admin  Mails: http://localhost:8025"
