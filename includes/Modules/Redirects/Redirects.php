<?php
namespace MMSEO\Modules\Redirects;

use MMSEO\Modules\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects module: option-stored redirect rules with hit counters.
 *
 * Rules stored in get_option('mmseo_redirects', []) as:
 *   [ 'src'=>string, 'dst'=>string, 'code'=>int, 'regex'=>bool, 'hits'=>int, 'last'=>int ]
 *
 * Matching happens on template_redirect priority 1.
 * Hit counters are throttled per-rule via a 60-second transient.
 */
class Redirects extends Module {

	public function id(): string {
		return 'redirects';
	}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'match' ], 1 );

		// Tell WP about the x_redirect_by header value.
		add_filter( 'x_redirect_by', fn() => 'mm-seo' );
	}

	// -------------------------------------------------------------------------
	// Matching
	// -------------------------------------------------------------------------

	/**
	 * Attempt to match the current request against stored redirect rules.
	 * Fires on template_redirect priority 1 before any output.
	 */
	public function match(): void {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Skip wp-admin, wp-json, wp-login early — before any normalization cost.
		if (
			str_contains( $request_uri, '/wp-admin' ) ||
			str_contains( $request_uri, '/wp-json' ) ||
			str_contains( $request_uri, 'wp-login.php' )
		) {
			return;
		}

		$rules = get_option( 'mmseo_redirects', [] );
		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return;
		}

		$path = $this->normalize_path( $request_uri );

		// Separate exact and regex rules for two-pass matching.
		$exact_rules = [];
		$regex_rules = [];
		foreach ( $rules as $index => $rule ) {
			if ( empty( $rule['src'] ) || empty( $rule['dst'] ) ) {
				continue;
			}
			if ( ! empty( $rule['regex'] ) ) {
				$regex_rules[ $index ] = $rule;
			} else {
				$exact_rules[ $index ] = $rule;
			}
		}

		// First pass: exact match (normalized, trailingslash-insensitive).
		foreach ( $exact_rules as $index => $rule ) {
			$src = rtrim( $this->normalize_path( $rule['src'] ), '/' );
			$cmp = rtrim( $path, '/' );
			if ( $src === $cmp ) {
				$this->do_redirect( $rules, $index, $rule, $path );
				return;
			}
		}

		// Second pass: regex match.
		foreach ( $regex_rules as $index => $rule ) {
			$src     = $rule['src'];
			$matches = [];
			if ( @preg_match( '#' . $src . '#i', $path, $matches ) ) {
				$dst = $rule['dst'];
				// Replace back-references $1..$9.
				$dst = preg_replace( '#' . $src . '#i', $dst, $path );
				$rule['dst'] = $dst;
				$this->do_redirect( $rules, $index, $rule, $path );
				return;
			}
		}
	}

	// -------------------------------------------------------------------------
	// Redirect execution
	// -------------------------------------------------------------------------

	/**
	 * Perform the redirect, increment hit counter (throttled), and exit.
	 *
	 * @param array<int, array<string, mixed>> $rules   Full rules array (for update).
	 * @param int                              $index   Rule index within $rules.
	 * @param array<string, mixed>             $rule    The matched rule.
	 * @param string                           $current Normalized current request path.
	 */
	private function do_redirect( array $rules, int $index, array $rule, string $current ): void {
		$dst  = $rule['dst'];
		$code = isset( $rule['code'] ) && in_array( (int) $rule['code'], [ 301, 302, 307 ], true )
			? (int) $rule['code']
			: 301;

		// Make dst absolute.
		if ( ! preg_match( '#^https?://#i', $dst ) ) {
			$dst = home_url( $dst );
		}

		// Loop guard: never redirect to same normalized path.
		$dst_path = $this->normalize_path( (string) wp_parse_url( $dst, PHP_URL_PATH ) );
		if ( rtrim( $dst_path, '/' ) === rtrim( $current, '/' ) ) {
			return;
		}

		// Throttled hit counter: use transient so we don't write on every request.
		$transient_key = 'mmseo_rhit_' . md5( $rule['src'] );
		if ( false === get_transient( $transient_key ) ) {
			$rules[ $index ]['hits'] = ( (int) ( $rule['hits'] ?? 0 ) ) + 1;
			$rules[ $index ]['last'] = time();
			update_option( 'mmseo_redirects', $rules );
			set_transient( $transient_key, 1, 60 );
		}

		wp_safe_redirect( $dst, $code );
		exit;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Normalize a URI/path for comparison:
	 * - Extract the path portion via parse_url.
	 * - URL-decode.
	 * - Lowercase.
	 * - Ensure leading slash.
	 *
	 * @param string $uri Raw URI or path.
	 * @return string Normalized path (may or may not have trailing slash — callers rtrim as needed).
	 */
	private function normalize_path( string $uri ): string {
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = urldecode( $path );
		$path = strtolower( $path );
		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}
		return $path;
	}
}
