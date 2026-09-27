<?php
namespace MMSEO\Modules\Redirects;

use MMSEO\Modules\Module;

defined( 'ABSPATH' ) || exit;

/**
 * 404 Monitor module.
 *
 * Logs 404 requests to {prefix}mmseo_404_log via INSERT … ON DUPLICATE KEY UPDATE.
 * Table is created lazily (dbDelta) when the module first registers and the stored
 * db_version does not match MMSEO_VERSION.
 *
 * Cross-agent contract columns:
 *   id BIGINT UNSIGNED AUTO_INCREMENT PK
 *   uri VARCHAR(2048) NOT NULL
 *   uri_hash CHAR(32) NOT NULL UNIQUE KEY
 *   referrer VARCHAR(2048) DEFAULT ''
 *   ua VARCHAR(255) DEFAULT ''
 *   hits INT UNSIGNED DEFAULT 1
 *   first_seen DATETIME
 *   last_seen DATETIME
 *
 * Cron: mmseo_purge_404_log — weekly, keeps newest 500 rows and deletes rows older than 90 days.
 */
class Monitor404 extends Module {

	public function id(): string {
		return 'monitor404';
	}

	public function register(): void {
		// Ensure the table exists (lazy creation / schema upgrade).
		self::install();

		// Log 404s on template_redirect priority 100 (after other modules ran).
		add_action( 'template_redirect', [ $this, 'log_404' ], 100 );

		// Schedule weekly purge cron if not already scheduled.
		if ( ! wp_next_scheduled( 'mmseo_purge_404_log' ) ) {
			wp_schedule_event( time(), 'weekly', 'mmseo_purge_404_log' );
		}

		add_action( 'mmseo_purge_404_log', [ self::class, 'purge_old' ] );
	}

	// -------------------------------------------------------------------------
	// Table install / schema
	// -------------------------------------------------------------------------

	/**
	 * Create or upgrade the 404-log table using dbDelta.
	 * Runs when the stored db_version does not match MMSEO_VERSION.
	 */
	public static function install(): void {
		if ( get_option( 'mmseo_db_version' ) === MMSEO_VERSION ) {
			return;
		}

		global $wpdb;

		$table      = $wpdb->prefix . 'mmseo_404_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uri VARCHAR(2048) NOT NULL DEFAULT '',
			uri_hash CHAR(32) NOT NULL DEFAULT '',
			referrer VARCHAR(2048) NOT NULL DEFAULT '',
			ua VARCHAR(255) NOT NULL DEFAULT '',
			hits INT UNSIGNED NOT NULL DEFAULT 1,
			first_seen DATETIME DEFAULT NULL,
			last_seen DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uri_hash (uri_hash)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'mmseo_db_version', MMSEO_VERSION, 'no' );
	}

	// -------------------------------------------------------------------------
	// Logging
	// -------------------------------------------------------------------------

	/**
	 * Log a 404 request. Fires on template_redirect priority 100.
	 */
	public function log_404(): void {
		if ( ! is_404() ) {
			return;
		}

		global $wpdb;

		$raw_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Normalize and truncate URI for storage (max 2000 chars to fit VARCHAR(2048)).
		$uri = substr( strtolower( urldecode( (string) wp_parse_url( $raw_uri, PHP_URL_PATH ) ) ), 0, 2000 );
		if ( '' === $uri ) {
			$uri = '/';
		}

		// Apply ignore-pattern filter.
		$ignore_patterns = apply_filters( 'mmseo_404_ignore_patterns', [
			'\.php$',
			'\.env',
			'wp-content/.*\.(map|txt)$',
			'\.(jpg|jpeg|png|gif|webp|svg|ico|css|js|woff2?)$',
			'xmlrpc',
		] );

		foreach ( (array) $ignore_patterns as $pattern ) {
			if ( @preg_match( '#' . $pattern . '#i', $uri ) ) {
				return;
			}
		}

		$referrer = substr( (string) ( $_SERVER['HTTP_REFERER'] ?? '' ), 0, 2048 );
		$ua       = substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 );
		$hash     = md5( $uri );
		$now      = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$wpdb->prefix}mmseo_404_log`
					(uri, uri_hash, referrer, ua, hits, first_seen, last_seen)
				VALUES
					(%s, %s, %s, %s, 1, %s, %s)
				ON DUPLICATE KEY UPDATE
					hits     = hits + 1,
					last_seen = NOW(),
					referrer  = VALUES(referrer)",
				$uri,
				$hash,
				$referrer,
				$ua,
				$now,
				$now
			)
		);
	}

	// -------------------------------------------------------------------------
	// Purge / maintenance
	// -------------------------------------------------------------------------

	/**
	 * Cron callback: delete rows beyond the newest 500 and rows older than 90 days.
	 */
	public static function purge_old(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'mmseo_404_log';

		// Delete rows older than 90 days.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE last_seen < DATE_SUB(NOW(), INTERVAL %d DAY)",
				90
			)
		);

		// Keep only the newest 500 rows by id.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM `{$table}`
			WHERE id NOT IN (
				SELECT id FROM (
					SELECT id FROM `{$table}` ORDER BY last_seen DESC LIMIT 500
				) AS keep_ids
			)"
		);
	}

	/**
	 * Truncate the entire 404-log table. Available for admin "Clear All" action.
	 */
	public static function purge_all(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}mmseo_404_log`" );
	}
}
