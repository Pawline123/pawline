#!/bin/sh
# Eindeutig gekennzeichnete DEMODATEN – nur für die lokale Test-Instanz.
# Alle Einträge tragen "[DEMO]" im Namen und die Markierung _pawline_demo=1.
# Entfernen: .\scripts\pawline.ps1 demo-entfernen
set -eu
U="$WP_ADMIN_USER"

if [ -n "$(wp post list --post_type=product --meta_key=_pawline_demo --meta_value=1 --format=ids)" ]; then
  echo "Demodaten existieren bereits."; exit 0
fi

CAT=$(wp wc product_cat create --user="$U" --name="[DEMO] Kategorie" --slug="demo-kategorie" --porcelain)
META='[{"key":"_pawline_demo","value":"1"},{"key":"_pawline_dropship","value":"yes"}]'

wp wc product create --user="$U" --name="[DEMO] Einfaches Produkt" --type=simple \
  --regular_price=19.90 --sku=DEMO-001 --manage_stock=false --stock_status=instock \
  --categories="[{\"id\":$CAT}]" --description="Demodaten – nicht für den Live-Import." \
  --meta_data="$META" --porcelain >/dev/null

VAR=$(wp wc product create --user="$U" --name="[DEMO] Variantenprodukt" --type=variable --sku=DEMO-002 \
  --categories="[{\"id\":$CAT}]" --description="Demodaten mit zwei Varianten." \
  --attributes='[{"name":"Größe","options":["S","M"],"variation":true,"visible":true}]' \
  --meta_data="$META" --porcelain)
for size in S M; do
  wp wc product_variation create "$VAR" --user="$U" --regular_price=24.90 --sku="DEMO-002-$size" \
    --manage_stock=false --stock_status=instock --attributes="[{\"name\":\"Größe\",\"option\":\"$size\"}]" --porcelain >/dev/null
done
echo "Demodaten angelegt (Kategorie $CAT, Variantenprodukt $VAR)."
