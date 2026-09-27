<?php
namespace MMSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Static accessor for all MM SEO option groups.
 * Option keys: mmseo_settings, mmseo_titles, mmseo_social.
 */
class Options {

	// -------------------------------------------------------------------------
	// Default shapes
	// -------------------------------------------------------------------------

	/**
	 * Default shape for mmseo_settings.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULT_SETTINGS = [
		'modules'                  => [
			'sitemaps'      => true,
			'schema'        => true,
			'social'        => true,
			'breadcrumbs'   => true,
			'redirects'     => false,
			'monitor404'    => false,
			'indexnow'      => false,
			'robots_editor' => false,
			'verification'  => true,
			'rss'           => false,
		],
		'separator'                => '|',
		'site_type'                => 'org',   // 'org' | 'person'
		'org_name'                 => '',
		'org_logo'                 => '',
		'social_profiles'          => [
			'facebook'  => '',
			'twitter'   => '',
			'instagram' => '',
			'linkedin'  => '',
			'youtube'   => '',
			'pinterest' => '',
		],
		'verification'             => [
			'google'    => '',
			'bing'      => '',
			'yandex'    => '',
			'pinterest' => '',
		],
		'delete_data_on_uninstall' => false,
		'force_output'             => false,
		'dismissed_notices'        => [],
	];

	/**
	 * Default shape for mmseo_titles.
	 *
	 * Post-type entries are generic defaults; actual values are retrieved via title_for().
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULT_TITLES = [
		'post_types' => [
			'post' => [
				'title'       => '%%title%% %%sep%% %%sitename%%',
				'desc'        => '',
				'noindex'     => false,
				'in_sitemap'  => true,
				'schema_type' => 'Article',
			],
			'page' => [
				'title'       => '%%title%% %%sep%% %%sitename%%',
				'desc'        => '',
				'noindex'     => false,
				'in_sitemap'  => true,
				'schema_type' => 'WebPage',
			],
		],
		'taxonomies' => [
			'category' => [
				'title'   => '%%term_title%% %%sep%% %%sitename%%',
				'desc'    => '',
				'noindex' => false,
			],
			'post_tag' => [
				'title'   => '%%term_title%% %%sep%% %%sitename%%',
				'desc'    => '',
				'noindex' => false,
			],
		],
		'archives'   => [
			'author' => [
				'title'   => '%%author%% %%sep%% %%sitename%%',
				'desc'    => '',
				'noindex' => false,
			],
			'date'   => [
				'title'   => '%%date%% %%sep%% %%sitename%%',
				'desc'    => '',
				'noindex' => true,
			],
			'search' => [
				'title'   => '%%searchphrase%% %%sep%% %%sitename%%',
				'desc'    => '',
				'noindex' => true,
			],
			'404'    => [
				// Note: the literal string is intentional — __() cannot be used in const.
				// The Replacer will handle this; translatable label added via filter in Phase 3.
				'title'   => 'Page not found %%sep%% %%sitename%%',
				'desc'    => '',
				'noindex' => true,
			],
		],
		'home'       => [
			'title' => '%%sitename%% %%sep%% %%tagline%%',
			'desc'  => '',
		],
	];

	/**
	 * Default shape for mmseo_social.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULT_SOCIAL = [
		'og_default_image'    => '',
		'og_default_image_id' => 0,
		'og_site_name'        => '',
		'twitter_card'        => 'summary_large_image',
		'twitter_site'        => '',
	];

	/** Generic default for unknown post types. */
	private const POST_TYPE_DEFAULT = [
		'title'       => '%%title%% %%sep%% %%sitename%%',
		'desc'        => '',
		'noindex'     => false,
		'in_sitemap'  => true,
		'schema_type' => 'WebPage',
	];

	/** Generic default for unknown taxonomies. */
	private const TAXONOMY_DEFAULT = [
		'title'   => '%%term_title%% %%sep%% %%sitename%%',
		'desc'    => '',
		'noindex' => false,
	];

	// -------------------------------------------------------------------------
	// Cached option values (per-request)
	// -------------------------------------------------------------------------

	/** @var array<string, mixed>|null */
	private static ?array $settings_cache = null;

	/** @var array<string, mixed>|null */
	private static ?array $titles_cache   = null;

	/** @var array<string, mixed>|null */
	private static ?array $social_cache   = null;

