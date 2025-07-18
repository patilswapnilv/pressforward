<?php
/**
 * Google Scholar module.
 *
 * @package PressForward
 */

use PressForward\Interfaces\FeedSource;

use PressForward\Core\DTO\FeedItem;

/**
 * PF_Google_Scholar class.
 */
class PF_Google_Scholar extends PF_Module implements FeedSource {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->feed_type = 'google-scholar';
		parent::start();
	}

	/**
	 * Fetches data from URL.
	 *
	 * @param \PressForward\Core\Models\Feed $feed Feed object.
	 * @return array|\WP_Error
	 */
	public function fetch( $feed ) {
		$url = $feed->get( 'remote_feed_url' );

		$is_profile = false !== strpos( $url, 'user=' );

		// If the URL is a GS profile URL, we need to sortby=pubdate to ensure we get the latest items.
		if ( $is_profile ) {
			$url = add_query_arg( 'sortby', 'pubdate', $url );
		}

		// Get the remote HTML content using WordPress functions.
		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 30,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
			]
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
				'entries' => [],
			];
		}

		// Get the body content.
		$html = wp_remote_retrieve_body( $response );

		// Check if we got content.
		if ( empty( $html ) ) {
			return [];
		}

		// Load HTML into DOMDocument.
		$doc = new DOMDocument();

		// Suppress errors for malformed HTML.
		libxml_use_internal_errors( true );
		$doc->loadHTML( $html );
		libxml_clear_errors();

		// Create a DOMXPath object for querying the DOM.
		$xpath = new DOMXPath( $doc );

		if ( $is_profile ) {
			$entries = $this->parse_items_from_profile( $xpath, $feed );
		} else {
			$entries = $this->parse_items_from_search( $xpath, $feed );
		}

		return $entries;
	}

	/**
	 * Parses items from Google Scholar profile page.
	 *
	 * @param DOMXPath                       $xpath The DOMXPath object.
	 * @param \PressForward\Core\Models\Feed $feed  The feed object.
	 * @return array The parsed entries.
	 */
	protected function parse_items_from_profile( $xpath, $feed ) {
		// Find all entries (table rows in the profile page).
		$entries_nodes = $xpath->query( '//tr[@class="gsc_a_tr"]' );

		$entries = [];

		foreach ( $entries_nodes as $entry_node ) {
			$entry = [];

			// Get the title and link (within td.gsc_a_t).
			$title_container = $xpath->query( './/td[@class="gsc_a_t"]', $entry_node )->item( 0 );
			if ( $title_container ) {
				$title_node = $xpath->query( './/a[@class="gsc_a_at"]', $title_container )->item( 0 );
				if ( $title_node instanceof DOMElement ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$entry['title'] = trim( $title_node->textContent );

					// For profile pages, links are relative, so we need to prepend the base URL.
					$relative_link = $title_node->getAttribute( 'href' );
					$entry['link'] = 'https://scholar.google.com' . $relative_link;
				}

				// Get authors (within div.gs_gray).
				$authors_node = $xpath->query( './/div[@class="gs_gray"][1]', $title_container )->item( 0 );
				if ( $authors_node ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$entry['authors'] = trim( $authors_node->textContent );
				}

				// Get publication venue (within second div.gs_gray).
				$publication_node = $xpath->query( './/div[@class="gs_gray"][2]', $title_container )->item( 0 );
				if ( $publication_node ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$entry['publication'] = trim( $publication_node->textContent );
				}
			}

			// Get year from the year column (td.gsc_a_y).
			$year_node = $xpath->query( './/td[@class="gsc_a_y"]', $entry_node )->item( 0 );
			if ( $year_node ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$entry['year'] = trim( $year_node->textContent );
			}

			// Generate an ID for this item.
			$id = pressforward_create_feed_item_id( $entry['link'], $entry['title'] );

			$feed_title = $feed->get( 'title' );
			if ( ! is_string( $feed_title ) ) {
				$feed_title = '';
			}

			$item_wp_date = ! empty( $entry['year'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $entry['year'] . '-01-01' ) ) : gmdate( 'Y-m-d H:i:s' );

			$entries[] = FeedItem::from_array(
				[
					'item_title'     => $entry['title'],
					'source_title'   => $feed_title,
					'item_date'      => $entry['year'] ?? $item_wp_date,
					'item_author'    => $entry['authors'] ?? '',
					'item_content'   => '',
					'item_link'      => $entry['link'] ?? '',
					'item_id'        => $id,
					'item_wp_date'   => $item_wp_date,
					'item_tags'      => '',
					'description'    => $entry['publication'] ?? '',
					'parent_feed_id' => $feed->get( 'id' ),
				]
			);
		}

		return $entries;
	}

	/**
	 * Parses items from Google Scholar search page.
	 *
	 * @param DOMXPath                       $xpath The DOMXPath object.
	 * @param \PressForward\Core\Models\Feed $feed  The feed object.
	 * @return array The parsed entries.
	 */
	protected function parse_items_from_search( $xpath, $feed ) {
		// Find all entries with class gs_ri (main result container).
		$entries_nodes = $xpath->query( '//div[@class="gs_ri"]' );

		$entries = [];

		foreach ( $entries_nodes as $entry_node ) {
			$entry = [];

			// Get the title and link (within h3.gs_rt).
			$title_node = $xpath->query( './/h3[@class="gs_rt"]', $entry_node )->item( 0 );
			if ( $title_node ) {
				$link_node = $xpath->query( './/a', $title_node )->item( 0 );
				if ( $link_node instanceof DOMElement ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$entry['title'] = trim( $link_node->textContent );
					$entry['link']  = $link_node->getAttribute( 'href' );
				} else {
					// Sometimes title has no link.
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$entry['title'] = trim( $title_node->textContent );
					$entry['link']  = '';
				}
			}

			// Get author, publication venue, year (within div.gs_a).
			$author_pub_node = $xpath->query( './/div[@class="gs_a"]', $entry_node )->item( 0 );
			if ( $author_pub_node ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$author_pub_text = trim( $author_pub_node->textContent );

				$entry['author_publication_info'] = $author_pub_text;

				// Try to extract year - often in format YYYY.
				if ( preg_match( '/\b(19|20)\d{2}\b/', $author_pub_text, $matches ) ) {
					$entry['year'] = $matches[0];
				}

				// Try to extract authors (usually before first dash).
				$parts = explode( '-', $author_pub_text, 2 );

				$entry['authors'] = trim( $parts[0] );

				// If we have publication info (after first dash).
				if ( count( $parts ) > 1 ) {
					$entry['publication'] = trim( $parts[1] );
				}
			}

			// Get snippet/abstract (within div.gs_rs).
			$snippet_node = $xpath->query( './/div[@class="gs_rs"]', $entry_node )->item( 0 );
			if ( $snippet_node ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$entry['snippet'] = trim( $snippet_node->textContent );
			}

			$id = pressforward_create_feed_item_id( $entry['link'], $entry['title'] );

			$feed_title = $feed->get( 'title' );
			if ( ! is_string( $feed_title ) ) {
				$feed_title = '';
			}

			$item_wp_date = ! empty( $entry['year'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $entry['year'] . '-01-01' ) ) : gmdate( 'Y-m-d H:i:s' );

			$entries[] = FeedItem::from_array(
				[
					'item_title'     => $entry['title'],
					'source_title'   => $feed_title,
					'item_date'      => $entry['year'] ?? $item_wp_date,
					'item_author'    => $entry['authors'] ?? '',
					'item_content'   => $entry['snippet'] ?? '',
					'item_link'      => $entry['link'] ?? '',
					'item_id'        => $id,
					'item_wp_date'   => $item_wp_date,
					'item_tags'      => '',
					'description'    => $entry['snippet'] ?? '',
					'parent_feed_id' => $feed->get( 'id' ),
				]
			);
		}

		return $entries;
	}

	/**
	 * Performs a health check on the feed.
	 *
	 * @param \PressForward\Core\Models\Feed $feed        Feed object.
	 * @param bool                           $is_new_feed Whether the feed is new.
	 */
	public function health_check( \PressForward\Core\Models\Feed $feed, $is_new_feed = false ) {
		$feed_url = $feed->get( 'remote_feed_url' );

		$feed_is_valid = false;

		// Fetch and see if it looks like a Google Scholar feed.
		$response = wp_remote_get(
			$feed_url,
			[
				'timeout'    => 30,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
			]
		);

		$body = '';
		if ( ! is_wp_error( $response ) ) {
			$body = wp_remote_retrieve_body( $response );

			// Check if the body contains Google Scholar specific content.
			if ( strpos( $body, 'Google Scholar' ) !== false ) {
				$feed_is_valid = true;
			}
		}

		$alert_box = pressforward( 'library.alertbox' );

		if ( ! $feed_is_valid ) {
			if ( $alert_box ) {
				$alert_box->switch_post_type( $feed->get( 'id' ) );
				$alert_box->add_bug_type_to_post( $feed->get( 'id' ), __( 'Bad Google Scholar feed URL', 'pressforward' ) );
			}

			return;
		}

		// Feed appears valid.
		if ( $alert_box ) {
			$alert_box->dismiss_alert( $feed->get( 'id' ) );
		}

		if ( $is_new_feed ) {
			// Get the feed title from search box HTML, class 'gs_in_txt'.
			$doc = new DOMDocument();
			libxml_use_internal_errors( true );
			$doc->loadHTML( $body );
			libxml_clear_errors();

			$xpath = new DOMXPath( $doc );

			$is_profile = false !== strpos( $feed_url, 'user=' );

			if ( $is_profile ) {
				// profile page - get the user's name from #gsc_prf_in.
				$user_name_node = $xpath->query( '//div[@id="gsc_prf_in"]' )->item( 0 );

				if ( $user_name_node instanceof DOMElement ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$user_name = trim( $user_name_node->textContent );

					$feed_title = sprintf(
						// translators: %s is the user's name.
						__( 'Google Scholar Profile: %s', 'pressforward' ),
						$user_name
					);
					$feed->set( 'title', $feed_title );

					$feed_description = sprintf(
						// translators: %s is the user's name.
						__( 'Google Scholar profile for %s', 'pressforward' ),
						$user_name
					);
					$feed->set( 'description', $feed_description );

					$feed->set( 'htmlUrl', $feed_url );
					$feed->set( 'feed_author', __( 'Google Scholar', 'pressforward' ) );

					$feed->save();
				} else {
					// Fallback if title not found.
					$feed->set( 'title', __( 'Google Scholar Profile', 'pressforward' ) );
				}
			} else {
				// search box node has 'name' attribute 'as_epq'.
				$search_box_node = $xpath->query( '//input[@name="as_epq"]' )->item( 0 );

				if ( $search_box_node instanceof DOMElement ) {
					$search_text = $search_box_node->getAttribute( 'value' );

					$feed_title = sprintf(
						// translators: %s is the search term.
						__( 'Google Scholar: "%s"', 'pressforward' ),
						$search_text
					);
					$feed->set( 'title', $feed_title );

					$feed_description = sprintf(
						// translators: %s is the search term.
						__( 'Google Scholar search for "%s"', 'pressforward' ),
						$search_text
					);
					$feed->set( 'description', $feed_description );

					$feed->set( 'htmlUrl', $feed_url );
					$feed->set( 'feed_author', __( 'Google Scholar', 'pressforward' ) );

					$feed->save();
				} else {
					// Fallback if title not found.
					$feed->set( 'title', __( 'Google Scholar Feed', 'pressforward' ) );
				}
			}
		}
	}
}
