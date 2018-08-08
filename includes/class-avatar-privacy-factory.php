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

use Dice\Dice;

use Avatar_Privacy\Core;

use Avatar_Privacy\Upload_Handlers\Upload_Handler;

use Avatar_Privacy\Avatar_Handlers\Default_Icons_Handler;
use Avatar_Privacy\Avatar_Handlers\Gravatar_Cache;
use Avatar_Privacy\Avatar_Handlers\User_Avatar_Handler;

use Avatar_Privacy\Components\Avatar_Handling;
use Avatar_Privacy\Components\Comments;
use Avatar_Privacy\Components\Integrations;
use Avatar_Privacy\Components\Privacy_Tools;
use Avatar_Privacy\Components\Settings_Page;
use Avatar_Privacy\Components\Setup;
use Avatar_Privacy\Components\Uninstallation;
use Avatar_Privacy\Components\User_Profile;

use Avatar_Privacy\Data_Storage\Cache;
use Avatar_Privacy\Data_Storage\Filesystem_Cache;
use Avatar_Privacy\Data_Storage\Options;
use Avatar_Privacy\Data_Storage\Network_Options;
use Avatar_Privacy\Data_Storage\Transients;
use Avatar_Privacy\Data_Storage\Site_Transients;

use Avatar_Privacy\Integrations\BBPress_Integration;

use Avatar_Privacy\Tools\Network\Gravatar_Service;

/**
 * A factory for creating Avatar_Privacy instances via dependency injection.
 *
 * @since 1.0.0
 *
 * @author Peter Putzer <github@mundschenk.at>
 */
abstract class Avatar_Privacy_Factory {
	const SHARED = [ 'shared' => true ];

	/**
	 * The factory instance.
	 *
	 * @var Dice
	 */
	private static $factory;

	/**
	 * Retrieves a factory set up for creating Avatar_Privacy instances.
	 *
	 * @param string $full_plugin_path The full path to the main plugin file (i.e. __FILE__).
	 *
	 * @return Dice
	 */
	public static function get( $full_plugin_path ) {
		if ( ! isset( self::$factory ) ) {
			self::$factory = new Dice();

			// Shared helpers.
			self::$factory->addRule( Cache::class, self::SHARED );
			self::$factory->addRule( Transients::class, self::SHARED );
			self::$factory->addRule( Site_Transients::class, self::SHARED );
			self::$factory->addRule( Options::class, self::SHARED );
			self::$factory->addRule( Network_Options::class, self::SHARED );
			self::$factory->addRule( Filesystem_Cache::class, self::SHARED );

			// Load version from plugin data.
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			self::$factory->addRule( Core::class, [
				'constructParams' => [
					$full_plugin_path,
					get_plugin_data( $full_plugin_path, false, false )['Version'],
				],
			] );

			// Additional parameters for components.
			self::$factory->addRule( Avatar_Handling::class, [
				'constructParams' => [ $full_plugin_path ],
			] );
			self::$factory->addRule( Comments::class, [
				'constructParams' => [ $full_plugin_path ],
			] );
			self::$factory->addRule( Privacy_Tools::class, [
				'constructParams' => [ $full_plugin_path ],
			] );
			self::$factory->addRule( Settings_Page::class, [
				'constructParams' => [ $full_plugin_path ],
			] );
			self::$factory->addRule( Setup::class, [
				'constructParams' => [ $full_plugin_path ],
			] );
			self::$factory->addRule( Uninstallation::class, [
				'constructParams' => [ $full_plugin_path ],
			] );
			self::$factory->addRule( User_Profile::class, [
				'constructParams' => [ $full_plugin_path ],
				'shared'          => true,
			] );

			// Avatar handlers.
			self::$factory->addRule( Default_Icons_Handler::class, [
				'constructParams' => [ $full_plugin_path ],
				'shared'          => true,
			] );
			self::$factory->addRule( Gravatar_Cache::class, self::SHARED );
			self::$factory->addRule( User_Avatar_Handler::class, self::SHARED );

			// Upload handlers.
			self::$factory->addRule( Upload_Handler::class, [
				'constructParams' => [ $full_plugin_path ],
				'shared'          => true,
			] );

			// Plugin integrations.
			self::$factory->addRule( BBPress_Integration::class, [
				'constructParams' => [ $full_plugin_path ],
				'shared'          => true,
			] );
			self::$factory->addRule( Integrations::class, [
				'constructParams' => [
					[
						self::$factory->create( BBPress_Integration::class ),
					],
				],
			] );

			// Tools.
			self::$factory->addRule( Gravatar_Service::class, self::SHARED );
		}

		return self::$factory;
	}
}
