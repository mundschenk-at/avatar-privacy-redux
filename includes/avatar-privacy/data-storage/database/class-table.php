<?php
/**
 * This file is part of Avatar Privacy.
 *
 * Copyright 2018-2020 Peter Putzer.
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

namespace Avatar_Privacy\Data_Storage\Database;

/**
 * A plugin-specific database handler.
 *
 * @since 2.1.0
 * @since 2.4.0 Renamed to Avatar_Privacy\Data_Storage\Database\Table and
 *              refactored as abstract base class.
 *
 * @author Peter Putzer <github@mundschenk.at>
 */
abstract class Table {

	/**
	 * The basename (without site prefix) of the table.
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	private $table_basename;

	/**
	 * The minimum version number for which the table does not need to be updated.
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	private $update_threshold;

	/**
	 * A column/field to placeholder mapping.
	 *
	 * @since 2.3.0
	 *
	 * @var string[]
	 */
	private $column_formats;

	/**
	 * Creates a new instance.
	 *
	 * @since 2.3.0 Parameter $core added.
	 * @since 2.4.0 Parameters replaced with $table_basename, $update_threshold,
	 *              and $column_formats.
	 *
	 * @param string   $table_basename   The basename (without site prefix) of the table.
	 * @param string   $update_threshold The minimum version number for which the table does not need to be updated.
	 * @param string[] $column_formats   A mapping from column to placeholder characters.
	 */
	public function __construct( $table_basename, $update_threshold, $column_formats ) {
		$this->table_basename   = $table_basename;
		$this->update_threshold = $update_threshold;
		$this->column_formats   = $column_formats;
	}

	/**
	 * Sets up the table, including necessary data upgrades. The method is called
	 * on every page load.
	 *
	 * @since 2.4.0
	 *
	 * @param string $previous_version The previously installed plugin version.
	 */
	public function setup( $previous_version ) {
		if ( $this->maybe_create_table( $previous_version ) ) {
			// We may need to update the contents as well.
			$this->maybe_upgrade_data( $previous_version );
		}
	}

	/**
	 * Retrieves the table prefix to use (for a given site or the current site).
	 *
	 * @global \wpdb    $wpdb    The WordPress Database Access Abstraction.
	 *
	 * @param  int|null $site_id Optional. The site ID. Null means the current $blog_id. Default null.
	 *
	 * @return string
	 */
	protected function get_table_prefix( $site_id = null ) {
		global $wpdb;

		if ( ! $this->use_global_table() ) {
			return $wpdb->get_blog_prefix( $site_id );
		} else {
			return $wpdb->base_prefix;
		}
	}

	/**
	 * Retrieves the table name to use (for a given site or the current site).
	 *
	 * @since 2.3.0 Visibility changed to public.
	 *
	 * @param int|null $site_id Optional. The site ID. Null means the current $blog_id. Default null.
	 *
	 * @return string
	 */
	public function get_table_name( $site_id = null ) {
		return $this->get_table_prefix( $site_id ) . $this->table_basename;
	}

	/**
	 * Checks if the given table exists.
	 *
	 * @since 2.3.0 Visibility changed to public.
	 *
	 * @param  string $table_name A table name.
	 *
	 * @return bool
	 */
	public function table_exists( $table_name ) {
		global $wpdb;

		return $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // WPCS: db call ok, cache ok.
	}

	/**
	 * Determines whether this (multisite) installation uses the global table.
	 * Result is ignored for single-site installations.
	 *
	 * @since 2.3.0 Visibility changed to public.
	 * @since 2.4.0 Made abstract.
	 *
	 * @return bool
	 */
	abstract public function use_global_table();

