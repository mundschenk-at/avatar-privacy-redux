<?php
/**
 * This file is part of Avatar Privacy.
 *
 * Copyright 2020-2021 Peter Putzer.
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

namespace Avatar_Privacy\Tools\Images;

use Avatar_Privacy\Exceptions\Upload_Handling_Exception;

/**
 * A utility class for handling image files.
 *
 * @internal
 *
 * @since 2.4.0
 *
 * @author Peter Putzer <github@mundschenk.at>
 */
class Image_File {
	const JPEG_IMAGE = 'image/jpeg';
	const PNG_IMAGE  = 'image/png';
	const GIF_IMAGE  = 'image/gif';
	const SVG_IMAGE  = 'image/svg+xml';

	const JPEG_EXTENSION     = 'jpg';
	const JPEG_ALT_EXTENSION = 'jpeg';
	const PNG_EXTENSION      = 'png';
	const GIF_EXTENSION      = 'gif';
	const SVG_EXTENSION      = 'svg';

	const CONTENT_TYPE = [
		self::JPEG_EXTENSION     => self::JPEG_IMAGE,
		self::JPEG_ALT_EXTENSION => self::JPEG_IMAGE,
		self::PNG_EXTENSION      => self::PNG_IMAGE,
		self::SVG_EXTENSION      => self::SVG_IMAGE,
	];

	const FILE_EXTENSION = [
		self::JPEG_IMAGE => self::JPEG_EXTENSION,
		self::PNG_IMAGE  => self::PNG_EXTENSION,
		self::GIF_IMAGE  => self::GIF_EXTENSION,
		self::SVG_IMAGE  => self::SVG_EXTENSION,
	];

	const ALLOWED_UPLOAD_MIME_TYPES = [
		'jpg|jpeg|jpe' => self::JPEG_IMAGE,
		'gif'          => self::GIF_IMAGE,
		'png'          => self::PNG_IMAGE,
	];

	/**
	 * Handles the file upload by optionally switching to the primary site of the network.
	 *
	 * @param  string[] $file      A slice of the $_FILES superglobal.
	 * @param  array    $overrides An associative array of names => values to override
	 *                             default variables. See `wp_handle_uploads` documentation
	 *                             for the full list of available overrides.
	 *
	 * @return string[]            Information about the uploaded file.
	 */
	public function handle_upload( array $file, array $overrides = [] ) {
		// Enable front end support.
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php'; // @codeCoverageIgnore
		}

		// Switch to primary site if this should be a global upload.
		$use_global_upload_dir = $this->is_global_upload( $overrides );
		if ( $use_global_upload_dir ) {
			\switch_to_blog( \get_main_site_id() );
		}

		// Ensure custom upload directory.
		$upload_dir        = $overrides['upload_dir'];
		$upload_dir_filter = function( array $uploads ) use ( $upload_dir ) {
			// @codeCoverageIgnoreStart
			$uploads['path']   = \str_replace( $uploads['subdir'], $upload_dir, $uploads['path'] );
			$uploads['url']    = \str_replace( $uploads['subdir'], $upload_dir, $uploads['url'] );
			$uploads['subdir'] = $upload_dir;

			return $uploads;
			// @codeCoverageIgnoreEnd
		};

		\add_filter( 'upload_dir', $upload_dir_filter );

		// Move uploaded file.
		$result = \wp_handle_upload( $file, $this->prepare_overrides( $overrides ) );

		// Restore standard upload directory.
		\remove_filter( 'upload_dir', $upload_dir_filter );

		// Ensure normalized path on Windows.
		if ( ! empty( $result['file'] ) ) {
			$result['file'] = \wp_normalize_path( $result['file'] );
		}

		// Switch back to current site.
		if ( $use_global_upload_dir ) {
			\restore_current_blog();
		}

		return $result;
	}

	/**
	 * Handles the file upload by optionally switching to the primary site of the network.
	 *
	 * @param  string $image_url The image file to sideload.
	 * @param  array  $overrides An associative array of names => values to override
	 *                           default variables. See `wp_handle_uploads` documentation
	 *                           for the full list of available overrides.
	 *
	 * @return string[]          Information about the sideloaded file.
	 *
	 * @throws Upload_Handling_Exception The method throws a `RuntimeException`
	 *                                   when an error is returned by `::handle_upload()`
	 *                                   or the image file could not be copied.
	 */
	public function handle_sideload( $image_url, array $overrides = [] ) {
		// Enable front end support.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php'; // @codeCoverageIgnore
		}

		// Save the file.
		$temp_file = \wp_tempnam( $image_url );
		if ( ! @\copy( $image_url, $temp_file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- We throw our own exception.
			throw new Upload_Handling_Exception( "Error copying $image_url to $temp_file." );
		}

		// Prepare file data.
		$file_data = [
			'tmp_name' => $temp_file,
			'name'     => $image_url,
		];

		// Optionally override target filename.
		if ( ! empty( $overrides['filename'] ) ) {
			$file_data['name'] = $overrides['filename'];
		}

		// Use a custom action if none is set.
		if ( empty( $overrides['action'] ) ) {
			$overrides['action'] = 'avatar_privacy_sideload';
		}

		// Now, sideload it in.
		$sideloaded = $this->handle_upload( $file_data, $overrides );

		if ( ! empty( $sideloaded['error'] ) ) {
			// Delete temporary file.
			@unlink( $temp_file );

			// Signal error.
			throw new Upload_Handling_Exception( $sideloaded['error'] );
		}

		return $sideloaded;
	}

	/**
	 * Determines if the upload should use the global upload directory.
	 *
	 * @param  string[] $overrides {
	 *     An associative array of names => values to override default variables.
	 *     See `wp_handle_uploads` documentation for the full list of available
	 *     overrides.
	 *
	 *     @type bool $global_upload Whether to use the global uploads directory on multisite.
	 * }
	 *
	 * @return bool
	 */
	protected function is_global_upload( $overrides ) {
		return ( ! empty( $overrides['global_upload'] ) && \is_multisite() );
	}

	/**
	 * Prepares the overrides array for `wp_handle_upload()`.
	 *
	 * @param  string[] $overrides An associative array of names => values to override
	 *                             default variables. See `wp_handle_uploads` documentation
	 *                             for the full list of available overrides.
	 *
	 * @return string[]
	 */
	protected function prepare_overrides( array $overrides ) {
		$defaults = [
			'mimes'     => self::ALLOWED_UPLOAD_MIME_TYPES,
			'action'    => 'avatar_privacy_upload',
			'test_form' => false,
		];

		return \wp_parse_args( $overrides, $defaults );
	}
}
