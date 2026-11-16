<?php
/**
 * Menü "Pawline" mit Überblick (Aufgaben) und Schnellzugriff auf alle Bereiche.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Admin_Dashboard {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu(): void {
		add_menu_page( 'Pawline', 'Pawline', Capabilities::MANAGE, 'pawline', array( __CLASS__, 'render' ), 'dashicons-pets', 56 );
		add_submenu_page( 'pawline', 'Überblick', 'Überblick', Capabilities::MANAGE, 'pawline', array( __CLASS__, 'render' ) );
	}

	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		$ours   = str_contains( $hook, 'pawline' ) || ( $screen && in_array( $screen->post_type, array( Supplier_Orders::POST_TYPE, Supplier_Invoices::POST_TYPE ), true ) );
		if ( $ours || ( $screen && function_exists( 'wc_get_page_screen_id' ) && wc_get_page_screen_id( 'shop-order' ) === $screen->id ) ) {
			wp_enqueue_style( 'pawline-admin', plugins_url( 'assets/admin.css', PAWLINE_DS_FILE ), array(), PAWLINE_DS_VERSION );
		}
	}

	public static function orders_url(): string {
		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders' );
		}
		return admin_url( 'edit.php?post_type=shop_order' );
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$map  = Supplier_Orders::linked_order_map();
		$paid = wc_get_orders(
			array(
				'status'  => array( 'processing' ),
				'limit'   => 100,
				'orderby' => 'date',
				'order'   => 'ASC',
				'type'    => 'shop_order',
			)
		);
		$todo_order = array();
		$todo_ship  = array();
		foreach ( $paid as $o ) {
			if ( empty( $map[ $o->get_id() ] ) ) {
				$todo_order[] = $o;
			} elseif ( ! Order_Integration::get_shipments( $o ) ) {
				$todo_ship[] = $o;
			}
		}
		$open_pos = get_posts(
			array(
				'post_type'      => Supplier_Orders::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => Supplier_Orders::META['status'],
						'value'   => array( 'offen', 'problem' ),
						'compare' => 'IN',
					),
				),
			)
		);
		$missing_amount = get_posts(
			array(
				'post_type'      => Supplier_Orders::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => Supplier_Orders::META['amount'],
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$conflicts = Product_Integration::conflicting_products();
		?>
		<div class="wrap pawline-wrap">
			<h1>Pawline – Überblick</h1>
			<p>Ablauf: <strong>Bestellung im Shop → Zahlung prüfen → beim Lieferanten manuell bestellen → Lieferantenauftrag erfassen → Versand/Tracking eintragen.</strong> Nichts davon läuft automatisch.</p>

			<nav class="pawline-tiles">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><span>Artikel</span>Produkte, Varianten, Preise</a>
				<a href="<?php echo esc_url( self::orders_url() ); ?>"><span>Kundenaufträge</span>Zahlung, Rechnung, Erstattung</a>
				<?php if ( post_type_exists( 'atum_supplier' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=atum_supplier' ) ); ?>"><span>Lieferanten</span>Stammdaten in ATUM</a>
				<?php else : ?>
					<a class="is-disabled" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><span>Lieferanten</span>ATUM nicht aktiv</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Supplier_Orders::POST_TYPE ) ); ?>"><span>Einkauf</span>Lieferantenaufträge</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Supplier_Invoices::POST_TYPE ) ); ?>"><span>Belege</span>Eingangsrechnungen (geschützt)</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pawline-reports' ) ); ?>"><span>Auswertung</span>Umsatz, Einkauf, Lücken</a>
			</nav>
			<p class="description">Ausgangsrechnungen und Stornobelege erstellt Germanized Pro; sie sind in der jeweiligen Bestellung abrufbar. Versand/Tracking wird in der Bestellung gepflegt.</p>

			<?php self::section( 'Beim Lieferanten bestellen', $todo_order, 'Bezahlte Bestellungen ohne Lieferantenauftrag.', true ); ?>
			<?php self::section( 'Versandinfo fehlt', $todo_ship, 'Lieferantenauftrag erfasst, aber noch keine Trackingnummer.', false ); ?>

			<h2>Lieferantenaufträge offen oder mit Problem (<?php echo (int) count( $open_pos ); ?>)</h2>
			<?php self::po_list( $open_pos ); ?>

			<h2>Lieferantenaufträge ohne Einkaufsbetrag (<?php echo (int) count( $missing_amount ); ?>)</h2>
			<?php self::po_list( $missing_amount ); ?>

			<?php if ( $conflicts ) : ?>
				<h2>Prüfen: Dropshipping-Artikel mit eigener Bestandsführung (<?php echo (int) count( $conflicts ); ?>)</h2>
				<ul>
					<?php foreach ( $conflicts as $p ) : ?>
						<li><a href="<?php echo esc_url( (string) get_edit_post_link( $p->get_id() ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! Private_Storage::is_outside_webroot_configured() ) : ?>
				<div class="notice notice-info inline"><p><strong>Belegablage:</strong> liegt derzeit unter <code><?php echo esc_html( Private_Storage::instance()->base_dir() ); ?></code> mit Zugriffssperre für Apache/IIS. Für den Live-Betrieb einen Ordner außerhalb des Web-Verzeichnisses über <code>PAWLINE_PRIVATE_DIR</code> in der wp-config.php festlegen. Bei nginx-Servern greift die .htaccess-Sperre nicht.</p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @param \WC_Order[] $orders */
	private static function section( string $title, array $orders, string $desc, bool $with_button ): void {
		printf( '<h2>%s (%d)</h2><p class="description">%s</p>', esc_html( $title ), count( $orders ), esc_html( $desc ) );
		if ( ! $orders ) {
			echo '<p><em>Nichts zu tun.</em></p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Bestellung</th><th>Datum</th><th>Betrag</th><th>Artikel</th><th></th></tr></thead><tbody>';
		foreach ( $orders as $o ) {
			$items = array();
			foreach ( $o->get_items() as $item ) {
				$items[] = $item->get_quantity() . '× ' . $item->get_name();
			}
			$created = $o->get_date_created();
			printf(
				'<tr><td><a href="%s">#%s</a></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_url( $o->get_edit_order_url() ),
				esc_html( $o->get_order_number() ),
				esc_html( $created ? $created->date_i18n( 'd.m.Y H:i' ) : '' ),
				wp_kses_post( $o->get_formatted_order_total() ),
				esc_html( implode( ', ', $items ) ),
				$with_button ? sprintf( '<a class="button button-small" href="%s">Lieferantenauftrag erfassen</a>', esc_url( admin_url( 'post-new.php?post_type=' . Supplier_Orders::POST_TYPE . '&wc_order=' . $o->get_id() ) ) ) : ''
			);
		}
		echo '</tbody></table>';
	}

	private static function po_list( array $ids ): void {
		if ( ! $ids ) {
			echo '<p><em>Keine.</em></p>';
			return;
		}
		echo '<ul>';
		foreach ( $ids as $id ) {
			$d = Supplier_Orders::get( $id );
			printf( '<li><a href="%s">%s</a> – %s</li>', esc_url( (string) get_edit_post_link( $id ) ), esc_html( get_the_title( $id ) ), esc_html( Util::PO_STATUSES[ $d['status'] ] ) );
		}
		echo '</ul>';
	}
}
