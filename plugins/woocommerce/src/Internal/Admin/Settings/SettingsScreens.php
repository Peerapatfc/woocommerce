<?php
/**
 * Registry of experimental settings screens.
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Collects the settings screens that extensions register, grouped by area.
 *
 * A screen ID only has to be unique within its area, like a section within a classic settings tab.
 *
 * @internal
 */
final class SettingsScreens {

	/**
	 * Areas that screens can be registered in.
	 */
	private const AREAS = array( 'payments' );

	/**
	 * Get the valid registered screens, keyed by area and then by screen ID.
	 *
	 * @since 11.3.0
	 *
	 * @return array<string, array<string, array{area: string, id: string, title: string, rest_path: string, scripts: string[]}>>
	 */
	public function get_screens(): array {
		/**
		 * Filters the settings screens. Experimental: the format can change in any release.
		 *
		 * Screens are keyed by area (such as `payments`) and then by screen ID. Each screen has a
		 * `title`, the `rest_path` of its settings entity, and optional `scripts` (script handles to
		 * load on the screen).
		 *
		 * @since 11.3.0
		 *
		 * @param array $screens The registered screens.
		 */
		$screens = apply_filters( '__experimental_woocommerce_settings_screens', array() );
		if ( ! is_array( $screens ) ) {
			return array();
		}

		$valid = array();
		foreach ( self::AREAS as $area ) {
			if ( ! isset( $screens[ $area ] ) || ! is_array( $screens[ $area ] ) ) {
				continue;
			}
			foreach ( $screens[ $area ] as $id => $screen ) {
				$normalized = self::normalize_screen( $area, $id, $screen );
				if ( null !== $normalized ) {
					$valid[ $area ][ $normalized['id'] ] = $normalized;
				}
			}
		}

		return $valid;
	}

	/**
	 * Get one registered screen.
	 *
	 * @since 11.3.0
	 *
	 * @param string $area The area.
	 * @param string $id   The screen ID.
	 * @return array{area: string, id: string, title: string, rest_path: string, scripts: string[]}|null
	 */
	public function get_screen( string $area, string $id ): ?array {
		return $this->get_screens()[ $area ][ $id ] ?? null;
	}

	/**
	 * Get the core-data entity kind for an area's screens. The entity name is the screen ID.
	 *
	 * @since 11.3.0
	 *
	 * @param string $area The area.
	 * @return string
	 */
	public static function get_entity_kind( string $area ): string {
		return 'woo_settings_' . $area;
	}

	/**
	 * Validate a registered screen, or return null when it can't be used.
	 *
	 * @param string $area   The area.
	 * @param mixed  $id     The screen ID.
	 * @param mixed  $screen The screen arguments.
	 * @return array{area: string, id: string, title: string, rest_path: string, scripts: string[]}|null
	 */
	private static function normalize_screen( string $area, $id, $screen ): ?array {
		if ( ! is_string( $id ) || sanitize_key( $id ) !== $id || '' === $id || ! is_array( $screen ) ) {
			return null;
		}

		$title     = $screen['title'] ?? null;
		$rest_path = $screen['rest_path'] ?? null;
		if ( ! is_string( $title ) || '' === $title || ! is_string( $rest_path ) || 0 !== strpos( $rest_path, '/' ) ) {
			return null;
		}

		$scripts = $screen['scripts'] ?? array();
		$scripts = is_array( $scripts ) ? array_values( array_filter( $scripts, 'is_string' ) ) : array();

		return array(
			'area'      => $area,
			'id'        => $id,
			'title'     => $title,
			'rest_path' => $rest_path,
			'scripts'   => $scripts,
		);
	}
}
