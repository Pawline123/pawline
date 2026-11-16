<?php
/**
 * Geschützte Ablage für Eingangsbelege.
 *
 * - Belege werden NUR gespeichert, wenn in der wp-config.php ein Ordner AUSSERHALB jedes
 *   öffentlich erreichbaren Verzeichnisses festgelegt ist:
 *   define( 'PAWLINE_PRIVATE_DIR', '/pfad/ausserhalb/webroot/pawline-belege' );
 *   Es gibt bewusst KEINEN Rückfall auf wp-content/uploads: Sperrdateien (.htaccess,
 *   web.config) wirken nicht bei jeder Serverkonfiguration (z. B. nginx).
 * - Als öffentlich gelten: ABSPATH, DOCUMENT_ROOT, WP_CONTENT_DIR und der Upload-Ordner.
 *   Der Pfad wird vor UND nach dem Anlegen (realpath, Symlinks aufgelöst) geprüft.
 * - Dateien liegen nicht in der Mediathek und erhalten zufällige Namen.
 * - Auslieferung nur über admin-post.php mit Rechteprüfung und Nonce.
 * - Gespeicherte Dateien werden nicht überschrieben; Prüfsumme (SHA-256) wird festgehalten.
 * - Sperrdateien werden zusätzlich angelegt (zweite Verteidigungslinie, nicht der Schutz).
 *
 * @package Pawline\Dropship
 */

namespace Pawline\Dropship;

defined( 'ABSPATH' ) || exit;

final class Private_Storage {

	private string $base;

	/** @var string[] Öffentlich erreichbare Verzeichnisse, in denen die Ablage nicht liegen darf. */
	private array $public_roots;

	/**
	 * @param string   $base_dir     Ablageordner; leer = nicht konfiguriert (Speichern gesperrt).
	 * @param string[] $public_roots Öffentlich erreichbare Verzeichnisse.
	 */
	public function __construct( string $base_dir, array $public_roots = array() ) {
		$this->base         = '' === trim( $base_dir ) ? '' : rtrim( self::normalize( $base_dir ), '/' );
		$this->public_roots = array_values( array_filter( array_map( 'strval', $public_roots ), static fn( $r ) => '' !== trim( $r ) ) );
	}

	/** Instanz mit konfiguriertem Pfad (WordPress-Kontext). */
	public static function instance(): self {
		static $inst = null;
		if ( null === $inst ) {
			$dir   = ( defined( 'PAWLINE_PRIVATE_DIR' ) && is_string( PAWLINE_PRIVATE_DIR ) ) ? PAWLINE_PRIVATE_DIR : '';
			$roots = array( ABSPATH );
			if ( defined( 'WP_CONTENT_DIR' ) ) {
				$roots[] = WP_CONTENT_DIR;
			}
			if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$roots[] = $_SERVER['DOCUMENT_ROOT']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
			if ( function_exists( 'wp_upload_dir' ) ) {
				$uploads = wp_upload_dir( null, false );
				if ( ! empty( $uploads['basedir'] ) ) {
					$roots[] = $uploads['basedir'];
				}
			}
			$inst = new self( $dir, $roots );
		}
		return $inst;
	}

	public function base_dir(): string {
		return $this->base;
	}

