<?php
/**
 * REST API controller for live SEO analysis.
 *
 * Endpoint: POST /mmseo/v1/analyze
 * Used by the Divi/classic meta box for debounced live analysis without persisting.
 *
 * @package MMSEO\Analysis
 */

namespace MMSEO\Analysis;

use MMSEO\ContentExtractor;
use MMSEO\Meta;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the REST route for live SEO analysis.
 */
class RestController {

	/** REST namespace. */
	private const NAMESPACE = 'mmseo/v1';

	/** REST route. */
	private const ROUTE = '/analyze';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the REST API route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => WP_REST_Server::CREATABLE, // POST
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'permissions' ],
				'args'                => $this->get_route_args(),
			]
		);
	}

	/**
	 * Check permissions: user must be logged in and able to edit the target post.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function permissions( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'mmseo_rest_forbidden',
				__( 'You must be logged in to use this endpoint.', 'mm-seo' ),
				[ 'status' => 401 ]
			);
		}

		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'mmseo_rest_forbidden',
				__( 'You do not have permission to edit this post.', 'mm-seo' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Handle the analysis request.
	 *
	 * Builds analysis context from submitted parameters (falling back to stored meta),
	 * runs all checks, and returns the result — without persisting anything.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( $post_id > 0 && ! $post instanceof WP_Post ) {
			return new WP_Error(
				'mmseo_rest_not_found',
				__( 'Post not found.', 'mm-seo' ),
				[ 'status' => 404 ]
			);
		}

		// Use submitted values; fall back to stored meta when param is absent.
		$title = $request->get_param( 'title' );
		if ( null === $title || '' === $title ) {
			$title = $post_id > 0 ? Meta::get_post( $post_id, 'title' ) : '';
			if ( '' === $title && $post instanceof WP_Post ) {
				$title = get_the_title( $post );
			}
		}

		$meta_desc = $request->get_param( 'meta_desc' );
		if ( null === $meta_desc ) {
			$meta_desc = $post_id > 0 ? Meta::get_post( $post_id, 'desc' ) : '';
		}

		$focus_kw = $request->get_param( 'focus_kw' );
		if ( null === $focus_kw ) {
			$focus_kw = $post_id > 0 ? Meta::get_post( $post_id, 'focuskw' ) : '';
		}

		// Slug always from saved post.
		$slug = $post instanceof WP_Post ? $post->post_name : '';

		// Content always from saved post_content (Divi builder runs server-side).
		$post_content = $post instanceof WP_Post ? $post->post_content : '';
		$content      = ContentExtractor::extract( $post_content );

		$context = [
			'title'     => (string) $title,
			'meta_desc' => (string) $meta_desc,
			'focus_kw'  => (string) $focus_kw,
			'slug'      => $slug,
			'content'   => $content,
			'post'      => $post,
		];

		$result = Analyzer::run_checks( $context );

		return new WP_REST_Response(
			[
				'score'         => $result['score'],
				'rating'        => $result['rating'],
				'breakdown'     => $result['breakdown'],
				'traffic_light' => Analyzer::traffic_light( $result['score'] ),
				'checks'        => $result['checks'],
			],
			200
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Define the validated/sanitized argument schema for the route.
	 *
	 * @return array<string, mixed>
	 */
	private function get_route_args(): array {
		return [
			'post_id'  => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( 'Post ID to analyze.', 'mm-seo' ),
			],
			'title'    => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'SEO title override (uses stored meta when absent).', 'mm-seo' ),
			],
			'meta_desc' => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Meta description override (uses stored meta when absent).', 'mm-seo' ),
			],
			'focus_kw' => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Focus keyword override (uses stored meta when absent).', 'mm-seo' ),
			],
		];
	}
}
