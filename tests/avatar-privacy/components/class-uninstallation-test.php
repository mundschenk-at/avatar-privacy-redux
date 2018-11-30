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
 * @package mundschenk-at/avatar-privacy/tests
 * @license http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Avatar_Privacy\Tests\Avatar_Privacy\Components;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

use Mockery as m;

use org\bovigo\vfs\vfsStream;

use Avatar_Privacy\Components\Uninstallation;

use Avatar_Privacy\Core;

use Avatar_Privacy\Components\Image_Proxy;

use Avatar_Privacy\Data_Storage\Database;
use Avatar_Privacy\Data_Storage\Filesystem_Cache;
use Avatar_Privacy\Data_Storage\Network_Options;
use Avatar_Privacy\Data_Storage\Options;
use Avatar_Privacy\Data_Storage\Site_Transients;
use Avatar_Privacy\Data_Storage\Transients;

use Avatar_Privacy\Upload_Handlers\User_Avatar_Upload_Handler;

/**
 * Avatar_Privacy\Components\Uninstallation unit test.
 *
 * @coversDefaultClass \Avatar_Privacy\Components\Uninstallation
 * @usesDefaultClass \Avatar_Privacy\Components\Uninstallation
 *
 * @uses ::__construct
 */
class Uninstallation_Test extends \Avatar_Privacy\Tests\TestCase {

	/**
	 * The system-under-test.
	 *
	 * @var Uninstallation
	 */
	private $sut;

	/**
	 * The options handler.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * The options handler.
	 *
	 * @var Network_Options
	 */
	private $network_options;

	/**
	 * The transients handler.
	 *
	 * @var Transients
	 */
	private $transients;

	/**
	 * The site transients handler.
	 *
	 * @var Site_Transients
	 */
	private $site_transients;

	/**
	 * The database handler.
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * The filesystem cache handler.
	 *
	 * @var Filesystem_Cache
	 */
	private $file_cache;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		parent::setUp();

		$filesystem = [
			'uploads'    => [
				'avatar-privacy' => [
					'cache'       => [],
					'user-avatar' => [
						'foo.png' => 'FAKE_PNG',
					],
				],
			],
		];

		// Set up virtual filesystem.
		$root = vfsStream::setup( 'root', null, $filesystem );
		set_include_path( 'vfs://root/' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_include_path

		// Helper mocks.
		$this->options         = m::mock( Options::class );
		$this->network_options = m::mock( Network_Options::class );
		$this->transients      = m::mock( Transients::class );
		$this->site_transients = m::mock( Site_Transients::class );
		$this->database        = m::mock( Database::class );
		$this->file_cache      = m::mock( Filesystem_Cache::class );

		$this->sut = m::mock( Uninstallation::class, [ 'plugin/file', $this->options, $this->network_options, $this->transients, $this->site_transients, $this->database, $this->file_cache ] )->makePartial()->shouldAllowMockingProtectedMethods();
	}

	/**
	 * Tests ::__construct.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor() {
		$mock = m::mock( Uninstallation::class )->makePartial();

		$mock->__construct( 'path/file', $this->options, $this->network_options, $this->transients, $this->site_transients, $this->database, $this->file_cache );

		$this->assertAttributeSame( 'path/file', 'plugin_file', $mock );
		$this->assertAttributeSame( $this->options, 'options', $mock );
		$this->assertAttributeSame( $this->network_options, 'network_options', $mock );
		$this->assertAttributeSame( $this->transients, 'transients', $mock );
		$this->assertAttributeSame( $this->site_transients, 'site_transients', $mock );
		$this->assertAttributeSame( $this->database, 'database', $mock );
		$this->assertAttributeSame( $this->file_cache, 'file_cache', $mock );
	}

	/**
	 * Tests ::run.
	 *
	 * @covers ::run
	 */
	public function test_run() {
		$this->sut->shouldReceive( 'delete_cached_files' )->once();
		$this->sut->shouldReceive( 'delete_uploaded_avatars' )->once();
		$this->sut->shouldReceive( 'delete_user_meta' )->once();
		$this->sut->shouldReceive( 'delete_options' )->once();
		$this->sut->shouldReceive( 'delete_transients' )->once();
		$this->sut->shouldReceive( 'drop_all_tables' )->once();

		$this->assertNull( $this->sut->run() );
	}

	/**
	 * Tests ::delete_uploaded_avatars.
	 *
	 * @covers ::delete_uploaded_avatars
	 */
	public function test_delete_uploaded_avatars() {
		$user_avatar        = User_Avatar_Upload_Handler::USER_META_KEY;
		$query              = [
			'meta_key'     => $user_avatar,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_compare' => 'EXISTS',
		];
		$user               = m::mock( 'WP_User' );
		$user->$user_avatar = [
			'file' => vfsStream::url( 'root/uploads/avatar-privacy/user-avatars/foo.png' ),
		];

		Functions\expect( 'get_users' )->once()->with( $query )->andReturn( [ $user ] );

		$this->assertNull( $this->sut->delete_uploaded_avatars() );
	}

