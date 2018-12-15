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

namespace Avatar_Privacy\Data_Storage;

/**
 * A plugin-specific network options handler.
 *
 * @since 1.0.0
 *
 * @author Peter Putzer <github@mundschenk.at>
 */
class Network_Options extends \Mundschenk\Data_Storage\Network_Options {
	const PREFIX = 'avatar_privacy_';

	/**
	 * The network option key (without the prefix) for using a global table in
	 * multisite installations.
	 *
	 * @var string
	 */
	const USE_GLOBAL_TABLE = 'use_global_table';

	/**
	 * The network option key (without the prefix) for the button to migrate from
	 * global table usage in  multisite installations.
	 *
	 * @since 2.1.0
	 *
	 * @var string
	 */
	const MIGRATE_FROM_GLOBAL_TABLE = 'migrate_from_global_table';

	/**
	 * The network option key (without the prefix) for storing the network-wide salt.
	 *
	 * @var string
	 */
	const SALT = 'salt';

	/**
	 * Creates a new instance.
	 */
	public function __construct() {
		parent::__construct( self::PREFIX );
	}

	/**
	 * Removes the prefix from an option name.
	 *
	 * @since 2.1.0
	 *
	 * @param  string $name The option name including the prefix.
	 *
	 * @return string       The option name without the prefix, or '' if an invalid name was given.
	 */
	public function remove_prefix( $name ) {
		$parts = \explode( self::PREFIX, $name, 2 );
		if ( '' === $parts[0] ) {
			return $parts[1];
		}

		return '';
	}
}
