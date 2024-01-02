<?php
/**
 * Server-side registration for the bookmarklet-code block.
 *
 * @package PressForward
 * @since 5.6.0
 */

namespace PressForward\Core\Blocks\ItemNominators;

add_action( 'init', __NAMESPACE__ . '\register_block' );

/**
 * Registers the item-nominators block.
 *
 * @since 5.6.0
 *
 * @return void
 */
function register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	register_block_type(
		__DIR__ . '/block.json',
		[
			'render_callback' => __NAMESPACE__ . '\render_block',
		]
	);
}

/**
 * Renders the item-nominators block.
 *
 * @since 5.6.0
 *
 * @param array $attributes The block attributes.
 * @return string
 */
function render_block( $attributes, $content, $block ) {
	$nominators = pressforward( 'controller.metas' )->get_post_pf_meta( get_the_ID(), 'nominator_array', true );

	$nominator_names = is_array( $nominators ) ? array_map(
		function ( $nominator ) {
			$user = get_user_by( 'id', $nominator['user_id'] );
			if ( $user ) {
				return $user->display_name;
			}
		},
		$nominators
	) : [];

	$nominator_names = array_filter( $nominator_names );
	sort( $nominator_names );

	if ( ! $nominator_names ) {
		return '';
	}

	// Assemble inline styles.
	$inline_styles = [];
	if ( isset( $attributes['style']['typography']['fontSize'] ) ) {
		$inline_styles[] = 'font-size: ' . $attributes['style']['typography']['fontSize'];
	}

	if ( isset( $attributes['style']['typography']['lineHeight'] ) ) {
		$inline_styles[] = 'line-height: ' . $attributes['style']['typography']['lineHeight'];
	}

	if ( isset( $attributes['style']['color']['background'] ) ) {
		$inline_styles[] = 'background-color: ' . $attributes['style']['color']['background'];
	}

	if ( isset( $attributes['style']['color']['text'] ) ) {
		$inline_styles[] = 'color: ' . $attributes['style']['color']['text'];
	}

	$spacing_types = [ 'margin', 'padding' ];
	foreach ( $spacing_types as $spacing_type ) {
		if ( ! isset( $attributes['style']['spacing'][ $spacing_type ] ) ) {
			continue;
		}

		if ( is_scalar( $attributes['style']['spacing'][ $spacing_type ] ) ) {
			$inline_styles[] = $spacing_type . ': ' . $attributes['style']['spacing'][ $spacing_type ];
			continue;
		}

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $spacing_direction ) {
			if ( isset( $attributes['style']['spacing'][ $spacing_type ][ $spacing_direction ] ) ) {
				$inline_styles[] = $spacing_type . '-' . $spacing_direction . ': ' . $attributes['style']['spacing'][ $spacing_type ][ $spacing_direction ];
			}
		}
	}

	$extra_attributes = [
		'style' => implode( ';', $inline_styles ),
	];

	wp_enqueue_style( 'pf-blocks-frontend' );

	ob_start();

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div ' . get_block_wrapper_attributes( $extra_attributes ) . '>';

	echo '<p class="pf-nominators-prefix">';
	echo wp_kses_post( $attributes['prefix'] );
	echo '</p>';

	echo '<p class="pf-nominators">';
	echo esc_html( implode( ', ', $nominator_names ) );
	echo '</p>';

	echo '</div>';

	$block = ob_get_clean();

	return $block;
}
