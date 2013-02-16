<?php

/**
 * Readability stuff
 */

class PF_Readability {
	/**
	 * Handles a readability request via POST
	 */
	public function make_it_readable(){

		// Verify nonce
		if ( !wp_verify_nonce($_POST[PF_SLUG . '_nomination_nonce'], 'nomination') )
			die( __( "Nonce check failed. Please ensure you're supposed to be nominating stories.", 'pf' ) );

		$item_id = $_POST['read_item_id'];
		//error_reporting(0);
		if ( false === ( $itemReadReady = get_transient( 'item_readable_content_' . $item_id ) ) ) {

			set_time_limit(0);
			$url = pf_de_https($_POST['url']);
			$descrip = $_POST['content'];

			if ($_POST['authorship'] == 'aggregation') {
				$aggregated = true;
			} else {
				$aggregated = false;
			}

			if ((strlen($descrip) <= 160) || $aggregated) {
				$itemReadReady = self::readability_object($url);
				if ($itemReadReady != 'error-secured') {
					if (!$itemReadReady) {
						$itemReadReady = __( "This content failed Readability.", 'pf' );
						$itemReadReady .= '<br />';
						$url = str_replace('&amp;','&', $url);
						#Try and get the OpenGraph description.
						if (OpenGraph::fetch($url)){
							$node = OpenGraph::fetch($url);
							$itemReadReady .= $node->description;
						} //Note the @ below. This is because get_meta_tags doesn't have a failure state to check, it just throws errors. Thanks PHP...
						elseif ('' != ($contentHtml = @get_meta_tags($url))) {
							# Try and get the HEAD > META DESCRIPTION tag.
							$itemReadReady .= __( "This content failed an OpenGraph check.", 'pf' );
							$itemReadReady .= '<br />';
							$descrip = $contentHtml['description'];

						}
						else
						{
							# Ugh... we can't get anything huh?
							$itemReadReady .= __( "This content has no description we can find.", 'pf' );
							$itemReadReady .= '<br />';
							# We'll want to return a false to loop with.
							$itemReadReady = $descrip;

						}
					}
				} else {
					die('secured');
				}
			} else {
				die('readable');
			}

			set_transient( 'item_readable_content_' . $item_id, $itemReadReady, 60*60*24 );
		}

		print_r($itemReadReady);
	}

	/**
	 * Runs a URL through Readability and hands back the stripped content
	 *
	 * @since 1.7
	 * @see http://www.keyvan.net/2010/08/php-readability/
	 * @param $url
	 */
	public static function readability_object($url) {

		set_time_limit(0);

		$url = pf_de_https($url);
		$url = str_replace('&amp;','&', $url);
		//print_r($url); print_r(' - Readability<br />');
		// change from Boone - use wp_remote_get() instead of file_get_contents()
		$request = wp_remote_get( $url, array('timeout' => '30') );
		if (is_wp_error($request)) {
			$content = 'error-secured';
			//print_r($request); die();
			return $content;
		}
		if ( ! empty( $request['body'] ) ){
			$html = $request['body'];
		} else {
			$content = false;
			return $content;
		}

		//check if tidy exists to clean up the input.
		if (function_exists('tidy_parse_string')) {
			$tidy = tidy_parse_string($html, array(), 'UTF8');
			$tidy->cleanRepair();
			$html = $tidy->value;
		}
		// give it to Readability
		$readability = new Readability($html, $url);

		// print debug output?
		// useful to compare against Arc90's original JS version -
		// simply click the bookmarklet with FireBug's
		// console window open
		$readability->debug = false;

		// convert links to footnotes?
		$readability->convertLinksToFootnotes = false;

		// process it
		$result = $readability->init();

		if ($result){
			$content = $readability->getContent()->innerHTML;
			//$content = $contentOut->innerHTML;
				//if we've got tidy, let's use it.
				if (function_exists('tidy_parse_string')) {
					$tidy = tidy_parse_string($content,
						array('indent'=>true, 'show-body-only'=>true),
						'UTF8');
					$tidy->cleanRepair();
					$content = $tidy->value;
				}

		} else {
			# If Readability can't get the content, send back a FALSE to loop with.
			$content = false;
			# and let's throw up an error via AJAX as well, so we know what's going on.
			print_r($url . ' fails Readability.<br />');
		}

		return $content;
	}
}
