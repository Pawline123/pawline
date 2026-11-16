# Pawline Shop

Vercel-kompatibler Next.js-Shop. Produkte werden über `/api/products` aus `https://pawline-shop.de/products.json?limit=250` geladen. Der Warenkorb leitet in den vorhandenen Shopify-Checkout weiter.

## Deployment
1. Den Inhalt dieses Ordners in ein GitHub-Repository hochladen (nicht die ZIP-Datei).
2. Repository in Vercel importieren.
3. Framework Preset: Next.js, Root Directory: `./`.
4. Deploy klicken.

Es sind keine Environment Variables nötig.

## WordPress-Projekt (Ablösung von Shopify)
Der Ordner `wordpress/` enthält das neue WordPress/WooCommerce-Projekt (Block-Theme `pawline`, Plugin `pawline-dropship`, Docker-Umgebung, Tests). Einstieg: `wordpress/README.md` und `wordpress/CLAUDE.md`. Der Next.js-Shop oben bleibt bis zur Umstellung unverändert.
