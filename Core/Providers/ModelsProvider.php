<?php
/**
 * Service provider for PF data models.
 *
 * @package PressForward
 */

namespace PressForward\Core\Providers;

use Intraxia\Jaxion\Contract\Core\Container;
use PressForward\Core\Models\Feed_Item;

/**
 * ModelsProvider class.
 */
class ModelsProvider extends \Intraxia\Jaxion\Assets\ServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ) {
		$container->define(
			'model.feed_item',
			function () {
				return ( function () {
					return new Feed_Item();
				} );
			}
		);
	}
}
