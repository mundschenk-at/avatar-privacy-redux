<?php
/**
 * This file is part of Avatar Privacy.
 *
 * Copyright 2018 Peter Putzer.
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

namespace Avatar_Privacy\Default_Icons;

use Avatar_Privacy\Data_Storage\Filesystem_Cache;

/**
 * An icon provider for "monsterid" style icons.
 *
 * @since 1.0.0
 *
 * @author Peter Putzer <github@mundschenk.at>
 */
class Monster_ID_Icon_Provider extends Generating_Icon_Provider {

	/**
	 * Creates a new instance.
	 *
	 * @param Generator\Monster_ID $generator   A generator instance.
	 * @param Filesystem_Cache     $file_cache  The file cache handler.
	 */
	public function __construct( Generator\Monster_ID $generator, Filesystem_Cache $file_cache ) {
		parent::__construct( $generator, $file_cache, [ 'monsterid' ] );
	}

	/**
	 * Retrieves the filename (including the sub-directory and file extension).
	 *
	 * @param  string $identity The identity (mail address) hash. Ignored.
	 * @param  int    $size     The requested size in pixels.
	 *
	 * @return string
	 */
	protected function get_filename( $identity, $size ) {
		return "monsterid/{$this->get_sub_dir( $identity )}/{$identity}-{$size}.png";
	}
}
