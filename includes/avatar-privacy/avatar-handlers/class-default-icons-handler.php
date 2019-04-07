<?php
/**
 * This file is part of Avatar Privacy.
 *
 * Copyright 2018-2019 Peter Putzer.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 *  ***
 *
 * @package mundschenk-at/avatar-privacy
 * @license http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Avatar_Privacy\Avatar_Handlers;

use Avatar_Privacy\Data_Storage\Filesystem_Cache;

use Avatar_Privacy\Avatar_Handlers\Default_Icons\Icon_Provider;


/**
 * Handles image caching for default icons.
 *
 * @since 2.0.0
 *
 * @author Peter Putzer <github@mundschenk.at>
 */
class Default_Icons_Handler implements Avatar_Handler {

	/**
	 * The filesystem cache handler.
	 *
	 * @var Filesystem_Cache
	 */
	private $file_cache;

	/**
	 * A list of icon providers.
	 *
	 * @var Icon_Provider[]
	 */
	private $icon_providers = [];

	/**
	 * The mapping of icon types to providers.
	 *
	 * @var Icon_Provider[]
	 */
	private $icon_provider_mapping = [];

	/**
	 * Creates a new instance.
	 *
	 * @since 2.1.0 Parameter $plugin_file removed.
	 *
	 * @param Filesystem_Cache $file_cache     The file cache handler.
	 * @param Icon_Provider[]  $icon_providers An array of icon providers.
	 */
	public function __construct( Filesystem_Cache $file_cache, array $icon_providers ) {
		$this->file_cache     = $file_cache;
		$this->icon_providers = $icon_providers;
	}

	/**
	 * Returns a mapping from icon types to specific providers.
	 *
	 * @since 2.1.0 Visibility changed to protected.
	 *
	 * @return Icon_Provider[]
	 */
	protected function get_provider_mapping() {
		if ( empty( $this->icon_provider_mapping ) ) {
			foreach ( $this->icon_providers as $provider ) {
				foreach ( $provider->get_provided_types() as $type ) {
					$this->icon_provider_mapping[ $type ] = $provider;
				}
			}
		}

		return $this->icon_provider_mapping;
	}

	/**
	 * Retrieves the URL for the given default icon type.
	 *
	 * @param  string $url  The fallback image URL.
	 * @param  string $hash The hashed mail address.
	 * @param  int    $size The size of the avatar image in pixels.
	 * @param  array  $args {
	 *     An array of arguments.
	 *
	 *     @type string $type     The avatar/icon type.
	 *     @type string $avatar   The full-size avatar image path.
	 *     @type string $mimetype The expected MIME type of the avatar image.
	 *     @type bool   $force    Optional. Whether to force the regeneration of the image file. Default false.
	 * }
	 *
	 * @return string
	 */
	public function get_url( $url, $hash, $size, array $args ) {
		$args = \wp_parse_args( $args, [ 'default' => '' ] );

		$providers = $this->get_provider_mapping();
		if ( ! empty( $providers[ $args['default'] ] ) ) {
			return $providers[ $args['default'] ]->get_icon_url( $hash, $size );
		}

		// Return the fallback default icon URL.
		return $url;
	}

	/**
	 * Caches the image specified by the parameters.
	 *
	 * @param  string $type      The image (sub-)type.
	 * @param  string $hash      The hashed mail address.
	 * @param  int    $size      The requested size in pixels.
	 * @param  string $subdir    The requested sub-directory.
	 * @param  string $extension The requested file extension.
	 *
	 * @return bool              Returns `true` if successful, `false` otherwise.
	 */
	public function cache_image( $type, $hash, $size, $subdir, $extension ) {
		return ! empty( $this->get_url( '', $hash, $size, [ 'default' => $type ] ) );
	}

	/**
	 * Adds new images to the list of default avatar images.
	 *
	 * @param  string[] $avatar_defaults The list of default avatar images.
	 *
	 * @return string[] The modified default avatar array.
	 */
	public function avatar_defaults( $avatar_defaults ) {
		// Remove Gravatar logo.
		unset( $avatar_defaults['gravatar_default'] );

		// Add non-default icons.
		foreach ( $this->icon_providers as $provider ) {
			$type = $provider->get_option_value();
			if ( ! isset( $avatar_defaults[ $type ] ) ) {
				$avatar_defaults[ $type ] = $provider->get_name();
			}
		}

		return $avatar_defaults;
	}
}
