<?php
namespace MMSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the custom capability mmseo_manage_options to manage_options.
 */
class Capabilities {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'map_meta_cap', [ $this, 'map_caps' ], 10, 4 );
	}

	/**
	 * Map mmseo_manage_options → manage_options.
	 *
	 * @param string[] $caps    Required primitive capabilities.
	 * @param string   $cap     Requested meta capability.
	 * @param int      $user_id User ID.
	 * @param mixed[]  $args    Additional context.
	 * @return string[]
	 */
	public function map_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'mmseo_manage_options' === $cap ) {
			return [ 'manage_options' ];
		}
		return $caps;
	}
}
