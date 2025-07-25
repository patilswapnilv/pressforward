<?php
/**
 * 'Feed Items' admin panel.
 *
 * Called 'All Content' for legacy reasons.
 *
 * @package PressForward
 */

namespace PressForward\Core\Admin;

use Intraxia\Jaxion\Contract\Core\HasActions;

use PressForward\Controllers\PFtoWPUsers;

/**
 * 'Feed Items' admin panel.
 */
class AllContent implements HasActions {
	/**
	 * SystemUsers interface.
	 *
	 * @access public
	 * @var \PressForward\Controllers\PFtoWPUsers
	 */
	public $user_interface;

	/**
	 * Constructor.
	 *
	 * @param \PressForward\Controllers\PFtoWPUsers $user_interface PFtoWPUsers object.
	 */
	public function __construct( PFtoWPUsers $user_interface ) {
		$this->user_interface = $user_interface;
	}

	/**
	 * {@inheritdoc}
	 */
	public function action_hooks() {
		return array(
			array(
				'hook'     => 'admin_menu',
				'method'   => 'add_plugin_admin_menu',
				'priority' => 11,
			),
		);
	}

	/**
	 * Adds 'Feed Items' admin menu item.
	 */
	public function add_plugin_admin_menu() {
		add_submenu_page(
			PF_MENU_SLUG,
			__( 'Feed Items', 'pressforward' ),
			__( 'Feed Items', 'pressforward' ),
			get_option( 'pf_menu_all_content_access', $this->user_interface->pf_get_defining_capability_by_role( 'contributor' ) ),
			'pf-all-content',
			array( $this, 'display_reader_builder' )
		);
	}

