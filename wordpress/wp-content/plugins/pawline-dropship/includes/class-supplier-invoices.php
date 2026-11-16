<?php
/**
 * Eingangsbelege (Lieferantenrechnungen) mit geschützter Dateiablage.
 *
 * - Beträge und Steuerangaben werden so erfasst, wie sie auf dem Originalbeleg stehen.
 * - Originaldateien (PDF, XML der E-Rechnung, Scan) werden unverändert abgelegt; Prüfsumme wird gespeichert.
 * - Dateien können über die Oberfläche nicht gelöscht werden; Belege mit Dateien nicht endgültig löschbar.
 * - Interne Lieferantenaufträge sind KEINE Eingangsrechnungen; sie werden getrennt geführt.
 * - Dies ist eine Arbeitsablage, keine Zusage einer GoBD-konformen Archivierung.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Supplier_Invoices {

	public const POST_TYPE = 'pawline_invoice';
	public const NONCE     = 'pawline_invoice_save';

	public const META = array(
		'supplier_name' => '_pawline_supplier_name',
		'invoice_no'    => '_pawline_invoice_no',
		'invoice_date'  => '_pawline_invoice_date',
		'net'           => '_pawline_net',
		'tax'           => '_pawline_tax',
		'gross'         => '_pawline_gross',
		'tax_note'      => '_pawline_tax_note',
		'currency'      => '_pawline_currency',
		'notes'         => '_pawline_notes',
	);

	public const PO_LINK_META = '_pawline_po_id';
	public const FILES_META   = '_pawline_files';
	public const LOG_META     = '_pawline_log';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'post_edit_form_tag', array( __CLASS__, 'form_enctype' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'auto_title' ), 10, 2 );
		add_filter( 'pre_delete_post', array( __CLASS__, 'prevent_delete' ), 10, 2 );
		add_action( 'admin_post_pawline_download', array( __CLASS__, 'download' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => 'Eingangsbelege',
					'singular_name' => 'Eingangsbeleg',
					'add_new'       => 'Neuer Eingangsbeleg',
					'add_new_item'  => 'Eingangsbeleg erfassen',
					'edit_item'     => 'Eingangsbeleg bearbeiten',
					'search_items'  => 'Eingangsbelege suchen',
					'not_found'     => 'Keine Eingangsbelege gefunden.',
					'menu_name'     => 'Eingangsbelege',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'pawline',
				'show_in_rest'    => false,
				'supports'        => false,
				'capability_type' => array( 'pawline_invoice', 'pawline_invoices' ),
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	public static function get( int $post_id ): array {
		$d = array();
		foreach ( self::META as $field => $key ) {
			$d[ $field ] = (string) get_post_meta( $post_id, $key, true );
		}
		foreach ( array( 'net', 'tax', 'gross' ) as $f ) {
			$d[ $f ] = '' === $d[ $f ] ? null : $d[ $f ];
		}
		$d['currency'] = $d['currency'] ? $d['currency'] : 'EUR';
		$d['po_ids']   = array_map( 'intval', get_post_meta( $post_id, self::PO_LINK_META, false ) );
		$files         = get_post_meta( $post_id, self::FILES_META, true );
		$d['files']    = is_array( $files ) ? $files : array();
		return $d;
	}

	public static function for_po( int $po_id ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => self::PO_LINK_META,
						'value' => (string) $po_id,
					),
				),
			)
		);
	}

	public static function form_enctype( \WP_Post $post ): void {
		if ( self::POST_TYPE === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	public static function meta_boxes(): void {
		add_meta_box( 'pawline_inv_main', 'Angaben laut Originalbeleg', array( __CLASS__, 'render_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'pawline_inv_files', 'Originaldateien (geschützt)', array( __CLASS__, 'render_files' ), self::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'pawline_inv_log', 'Änderungsprotokoll', array( __CLASS__, 'render_log' ), self::POST_TYPE, 'normal', 'low' );
	}

	public static function render_box( \WP_Post $post ): void {
		$d = self::get( $post->ID );
		if ( 'auto-draft' === $post->post_status && isset( $_GET['pawline_po'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$d['po_ids'] = Util::parse_id_list( wp_unslash( $_GET['pawline_po'] ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
			if ( $d['po_ids'] ) {
				$d['supplier_name'] = (string) get_post_meta( $d['po_ids'][0], Supplier_Orders::META['supplier_name'], true );
			}
		}
		wp_nonce_field( self::NONCE, '_pawline_inv_nonce' );
		$fmt = static fn( $v ) => null === $v ? '' : str_replace( '.', ',', $v );
		?>
		<p class="description">Übernimm die Werte <strong>genau so, wie sie auf der Rechnung des Lieferanten stehen</strong> – auch Umsatzsteuer, die du als Kleinunternehmer nicht als Vorsteuer abziehen kannst.</p>
		<table class="form-table pawline-form">
			<tr><th><label for="pi_supplier">Lieferant</label></th>
				<td><input type="text" class="regular-text" id="pi_supplier" name="pawline_inv[supplier_name]" value="<?php echo esc_attr( $d['supplier_name'] ); ?>" required></td></tr>
			<tr><th><label for="pi_no">Rechnungsnummer des Lieferanten</label></th>
				<td><input type="text" class="regular-text" id="pi_no" name="pawline_inv[invoice_no]" value="<?php echo esc_attr( $d['invoice_no'] ); ?>"></td></tr>
			<tr><th><label for="pi_date">Rechnungsdatum</label></th>
				<td><input type="date" id="pi_date" name="pawline_inv[invoice_date]" value="<?php echo esc_attr( $d['invoice_date'] ); ?>"></td></tr>
			<tr><th>Beträge laut Beleg</th>
				<td>
					<label>Netto <input type="text" name="pawline_inv[net]" value="<?php echo esc_attr( $fmt( $d['net'] ) ); ?>" size="10" inputmode="decimal"></label>
					<label>Umsatzsteuer <input type="text" name="pawline_inv[tax]" value="<?php echo esc_attr( $fmt( $d['tax'] ) ); ?>" size="10" inputmode="decimal"></label>
					<label>Gesamt (brutto) <input type="text" name="pawline_inv[gross]" value="<?php echo esc_attr( $fmt( $d['gross'] ) ); ?>" size="10" inputmode="decimal"></label>
					<label>Währung <input type="text" name="pawline_inv[currency]" value="<?php echo esc_attr( $d['currency'] ); ?>" size="4" maxlength="3"></label>
				</td></tr>
			<tr><th><label for="pi_taxnote">Steuerangabe laut Beleg</label></th>
				<td><input type="text" class="regular-text" id="pi_taxnote" name="pawline_inv[tax_note]" value="<?php echo esc_attr( $d['tax_note'] ); ?>" placeholder="z. B. 19 % USt / ohne USt / Steuerschuldnerschaft des Leistungsempfängers">
				<p class="description">Grenzüberschreitende Einkäufe (z. B. aus dem EU-Ausland oder Drittland) können besondere steuerliche Pflichten auslösen. Diese werden nicht automatisch behandelt – bitte steuerlich klären.</p></td></tr>
			<tr><th><label for="pi_pos">Lieferantenaufträge</label></th>
				<td><input type="text" class="regular-text" id="pi_pos" name="pawline_inv[po_ids]" value="<?php echo esc_attr( implode( ', ', $d['po_ids'] ) ); ?>" placeholder="z. B. 51, 52">
				<p class="description">IDs der Lieferantenaufträge (LA-Nummer ohne „LA-“). Eine Rechnung kann mehrere Aufträge abdecken.</p>
				<?php foreach ( $d['po_ids'] as $po ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $po ) ); ?>"><?php echo esc_html( get_the_title( $po ) ); ?></a><br>
				<?php endforeach; ?></td></tr>
			<tr><th><label for="pi_notes">Notizen</label></th>
				<td><textarea id="pi_notes" name="pawline_inv[notes]" rows="2" class="large-text"><?php echo esc_textarea( $d['notes'] ); ?></textarea></td></tr>
		</table>
		<?php
		$warning = self::consistency_warning( $d );
		if ( $warning ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $warning ) );
		}
	}

	public static function render_files( \WP_Post $post ): void {
		$d       = self::get( $post->ID );
		$storage = Private_Storage::instance();
		if ( $d['files'] ) {
			echo '<table class="widefat striped"><thead><tr><th>Datei</th><th>Typ</th><th>Größe</th><th>Hochgeladen</th><th>Prüfsumme</th></tr></thead><tbody>';
			foreach ( $d['files'] as $i => $f ) {
				$url = wp_nonce_url(
					admin_url( 'admin-post.php?action=pawline_download&invoice=' . $post->ID . '&file=' . (int) $i ),
					'pawline_download_' . $post->ID . '_' . (int) $i
				);
				$ok = $storage->verify( $f );
				printf(
					'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s KB</td><td>%s</td><td>%s</td></tr>',
					esc_url( $url ),
					esc_html( $f['original_name'] ),
					esc_html( $f['mime'] ),
					esc_html( number_format_i18n( $f['size'] / 1024, 1 ) ),
					esc_html( $f['uploaded_at'] ),
					$ok ? '✔ unverändert' : '<strong style="color:#b32d2e">⚠ Datei fehlt oder verändert</strong>'
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p>Noch keine Datei abgelegt.</p>';
		}
		?>
		<?php
		$storage_error = $storage->location_error();
		if ( null !== $storage_error ) {
			printf( '<div class="notice notice-error inline"><p><strong>Beleguploads gesperrt:</strong> %s</p></div>', esc_html( $storage_error ) );
			return;
		}
		?>
		<p><label for="pawline_files"><strong>Originalbeleg hinzufügen</strong> (PDF, XML der E-Rechnung, JPG, PNG; max. 20 MB je Datei)</label><br>
		<input type="file" id="pawline_files" name="pawline_files[]" multiple accept=".pdf,.xml,.jpg,.jpeg,.png"></p>
		<p class="description">Dateien werden außerhalb des Web-Verzeichnisses und der Mediathek mit zufälligem Namen gespeichert und nur angemeldeten Berechtigten über eine geprüfte Download-Adresse ausgeliefert. Abgelegte Dateien können hier nicht gelöscht oder ersetzt werden.</p>
		<?php
	}

	public static function render_log( \WP_Post $post ): void {
		Plugin::render_log( (array) get_post_meta( $post->ID, self::LOG_META, true ) );
	}

	/** Hinweis, wenn Netto + Steuer ≠ Brutto (Werte bleiben trotzdem wie eingegeben). */
	public static function consistency_warning( array $d ): ?string {
		if ( null === $d['net'] || null === $d['tax'] || null === $d['gross'] ) {
			return null;
		}
		$diff = Margin::to_cents( $d['net'] ) + Margin::to_cents( $d['tax'] ) - Margin::to_cents( $d['gross'] );
		return abs( $diff ) > 1 ? 'Netto + Umsatzsteuer ergibt nicht den Gesamtbetrag. Bitte mit dem Originalbeleg abgleichen.' : null;
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['_pawline_inv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pawline_inv_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( Capabilities::DOCUMENTS ) ) {
			return;
		}
		$in     = isset( $_POST['pawline_inv'] ) && is_array( $_POST['pawline_inv'] ) ? wp_unslash( $_POST['pawline_inv'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$before = self::get( $post_id );
		$errors = array();

		$after = array(
			'supplier_name' => sanitize_text_field( $in['supplier_name'] ?? '' ),
			'invoice_no'    => sanitize_text_field( $in['invoice_no'] ?? '' ),
			'invoice_date'  => Util::sanitize_date( $in['invoice_date'] ?? '' ),
			'tax_note'      => sanitize_text_field( $in['tax_note'] ?? '' ),
			'currency'      => Util::sanitize_currency( $in['currency'] ?? 'EUR' ),
			'notes'         => sanitize_textarea_field( $in['notes'] ?? '' ),
		);
		foreach ( array( 'net' => 'Netto', 'tax' => 'Umsatzsteuer', 'gross' => 'Gesamt' ) as $f => $label ) {
			$raw = trim( (string) ( $in[ $f ] ?? '' ) );
			$val = Util::parse_amount( $raw );
			if ( '' !== $raw && null === $val ) {
				$errors[] = sprintf( '%s-Betrag ist ungültig und wurde nicht geändert.', $label );
				$val      = $before[ $f ];
			}
			$after[ $f ] = $val;
		}

		foreach ( self::META as $field => $key ) {
			if ( null === $after[ $field ] || '' === $after[ $field ] ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $after[ $field ] );
			}
		}

		$po_ids = array();
		foreach ( Util::parse_id_list( $in['po_ids'] ?? '' ) as $po ) {
			if ( Supplier_Orders::POST_TYPE === get_post_type( $po ) ) {
				$po_ids[] = $po;
			} else {
				$errors[] = sprintf( 'Lieferantenauftrag %d existiert nicht und wurde nicht zugeordnet.', $po );
			}
		}
		delete_post_meta( $post_id, self::PO_LINK_META );
		foreach ( $po_ids as $po ) {
			add_post_meta( $post_id, self::PO_LINK_META, (string) $po );
		}
		$after['po_ids'] = $po_ids;

		// Dateien übernehmen (nur anhängen, nie ersetzen).
		$added = self::handle_uploads( $post_id, $errors );
		$log   = Util::changed_fields( $before, $after );
		foreach ( $added as $name ) {
			$log[] = 'Datei hinzugefügt: ' . $name;
		}
		Plugin::append_log( $post_id, self::LOG_META, $log );

		if ( $errors ) {
			set_transient( 'pawline_notice_' . get_current_user_id(), $errors, 60 );
		}
	}

	/** @return string[] Namen der hinzugefügten Dateien. */
	private static function handle_uploads( int $post_id, array &$errors ): array {
		static $done = array();
		if ( isset( $done[ $post_id ] ) ) {
			return array(); // save_post kann pro Anfrage mehrfach feuern.
		}
		$done[ $post_id ] = true;
		if ( empty( $_FILES['pawline_files']['name'] ) || ! is_array( $_FILES['pawline_files']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return array();
		}
		$files   = $_FILES['pawline_files']; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$storage = Private_Storage::instance();
		$blocked = $storage->location_error();
		if ( null !== $blocked ) {
			$errors[] = 'Beleg nicht gespeichert: ' . $blocked;
			return array();
		}
		$records = self::get( $post_id )['files'];
		$added   = array();
		foreach ( $files['name'] as $i => $name ) {
			if ( '' === $name || UPLOAD_ERR_NO_FILE === (int) $files['error'][ $i ] ) {
				continue;
			}
			if ( UPLOAD_ERR_OK !== (int) $files['error'][ $i ] || ! is_uploaded_file( $files['tmp_name'][ $i ] ) ) {
				$errors[] = sprintf( 'Upload von „%s“ fehlgeschlagen (Fehlercode %d).', Util::safe_display_name( $name ), (int) $files['error'][ $i ] );
				continue;
			}
			$result = $storage->store( $files['tmp_name'][ $i ], (string) $name, true );
			if ( $result instanceof \RuntimeException ) {
				$errors[] = sprintf( '„%s“: %s', Util::safe_display_name( $name ), $result->getMessage() );
				continue;
			}
			$result['uploaded_by'] = get_current_user_id();
			$records[]             = $result;
			$added[]               = $result['original_name'];
		}
		if ( $added ) {
			update_post_meta( $post_id, self::FILES_META, $records );
		}
		return $added;
	}

	/** Geschützter Download. */
	public static function download(): void {
		$invoice = isset( $_GET['invoice'] ) ? absint( $_GET['invoice'] ) : 0;
		$index   = isset( $_GET['file'] ) ? absint( $_GET['file'] ) : -1;
		if ( ! $invoice || $index < 0 || ! check_admin_referer( 'pawline_download_' . $invoice . '_' . $index ) ) {
			wp_die( 'Ungültige Anfrage.', '', array( 'response' => 400 ) );
		}
		if ( ! current_user_can( Capabilities::DOCUMENTS ) || ! current_user_can( 'edit_post', $invoice ) || self::POST_TYPE !== get_post_type( $invoice ) ) {
			wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) );
		}
		$files  = self::get( $invoice )['files'];
		$record = $files[ $index ] ?? null;
		$path   = $record ? Private_Storage::instance()->path_for( (string) $record['key'] ) : null;
		if ( ! $record || ! $path ) {
			wp_die( 'Datei nicht gefunden.', '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: ' . $record['mime'] );
		header( 'Content-Length: ' . filesize( $path ) );
		$ascii = preg_replace( '/[^A-Za-z0-9._\- ]/', '_', (string) $record['original_name'] );
		header( 'Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( (string) $record['original_name'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Belege mit Dateien nicht endgültig löschen (Papierkorb bleibt möglich). */
	public static function prevent_delete( $check, $post ) {
		if ( $post instanceof \WP_Post && self::POST_TYPE === $post->post_type && ! empty( self::get( $post->ID )['files'] ) ) {
			return false;
		}
		return $check;
	}

	public static function auto_title( array $data, array $postarr ): array {
		if ( self::POST_TYPE !== $data['post_type'] || empty( $postarr['ID'] ) ) {
			return $data;
		}
		$in   = isset( $_POST['pawline_inv'] ) && is_array( $_POST['pawline_inv'] ) ? wp_unslash( $_POST['pawline_inv'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$id   = (int) $postarr['ID'];
		$name = sanitize_text_field( $in['supplier_name'] ?? get_post_meta( $id, self::META['supplier_name'], true ) );
		$no   = sanitize_text_field( $in['invoice_no'] ?? get_post_meta( $id, self::META['invoice_no'], true ) );
		$data['post_title'] = implode( ' · ', array_filter( array( 'ER-' . $id, $name, $no ) ) );
		return $data;
	}

	public static function columns( array $cols ): array {
		return array(
			'cb'           => $cols['cb'] ?? '',
			'title'        => 'Eingangsbeleg',
			'pawline_date' => 'Rechnungsdatum',
			'pawline_sum'  => 'Gesamt laut Beleg',
			'pawline_tax'  => 'Steuerangabe',
			'pawline_pos'  => 'Lieferantenaufträge',
			'pawline_file' => 'Dateien',
		);
	}

	public static function column( string $col, int $post_id ): void {
		$d = self::get( $post_id );
		switch ( $col ) {
			case 'pawline_date':
				echo esc_html( $d['invoice_date'] ? $d['invoice_date'] : '–' );
				break;
			case 'pawline_sum':
				echo esc_html( Util::format_amount( $d['gross'], $d['currency'] ) );
				break;
			case 'pawline_tax':
				echo esc_html( $d['tax_note'] ? $d['tax_note'] : '–' );
				break;
			case 'pawline_pos':
				echo $d['po_ids'] ? esc_html( 'LA-' . implode( ', LA-', $d['po_ids'] ) ) : '<em>nicht zugeordnet</em>';
				break;
			case 'pawline_file':
				echo $d['files'] ? esc_html( (string) count( $d['files'] ) ) : '<em>keine Datei</em>';
				break;
		}
	}
}