	/**
	 * Creates the plugin's database table if it doesn't already exist. The
	 * table may be created as a global table for legacy multisite installations.
	 * Makes the name of the table available through $wpdb->avatar_privacy.
	 *
	 * @global \wpdb $wpdb             The WordPress Database Access Abstraction.
	 *
	 * @param string $previous_version The previously installed plugin version.
	 *
	 * @return bool                    Returns true if the table was created/updated.
	 */
	public function maybe_create_table( $previous_version ) {
		global $wpdb;

		// Force DB update?
		$db_needs_update = \version_compare( $previous_version, $this->update_threshold, '<' );

		// Check if the table exists.
		if ( ! $db_needs_update && \property_exists( $wpdb, $this->table_basename ) ) {
			return false;
		}

		// Set up table name.
		$table_name = $this->get_table_name();

		// Fix $wpdb object if table already exists, unless we need an update.
		if ( ! $db_needs_update && $this->table_exists( $table_name ) ) {
			$this->register_table( $wpdb, $table_name );
			return false;
		}

		// Create the table.
		$this->db_delta( $this->get_table_definition( $table_name ) . " {$wpdb->get_charset_collate()};" );

		if ( $this->table_exists( $table_name ) ) {
			$this->register_table( $wpdb, $table_name );
			return true;
		}

		// Should not ever happen.
		return false;
	}

	/**
	 * Retrieves the CREATE TABLE definition formatted for use by \db_delta(),
	 * without the charset collate clause.
	 *
	 * Example:
	 * `CREATE TABLE some_table (
	 *  id mediumint(9) NOT NULL AUTO_INCREMENT,
	 *  some_column varchar(100) NOT NULL,
	 *  PRIMARY KEY (id)
	 * )`
	 *
	 * @since 2.4.0
	 *
	 * @param string $table_name The table name including any prefixes.
	 *
	 * @return string
	 */
	abstract protected function get_table_definition( $table_name );

	/**
	 * Registers the table with the given \wpdb instance.
	 *
	 * @param  \wpdb  $db         The database instance.
	 * @param  string $table_name The table name (with prefix).
	 */
	protected function register_table( \wpdb $db, $table_name ) {
		$basename = $this->table_basename;

		// Make sure that $wpdb knows about our table.
		if ( \is_multisite() && $this->use_global_table() ) {
			$db->ms_global_tables[] = $basename;
		} else {
			$db->tables[] = $basename;
		}

		// Also register the "shortcut" property.
		$db->$basename = $table_name;
	}

	/**
	 * Applies the `dbDelta` function to the given queries.
	 *
	 * @param  string|string[] $queries The query to run. Can be multiple queries in an array, or a string of queries separated by semicolons.
	 * @param  bool            $execute Optional. Whether or not to execute the query right away. Default `true`.
	 *
	 * @return string[]                           Strings containing the results of the various update queries.
	 */
	protected function db_delta( $queries, $execute = true ) {
		if ( ! function_exists( 'dbDelta' ) ) {
			// Load upgrade.php for the dbDelta function.
			require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		return \dbDelta( $queries, $execute );
	}

	/**
	 * Drops the table for the given site.
	 *
	 * @global \wpdb $wpdb The WordPress Database Access Abstraction.
	 *
	 * @param int|null $site_id Optional. The site ID. Null means the current $blog_id. Ddefault null.
	 */
	public function drop_table( $site_id = null ) {
		global $wpdb;

		$table_name = $this->get_table_name( $site_id );
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name};" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Retrieves the correct format strings for the given columns.
	 *
	 * @since 2.4.0 Moved from Avatar_Privacy\Core\Comment_Author_Fields and renamed to get_format.
	 *
	 * @param  array $columns An array of values index by column name.
	 *
	 * @return string[]
	 *
	 * @throws \RuntimeException A \RuntimeException is raised when invalid column names are used.
	 */
	protected function get_format( array $columns ) {
		$format_strings = [];

		foreach ( $columns as $key => $value ) {
			if ( ! empty( $this->column_formats[ $key ] ) ) {
				$format_strings[] = $this->column_formats[ $key ];
			} else {
				throw new \RuntimeException( "Invalid column name '{$key}'." );
			}
		}

		return $format_strings;
	}


	/**
	 * Sometimes, the table data needs to updated when upgrading.
	 *
	 * The table itself is already guarantueed to exist.
	 *
	 * @param string $previous_version The previously installed plugin version.
	 *
	 * @return int                     The number of upgraded rows.
	 */
	abstract public function maybe_upgrade_data( $previous_version );
}