	/**
	 * Prüft, ob die Ablage sicher ist. Gibt null zurück, wenn ja, sonst den Grund.
	 * Legt den Ordner an, wenn er fehlt (aber nur, wenn der Pfad schon vorab außerhalb liegt).
	 */
	public function location_error(): ?string {
		if ( '' === $this->base ) {
			return 'Keine geschützte Belegablage konfiguriert. In der wp-config.php PAWLINE_PRIVATE_DIR auf einen Ordner außerhalb des Web-Verzeichnisses setzen. Beleguploads sind bis dahin gesperrt.';
		}
		if ( ! str_starts_with( $this->base, '/' ) && ! preg_match( '#^[A-Za-z]:/#', $this->base ) ) {
			return 'PAWLINE_PRIVATE_DIR muss ein absoluter Pfad sein. Beleguploads sind gesperrt.';
		}
		$public = $this->public_root_containing( $this->base );
		if ( null !== $public ) {
			return sprintf( 'PAWLINE_PRIVATE_DIR liegt innerhalb des öffentlich erreichbaren Verzeichnisses %s. Beleguploads sind gesperrt.', $public );
		}
		// Nächsten vorhandenen Elternordner auflösen, damit ein Symlink nicht erst nach dem Anlegen auffällt.
		$ancestor = $this->base;
		while ( ! file_exists( $ancestor ) && dirname( $ancestor ) !== $ancestor ) {
			$ancestor = dirname( $ancestor );
		}
		$ancestor_real = realpath( $ancestor );
		if ( false !== $ancestor_real ) {
			$rest   = substr( $this->base, strlen( rtrim( $ancestor, '/' ) ) );
			$public = $this->public_root_containing( self::normalize( $ancestor_real . '/' . $rest ) );
			if ( null !== $public ) {
				return sprintf( 'PAWLINE_PRIVATE_DIR verweist (z. B. über einen Symlink) in das öffentlich erreichbare Verzeichnis %s. Beleguploads sind gesperrt.', $public );
			}
		}
		if ( ! is_dir( $this->base ) && ! @mkdir( $this->base, 0750, true ) && ! is_dir( $this->base ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return 'Der Ordner aus PAWLINE_PRIVATE_DIR existiert nicht und konnte nicht angelegt werden. Beleguploads sind gesperrt.';
		}
		$real = realpath( $this->base );
		if ( false === $real ) {
			return 'Der Ordner aus PAWLINE_PRIVATE_DIR ist nicht lesbar. Beleguploads sind gesperrt.';
		}
		$public = $this->public_root_containing( self::normalize( $real ) );
		if ( null !== $public ) {
			return sprintf( 'PAWLINE_PRIVATE_DIR verweist (z. B. über einen Symlink) in das öffentlich erreichbare Verzeichnis %s. Beleguploads sind gesperrt.', $public );
		}
		if ( ! is_writable( $this->base ) ) {
			return 'Der Ordner aus PAWLINE_PRIVATE_DIR ist für PHP nicht beschreibbar. Beleguploads sind gesperrt.';
		}
		return null;
	}

	public function is_secure(): bool {
		return null === $this->location_error();
	}

	/** Öffentliches Verzeichnis, in dem $path liegt (oder das $path ist), sonst null. */
	private function public_root_containing( string $path ): ?string {
		$path = rtrim( $path, '/' ) . '/';
		foreach ( $this->public_roots as $root ) {
			$candidates = array( self::normalize( $root ) );
			$real       = realpath( $root );
			if ( false !== $real ) {
				$candidates[] = self::normalize( $real );
			}
			foreach ( $candidates as $c ) {
				$c = rtrim( $c, '/' ) . '/';
				if ( self::starts_with_path( $path, $c ) ) {
					return rtrim( $c, '/' );
				}
			}
		}
		return null;
	}

	private static function starts_with_path( string $path, string $prefix ): bool {
		if ( '/' === $prefix ) {
			return true;
		}
		if ( '\\' === DIRECTORY_SEPARATOR || preg_match( '#^[A-Za-z]:/#', $prefix ) ) {
			return str_starts_with( strtolower( $path ), strtolower( $prefix ) );
		}
		return str_starts_with( $path, $prefix );
	}

	/** Schrägstriche vereinheitlichen und "." / ".." auflösen (ohne Dateisystemzugriff). */
	public static function normalize( string $path ): string {
		$path   = str_replace( '\\', '/', $path );
		$prefix = '';
		if ( preg_match( '#^([A-Za-z]:)?/#', $path, $m ) ) {
			$prefix = $m[0];
			$path   = substr( $path, strlen( $prefix ) );
		}
		$parts = array();
		foreach ( explode( '/', $path ) as $seg ) {
			if ( '' === $seg || '.' === $seg ) {
				continue;
			}
			if ( '..' === $seg ) {
				array_pop( $parts );
				continue;
			}
			$parts[] = $seg;
		}
		return $prefix . implode( '/', $parts );
	}

	/** Sperrdateien als zusätzliche Absicherung anlegen – nur in einer sicheren Ablage. */
	public function ensure_protected(): bool {
		if ( ! $this->is_secure() ) {
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
		if ( ! $this->is_secure() ) {
			return new \RuntimeException( (string) $this->location_error() );
		}
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
			return new \RuntimeException( 'Belegverzeichnis konnte nicht vorbereitet werden.' );
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
		if ( '' === $this->base || ! Util::is_valid_storage_key( $key ) ) {
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
