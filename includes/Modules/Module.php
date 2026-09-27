<?php
namespace MMSEO\Modules;

use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for all MM SEO modules.
 *
 * Each module has a unique string id, an enabled() check, and a register()
 * method that adds its WordPress hooks.
 */
abstract class Module {

	/**
	 * Return the unique module id (e.g. 'sitemaps', 'redirects').
	 *
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * Check whether this module is enabled in settings.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		return Options::module_enabled( $this->id() );
	}

	/**
	 * Register WordPress hooks for this module.
	 * Only called when the module is enabled.
	 *
	 * @return void
	 */
	abstract public function register(): void;
}