	/**
	 * Tests ::delete_cached_files.
	 *
	 * @covers ::delete_cached_files
	 */
	public function test_delete_cached_files() {
		$this->file_cache->shouldReceive( 'invalidate' )->once();

		$this->assertNull( $this->sut->delete_cached_files() );
	}

	/**
	 * Tests ::drop_all_tables.
	 *
	 * @covers ::drop_all_tables
	 */
	public function test_drop_all_tables() {
		Functions\expect( 'is_multisite' )->once()->andReturn( false );

		$this->database->shouldReceive( 'drop_table' )->once()->withNoArgs();

		$this->assertNull( $this->sut->drop_all_tables() );
	}

	/**
	 * Tests ::drop_all_tables.
	 *
	 * @covers ::drop_all_tables
	 */
	public function test_drop_all_tables_multisite() {
		$site_ids   = [ 1, 2, 10 ];
		$site_count = \count( $site_ids );

		Functions\expect( 'is_multisite' )->once()->andReturn( true );
		Functions\expect( 'get_sites' )->once()->with( [ 'fields' => 'ids' ] )->andReturn( $site_ids );
		$this->database->shouldReceive( 'drop_table' )->times( $site_count )->with( m::type( 'int' ) );

		$this->assertNull( $this->sut->drop_all_tables() );
	}

	/**
	 * Tests ::delete_user_meta.
	 *
	 * @covers ::delete_user_meta
	 */
	public function test_delete_user_meta() {
		Functions\expect( 'delete_metadata' )->once()->with( 'user', 0, Core::GRAVATAR_USE_META_KEY, null, true );
		Functions\expect( 'delete_metadata' )->once()->with( 'user', 0, Core::ALLOW_ANONYMOUS_META_KEY, null, true );
		Functions\expect( 'delete_metadata' )->once()->with( 'user', 0, User_Avatar_Upload_Handler::USER_META_KEY, null, true );

		$this->assertNull( $this->sut->delete_user_meta() );
	}

	/**
	 * Tests ::delete_options.
	 *
	 * @covers ::delete_options
	 */
	public function test_delete_options() {
		$this->options->shouldReceive( 'delete' )->once()->with( Core::SETTINGS_NAME );
		$this->options->shouldReceive( 'reset_avatar_default' )->once();

		Functions\expect( 'is_multisite' )->once()->andReturn( false );

		$this->network_options->shouldReceive( 'delete' )->once()->with( Network_Options::USE_GLOBAL_TABLE );

		$this->assertNull( $this->sut->delete_options() );
	}

	/**
	 * Tests ::delete_options.
	 *
	 * @covers ::delete_options
	 */
	public function test_delete_options_multisite() {
		$site_ids   = [ 1, 2, 3 ];
		$site_count = \count( $site_ids );

		Functions\expect( 'is_multisite' )->once()->andReturn( true );
		Functions\expect( 'get_sites' )->once()->with( [ 'fields' => 'ids' ] )->andReturn( $site_ids );
		Functions\expect( 'switch_to_blog' )->times( $site_count )->with( m::type( 'int' ) );
		Functions\expect( 'restore_current_blog' )->times( $site_count );

		// FIXME: The main site is included!
		$this->options->shouldReceive( 'delete' )->times( $site_count + 1 )->with( Core::SETTINGS_NAME );
		$this->options->shouldReceive( 'reset_avatar_default' )->times( $site_count + 1 );
		$this->network_options->shouldReceive( 'delete' )->once()->with( Network_Options::USE_GLOBAL_TABLE );

		$this->assertNull( $this->sut->delete_options() );
	}

	/**
	 * Tests ::delete_transients.
	 *
	 * @covers ::delete_transients
	 */
	public function test_delete_transients() {
		$key1      = 'foo';
		$key2      = 'bar';
		$key3      = 'acme';
		$site_key1 = 'foobar';
		$site_key2 = 'barfoo';

		$this->transients->shouldReceive( 'get_keys_from_database' )->once()->andReturn( [ $key1, $key2, $key3 ] );
		$this->transients->shouldReceive( 'delete' )->once()->with( $key1, true );
		$this->transients->shouldReceive( 'delete' )->once()->with( $key2, true );
		$this->transients->shouldReceive( 'delete' )->once()->with( $key3, true );

		$this->site_transients->shouldReceive( 'get_keys_from_database' )->once()->andReturn( [ $site_key1, $site_key2 ] );
		$this->site_transients->shouldReceive( 'delete' )->once()->with( $site_key1, true );
		$this->site_transients->shouldReceive( 'delete' )->once()->with( $site_key2, true );

		$this->assertNull( $this->sut->delete_transients() );
	}
}
