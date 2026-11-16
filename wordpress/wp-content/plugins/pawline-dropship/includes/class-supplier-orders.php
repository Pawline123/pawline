<?php
/**
 * Lieferantenaufträge (manuell erfasst, nichts wird automatisch bestellt oder bezahlt).
 *
 * Ein Lieferantenauftrag kann mehreren Kundenbestellungen zugeordnet sein und umgekehrt.
 * Er verändert KEINEN Lagerbestand (Dropshipping: Ware geht direkt an den Kunden).
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Supplier_Orders {

	public const POST_TYPE = 'pawline_po';
	public const NONCE     = 'pawline_po_save';

	public const META = array(
		'supplier_name' => '_pawline_supplier_name',
		'supplier_id'   => '_pawline_supplier_id',
		'external_no'   => '_pawline_external_no',
		'amount'        => '_pawline_amount',
		'currency'      => '_pawline_currency',
		'status'        => '_pawline_status',
		'ordered_at'    => '_pawline_ordered_at',
		'notes'         => '_pawline_notes',
	);

	public const ORDER_LINK_META = '_pawline_order_id';
	public const LOG_META        = '_pawline_log';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'auto_title' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => 'Lieferantenaufträge',
					'singular_name' => 'Lieferantenauftrag',
					'add_new'       => 'Neuer Lieferantenauftrag',
					'add_new_item'  => 'Lieferantenauftrag erfassen',
					'edit_item'     => 'Lieferantenauftrag bearbeiten',
					'search_items'  => 'Lieferantenaufträge suchen',
					'not_found'     => 'Keine Lieferantenaufträge gefunden.',
					'menu_name'     => 'Einkauf',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'pawline',
				'show_in_rest'    => false,
				'supports'        => false,
				'capability_type' => array( 'pawline_po', 'pawline_pos' ),
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/** Gespeicherte Daten eines Auftrags. */
	public static function get( int $post_id ): array {
		$d = array();
		foreach ( self::META as $field => $key ) {
			$d[ $field ] = get_post_meta( $post_id, $key, true );
		}
		$d['status']    = Util::sanitize_status( $d['status'] );
		$d['currency']  = $d['currency'] ? $d['currency'] : 'EUR';
		$d['amount']    = '' === $d['amount'] ? null : (string) $d['amount'];
		$d['order_ids'] = array_map( 'intval', get_post_meta( $post_id, self::ORDER_LINK_META, false ) );
		return $d;
	}

	/**
	 * Zuordnung Kundenbestellung => Lieferantenaufträge (ohne stornierte, ohne Papierkorb).
	 *
	 * @return array<int,int[]>
	 */
	public static function linked_order_map(): array {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$map = array();
		foreach ( $ids as $po_id ) {
			if ( 'storniert' === Util::sanitize_status( get_post_meta( $po_id, self::META['status'], true ) ) ) {
				continue;
			}
			foreach ( get_post_meta( $po_id, self::ORDER_LINK_META, false ) as $oid ) {
				$map[ (int) $oid ][] = (int) $po_id;
			}
		}
		return $map;
	}

	/** Aufträge zu einer Kundenbestellung (inkl. stornierte, zur Anzeige). */
	public static function for_order( int $order_id ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => self::ORDER_LINK_META,
						'value' => (string) $order_id,
					),
				),
			)
		);
	}

	public static function meta_boxes(): void {
		add_meta_box( 'pawline_po_main', 'Lieferantenauftrag', array( __CLASS__, 'render_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'pawline_po_log', 'Änderungsprotokoll', array( __CLASS__, 'render_log' ), self::POST_TYPE, 'normal', 'low' );
		add_meta_box( 'pawline_po_invoices', 'Zugeordnete Eingangsbelege', array( __CLASS__, 'render_invoices' ), self::POST_TYPE, 'side' );
	}

	public static function render_box( \WP_Post $post ): void {
		$d = self::get( $post->ID );
		if ( 'auto-draft' === $post->post_status && isset( $_GET['wc_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$d['order_ids'] = Util::parse_id_list( wp_unslash( $_GET['wc_order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		}
		wp_nonce_field( self::NONCE, '_pawline_po_nonce' );
		$suppliers = self::atum_suppliers();
		?>
		<p class="description">Erfasse hier, was du im Lieferantenportal <strong>manuell</strong> bestellt hast. Dieses Plugin bestellt oder bezahlt nichts automatisch und bucht keinen Lagerbestand.</p>
		<table class="form-table pawline-form">
			<tr>
				<th><label for="pawline_order_ids">Kundenbestellung(en)</label></th>
				<td>
					<input type="text" class="regular-text" id="pawline_order_ids" name="pawline[order_ids]" value="<?php echo esc_attr( implode( ', ', $d['order_ids'] ) ); ?>" placeholder="z. B. 1042, 1043">
					<p class="description">Bestellnummern (IDs), kommagetrennt. Ein Lieferantenauftrag kann mehrere Bestellungen abdecken.</p>
					<?php foreach ( $d['order_ids'] as $oid ) : ?>
						<?php $o = function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null; ?>
						<?php if ( $o ) : ?>
							<a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">Bestellung #<?php echo esc_html( $o->get_order_number() ); ?></a> (<?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?>)<br>
						<?php endif; ?>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th><label for="pawline_supplier_name">Lieferant</label></th>
				<td>
					<?php if ( $suppliers ) : ?>
						<select name="pawline[supplier_id]" id="pawline_supplier_id">
							<option value="">– ATUM-Lieferant wählen (optional) –</option>
							<?php foreach ( $suppliers as $sid => $sname ) : ?>
								<option value="<?php echo esc_attr( $sid ); ?>" <?php selected( (int) $d['supplier_id'], $sid ); ?>><?php echo esc_html( $sname ); ?></option>
							<?php endforeach; ?>
						</select><br>
					<?php endif; ?>
					<input type="text" class="regular-text" id="pawline_supplier_name" name="pawline[supplier_name]" value="<?php echo esc_attr( $d['supplier_name'] ); ?>" placeholder="Name des Lieferanten">
				</td>
			</tr>
			<tr>
				<th><label for="pawline_external_no">Auftragsnummer beim Lieferanten</label></th>
				<td><input type="text" class="regular-text" id="pawline_external_no" name="pawline[external_no]" value="<?php echo esc_attr( $d['external_no'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="pawline_amount">Einkaufsbetrag (gezahlt, brutto)</label></th>
				<td>
					<input type="text" id="pawline_amount" name="pawline[amount]" value="<?php echo esc_attr( null === $d['amount'] ? '' : str_replace( '.', ',', $d['amount'] ) ); ?>" placeholder="z. B. 24,90" inputmode="decimal">
					<input type="text" id="pawline_currency" name="pawline[currency]" value="<?php echo esc_attr( $d['currency'] ); ?>" size="4" maxlength="3" aria-label="Währung">
					<p class="description">Tatsächlich gezahlter Betrag einschließlich Umsatzsteuer und Versand des Lieferanten. Als Kleinunternehmer ist die Vorsteuer nicht abziehbar und gehört zu den Kosten. Leer lassen, wenn noch unbekannt.</p>
				</td>
			</tr>
			<tr>
				<th><label for="pawline_status">Status</label></th>
				<td>
					<select id="pawline_status" name="pawline[status]">
						<?php foreach ( Util::PO_STATUSES as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $d['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pawline_ordered_at">Bestelldatum beim Lieferanten</label></th>
				<td><input type="date" id="pawline_ordered_at" name="pawline[ordered_at]" value="<?php echo esc_attr( $d['ordered_at'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="pawline_notes">Notizen</label></th>
				<td><textarea id="pawline_notes" name="pawline[notes]" rows="3" class="large-text"><?php echo esc_textarea( $d['notes'] ); ?></textarea></td>
			</tr>
		</table>
		<?php
	}

	public static function render_log( \WP_Post $post ): void {
		Plugin::render_log( (array) get_post_meta( $post->ID, self::LOG_META, true ) );
	}

	public static function render_invoices( \WP_Post $post ): void {
		$ids = Supplier_Invoices::for_po( $post->ID );
		if ( ! $ids ) {
			echo '<p>Noch kein Eingangsbeleg zugeordnet.</p>';
		}
		foreach ( $ids as $iid ) {
			printf( '<a href="%s">%s</a><br>', esc_url( get_edit_post_link( $iid ) ), esc_html( get_the_title( $iid ) ) );
		}
		printf(
			'<p><a class="button" href="%s">Eingangsbeleg erfassen</a></p>',
			esc_url( admin_url( 'post-new.php?post_type=' . Supplier_Invoices::POST_TYPE . '&pawline_po=' . $post->ID ) )
		);
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['_pawline_po_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pawline_po_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in     = isset( $_POST['pawline'] ) && is_array( $_POST['pawline'] ) ? wp_unslash( $_POST['pawline'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$before = self::get( $post_id );

		$errors    = array();
		$order_ids = array();
		foreach ( Util::parse_id_list( $in['order_ids'] ?? '' ) as $oid ) {
			if ( function_exists( 'wc_get_order' ) && wc_get_order( $oid ) ) {
				$order_ids[] = $oid;
			} else {
				$errors[] = sprintf( 'Bestellung %d existiert nicht und wurde nicht zugeordnet.', $oid );
			}
		}

		$amount_raw = trim( (string) ( $in['amount'] ?? '' ) );
		$amount     = Util::parse_amount( $amount_raw );
		if ( '' !== $amount_raw && null === $amount ) {
			$errors[] = 'Der Einkaufsbetrag ist ungültig und wurde nicht geändert.';
			$amount   = $before['amount'];
		}

		$status     = Util::sanitize_status( $in['status'] ?? 'offen' );
		$ordered_at = Util::sanitize_date( $in['ordered_at'] ?? '' );
		if ( '' === $ordered_at && 'bestellt' === $status && 'offen' === $before['status'] ) {
			$ordered_at = current_time( 'Y-m-d' );
		}

		$supplier_id   = absint( $in['supplier_id'] ?? 0 );
		$supplier_name = sanitize_text_field( $in['supplier_name'] ?? '' );
		if ( $supplier_id && '' === $supplier_name ) {
			$supplier_name = get_the_title( $supplier_id );
		}

		$after = array(
			'supplier_name' => $supplier_name,
			'supplier_id'   => $supplier_id ? (string) $supplier_id : '',
			'external_no'   => sanitize_text_field( $in['external_no'] ?? '' ),
			'amount'        => $amount,
			'currency'      => Util::sanitize_currency( $in['currency'] ?? 'EUR' ),
			'status'        => $status,
			'ordered_at'    => $ordered_at,
			'notes'         => sanitize_textarea_field( $in['notes'] ?? '' ),
		);

		foreach ( self::META as $field => $key ) {
			if ( null === $after[ $field ] || '' === $after[ $field ] ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $after[ $field ] );
			}
		}

		delete_post_meta( $post_id, self::ORDER_LINK_META );
		foreach ( $order_ids as $oid ) {
			add_post_meta( $post_id, self::ORDER_LINK_META, (string) $oid );
		}

		$after['order_ids'] = $order_ids;
		Plugin::append_log( $post_id, self::LOG_META, Util::changed_fields( $before, $after ) );

		// Bestellnotiz in der Kundenbestellung (nur bei neuen Zuordnungen).
		foreach ( array_diff( $order_ids, $before['order_ids'] ) as $oid ) {
			$o = wc_get_order( $oid );
			if ( $o ) {
				$o->add_order_note( sprintf( 'Lieferantenauftrag LA-%d zugeordnet (manuell erfasst).', $post_id ) );
			}
		}

		if ( $errors ) {
			set_transient( 'pawline_notice_' . get_current_user_id(), $errors, 60 );
		}
	}

	/** Titel automatisch: "LA-123 · Lieferant · Nr." */
	public static function auto_title( array $data, array $postarr ): array {
		if ( self::POST_TYPE !== $data['post_type'] || empty( $postarr['ID'] ) ) {
			return $data;
		}
		$in    = isset( $_POST['pawline'] ) && is_array( $_POST['pawline'] ) ? wp_unslash( $_POST['pawline'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$parts = array( 'LA-' . (int) $postarr['ID'] );
		$name  = sanitize_text_field( $in['supplier_name'] ?? get_post_meta( (int) $postarr['ID'], self::META['supplier_name'], true ) );
		$ext   = sanitize_text_field( $in['external_no'] ?? get_post_meta( (int) $postarr['ID'], self::META['external_no'], true ) );
		if ( $name ) {
			$parts[] = $name;
		}
		if ( $ext ) {
			$parts[] = $ext;
		}
		$data['post_title'] = implode( ' · ', $parts );
		return $data;
	}

	public static function columns( array $cols ): array {
		return array(
			'cb'             => $cols['cb'] ?? '',
			'title'          => 'Lieferantenauftrag',
			'pawline_orders' => 'Kundenbestellungen',
			'pawline_amount' => 'Einkaufsbetrag',
			'pawline_status' => 'Status',
			'pawline_date'   => 'Bestellt am',
			'pawline_docs'   => 'Belege',
		);
	}

	public static function column( string $col, int $post_id ): void {
		$d = self::get( $post_id );
		switch ( $col ) {
			case 'pawline_orders':
				$links = array();
				foreach ( $d['order_ids'] as $oid ) {
					$o       = wc_get_order( $oid );
					$links[] = $o ? sprintf( '<a href="%s">#%s</a>', esc_url( $o->get_edit_order_url() ), esc_html( $o->get_order_number() ) ) : '#' . (int) $oid . ' (fehlt)';
				}
				echo $links ? wp_kses_post( implode( ', ', $links ) ) : '<em>keine</em>';
				break;
			case 'pawline_amount':
				echo null === $d['amount'] ? '<em>nicht erfasst</em>' : esc_html( Util::format_amount( $d['amount'], $d['currency'] ) );
				break;
			case 'pawline_status':
				echo esc_html( Util::PO_STATUSES[ $d['status'] ] );
				break;
			case 'pawline_date':
				echo esc_html( $d['ordered_at'] ? $d['ordered_at'] : '–' );
				break;
			case 'pawline_docs':
				echo esc_html( (string) count( Supplier_Invoices::for_po( $post_id ) ) );
				break;
		}
	}

	public static function notices(): void {
		$key    = 'pawline_notice_' . get_current_user_id();
		$errors = get_transient( $key );
		if ( ! $errors ) {
			return;
		}
		delete_transient( $key );
		foreach ( (array) $errors as $e ) {
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $e ) );
		}
	}

	/** ATUM-Lieferanten, falls ATUM aktiv ist (Post-Typ "atum_supplier"). */
	public static function atum_suppliers(): array {
		if ( ! post_type_exists( 'atum_supplier' ) ) {
			return array();
		}
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'atum_supplier', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ) ) as $p ) {
			$out[ $p->ID ] = $p->post_title;
		}
		return $out;
	}
}
