<?php
/**
 * Term SEO meta fields for taxonomy edit screens.
 *
 * @package MMSEO
 */

namespace MMSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class TermSeo
 *
 * Adds SEO meta fields to taxonomy term edit screens and handles saving.
 */
class TermSeo {

	/**
	 * Register hooks for all public taxonomies.
	 */
	public function register() {
		foreach ( get_taxonomies( [ 'public' => true ] ) as $tax ) {
			add_action( "{$tax}_edit_form_fields", [ $this, 'render_edit_fields' ], 10, 2 );
			add_action( "edited_{$tax}",           [ $this, 'save_term' ], 10, 2 );
			add_action( "created_{$tax}",          [ $this, 'save_term' ], 10, 2 );
		}
	}

	/**
	 * Render SEO meta fields on the term edit form.
	 *
	 * @param \WP_Term $term     The term object.
	 * @param string   $taxonomy The taxonomy slug.
	 */
	public function render_edit_fields( $term, $taxonomy ) {
		wp_nonce_field( 'mmseo_term_save_' . $term->term_id, 'mmseo_term_nonce' );

		$title     = \MMSEO\Meta::get_term( $term->term_id, 'title' );
		$desc      = \MMSEO\Meta::get_term( $term->term_id, 'desc' );
		$canonical = \MMSEO\Meta::get_term( $term->term_id, 'canonical' );
		$noindex   = \MMSEO\Meta::get_term( $term->term_id, 'noindex' );
		$og_image  = \MMSEO\Meta::get_term( $term->term_id, 'og_image' );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="mmseo_title"><?php esc_html_e( 'SEO Title', 'mm-seo' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					name="mmseo_title"
					id="mmseo_title"
					value="<?php echo esc_attr( $title ); ?>"
					class="large-text"
				/>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="mmseo_desc"><?php esc_html_e( 'Meta Description', 'mm-seo' ); ?></label>
			</th>
			<td>
				<textarea
					name="mmseo_desc"
					id="mmseo_desc"
					rows="3"
					class="large-text"
				><?php echo esc_textarea( $desc ); ?></textarea>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="mmseo_canonical"><?php esc_html_e( 'Canonical URL', 'mm-seo' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					name="mmseo_canonical"
					id="mmseo_canonical"
					value="<?php echo esc_attr( $canonical ); ?>"
					class="large-text"
				/>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="mmseo_noindex"><?php esc_html_e( 'No Index', 'mm-seo' ); ?></label>
			</th>
			<td>
				<select name="mmseo_noindex" id="mmseo_noindex">
					<option value="" <?php selected( $noindex, '' ); ?>><?php esc_html_e( 'Default', 'mm-seo' ); ?></option>
					<option value="1" <?php selected( $noindex, '1' ); ?>><?php esc_html_e( 'No Index', 'mm-seo' ); ?></option>
					<option value="0" <?php selected( $noindex, '0' ); ?>><?php esc_html_e( 'Force Index', 'mm-seo' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="mmseo_og_image"><?php esc_html_e( 'OG Image URL', 'mm-seo' ); ?></label>
			</th>
			<td>
				<input
					type="url"
					name="mmseo_og_image"
					id="mmseo_og_image"
					value="<?php echo esc_attr( $og_image ); ?>"
					class="large-text"
				/>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term SEO meta fields.
	 *
	 * @param int $term_id The term ID.
	 * @param int $tt_id   The term taxonomy ID.
	 */
	public function save_term( $term_id, $tt_id ) {
		// Nonce verification.
		if (
			! isset( $_POST['mmseo_term_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['mmseo_term_nonce'] ), 'mmseo_term_save_' . $term_id )
		) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_term', $term_id ) && ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// SEO Title.
		$this->save_term_meta(
			$term_id,
			'_mmseo_title',
			isset( $_POST['mmseo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['mmseo_title'] ) ) : ''
		);

		// Meta Description.
		$this->save_term_meta(
			$term_id,
			'_mmseo_desc',
			isset( $_POST['mmseo_desc'] ) ? sanitize_text_field( wp_unslash( $_POST['mmseo_desc'] ) ) : ''
		);

		// Canonical URL.
		$this->save_term_meta(
			$term_id,
			'_mmseo_canonical',
			isset( $_POST['mmseo_canonical'] ) ? esc_url_raw( wp_unslash( $_POST['mmseo_canonical'] ) ) : ''
		);

		// No Index — whitelist '', '0', '1'.
		$noindex_raw = isset( $_POST['mmseo_noindex'] ) ? wp_unslash( $_POST['mmseo_noindex'] ) : '';
		$noindex     = in_array( $noindex_raw, [ '', '0', '1' ], true ) ? $noindex_raw : '';
		$this->save_term_meta( $term_id, '_mmseo_noindex', $noindex );

		// OG Image URL.
		$this->save_term_meta(
			$term_id,
			'_mmseo_og_image',
			isset( $_POST['mmseo_og_image'] ) ? esc_url_raw( wp_unslash( $_POST['mmseo_og_image'] ) ) : ''
		);
	}

	/**
	 * Update or delete a term meta value.
	 *
	 * @param int    $term_id The term ID.
	 * @param string $key     The meta key.
	 * @param string $value   The sanitised value.
	 */
	private function save_term_meta( $term_id, $key, $value ) {
		if ( '' === $value ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $value );
		}
	}
}
