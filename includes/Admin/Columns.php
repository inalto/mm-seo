<?php
/**
 * SEO score column for post list tables.
 *
 * @package MMSEO
 */

namespace MMSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Columns
 *
 * Adds and renders an SEO score column in admin post list tables,
 * with sorting support.
 */
class Columns {

	/**
	 * Register hooks for all public post types.
	 */
	public function register() {
		foreach ( get_post_types( [ 'public' => true ] ) as $pt ) {
			if ( 'attachment' === $pt ) {
				continue;
			}

			add_filter( "manage_{$pt}_posts_columns",        [ $this, 'add_column' ] );
			add_action( "manage_{$pt}_posts_custom_column",  [ $this, 'render_column' ], 10, 2 );
			add_filter( "manage_edit-{$pt}_sortable_columns", [ $this, 'sortable_columns' ] );
		}

		add_action( 'pre_get_posts', [ $this, 'sort_by_score' ] );
		add_action( 'admin_head',    [ $this, 'output_column_css' ] );
	}

	/**
	 * Insert the SEO score column after the title column.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_column( $columns ) {
		$new = [];

		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;

			if ( 'title' === $key ) {
				$new['mmseo_score'] =
					'<span class="dashicons dashicons-chart-line" title="' .
					esc_attr__( 'MM Score', 'mm-seo' ) .
					'"><span class="screen-reader-text">' .
					esc_html__( 'MM Score', 'mm-seo' ) .
					'</span></span>';
			}
		}

		return $new;
	}

	/**
	 * Render the SEO score column cell.
	 *
	 * @param string $column  The column slug.
	 * @param int    $post_id The post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'mmseo_score' !== $column ) {
			return;
		}

		$score = (string) get_post_meta( $post_id, '_mmseo_seo_score', true );

		if ( '' === $score || ! is_numeric( $score ) ) {
			echo '<span class="mmseo-dot mmseo-dot--gray" title="&#8212;"></span>';
			return;
		}

		$score = (int) $score;
		$color = $score >= 71 ? 'green' : ( $score >= 41 ? 'amber' : 'red' );

		echo '<span class="mmseo-dot mmseo-dot--' . esc_attr( $color ) . '" title="' . esc_attr( $score ) . '">' . esc_html( $score ) . '</span>';
	}

	/**
	 * Register the SEO score column as sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function sortable_columns( $columns ) {
		$columns['mmseo_score'] = 'mmseo_score';
		return $columns;
	}

	/**
	 * Modify the main query to sort by SEO score meta value.
	 *
	 * @param \WP_Query $query The current query.
	 */
	public function sort_by_score( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'mmseo_score' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', '_mmseo_seo_score' );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Output inline CSS for the SEO score column on post list screens.
	 */
	public function output_column_css() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		?>
		<style>
			.column-mmseo_score { width: 48px; text-align: center; }
			.mmseo-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; font-size: 0; }
			.mmseo-dot--green  { background-color: #46b450; }
			.mmseo-dot--amber  { background-color: #ffb900; }
			.mmseo-dot--red    { background-color: #dc3232; }
			.mmseo-dot--gray   { background-color: #999; }
		</style>
		<?php
	}
}
