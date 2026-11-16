<?php
/**
 * Geschützte Ablage für Eingangsbelege.
 *
 * - Dateien liegen NICHT in der Mediathek und erhalten zufällige Namen.
 * - Standardpfad: wp-content/uploads/pawline-private/ mit Sperrdateien für Apache/IIS.
 *   Besser: in wp-config.php einen Pfad AUSSERHALB des Web-Verzeichnisses setzen:
 *   define( 'PAWLINE_PRIVATE_DIR', '/pfad/ausserhalb/webroot/pawline-belege' );
 * - Auslieferung nur über admin-post.php mit Rechteprüfung und Nonce.
 * - Gespeicherte Dateien werden nicht überschrieben; Prüfsumme (SHA-256) wird festgehalten.
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Private_Storage {

	private string $base;

	public function __construct( string $base_dir ) {
		$this->base = rtrim( str_replace( '\\', '/', $base_dir ), '/' );
	}

	/** Instanz mit konfiguriertem Pfad (WordPress-Kontext). */
	public static function instance(): self {
		static $inst = null;
		if ( null === $inst ) {
			if ( defined( 'PAWLINE_PRIVATE_DIR' ) && PAWLINE_PRIVATE_DIR ) {
				$dir = (string) PAWLINE_PRIVATE_DIR;
			} else {
				$uploads = wp_upload_dir( null, false );
				$dir     = $uploads['basedir'] . '/pawline-private';
			}
			$inst = new self( $dir );
		}
		return $inst;
	}

	public function base_dir(): string {
		return $this->base;
	}

	/** Gibt an, ob der Ablagepfad außerhalb des Web-Verzeichnisses konfiguriert ist. */
	public static function is_outside_webroot_configured(): bool {
		return defined( 'PAWLINE_PRIVATE_DIR' ) && PAWLINE_PRIVATE_DIR;
	}

	/** Verzeichnis und Sperrdateien anlegen. */
	public function ensure_protected(): bool {
		if ( ! is_dir( $this->base ) && ! mkdir( $this->base, 0750, true ) && ! is_dir( $this->base ) ) {
			return false;
		}
		$files = array(
			'.htaccess'  => "# Pawline: Zugriff von außen sperren\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\nOptions -Indexes\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Stille ist Gold.\nhttp_response_code( 403 );\n",
		);
		foreach ( $files as $name => $content ) {
			$path = $this->base . '/' . $name;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $content );
			}
		}
		return true;
	}

	/**
	 * Datei übernehmen.
	 *
	 * @param string $tmp_path      Temporäre Datei (bereits mit is_uploaded_file geprüft).
	 * @param string $original_name Ursprünglicher Dateiname.
	 * @param bool   $is_upload     true: move_uploaded_file, false: copy (CLI/Tests).
	 * @return array|\RuntimeException Datensatz oder Fehler.
	 */
	public function store( string $tmp_path, string $original_name, bool $is_upload = true ) {
		if ( ! is_file( $tmp_path ) ) {
			return new \RuntimeException( 'Datei nicht gefunden.' );
		}
		$size = (int) filesize( $tmp_path );
		if ( $size <= 0 || $size > Util::MAX_FILE_BYTES ) {
			return new \RuntimeException( 'Datei ist leer oder größer als 20 MB.' );
		}
		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		$mime  = (string) $finfo->file( $tmp_path );
		$ext   = Util::validate_file_type( $original_name, $mime );
		if ( null === $ext ) {
			return new \RuntimeException( 'Nur PDF, XML (E-Rechnung), JPG oder PNG mit passendem Inhalt sind erlaubt.' );
		}
		if ( ! $this->ensure_protected() ) {
			return new \RuntimeException( 'Belegverzeichnis konnte nicht angelegt werden.' );
		}
		$year = gmdate( 'Y' );
		$dir  = $this->base . '/' . $year;
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0750, true ) && ! is_dir( $dir ) ) {
			return new \RuntimeException( 'Jahresverzeichnis konnte nicht angelegt werden.' );
		}
		do {
			$key = $year . '/' . bin2hex( random_bytes( 16 ) ) . '.' . $ext;
		} while ( file_exists( $this->base . '/' . $key ) );

		$target = $this->base . '/' . $key;
		$ok     = $is_upload ? move_uploaded_file( $tmp_path, $target ) : copy( $tmp_path, $target );
		if ( ! $ok ) {
			return new \RuntimeException( 'Datei konnte nicht gespeichert werden.' );
		}
		chmod( $target, 0640 );

		return array(
			'key'           => $key,
			'original_name' => Util::safe_display_name( $original_name ),
			'mime'          => $mime,
			'size'          => $size,
			'sha256'        => hash_file( 'sha256', $target ),
			'uploaded_at'   => gmdate( 'c' ),
		);
	}

	/** Absoluten Pfad zu einem Schlüssel – nur innerhalb der Ablage. */
	public function path_for( string $key ): ?string {
		if ( ! Util::is_valid_storage_key( $key ) ) {
			return null;
		}
		$path = $this->base . '/' . $key;
		$real = realpath( $path );
		$base = realpath( $this->base );
		if ( false === $real || false === $base ) {
			return null;
		}
		$real = str_replace( '\\', '/', $real );
		$base = str_replace( '\\', '/', $base );
		return str_starts_with( $real, $base . '/' ) ? $real : null;
	}

	/** Prüfsumme kontrollieren (Unverändertheit). */
	public function verify( array $record ): bool {
		$path = $this->path_for( (string) ( $record['key'] ?? '' ) );
		return null !== $path && hash_equals( (string) ( $record['sha256'] ?? '' ), (string) hash_file( 'sha256', $path ) );
	}
}