	/**
	 * Displays Feed Items admin panel.
	 */
	public function display_reader_builder() {
		wp_enqueue_script( 'pf' );
		wp_enqueue_script( 'pf-views' );
		wp_enqueue_script( 'pf-relationships' );
		wp_enqueue_script( 'pf-archive-nom-imp' );

		wp_enqueue_style( 'pf-style' );

		$do_infinite_scroll = 'false' !== get_user_option( 'pf_user_scroll_switch', pressforward( 'controller.template_factory' )->user_id() );

		if ( $do_infinite_scroll ) {
			wp_enqueue_script( 'pf-scroll' );
		}

		$user_obj = wp_get_current_user();
		$user_id  = $user_obj->ID;

		$per_page = 20;

		// Calling the feedlist within the pf class.
		if ( isset( $_GET['pc'] ) ) {
			$current_page = intval( $_GET['pc'] );
		} else {
			$current_page = 1;
		}

		$current_start = ( ( $current_page - 1 ) * $per_page ) + 1;

		$container_classes = [
			'pf_container',
			'pf-all-content',
			'full',
		];

		if ( isset( $_GET['reveal'] ) && ( 'no_hidden' === $_GET['reveal'] ) ) {
			$container_classes[] = 'archived_visible';
		}

		$view_check = get_user_meta( $user_id, 'pf_user_read_state', true );
		if ( 'golist' === $view_check ) {
			$container_classes[] = 'list';
		} else {
			$container_classes[] = 'grid';
		}

		if ( $do_infinite_scroll ) {
			$container_classes[] = 'infinite-scroll';
		}

		$pf_url = defined( 'PF_URL' ) ? PF_URL : '';

		?>
		<div class="pf-loader"></div>
		<div class="<?php echo esc_attr( implode( ' ', $container_classes ) ); ?>">
			<header id="app-banner">
				<div class="title-span title">
					<?php pressforward( 'controller.template_factory' )->the_page_headline( __( 'Feed Items', 'pressforward' ) ); ?>
					<button class="btn btn-small" id="fullscreenfeed"> <?php esc_html_e( 'Full Screen', 'pressforward' ); ?> </button>
				</div><!-- End title -->
				<?php pressforward( 'admin.templates' )->search_template(); ?>

			</header><!-- End Header -->
			<?php
				pressforward( 'admin.templates' )->nav_bar();
			?>
			<div role="main">
				<?php pressforward( 'admin.templates' )->the_side_menu(); ?>
				<?php pressforward( 'schema.folders' )->folderbox(); ?>
				<div id="entries">
					<?php echo '<img class="loading-top" src="' . esc_attr( $pf_url ) . 'assets/images/ajax-loader.gif" alt="' . esc_attr__( 'Loading...', 'pressforward' ) . '" style="display: none" />'; ?>

					<div id="errors">
					<?php
					if ( 0 >= self::count_the_posts( 'pf_feed' ) ) {
						echo '<p>' . esc_html__( 'You need to add feeds, there are none in the system.', 'pressforward' ) . '</p>';
					}
					?>
					</div>

				<?php
				pressforward( 'admin.templates' )->nominate_this( 'as_feed_item' );

				// Use this foreach loop to go through the overall feedlist, select each individual feed item (post) and do stuff with it.
				$index = $current_start;

				if ( isset( $_GET['by'] ) ) {
					$limit = sanitize_text_field( wp_unslash( $_GET['by'] ) );
				} else {
					$limit = false;
				}

				$archive_feed_args = array(
					'start'          => $current_start,
					'posts_per_page' => 24,
					'relationship'   => $limit,
				);

				if ( isset( $_POST['search-terms'] ) ) {
					$archive_feed_args['search_terms']     = sanitize_text_field( wp_unslash( $_POST['search-terms'] ) );
					$archive_feed_args['exclude_archived'] = true;
				}

				if ( ! isset( $_GET['reveal'] ) ) {
					$archive_feed_args['exclude_archived'] = true;
				}

				if ( isset( $_GET['reveal'] ) ) {
					$archive_feed_args['reveal'] = sanitize_text_field( wp_unslash( $_GET['reveal'] ) );
				}

				$archive_feed_args['count_total'] = true;

				if ( isset( $_GET['sort-by'] ) ) {
					$sort_by    = sanitize_text_field( wp_unslash( $_GET['sort-by'] ) );
					$sort_order = isset( $_GET['sort-order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['sort-order'] ) ) ) ? 'ASC' : 'DESC';

					switch ( $sort_by ) {
						case 'item-date':
						default:
							$archive_feed_args['orderby'] = [
								'meta_value' => $sort_order,
							];
							break;

						case 'feed-in-date':
							$archive_feed_args['orderby'] = [
								'date' => $sort_order,
							];
							break;
					}
				}

				$date_range_start = isset( $_GET['date-range-start'] ) ? sanitize_text_field( wp_unslash( $_GET['date-range-start'] ) ) : '';
				$date_range_end   = isset( $_GET['date-range-end'] ) ? sanitize_text_field( wp_unslash( $_GET['date-range-end'] ) ) : '';

				if ( $date_range_start || $date_range_end ) {
					$date_query = [];

					if ( $date_range_start ) {
						$date_query['after'] = $date_range_start;
					}

					if ( $date_range_end ) {
						$date_query['before'] = $date_range_end;
					}

					$archive_feed_args['date_query'] = $date_query;
				}

				$items_to_display = pressforward( 'controller.loops' )->archive_feed_to_display( $archive_feed_args );

				$this->prime_relationship_caches( $items_to_display['items'] );
				$this->prime_draft_caches( $items_to_display['items'] );

				foreach ( $items_to_display['items'] as $item ) {
					pressforward( 'admin.templates' )->form_of_an_item( $item, $index );

					++$index;

					/*
					 * Check out the built comment form from EditFlow at https://github.com/danielbachhuber/Edit-Flow/blob/master/modules/editorial-comments/editorial-comments.php.
					 * So, we're going to need some AJAXery method of sending RSS data to a nominations post.
					 * Best example I can think of? The editorial comments from EditFlow, see edit-flow/modules/editorial-comments/editorial-comments.php, esp ln 284.
					 * But lets start simple and get the hang of AJAX in WP first. http://wp.tutsplus.com/articles/getting-started-with-ajax-wordpress-pagination/.
					 * Eventually should use http://wpseek.com/wp_insert_post/ I think....
					 * So what to submit? I could store all the post data in hidden fields and submit it within seperate form docs, but that's a lot of data.
					 * Perhaps just an md5 hash of the ID of the post? Then use the retrieval function to find the matching post and submit it properly? Something to experement with...
					 */
				} // End foreach.

				?>

			<div class="clear"></div>
			<?php
			echo '</div><!-- End entries -->';
			?>
			<div class="clear"></div>
			<?php
			echo '</div><!-- End main -->';

			$previous_page = $current_page - 1;
			$next_page     = $current_page + 1;

			if ( ! empty( $_GET['by'] ) ) {
				$limit_q = '&by=' . $limit;
			} else {
				$limit_q = '';
			}

			$page_prev = '?page=pf-all-content' . $limit_q . '&pc=' . $previous_page;
			$page_next = '?page=pf-all-content' . $limit_q . '&pc=' . $next_page;
			if ( isset( $_GET['folder'] ) ) {
				$page_q     = sanitize_text_field( wp_unslash( $_GET['folder'] ) );
				$page_qed   = '&folder=' . $page_q;
				$page_next .= $page_qed;
				$page_prev .= $page_qed;

			}

			if ( isset( $_GET['feed'] ) ) {
				$page_q     = sanitize_text_field( wp_unslash( $_GET['feed'] ) );
				$page_qed   = '&feed=' . $page_q;
				$page_next .= $page_qed;
				$page_prev .= $page_qed;
			}

			if ( $index >= $per_page ) {
				$pagination_links = [];

				echo '<div class="pf-navigation">';

				if ( $previous_page > 0 ) {
					$pagination_links[] = '<span class="feedprev"><a class="prevnav" href="admin.php' . esc_attr( $page_prev ) . '">' . esc_html__( 'Previous Page', 'pressforward' ) . '</a></span>';
				}

				if ( $next_page <= $items_to_display['max_num_pages'] ) {
					$pagination_links[] = '<span class="feednext"><a class="nextnav" href="admin.php' . esc_attr( $page_next ) . '">' . esc_html__( 'Next Page', 'pressforward' ) . '</a></span>';
				}

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo implode( ' | ', $pagination_links );

				echo '</div>';
			}

			?>

		<div class="clear"></div>
		<?php
		echo '</div><!-- End container-fluid -->';

		if ( $do_infinite_scroll ) {
			pressforward( 'admin.templates' )->infinite_scroll_status_markup();
		}
	}

	/**
	 * Primes relationship caches for a set of items for the current user.
	 *
	 * @since 5.8.0
	 *
	 * @param array $items Items to prime caches for.
	 */
	public function prime_relationship_caches( $items ) {
		pf_prime_relationship_caches( wp_list_pluck( $items, 'post_id' ), get_current_user_id() );
	}

	/**
	 * Primes is_drafted caches for a set of items.
	 *
	 * @since 5.8.0
	 *
	 * @param array $items Items to prime caches for.
	 */
	public function prime_draft_caches( $items ) {
		pf_prime_is_drafted_caches( wp_list_pluck( $items, 'item_id' ) );
	}

	/**
	 * Generates a post count for a post type and optional date limits.
	 *
	 * @param string $post_type Post type.
	 * @param int    $date_less Number of months.
	 * @return int
	 */
	public function count_the_posts( $post_type, $date_less = 0 ) {

		if ( ! $date_less ) {
			$query_arg = array(
				'post_type'      => $post_type,
				'posts_per_page' => -1,
			);
		} else {
			if ( $date_less < 12 ) {
				$y = (int) gmdate( 'Y' );
				$m = (int) gmdate( 'm' );
				$m = $m + $date_less;
			} else {
				$y = (int) gmdate( 'Y' );
				$y = $y - floor( $date_less / 12 );
				$m = (int) gmdate( 'm' );
				$m = $m - ( abs( $date_less ) - ( 12 * floor( $date_less / 12 ) ) );
			}
			$query_arg = array(
				'post_type'      => $post_type,
				'year'           => $y,
				'monthnum'       => $m,
				'posts_per_page' => -1,
			);
		}

		$query      = new \WP_Query( $query_arg );
		$post_count = $query->post_count;
		wp_reset_postdata();

		return $post_count;
	}
}
