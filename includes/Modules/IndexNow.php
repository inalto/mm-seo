<?php
namespace MMSEO\Modules;

use MMSEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * IndexNow module.
 *
 * Serves the key verification file at /{key}.txt and pings
 * https://api.indexnow.org/indexnow on publish/update/trash of public posts.
 *
 * Key is stored in mmseo_indexnow_key (non-autoloaded option).
 * Pings are non-blocking (timeout 2s) and throttled per-post via a 5-minute transient.
 */
class IndexNow extends Module {

	/** IndexNow API endpoint. */
	private const ENDPOINT = 'https://api.indexnow.org/indexnow';

	public function id(): string {
		return 'indexnow';
	}

	public function register(): void {
		if ( Plugin::is_frontend_suspended() ) {
			return;
		}

		// Ensure the key exists.
		$this->get_or_create_key();

		// Serve the key file via parse_request (fires before rewrites resolve).
		add_action( 'parse_request', [ $this, 'maybe_serve_key_file' ], 1 );

		// Ping on post status transitions.
		add_action( 'transition_post_status', [ $this, 'on_status_transition' ], 10, 3 );
	}

	// -------------------------------------------------------------------------
	// Key management
	// -------------------------------------------------------------------------

	/**
	 * Get the IndexNow key, generating and storing it if it does not exist yet.
	 *
	 * @return string
	 */
	public function get_or_create_key(): string {
		$key = get_option( 'mmseo_indexnow_key' );
		if ( ! $key ) {
			// Generate a 32-char lowercase hex key.
			$key = md5( wp_generate_uuid4() );
			add_option( 'mmseo_indexnow_key', $key, '', 'no' );
		}
		return (string) $key;
	}

	// -------------------------------------------------------------------------
	// Key file endpoint
	// -------------------------------------------------------------------------

	/**
	 * If the request URI matches /{key}.txt, serve the key and exit.
	 * Hooked on parse_request priority 1 (before WP rewrites run).
	 *
	 * @param \WP $wp Current WP instance (unused but required by hook signature).
	 */
	public function maybe_serve_key_file( \WP $wp ): void {
		$key = get_option( 'mmseo_indexnow_key' );
		if ( ! $key ) {
			return;
		}

		$request_path = strtolower( rtrim(
			(string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ),
			'/'
		) );

		$expected = '/' . strtolower( (string) $key ) . '.txt';

		if ( $request_path !== $expected ) {
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $key;
		exit;
	}

	// -------------------------------------------------------------------------
	// Ping on status transition
	// -------------------------------------------------------------------------

	/**
	 * Send an IndexNow ping when a public post is published, updated, or trashed.
	 *
	 * Triggers when:
	 *   - new status is 'publish' (post just published or updated while published), OR
	 *   - old status is 'publish' and new status is not 'publish' (post removed from index).
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       The post object.
	 */
	public function on_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		// Only handle public post types.
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || ! $post_type_obj->public ) {
			return;
		}

		$should_ping = ( 'publish' === $new_status ) ||
		               ( 'publish' === $old_status && 'publish' !== $new_status );

		if ( ! $should_ping ) {
			return;
		}

		// Throttle: do not ping the same post within 5 minutes.
		$transient_key = 'mmseo_inow_' . $post->ID;
		if ( false !== get_transient( $transient_key ) ) {
			return;
		}
		set_transient( $transient_key, 1, 5 * MINUTE_IN_SECONDS );

		$permalink = get_permalink( $post->ID );
		if ( ! $permalink ) {
			return;
		}

		self::ping( [ $permalink ] );
	}

	// -------------------------------------------------------------------------
	// Public ping helper
	// -------------------------------------------------------------------------

	/**
	 * Send a non-blocking IndexNow ping for the given URLs.
	 *
	 * @param string[] $urls Absolute URLs to submit.
	 */
	public static function ping( array $urls ): void {
		if ( empty( $urls ) ) {
			return;
		}

		$key = get_option( 'mmseo_indexnow_key' );
		if ( ! $key ) {
			return;
		}

		$host         = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$key_location = home_url( '/' . $key . '.txt' );

		$body = wp_json_encode( [
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => $key_location,
			'urlList'     => array_values( $urls ),
		] );

		wp_remote_post(
			self::ENDPOINT,
			[
				'headers'   => [ 'Content-Type' => 'application/json; charset=utf-8' ],
				'body'      => $body,
				'blocking'  => false,
				'timeout'   => 2,
				'sslverify' => true,
			]
		);
	}
}
