#!/bin/sh
# Entfernt alle mit _pawline_demo=1 markierten Produkte und die Demo-Kategorie.
set -eu
IDS=$(wp post list --post_type=product --meta_key=_pawline_demo --meta_value=1 --post_status=any --format=ids)
for id in $IDS; do
  wp wc product delete "$id" --user="$WP_ADMIN_USER" --force=true >/dev/null && echo "gelöscht: Produkt $id"
done
CAT=$(wp term list product_cat --slug=demo-kategorie --field=term_id)
[ -n "$CAT" ] && wp term delete product_cat "$CAT"
echo "Demodaten entfernt."
