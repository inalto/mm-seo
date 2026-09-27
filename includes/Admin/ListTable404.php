<?php
/**
 * WP_List_Table implementation for the 404 monitor log.
 *
 * @package MMSEO
 */

namespace MMSEO\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class ListTable404
 *
 * Displays the 404 error log in a sortable, bulk-actionable WP_List_Table.
 */
class ListTable404 extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => '404 entry',
			'plural'   => '404 entries',
			'ajax'     => false,
		] );
	}

	/**
	 * Return table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return [
			'cb'        => '<input type="checkbox" />',
			'uri'       => __( 'URI', 'mm-seo' ),
			'hits'      => __( 'Hits', 'mm-seo' ),
			'referrer'  => __( 'Referrer', 'mm-seo' ),
			'last_seen' => __( 'Last Seen', 'mm-seo' ),
		];
	}

	/**
	 * Return sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return [
			'hits'      => [ 'hits', false ],
			'last_seen' => [ 'last_seen', true ],
		];
	}

	/**
	 * Return available bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return [
			'delete' => __( 'Delete', 'mm-seo' ),
		];
	}

	/**
	 * Query the database and set up pagination.
	 */
	public function prepare_items() {
		global $wpdb;

		$table = $wpdb->prefix . 'mmseo_404_log';

		// Check the table exists before querying.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;

		if ( ! $exists ) {
			$this->items = [];
			return;
		}

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Sanitise orderby.
		$allowed_orderby = [ 'hits', 'last_seen' ];
		$orderby         = sanitize_key( $_GET['orderby'] ?? 'last_seen' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'last_seen';
		}

		// Sanitise order.
		$order_input = strtoupper( sanitize_key( $_GET['order'] ?? 'desc' ) );
		$order       = 'ASC' === $order_input ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				( $current_page - 1 ) * $per_page
			),
			ARRAY_A
		);

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
		] );

		$this->items = $items ?? [];
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return '<input type="checkbox" name="log_ids[]" value="' . esc_attr( $item['id'] ) . '" />';
	}

	/**
	 * Render the URI column with row actions.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_uri( $item ) {
		$uri          = esc_html( $item['uri'] );
		$redirect_url = admin_url( 'admin.php?page=mm-seo-redirects&src=' . urlencode( $item['uri'] ) );

		$row_actions = [
			'create_redirect' => '<a href="' . esc_url( $redirect_url ) . '">' . __( 'Create Redirect', 'mm-seo' ) . '</a>',
		];

		return $uri . $this->row_actions( $row_actions );
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item   Row data.
	 * @param string $column Column slug.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'uri':
				return esc_html( $item['uri'] );
			case 'hits':
				return esc_html( $item['hits'] );
			case 'referrer':
				return esc_html( $item['referrer'] );
			case 'last_seen':
				return esc_html( $item['last_seen'] );
			default:
				return '';
		}
	}

	/**
	 * Process bulk actions (delete).
	 */
	public function process_bulk_action() {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		// Verify nonce. WP_List_Table bulk nonce uses 'bulk-' + plural.
		$nonce_action = 'bulk-' . $this->_args['plural'];
		if (
			! isset( $_REQUEST['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), $nonce_action )
		) {
			wp_die( esc_html__( 'Security check failed.', 'mm-seo' ) );
		}

		if ( ! current_user_can( 'mmseo_manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mm-seo' ) );
		}

		$ids = array_map( 'absint', $_REQUEST['log_ids'] ?? [] );

		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;

		$table        = $wpdb->prefix . 'mmseo_404_log';
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids ) );
	}

	/**
	 * Static render method called from the SettingsPage.
	 */
	public static function render() {
		if ( ! \MMSEO\Options::module_enabled( 'monitor404' ) ) {
			echo '<p>' . esc_html__( 'Enable the 404 Monitor module to start logging not-found requests.', 'mm-seo' ) . '</p>';
			return;
		}

		global $wpdb;

		$table  = $wpdb->prefix . 'mmseo_404_log';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;

		if ( ! $exists ) {
			echo '<p>' . esc_html__( 'The 404 log table has not been created yet. Re-save the plugin settings to initialise it.', 'mm-seo' ) . '</p>';
			return;
		}

		$list_table = new self();
		$list_table->process_bulk_action();
		$list_table->prepare_items();
		?>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ?? '' ); ?>" />
			<?php
			wp_nonce_field( 'bulk-' . $list_table->_args['plural'] );
			$list_table->display();
			?>
		</form>
		<?php
	}
}