	// -------------------------------------------------------------------------
	// Option readers
	// -------------------------------------------------------------------------

	/**
	 * Return the full mmseo_settings array (merged over defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		if ( null === self::$settings_cache ) {
			$saved                 = get_option( 'mmseo_settings', [] );
			self::$settings_cache  = self::deep_merge( self::DEFAULT_SETTINGS, is_array( $saved ) ? $saved : [] );
		}
		return self::$settings_cache;
	}

	/**
	 * Return the full mmseo_titles array (merged over defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function titles(): array {
		if ( null === self::$titles_cache ) {
			$saved               = get_option( 'mmseo_titles', [] );
			self::$titles_cache  = self::deep_merge( self::DEFAULT_TITLES, is_array( $saved ) ? $saved : [] );
		}
		return self::$titles_cache;
	}

	/**
	 * Return the full mmseo_social array (merged over defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function social(): array {
		if ( null === self::$social_cache ) {
			$saved              = get_option( 'mmseo_social', [] );
			self::$social_cache = self::deep_merge( self::DEFAULT_SOCIAL, is_array( $saved ) ? $saved : [] );
		}
		return self::$social_cache;
	}

	/**
	 * Get a value from mmseo_settings using dot notation.
	 *
	 * Example: Options::get('verification.google')
	 *
	 * @param string $key     Dot-separated key path.
	 * @param mixed  $default Fallback when key is not found.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::settings();
		$keys     = explode( '.', $key );
		$value    = $settings;
		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! array_key_exists( $k, $value ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}
		return $value;
	}

	/**
	 * Check whether a given module id is enabled.
	 *
	 * @param string $id Module id (e.g. 'sitemaps', 'redirects').
	 */
	public static function module_enabled( string $id ): bool {
		$modules = self::get( 'modules', [] );
		return isset( $modules[ $id ] ) && (bool) $modules[ $id ];
	}

	/**
	 * Return the title/desc/noindex settings for a specific context, merging
	 * saved options over hard-coded defaults.
	 *
	 * @param string $context_type 'post_types' | 'taxonomies' | 'archives'
	 * @param string $name         Post type slug, taxonomy slug, or archive key.
	 * @return array<string, mixed>
	 */
	public static function title_for( string $context_type, string $name ): array {
		$titles = self::titles();

		switch ( $context_type ) {
			case 'post_types':
				$default = self::POST_TYPE_DEFAULT;
				$saved   = $titles['post_types'][ $name ] ?? [];
				break;

			case 'taxonomies':
				$default = self::TAXONOMY_DEFAULT;
				$saved   = $titles['taxonomies'][ $name ] ?? [];
				break;

			case 'archives':
				$default = [
					'title'   => '%%archive_title%% %%sep%% %%sitename%%',
					'desc'    => '',
					'noindex' => false,
				];
				$saved   = $titles['archives'][ $name ] ?? [];
				break;

			default:
				return [];
		}

		return array_merge( $default, $saved );
	}

	// -------------------------------------------------------------------------
	// Writers
	// -------------------------------------------------------------------------

	/**
	 * Update mmseo_settings with a partial array (deep-merged).
	 *
	 * @param array<string, mixed> $partial
	 */
	public static function update_settings( array $partial ): void {
		$current = self::settings();
		$merged  = self::deep_merge( $current, $partial );
		update_option( 'mmseo_settings', $merged );
		self::$settings_cache = null; // Invalidate cache.
	}

	/**
	 * Seed default options if they are not yet present in the database.
	 * Called on activation. Safe to call multiple times.
	 */
	public static function seed_defaults(): void {
		if ( false === get_option( 'mmseo_settings' ) ) {
			add_option( 'mmseo_settings', self::DEFAULT_SETTINGS, '', 'yes' );
		}
		if ( false === get_option( 'mmseo_titles' ) ) {
			add_option( 'mmseo_titles', self::DEFAULT_TITLES, '', 'yes' );
		}
		if ( false === get_option( 'mmseo_social' ) ) {
			add_option( 'mmseo_social', self::DEFAULT_SOCIAL, '', 'yes' );
		}
		if ( false === get_option( 'mmseo_db_version' ) ) {
			add_option( 'mmseo_db_version', MMSEO_VERSION, '', 'no' );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Recursively merge $override into $base (like wp_parse_args but deep).
	 *
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $override
	 * @return array<string, mixed>
	 */
	private static function deep_merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = self::deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}
}
