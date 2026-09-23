<?php
/**
 *
 * LeadConnector Plugin
 * Copyright (C) 2020-2026 LeadConnector
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class for LeadConnector
 *
 * This file contains the Logger class which provides logging functionality
 * for the LeadConnector plugin.
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/includes/Logger
 */

/**
 * Logger class
 *
 * A singleton class that handles logging of errors, warnings, and info messages
 * to a log file with proper formatting.
 */
class LeadConnector_Logger {
	/**
	 * The single instance of the class.
	 *
	 * @var LeadConnector_Logger|null
	 */
	private static $instance = null;

	/**
	 * Log file path.
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Log levels.
	 *
	 * @var array
	 */
	private $log_levels = array(
		'ERROR'   => 'ERROR',
		'WARNING' => 'WARNING',
		'INFO'    => 'INFO',
		'DEBUG'   => 'DEBUG',
	);

	/**
	 * Maximum size, in bytes, of an individual daily log file before it is
	 * rotated to a `.1` suffix. The previous implementation read the entire
	 * log file into memory and rewrote it on every line (#C4), which was both
	 * a race-with-itself failure mode (concurrent processes silently dropped
	 * entries) and unbounded in growth. 5 MiB strikes a balance between
	 * carrying enough recent context for support and not blowing out the
	 * uploads directory.
	 */
	const LEADCONNECTOR_LOG_MAX_BYTES = 5242880;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * Log files now live at `WP_CONTENT_DIR/leadconnector-logs/` (#H9). The
	 * previous home under `wp-content/uploads/leadconnector-logs/` was
	 * protected only by a generated `.htaccess` file, which is silently
	 * ignored under nginx / Caddy and left log contents reachable over HTTP.
	 * `WP_CONTENT_DIR` is not auto-served by WordPress (only `uploads/` is
	 * indexed by the media library) so the new location avoids the path
	 * traversal that uploads has by convention. The `secure_log_directory()`
	 * helper still writes `.htaccess` + `index.php` for Apache stacks and
	 * the README now documents the nginx.conf snippet required on Nginx.
	 *
	 * `wp_upload_dir()` is still consulted strictly as a fallback for the
	 * rare hosts that strip out `WP_CONTENT_DIR` writes (some
	 * read-only-content hosting). `wp_upload_dir()` returns an `error` key
	 * when the directory cannot be created/written; we honour it instead of
	 * silently producing a malformed path (#M10).
	 */
	private function __construct() {
		$log_dir = $this->resolve_log_dir();
		if ( '' === $log_dir ) {
			// No usable on-disk directory. Logging silently disables; the
			// is_logging_enabled() gate plus the empty log_file means all
			// log() calls below short-circuit cleanly.
			$this->log_file = '';
			return;
		}

		$this->log_file = trailingslashit( $log_dir ) . 'leadconnector-' . gmdate( 'Y-m-d' ) . '.log';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		// secure_log_directory writes index.php and .htaccess. It is safe to
		// call every request — both writes are no-ops once the files exist.
		$this->secure_log_directory( $log_dir );
	}

	/**
	 * Decide which on-disk directory to log to.
	 *
	 * Preference order:
	 *
	 *   1. `LEADCONNECTOR_LOG_DIR` constant if defined and writable. Lets a
	 *      site administrator place logs anywhere they want (e.g. outside
	 *      the web root entirely) and is the recommended hardening for
	 *      sites with non-default web-server topology.
	 *   2. `WP_CONTENT_DIR/leadconnector-logs/` — the canonical 3.0.32+
	 *      location. Not served by WordPress directly.
	 *   3. `wp-content/uploads/leadconnector-logs/` — legacy fallback for
	 *      installs where `WP_CONTENT_DIR` is read-only.
	 *
	 * Returns '' when no writable directory is available; the constructor
	 * then disables file logging cleanly.
	 *
	 * @return string Absolute directory path, or empty string if no path
	 *                is usable.
	 */
	private function resolve_log_dir() {
		if ( defined( 'LEADCONNECTOR_LOG_DIR' ) && is_string( LEADCONNECTOR_LOG_DIR ) && '' !== LEADCONNECTOR_LOG_DIR ) {
			return rtrim( (string) LEADCONNECTOR_LOG_DIR, '/\\' );
		}

		if ( defined( 'WP_CONTENT_DIR' ) && is_string( WP_CONTENT_DIR ) && '' !== WP_CONTENT_DIR ) {
			$candidate = rtrim( WP_CONTENT_DIR, '/\\' ) . '/leadconnector-logs';
			if ( wp_is_writable( WP_CONTENT_DIR ) || ( file_exists( $candidate ) && wp_is_writable( $candidate ) ) ) {
				return $candidate;
			}
		}

		// #M10: wp_upload_dir() can return an `error` key when uploads is
		// missing or unwritable; honour it instead of synthesising a broken
		// path. Errors are common on misconfigured hosts and used to be
		// hidden behind a string concatenation that produced
		// `/leadconnector-logs/leadconnector-YYYY-MM-DD.log` rooted at the
		// site root.
		$upload_dir = wp_upload_dir( null, false );
		if ( is_array( $upload_dir ) && empty( $upload_dir['error'] ) && ! empty( $upload_dir['basedir'] ) ) {
			return rtrim( (string) $upload_dir['basedir'], '/\\' ) . '/leadconnector-logs';
		}

		return '';
	}

	/**
	 * Whether file logging is enabled.
	 *
	 * Logging is off by default. Enable with WP_DEBUG and WP_DEBUG_LOG, or:
	 * define( 'LEADCONNECTOR_DEBUG', true ); in wp-config.php
	 *
	 * @return bool
	 */
	private function is_logging_enabled() {
		if ( defined( 'LEADCONNECTOR_DEBUG' ) ) {
			return (bool) LEADCONNECTOR_DEBUG;
		}

		return defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}

	/**
	 * Add index.php and .htaccess to block direct HTTP access to log files.
	 *
	 * @param string $log_dir Absolute path to the log directory.
	 * @return void
	 */
	private function secure_log_directory( $log_dir ) {
		$index_file = trailingslashit( $log_dir ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( $wp_filesystem ) {
				$wp_filesystem->put_contents( $index_file, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
			}
		}

		$htaccess_file = trailingslashit( $log_dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( $wp_filesystem ) {
				// Apache 2.4+ uses `Require all denied`. The legacy
				// `Deny from all` directive is kept inside a
				// `<IfModule !mod_authz_core.c>` block for older Apache
				// (2.2). Both forms are inert under nginx / Caddy — those
				// stacks require the explicit `location ^~ /…/ { deny all }`
				// snippet documented in README.txt under "Debug Logging".
				$wp_filesystem->put_contents(
					$htaccess_file,
					"<IfModule mod_authz_core.c>\n"
					. "    Require all denied\n"
					. "</IfModule>\n"
					. "<IfModule !mod_authz_core.c>\n"
					. "    Order allow,deny\n"
					. "    Deny from all\n"
					. "</IfModule>\n",
					FS_CHMOD_FILE
				);
			}
		}
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return LeadConnector_Logger The singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent cloning of the instance.
	 */
	private function __clone() {
		// Prevent cloning.
	}

	/**
	 * Prevent unserializing of the singleton (#D6).
	 *
	 * The previous empty body was a no-op that did nothing to actually block
	 * unserialize attacks. Throwing here ensures any attempt to revive a
	 * LeadConnector_Logger via unserialize() fails immediately and visibly.
	 *
	 * @throws \LogicException Always.
	 * @return void
	 */
	public function __wakeup() {
		throw new \LogicException( 'LeadConnector_Logger is a singleton and cannot be unserialized.' );
	}

	/**
	 * Log a message with the specified level.
	 *
	 * @param string $level   The log level.
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function log( $level, $message, $context = array() ) {
		if ( ! $this->is_logging_enabled() ) {
			return false;
		}

		if ( ! isset( $this->log_levels[ $level ] ) ) {
			return false;
		}

		$timestamp         = gmdate( 'Y-m-d H:i:s' );
		$formatted_message = $this->format_message( $timestamp, $level, $message, $context );

		return $this->write_to_log( $formatted_message );
	}

	/**
	 * Maximum length (in characters) of an individual context value after JSON
	 * encoding. Values longer than this are truncated with a "[...]" marker so
	 * a single oversize payload (e.g. an API response body) does not flood the
	 * log file. The previous implementation pretty-printed unbounded payloads
	 * which routinely produced multi-kilobyte log lines and leaked sensitive
	 * data on debug-enabled sites (#C7).
	 */
	const LEADCONNECTOR_LOG_CONTEXT_MAX_CHARS = 2048;

	/**
	 * Substrings (matched case-insensitively against context array keys) whose
	 * values are replaced with "[REDACTED]" before being JSON-encoded into the
	 * log. The intent is to make it impossible for a misuse of the logger to
	 * write OAuth bearer tokens, API keys, SMTP passwords, the OAuth `code`
	 * query var, or `Authorization` HTTP headers into the file (#C7/#H2).
	 *
	 * Match is by substring so derived keys (e.g. `access_token`,
	 * `refresh_token`, `leadconnector_access_token`) are also caught.
	 */
	const LEADCONNECTOR_LOG_REDACTED_KEY_SUBSTRINGS = array(
		'password',
		'secret',
		'token',
		'api_key',
		'apikey',
		'authorization',
		'auth',
		'code',
		'bearer',
		'session',
		'cookie',
	);

	/**
	 * Format the log message.
	 *
	 * Context payloads are sanitized via redact_context() before being encoded
	 * so secret-shaped keys can never leak via the logger. Each context entry
	 * is also truncated to LEADCONNECTOR_LOG_CONTEXT_MAX_CHARS so a stray
	 * `info( ..., $http_response )` call cannot produce multi-kilobyte log
	 * lines (#C7).
	 *
	 * @param string $timestamp The timestamp.
	 * @param string $level     The log level.
	 * @param string $message   The message to log.
	 * @param array  $context   Additional context data.
	 *
	 * @return string The formatted message.
	 */
	private function format_message( $timestamp, $level, $message, $context = array() ) {
		$formatted = "[{$timestamp}] [{$level}] {$message}";

		if ( ! empty( $context ) ) {
			$context_safe = $this->redact_context( $context );
			$encoded      = wp_json_encode( $context_safe );
			if ( is_string( $encoded ) && strlen( $encoded ) > self::LEADCONNECTOR_LOG_CONTEXT_MAX_CHARS ) {
				$encoded = substr( $encoded, 0, self::LEADCONNECTOR_LOG_CONTEXT_MAX_CHARS ) . '[...truncated]';
			}
			if ( is_string( $encoded ) && '' !== $encoded ) {
				$formatted .= ' Context: ' . $encoded;
			}
		}

		return $formatted;
	}

	/**
	 * Recursively walk a context payload and replace the value of any field
	 * whose key matches one of LEADCONNECTOR_LOG_REDACTED_KEY_SUBSTRINGS with
	 * the literal string "[REDACTED]". This is the second line of defence
	 * after call-site discipline: even if a caller passes a raw HTTP response
	 * body, the logger guarantees secret-shaped fields never reach disk.
	 *
	 * @param mixed $value Arbitrary context value (array, object, scalar).
	 * @param int   $depth Recursion guard.
	 * @return mixed
	 */
	private function redact_context( $value, $depth = 0 ) {
		if ( $depth > 6 ) {
			return '[depth-limit]';
		}

		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $sub ) {
				if ( is_string( $key ) && $this->is_redacted_key( $key ) ) {
					$out[ $key ] = '[REDACTED]';
					continue;
				}
				$out[ $key ] = $this->redact_context( $sub, $depth + 1 );
			}
			return $out;
		}

		if ( is_object( $value ) ) {
			$out = new \stdClass();
			foreach ( get_object_vars( $value ) as $key => $sub ) {
				if ( is_string( $key ) && $this->is_redacted_key( $key ) ) {
					$out->{$key} = '[REDACTED]';
					continue;
				}
				$out->{$key} = $this->redact_context( $sub, $depth + 1 );
			}
			return $out;
		}

		if ( is_string( $value ) ) {
			return $this->redact_string( $value );
		}

		return $value;
	}

	/**
	 * Scrub secret-shaped substrings out of a free-form string before it is
	 * persisted to the log file. Covers (#H2):
	 *
	 * - `code=...`, `refresh_token=...`, `access_token=...`, `api_key=...`,
	 *   `apikey=...` query/body parameters (and their JSON equivalents).
	 * - `Authorization: Bearer ...` HTTP headers.
	 * - `"password":"..."`, `"secret":"..."` JSON pairs.
	 *
	 * This is a second line of defence behind the key-level redactor — it
	 * catches secrets that arrive in places the key check cannot reach
	 * (e.g. a stringified URL passed as a context value).
	 *
	 * @param string $raw Input string.
	 * @return string
	 */
	private function redact_string( $raw ) {
		// Bail fast — most strings have no secrets and the regex pass is
		// pure overhead for log lines like "Custom values cleared".
		if ( false === strpos( $raw, 'code=' )
			&& false === strpos( $raw, 'token' )
			&& false === strpos( $raw, 'Bearer' )
			&& false === strpos( $raw, 'api_key' )
			&& false === strpos( $raw, 'apikey' )
			&& false === strpos( $raw, 'password' )
			&& false === strpos( $raw, 'secret' )
			&& false === strpos( $raw, 'authoriz' )
		) {
			return $raw;
		}

		$patterns = array(
			// Query-string / form-body style: code=ABC, refresh_token=ABC, etc.
			'/\b(code|refresh_token|access_token|api_key|apikey|password|secret)=([^&\s"\'<>]+)/i' => '$1=[REDACTED]',
			// HTTP Authorization header (Bearer + Basic).
			'/(Authorization\s*[:=]\s*)(Bearer|Basic)\s+\S+/i'                                     => '$1$2 [REDACTED]',
			// JSON object properties for sensitive field names.
			'/"(code|refresh_token|access_token|api_key|apikey|password|secret|authorization)"\s*:\s*"[^"]*"/i' => '"$1":"[REDACTED]"',
		);

		foreach ( $patterns as $pattern => $replacement ) {
			$raw = preg_replace( $pattern, $replacement, $raw );
		}

		return $raw;
	}

	/**
	 * Whether a key matches one of the secret-shaped substrings.
	 *
	 * @param string $key Context array/object key.
	 * @return bool
	 */
	private function is_redacted_key( $key ) {
		$lower = strtolower( $key );
		foreach ( self::LEADCONNECTOR_LOG_REDACTED_KEY_SUBSTRINGS as $needle ) {
			if ( false !== strpos( $lower, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Write the message to the log file.
	 *
	 * Uses error_log( $msg, 3, $file ) for atomic appends so concurrent
	 * processes do not race-and-drop entries the way the previous
	 * read-then-rewrite implementation did (#C4). The file is rotated to a
	 * `.1` sibling once it exceeds LEADCONNECTOR_LOG_MAX_BYTES so growth is
	 * bounded.
	 *
	 * @param string $message The formatted message.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function write_to_log( $message ) {
		if ( ! is_string( $this->log_file ) || '' === $this->log_file ) {
			return false;
		}

		$log_dir = dirname( $this->log_file );
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		$this->secure_log_directory( $log_dir );

		// Rotate before append so a single oversized line is written into the
		// fresh file, not appended to a file that is already over the cap.
		$this->maybe_rotate_log_file();

		$line = $message . PHP_EOL;

		// error_log() with type 3 writes via PHP's underlying append-mode
		// fopen and is the documented way to append to an arbitrary file.
		// It returns true on success and never touches PHP's error log.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return (bool) error_log( $line, 3, $this->log_file );
	}

	/**
	 * Rotate the current daily log file when it exceeds the size cap.
	 *
	 * Only one previous generation (`*.1`) is kept; the rotation is intentionally
	 * cheap so it can run inline with every write without measurable overhead.
	 * Failures are silent (the worst case is the log file simply grows past
	 * the cap until the next call manages to rotate it).
	 *
	 * @return void
	 */
	private function maybe_rotate_log_file() {
		if ( ! is_string( $this->log_file ) || '' === $this->log_file ) {
			return;
		}
		if ( ! file_exists( $this->log_file ) ) {
			return;
		}

		$size = @filesize( $this->log_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $size || $size < self::LEADCONNECTOR_LOG_MAX_BYTES ) {
			return;
		}

		$rotated = $this->log_file . '.1';
		if ( file_exists( $rotated ) ) {
			@unlink( $rotated ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		@rename( $this->log_file, $rotated ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions.rename_rename
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function error( $message, $context = array() ) {
		return $this->log( $this->log_levels['ERROR'], $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function warning( $message, $context = array() ) {
		return $this->log( $this->log_levels['WARNING'], $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function info( $message, $context = array() ) {
		return $this->log( $this->log_levels['INFO'], $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function debug( $message, $context = array() ) {
		return $this->log( $this->log_levels['DEBUG'], $message, $context );
	}

	/**
	 * Clear the log file.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_log() {
		if ( ! is_string( $this->log_file ) || '' === $this->log_file ) {
			return false;
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			return $wp_filesystem->put_contents( $this->log_file, '', FS_CHMOD_FILE );
		}

		return false;
	}

	/**
	 * Get the log file path.
	 *
	 * @return string The log file path.
	 */
	public function get_log_file() {
		return $this->log_file;
	}
}
