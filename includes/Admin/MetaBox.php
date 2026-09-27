<?php
namespace MMSEO\Admin;

defined( 'ABSPATH' ) || exit;

use MMSEO\Meta;
use MMSEO\Options;

/**
 * Classic-editor meta box: MM SEO panel on post edit screens.
 *
 * @package MMSEO\Admin
 */
class MetaBox {

	/** @var string[] Allowed schema types. */
	private const SCHEMA_TYPES = [
		'',
		'Article',
		'BlogPosting',
		'NewsArticle',
		'WebPage',
		'AboutPage',
		'ContactPage',
		'FAQPage',
		'Event',
		'TouristAttraction',
		'TouristTrip',
		'Product',
		'Recipe',
		'None',
	];

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );
		add_action( 'save_post',      [ $this, 'save' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Meta box registration
	// -------------------------------------------------------------------------

	/**
	 * Add the MM SEO meta box to all eligible post types.
	 */
	public function add_boxes(): void {
		$post_types = array_diff(
			get_post_types( [ 'public' => true ] ),
			[ 'attachment' ]
		);

		foreach ( $post_types as $pt ) {
			// Skip if this post type uses the block editor.
			if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $pt ) ) {
				continue;
			}

			add_meta_box(
				'mmseo_metabox',
				__( 'MM SEO', 'mm-seo' ),
				[ $this, 'render' ],
				$pt,
				'normal',
				'high'
			);
		}
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the meta box HTML.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'mmseo_meta_save_' . $post->ID, 'mmseo_meta_nonce' );

		// Helper.
		$v  = fn( string $key ): string => Meta::get_post( $post->ID, $key );
		?>
		<div id="mmseo-metabox-wrap">
			<?php /* Tab nav */ ?>
			<div class="mmseo-tabs-nav" role="tablist">
				<button type="button" class="mmseo-tab-btn mmseo-tab-active" data-tab="mmseo-tab-general" role="tab" aria-selected="true">
					<?php esc_html_e( 'General', 'mm-seo' ); ?>
				</button>
				<button type="button" class="mmseo-tab-btn" data-tab="mmseo-tab-social" role="tab" aria-selected="false">
					<?php esc_html_e( 'Social', 'mm-seo' ); ?>
				</button>
				<button type="button" class="mmseo-tab-btn" data-tab="mmseo-tab-advanced" role="tab" aria-selected="false">
					<?php esc_html_e( 'Advanced', 'mm-seo' ); ?>
				</button>
			</div>

			<?php /* ── General tab ── */ ?>
			<div id="mmseo-tab-general" class="mmseo-tab-panel mmseo-tab-panel-active">

				<?php /* Snippet preview */ ?>
				<div id="mmseo-snippet" class="mmseo-snippet-preview"></div>

				<?php /* SEO title */ ?>
				<p>
					<label for="mmseo-seo-title"><strong><?php esc_html_e( 'SEO Title', 'mm-seo' ); ?></strong></label>
					<span class="mmseo-char-counter" id="mmseo-title-counter"></span>
				</p>
				<input
					type="text"
					id="mmseo-seo-title"
					name="_mmseo_title"
					value="<?php echo esc_attr( $v( 'title' ) ); ?>"
					class="widefat"
					autocomplete="off"
				>
				<p class="description"><?php esc_html_e( 'Leave empty to use the title template set in Titles & Meta settings.', 'mm-seo' ); ?></p>

				<?php /* Meta description */ ?>
				<p>
					<label for="mmseo-meta-desc"><strong><?php esc_html_e( 'Meta Description', 'mm-seo' ); ?></strong></label>
					<span class="mmseo-char-counter" id="mmseo-desc-counter"></span>
					<span class="mmseo-counter-hint"><?php esc_html_e( '70–156 chars recommended', 'mm-seo' ); ?></span>
				</p>
				<textarea
					id="mmseo-meta-desc"
					name="_mmseo_desc"
					class="widefat"
					rows="3"
					autocomplete="off"
				><?php echo esc_textarea( $v( 'desc' ) ); ?></textarea>

				<?php /* Focus keyword */ ?>
				<p>
					<label for="mmseo-focuskw"><strong><?php esc_html_e( 'Focus Keyword', 'mm-seo' ); ?></strong></label>
				</p>
				<input
					type="text"
					id="mmseo-focuskw"
					name="_mmseo_focuskw"
					value="<?php echo esc_attr( $v( 'focuskw' ) ); ?>"
					class="widefat"
					placeholder="<?php esc_attr_e( 'Enter focus keyword or phrase…', 'mm-seo' ); ?>"
				>

				<?php /* MM Score */ ?>
				<div class="mmseo-mm-score-section">
					<p class="mmseo-mm-score-label"><strong><?php esc_html_e( 'MM Score', 'mm-seo' ); ?></strong></p>
					<div class="mmseo-score-wrap" id="mmseo-score-wrap"></div>
					<div class="mmseo-breakdown-wrap" id="mmseo-breakdown-wrap"></div>
					<ul id="mmseo-analysis" class="mmseo-analysis-list"></ul>
					<p class="description" id="mmseo-analysis-note"><?php esc_html_e( 'Analysis uses the last saved content.', 'mm-seo' ); ?></p>
				</div>

			</div>

			<?php /* ── Social tab ── */ ?>
			<div id="mmseo-tab-social" class="mmseo-tab-panel" style="display:none;">

				<h4><?php esc_html_e( 'Open Graph', 'mm-seo' ); ?></h4>

				<p>
					<label for="mmseo-og-title"><strong><?php esc_html_e( 'OG Title', 'mm-seo' ); ?></strong></label>
				</p>
				<input
					type="text"
					id="mmseo-og-title"
					name="_mmseo_og_title"
					value="<?php echo esc_attr( $v( 'og_title' ) ); ?>"
					class="widefat"
				>

				<p>
					<label for="mmseo-og-desc"><strong><?php esc_html_e( 'OG Description', 'mm-seo' ); ?></strong></label>
				</p>
				<textarea
					id="mmseo-og-desc"
					name="_mmseo_og_desc"
					class="widefat"
					rows="2"
				><?php echo esc_textarea( $v( 'og_desc' ) ); ?></textarea>

				<p>
					<label for="mmseo-og-image"><strong><?php esc_html_e( 'OG Image URL', 'mm-seo' ); ?></strong></label>
				</p>
				<input
					type="url"
					id="mmseo-og-image"
					name="_mmseo_og_image"
					value="<?php echo esc_attr( esc_url( $v( 'og_image' ) ) ); ?>"
					class="widefat"
					placeholder="https://"
				>
				<input type="hidden" id="mmseo-og-image-id" name="_mmseo_og_image_id" value="<?php echo esc_attr( $v( 'og_image_id' ) ); ?>">
				<button type="button" class="button mmseo-media-btn" data-target="mmseo-og-image" data-id-target="mmseo-og-image-id">
					<?php esc_html_e( 'Choose Image', 'mm-seo' ); ?>
				</button>

				<h4><?php esc_html_e( 'Twitter Card', 'mm-seo' ); ?></h4>

				<p>
					<label for="mmseo-tw-title"><strong><?php esc_html_e( 'Twitter Title', 'mm-seo' ); ?></strong></label>
				</p>
				<input
					type="text"
					id="mmseo-tw-title"
					name="_mmseo_tw_title"
					value="<?php echo esc_attr( $v( 'tw_title' ) ); ?>"
					class="widefat"
				>

				<p>
					<label for="mmseo-tw-desc"><strong><?php esc_html_e( 'Twitter Description', 'mm-seo' ); ?></strong></label>
				</p>
				<textarea
					id="mmseo-tw-desc"
					name="_mmseo_tw_desc"
					class="widefat"
					rows="2"
				><?php echo esc_textarea( $v( 'tw_desc' ) ); ?></textarea>

				<p>
					<label for="mmseo-tw-image"><strong><?php esc_html_e( 'Twitter Image URL', 'mm-seo' ); ?></strong></label>
				</p>
				<input
					type="url"
					id="mmseo-tw-image"
					name="_mmseo_tw_image"
					value="<?php echo esc_attr( esc_url( $v( 'tw_image' ) ) ); ?>"
					class="widefat"
					placeholder="https://"
				>
				<button type="button" class="button mmseo-media-btn" data-target="mmseo-tw-image" data-id-target="">
					<?php esc_html_e( 'Choose Image', 'mm-seo' ); ?>
				</button>

			</div>

			<?php /* ── Advanced tab ── */ ?>
			<div id="mmseo-tab-advanced" class="mmseo-tab-panel" style="display:none;">

				<?php /* Canonical URL */ ?>
				<p>
					<label for="mmseo-canonical"><strong><?php esc_html_e( 'Canonical URL', 'mm-seo' ); ?></strong></label>
				</p>
				<input
					type="url"
					id="mmseo-canonical"
					name="_mmseo_canonical"
					value="<?php echo esc_attr( esc_url( $v( 'canonical' ) ) ); ?>"
					class="widefat"
					placeholder="<?php echo esc_attr( get_permalink( $post->ID ) ); ?>"
				>

				<?php /* Noindex */ ?>
				<p>
					<label for="mmseo-noindex"><strong><?php esc_html_e( 'Robots Index', 'mm-seo' ); ?></strong></label>
				</p>
				<select id="mmseo-noindex" name="_mmseo_noindex">
					<option value=""<?php selected( $v( 'noindex' ), '' ); ?>><?php esc_html_e( 'Default (from settings)', 'mm-seo' ); ?></option>
					<option value="1"<?php selected( $v( 'noindex' ), '1' ); ?>><?php esc_html_e( 'No Index (exclude)', 'mm-seo' ); ?></option>
					<option value="0"<?php selected( $v( 'noindex' ), '0' ); ?>><?php esc_html_e( 'Force Index', 'mm-seo' ); ?></option>
				</select>

				<?php /* Nofollow */ ?>
				<p>
					<label>
						<input
							type="checkbox"
							name="_mmseo_nofollow"
							value="1"
							<?php checked( '1', $v( 'nofollow' ) ); ?>
						>
						<?php esc_html_e( 'No Follow (instruct crawlers not to follow links)', 'mm-seo' ); ?>
					</label>
				</p>

				<?php /* Robots advanced directives */ ?>
				<p><strong><?php esc_html_e( 'Advanced Robots Directives', 'mm-seo' ); ?></strong></p>
				<?php
				$robots_adv = $v( 'robots_adv' );
				$adv_active = array_filter( array_map( 'trim', explode( ',', $robots_adv ) ) );

				$adv_options = [
					'noarchive'    => __( 'No Archive (prevent cached copies)', 'mm-seo' ),
					'nosnippet'    => __( 'No Snippet (prevent text/video snippets)', 'mm-seo' ),
					'noimageindex' => __( 'No Image Index (prevent image indexing)', 'mm-seo' ),
				];
				foreach ( $adv_options as $val => $label ) :
					?>
					<label style="display:block;">
						<input
							type="checkbox"
							name="_mmseo_robots_adv[]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( in_array( $val, $adv_active, true ), true ); ?>
						>
						<?php echo esc_html( $label ); ?>
					</label>
					<?php
				endforeach;
				?>

				<?php /* Schema type */ ?>
				<p>
					<label for="mmseo-schema-type"><strong><?php esc_html_e( 'Schema Type', 'mm-seo' ); ?></strong></label>
				</p>
				<select id="mmseo-schema-type" name="_mmseo_schema_type">
					<?php foreach ( self::SCHEMA_TYPES as $type ) : ?>
						<option
							value="<?php echo esc_attr( $type ); ?>"
							<?php selected( $v( 'schema_type' ), $type ); ?>
						><?php echo esc_html( '' === $type ? __( 'Default (inherit from settings)', 'mm-seo' ) : $type ); ?></option>
					<?php endforeach; ?>
				</select>

				<?php /* Breadcrumb title */ ?>
				<p>
					<label for="mmseo-breadcrumb-title"><strong><?php esc_html_e( 'Breadcrumb Title', 'mm-seo' ); ?></strong></label>
				</p>
				<input
					type="text"
					id="mmseo-breadcrumb-title"
					name="_mmseo_breadcrumb_title"
					value="<?php echo esc_attr( $v( 'breadcrumb_title' ) ); ?>"
					class="widefat"
					placeholder="<?php esc_attr_e( 'Leave empty to use post title', 'mm-seo' ); ?>"
				>

			</div>
		</div>

		<script>
		(function(){
			document.addEventListener('DOMContentLoaded',function(){
				// Tab switching.
				var btns=document.querySelectorAll('#mmseo-metabox-wrap .mmseo-tab-btn');
				var panels=document.querySelectorAll('#mmseo-metabox-wrap .mmseo-tab-panel');
				btns.forEach(function(btn){
					btn.addEventListener('click',function(){
						var target=this.dataset.tab;
						btns.forEach(function(b){b.classList.remove('mmseo-tab-active');b.setAttribute('aria-selected','false');});
						panels.forEach(function(p){p.style.display='none';p.classList.remove('mmseo-tab-panel-active');});
						this.classList.add('mmseo-tab-active');
						this.setAttribute('aria-selected','true');
						var panel=document.getElementById(target);
						if(panel){panel.style.display='';panel.classList.add('mmseo-tab-panel-active');}
					});
				});

				// Char counters.
				function updateCounter(input,counterId){
					var el=document.getElementById(counterId);
					if(el){el.textContent=input.value.length+' '+<?php echo wp_json_encode( __( 'chars', 'mm-seo' ) ); ?>;}
				}
				var titleInput=document.getElementById('mmseo-seo-title');
				var descInput=document.getElementById('mmseo-meta-desc');
				if(titleInput){
					titleInput.addEventListener('input',function(){updateCounter(this,'mmseo-title-counter');});
					updateCounter(titleInput,'mmseo-title-counter');
				}
				if(descInput){
					descInput.addEventListener('input',function(){updateCounter(this,'mmseo-desc-counter');});
					updateCounter(descInput,'mmseo-desc-counter');
				}
			});
		})();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Save meta box fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		// Nonce check.
		if (
			! isset( $_POST['mmseo_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mmseo_meta_nonce'] ) ), 'mmseo_meta_save_' . $post_id )
		) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Skip autosaves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// ── Scalar field map ──────────────────────────────────────────────────
		$scalar_fields = [
			'_mmseo_title'            => 'sanitize_text_field',
			'_mmseo_desc'             => 'sanitize_text_field',
			'_mmseo_focuskw'          => 'sanitize_text_field',
			'_mmseo_canonical'        => 'esc_url_raw',
			'_mmseo_noindex'          => [ Meta::class, 'sanitize_noindex_nofollow' ],
			'_mmseo_nofollow'         => [ Meta::class, 'sanitize_noindex_nofollow' ],
			'_mmseo_og_title'         => 'sanitize_text_field',
			'_mmseo_og_desc'          => 'sanitize_text_field',
			'_mmseo_og_image'         => 'esc_url_raw',
			'_mmseo_og_image_id'      => 'absint',
			'_mmseo_tw_title'         => 'sanitize_text_field',
			'_mmseo_tw_desc'          => 'sanitize_text_field',
			'_mmseo_tw_image'         => 'esc_url_raw',
			'_mmseo_breadcrumb_title' => 'sanitize_text_field',
		];

		foreach ( $scalar_fields as $meta_key => $sanitizer ) {
			if ( ! isset( $_POST[ $meta_key ] ) ) {
				continue;
			}
			$raw   = wp_unslash( $_POST[ $meta_key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$value = call_user_func( $sanitizer, $raw );

			if ( '' === (string) $value || 0 === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		// ── Schema type ──────────────────────────────────────────────────────
		if ( isset( $_POST['_mmseo_schema_type'] ) ) {
			$schema_raw  = sanitize_text_field( wp_unslash( $_POST['_mmseo_schema_type'] ) );
			$schema_safe = in_array( $schema_raw, self::SCHEMA_TYPES, true ) ? $schema_raw : '';
			if ( '' === $schema_safe ) {
				delete_post_meta( $post_id, '_mmseo_schema_type' );
			} else {
				update_post_meta( $post_id, '_mmseo_schema_type', $schema_safe );
			}
		}

		// ── Robots advanced (checkboxes) ─────────────────────────────────────
		$allowed_adv  = [ 'noarchive', 'nosnippet', 'noimageindex' ];
		$adv_raw      = isset( $_POST['_mmseo_robots_adv'] ) && is_array( $_POST['_mmseo_robots_adv'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['_mmseo_robots_adv'] ) )
			: [];
		$adv_filtered = array_filter( $adv_raw, fn( string $v ) => in_array( $v, $allowed_adv, true ) );
		$adv_value    = Meta::sanitize_robots_adv( implode( ',', $adv_filtered ) );
		if ( '' === $adv_value ) {
			delete_post_meta( $post_id, '_mmseo_robots_adv' );
		} else {
			update_post_meta( $post_id, '_mmseo_robots_adv', $adv_value );
		}

		// ── og_image_id: also save when explicitly set to 0 ─────────────────
		if ( isset( $_POST['_mmseo_og_image_id'] ) ) {
			$og_id = absint( wp_unslash( $_POST['_mmseo_og_image_id'] ) );
			if ( 0 === $og_id ) {
				delete_post_meta( $post_id, '_mmseo_og_image_id' );
			} else {
				update_post_meta( $post_id, '_mmseo_og_image_id', $og_id );
			}
		}
	}
}
