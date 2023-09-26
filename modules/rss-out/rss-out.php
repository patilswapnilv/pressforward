<?php
/**
 * Module to output RSS feeds.
 *
 * @package PressForward
 */

/**
 * PF_Module class.
 */
class PF_RSS_Out extends PF_Module {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::start();
		add_action( 'init', array( $this, 'request_feed' ) );
	}

	/**
	 * Default settings for the module.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'name'        => __( 'RSS Output Module', 'pressforward' ),
			'slug'        => 'rss-out',
			'description' => __( 'This module provides a way to output RSS Feeds from your subscribed items. An RSS Feed consisting of all your subscribed items will be available at your domain "/feedforward."', 'pressforward' ),
			'thumbnail'   => '',
			'options'     => '',
		);
	}

	/**
	 * Registers 'feedforward' feed with WP.
	 */
	public function request_feed() {
		global $wp_rewrite;
		add_feed( 'feedforward', array( $this, 'all_feed_assembler' ) );
	}

	/**
	 * Feed-generating callback for 'feedforward' theme.
	 */
	public function all_feed_assembler() {
		ob_start();

		if ( isset( $_GET['from'] ) ) {
			$from_ut = sanitize_text_field( wp_unslash( $_GET['from'] ) );
		} else {
			$from_ut = 0;
		}

		if ( isset( $_GET['limitless'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['limitless'] ) ) ) {
			$limitless = true;
		} else {
			$limitless = false;
		}

		if ( ( $from_ut < 100 ) || ( $from_ut > gmdate( 'U' ) ) ) {
			$from_ut = false;
		}

		header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
		echo '<?xml version="1.0" encoding="utf-8"?>';
		echo '<!-- RSS Generated by PressForward plugin on ' . esc_html( get_site_url() ) . ' on ' . esc_html( gmdate( 'm/d/Y; h:i:s A T' ) ) . " -->\n";
		?><rss version="2.0" xmlns:blogChannel="http://backend.userland.com/blogChannelModule" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" xmlns:slash="http://purl.org/rss/1.0/modules/slash/" xmlns:freebase="http://rdf.freebase.com/ns/internet/website_category" xmlns:creativeCommons="http://backend.userland.com/creativeCommonsRssModule">
			<channel>
				<title><?php bloginfo( 'name' ); ?> - <?php esc_html_e( 'PressForward Unfiltered Feed', 'pressforward' ); ?></title>
				<link><?php echo esc_html( home_url( '/?feed=feedforward' ) ); ?></link>
				<description>The aggregation of all feeds collected with PressForward at <?php bloginfo( 'name' ); ?></description>
				<language><?php bloginfo( 'language' ); ?></language>
				<blogChannel:blink>http://pressforward.org/news/</blogChannel:blink>
				<creativeCommons:license>http://creativecommons.org/licenses/by/3.0/us/</creativeCommons:license>
				<lastBuildDate><?php echo esc_html( gmdate( 'D, d M Y H:i:s O' ) ); ?></lastBuildDate>
				<atom:link href="<?php echo esc_html( home_url( '/?feed=feedforward' ) ); ?>" rel="self" type="application/rss+xml" />
				<docs>http://feed2.w3.org/docs/rss2.html</docs>
				<generator>PressForward</generator>
				<?php // Built based on MQL spec (http://wiki.freebase.com/wiki/MQL) for queries in style of [{  "type": "/internet/website_category", "id": null, "name": "Aggregator" }]. ?>
				<category domain="Freebase">Aggregator</category>
				<category domain="Freebase">/m/075x5v</category>
				<category domain="Freebase">/en/aggregator</category>
				<freebase:name>Aggregator</freebase:name>
				<freebase:mid>/m/075x5v</freebase:mid>
				<freebase:id>/en/aggregator</freebase:id>
				<?php

				$admin_email = get_bloginfo( 'admin_email' );
				$user_obj    = get_user_by( 'email', $admin_email );
				if ( ! $user_obj ) {
					$first_admin = get_users(
						array(
							'role'   => 'administrator',
							'number' => 1,
						)
					);
					foreach ( $first_admin as $admin ) {
						$admin_email = $admin->user_email;
					}
					$user_obj = get_user_by( 'email', $admin_email );
				}
				?>

				<managingEditor><?php bloginfo( 'admin_email' ); ?> (<?php echo esc_html( $user_obj->display_name ); ?>)</managingEditor>
				<webMaster><?php bloginfo( 'admin_email' ); ?> (<?php echo esc_html( $user_obj->display_name ); ?>)</webMaster>
				<ttl>30</ttl>
				<?php
					$c = 0;
				foreach ( pressforward( 'controller.loops' )->archive_feed_to_display( 0, 50, $from_ut, $limitless ) as $item ) {
					echo '<item>';
					?>
					<title><![CDATA[<?php echo esc_html( wp_strip_all_tags( $item['item_title'] ) ); ?>]]></title>
					<?php
					// <link> should send users to published nominations when available.
					?>
					<link><?php echo esc_html( $item['item_link'] ); ?></link>
					<guid isPermaLink="true"><?php echo esc_html( $item['item_link'] ); ?></guid>
					<?php
					if ( ! empty( $item['item_tags'] ) ) {
						$items = explode( ',', $item['item_tags'] );
						foreach ( $items as $tag ) {
							echo '<category><![CDATA[' . esc_html( $tag ) . ']]></category>';
						}
					}

					$source = get_the_source_title( $item['post_id'] );
					if ( ! empty( $source ) ) {
						$publisher = '<![CDATA[' . esc_html( $source ) . ']]>';
						echo '<dc:publisher>' . esc_html( $publisher ) . '</dc:publisher>';
					}

					?>
					<dc:creator><?php echo esc_html( $item['item_author'] ); ?></dc:creator>
					<?php
					$content = $item['item_content'];
					$excerpt = pf_feed_excerpt( $content );
					?>
					<description><![CDATA[<?php echo esc_html( wp_strip_all_tags( $excerpt ) ); ?>]]></description>
					<content:encoded><![CDATA[<?php echo esc_html( wp_strip_all_tags( $content ) ); ?>]]></content:encoded>
					<pubDate><?php echo esc_html( gmdate( 'D, d M Y H:i:s O', strtotime( $item['item_date'] ) ) ); ?></pubDate>

					<?php
					// Should use <source>, but not passing along RSS link, something to change.
					echo '</item>';
					++$c;

					if ( 50 === $c ) {
						break;
					}
				}
				?>
			</channel>
		</rss>

		<?php
		ob_end_flush();
	}
}
