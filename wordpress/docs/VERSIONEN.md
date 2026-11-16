# Installierte Versionen

Wird von `scripts/setup-wp.sh` beim ersten `setup` automatisch mit den tatsächlich installierten Versionen überschrieben.

Bisher festgelegt (Container-Images in `docker-compose.yml`):

- `mariadb:11.4` (LTS-Reihe)
- `wordpress:php8.3-apache`
- `wordpress:cli-php8.3`
- `axllent/mailpit:latest`

Plugins werden in der jeweils aktuellen stabilen Version von wordpress.org installiert: WooCommerce, Germanized for WooCommerce, ATUM Inventory Management, WooCommerce Stripe Gateway. Theme und Plugin von Pawline: 0.1.0. Mindestanforderungen: WordPress 6.6, PHP 8.1, WooCommerce 9.0.

Die Image-Tags und Plugin-Versionen konnten in der Erstellungsumgebung nicht abgerufen werden (Downloads gesperrt) und sind beim ersten Setup zu bestätigen.
