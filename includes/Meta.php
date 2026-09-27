<?php
namespace MMSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all _mmseo_* post and term meta keys with show_in_rest and auth_callback.
 */
class Meta {

	/**
	 * All post meta key suffixes (without the _mmseo_ prefix).
	 * Used by other components to enumerate registered keys.
	 *
	 * @var string[]
	 */
	public const POST_KEYS = [
		'title',
		'desc',
		'focuskw',
		'canonical',
		'noindex',
		'nofollow',
		'robots_adv',
		'og_title',
		'og_desc',
		'og_image',
		'og_image_id',
		'tw_title',
		'tw_desc',
		'tw_image',
		'breadcrumb_title',
		'schema_type',
		'seo_score',
		'analysis',
	];

	/**
	 * All term meta key suffixes (without the _mmseo_ prefix).
	 *
	 * @var string[]
	 */
	public const TERM_KEYS = [
		'title',
		'desc',
		'noindex',
		'canonical',
		'og_image',
	];

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_meta_keys' ], 20 );
		add_action( 'init', [ $this, 'register_term_meta_keys' ], 20 );
	}

	/**
	 * Register all post meta keys for public post types (excluding 'attachment').
	 */
	public function register_post_meta_keys(): void {
		$post_types = array_diff(
			get_post_types( [ 'public' => true ] ),
			[ 'attachment' ]
		);

		foreach ( $post_types as $post_type ) {
			foreach ( self::get_post_meta_definitions() as $key => $args ) {
				register_post_meta( $post_type, $key, $args );
			}
		}
	}

	/**
	 * Register all term meta keys for public taxonomies.
	 */
	public function register_term_meta_keys(): void {
		$taxonomies = get_taxonomies( [ 'public' => true ] );

		foreach ( $taxonomies as $taxonomy ) {
			foreach ( self::get_term_meta_definitions() as $key => $args ) {
				register_term_meta( $taxonomy, $key, $args );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Static helpers
	// -------------------------------------------------------------------------

	/**
	 * Get a post meta value by key suffix (without _mmseo_ prefix).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Suffix, e.g. 'title' → _mmseo_title.
	 * @return string
	 */
	public static function get_post( int $post_id, string $key ): string {
		return (string) get_post_meta( $post_id, '_mmseo_' . $key, true );
	}

	/**
	 * Get a term meta value by key suffix (without _mmseo_ prefix).
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Suffix, e.g. 'title' → _mmseo_title.
	 * @return string
	 */
	public static function get_term( int $term_id, string $key ): string {
		return (string) get_term_meta( $term_id, '_mmseo_' . $key, true );
	}

	// -------------------------------------------------------------------------
	// Private definition builders
	// -------------------------------------------------------------------------

	/**
	 * Build full post meta key definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_post_meta_definitions(): array {
		$post_auth = fn( bool $allowed, string $meta_key, int $object_id ): bool =>
			current_user_can( 'edit_post', $object_id );

		return [
			'_mmseo_title' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_desc' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_focuskw' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_canonical' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_noindex' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_noindex_nofollow' ],
				'auth_callback'     => $post_auth,
			],
			'_mmseo_nofollow' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_noindex_nofollow' ],
				'auth_callback'     => $post_auth,
			],
			'_mmseo_robots_adv' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_robots_adv' ],
				'auth_callback'     => $post_auth,
			],
			'_mmseo_og_title' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_og_desc' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_og_image' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_og_image_id' => [
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_tw_title' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_tw_desc' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_tw_image' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_breadcrumb_title' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_schema_type' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_seo_score' => [
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $post_auth,
			],
			'_mmseo_analysis' => [
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [ 'type' => 'string' ],
				],
				// JSON string — sanitize as text, validate JSON on read.
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $post_auth,
			],
		];
	}

	/**
	 * Build full term meta key definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_term_meta_definitions(): array {
		$term_auth = fn( bool $allowed ): bool => current_user_can( 'manage_categories' );

		return [
			'_mmseo_title' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $term_auth,
			],
			'_mmseo_desc' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $term_auth,
			],
			'_mmseo_noindex' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_noindex_nofollow' ],
				'auth_callback'     => $term_auth,
			],
			'_mmseo_canonical' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $term_auth,
			],
			'_mmseo_og_image' => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $term_auth,
			],
		];
	}

	// -------------------------------------------------------------------------
	// Sanitize callbacks
	// -------------------------------------------------------------------------

	/**
	 * Whitelist sanitizer for noindex/nofollow: accepts '', '0', '1'.
	 *
	 * @param mixed $value Raw input.
	 * @return string Sanitized value.
	 */
	public static function sanitize_noindex_nofollow( mixed $value ): string {
		$value = (string) $value;
		return in_array( $value, [ '', '0', '1' ], true ) ? $value : '';
	}

	/**
	 * Sanitize robots_adv: accept comma-separated values from whitelist.
	 *
	 * @param mixed $value Raw input.
	 * @return string Sanitized CSV.
	 */
	public static function sanitize_robots_adv( mixed $value ): string {
		$allowed = [ 'noarchive', 'nosnippet', 'noimageindex' ];
		$parts   = array_filter(
			array_map( 'trim', explode( ',', (string) $value ) )
		);
		$safe    = array_filter(
			$parts,
			fn( string $p ) => in_array( $p, $allowed, true )
		);
		return implode( ',', $safe );
	}
}
