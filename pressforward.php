<?php
/*
Plugin Name: PressForward
Plugin URI: http://pressforward.org/
Description: The PressForward Plugin is a tool by the Roy Rosenzweig Center for History and New Media for aggregating and curating web-based content within the WordPress dashboard.
Version: 3.7.0
GitHub Plugin URI: https://github.com/PressForward/pressforward
Author: Aram Zucker-Scharff, Boone B Gorges, Jeremy Boggs
Author URI: http://pressforward.org/about/team/
License: GPL2
*/

/*  Developed for the Roy Rosenzweig Center for History and New Media

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//Set up some constants
define( 'PF_SLUG', 'pf' );
define( 'PF_TITLE', 'PressForward' );
define( 'PF_MENU_SLUG', PF_SLUG . '-menu' );
define( 'PF_NOM_EDITOR', 'edit.php?post_type=nomination' );
define( 'PF_NOM_POSTER', 'post-new.php?post_type=nomination' );
define( 'PF_ROOT', dirname(__FILE__) );
define( 'PF_FILE_PATH', PF_ROOT . '/' . basename(__FILE__) );
define( 'PF_URL', plugins_url('/', __FILE__) );
define( 'PF_VERSION', '3.6' );

class PressForward {
	var $modules = array();

	var $schema;
	var $admin;
	var $nominations;
	var $pf_feed_items;
	var $pf_feeds;
	var $pf_retrieve;
	var $opml_reader;
	var $og_reader;
	var $readability;
	var $relationships;
	var $subscribed_feeds;

	public static function init() {
		static $instance;

		if ( ! is_a( $instance, 'PressForward' ) ) {
			$instance = new self();
		}

		return $instance;
	}


	/**
	 * Construct function.
	 *
	 * See http://php.net/manual/en/language.oop5.decon.php to get a better understanding of what's going on here.
	 *
	 * @since 1.7
	 *
	 */
	private function __construct() {

		$this->includes();

		$this->set_up_opml_reader();
		$this->set_up_og_reader();
		$this->set_up_readability();

		$this->set_up_feeds();
		$this->set_up_feed_items();
		$this->set_up_schema();
		$this->set_up_relationships();
		$this->set_up_feed_retrieve();
		$this->set_up_nominations();
		$this->set_up_folders();
		$this->set_up_form_of();
		$this->set_up_admin();
		$this->set_up_tools();
		$this->set_up_add_feeds();
		$this->set_up_subscribed_feeds();

		add_action( 'plugins_loaded', array( $this, 'pressforward_init' ) );

		add_action( 'pressforward_init', array( $this, 'setup_modules' ), 1000 );

		add_action( 'wp', array( $this, 'check_installed' ), 0 );

		load_plugin_textdomain( 'pf', false, PF_ROOT . '/languages' );
	}

	/**
	 * Include necessary files.
	 *
	 * @since 1.7
	 */
	function includes() {

		// External libraries

		// Pull and parse Open Graph data from a page.
		require_once( PF_ROOT . "/lib/PF_OpenGraph.php" );

		// Check the HTML of each item for open tags and close them.
		// I've altered it specifically for some odd HTML artifacts that occur when
		// WP sanitizes the content input.
		require_once( PF_ROOT . "/lib/pf_htmlchecker.php" );

		// A slightly altered version of the Readability library from Five Filters,
		// who based it off readability.com's code.
		require_once( PF_ROOT . "/lib/fivefilters-readability/Readability.php" );

		// For reading through an HTML page.
		require_once( PF_ROOT . "/lib/pf_simple_html_dom.php" );
		#$dom = new pf_simple_html_dom;

		// Internal tools
		require_once( PF_ROOT . '/includes/opml/maker.php' );
		require_once( PF_ROOT . '/includes/opml/object.php' );
		require_once( PF_ROOT . '/includes/opml/reader.php' );
		require_once(PF_ROOT . "/includes/class-pf_metas.php");
		require_once(PF_ROOT . "/includes/class-PF_Add_Feeds.php");

		// Load the module base class and our test module
		require_once( PF_ROOT . "/includes/functions.php" );
		require_once( PF_ROOT . "/includes/module-base.php" );
		require_once( PF_ROOT . '/includes/schema.php' );
		require_once( PF_ROOT . '/includes/readable.php' );
		require_once( PF_ROOT . '/includes/feed-items.php' );
		require_once( PF_ROOT . '/includes/feeds.php' );
		require_once( PF_ROOT . '/includes/class-PF_Subscribed_Feeds.php' );
		require_once( PF_ROOT . '/includes/class-pf_folders.php' );
		require_once( PF_ROOT . '/includes/slurp.php' );
		require_once( PF_ROOT . '/includes/relationships.php' );
		require_once( PF_ROOT . '/includes/nominations.php' );
		require_once( PF_ROOT . '/includes/internal-templates.php' );
		require_once( PF_ROOT . '/includes/admin.php' );
		require_once( PF_ROOT . '/includes/tools.php' );
    	require_once( PF_ROOT . '/includes/template-tags.php' );
    	require_once( PF_ROOT . '/includes/alert-box/alert-box.php' );
		require_once( PF_ROOT . '/lib/urlresolver/URLResolver.php' );

	}

	/**
	 * Sets up the OPML Reader
	 *
	 * @since 3.0
	 */
	function set_up_opml_reader() {
		if ( empty( $this->opml_reader ) ) {
			$this->opml_reader = new OPML_reader;
		}
	}

	/**
	 * Sets up the OG Reader
	 *
	 * @since 3.0
	 */
	function set_up_og_reader() {
		if ( empty( $this->og_reader ) ) {
			$this->og_reader = new PF_OpenGraph;
		}
	}

	/**
	 * Sets up the Readability Object
	 *
	 * @since 3.0
	 */
	function set_up_readability() {
		if ( empty( $this->readability ) ) {
			$this->readability = new PF_Readability;
		}
	}

	/**
	 * Sets up the schema for feed items
	 *
	 * @since 1.7
	 */
	function set_up_schema() {
		if ( empty( $this->schema ) ) {
			$this->schema = new PF_Feed_Item_Schema;
		}
	}

	/**
	 * Sets up the Relationships schema
	 *
	 * @since 3.0
	 */
	function set_up_relationships() {
		if ( empty( $this->relationships ) ) {
			$this->relationships = new PF_RSS_Import_Relationship;
		}
	}

	/**
	 * Sets up the Feeds functionality
	 *
	 * @since 2.2
	 */
	function set_up_feed_items() {
		if ( empty( $this->pf_feed_items ) ) {
			$this->pf_feed_items = new PF_Feed_Item;
		}
	}

	/**
	 * Sets up the Feeds functionality
	 *
	 * @since 2.2
	 */
	function set_up_feeds() {
		if ( empty( $this->pf_feeds ) ) {
			$this->pf_feeds = PF_Feeds_Schema::init();
		}
	}

	/**
	 * Sets up the Retrieval functionality
	 *
	 * @since 2.2
	 */
	function set_up_feed_retrieve() {
		if ( empty( $this->pf_retrieve ) ) {
			$this->pf_retrieve = new PF_Feed_Retrieve;
		}
	}

	/**
	 * Sets up the Nominations admin
	 *
	 * @since 1.7
	 */
	function set_up_nominations() {
		if ( empty( $this->nominations ) ) {
			$this->nominations = new PF_Nominations;
		}
	}

	/**
	 * Sets up the Folders
	 *
	 * @since 1.7
	 */
	function set_up_folders() {
		if ( empty( $this->pf_folders ) ) {
			$this->pf_folders = PF_Folders::init();
		}
	}

	/**
	 * Sets up the Dashboard admin parts
	 *
	 * @since 1.7
	 */
	function set_up_form_of() {
		if ( empty( $this->form_of ) ) {
			$this->form_of = new PF_Form_Of;
		}
	}

	/**
	 * Sets up the Dashboard admin
	 *
	 * @since 1.7
	 */
	function set_up_admin() {
		if ( empty( $this->admin ) ) {
			$this->admin = new PF_Admin;
		}
	}

	/**
	 * Sets up the Tools admin
	 *
	 * @since 3.5
	 */
	function set_up_tools() {
		if ( empty( $this->tools ) ) {
			$this->tools = PF_Tools::init();
		}
	}

	/**
	 * Sets up the Tools admin
	 *
	 * @since 3.6
	 */
	function set_up_add_feeds() {
		if ( empty( $this->add_feeds ) ) {
			$this->add_feeds = PF_Add_Feeds::init();
		}
	}

	/**
	 * Sets up the Subscribed Feeds admin
	 *
	 * @since 3.7
	 */
	function set_up_subscribed_feeds() {
		if ( empty( $this->subscribed_feeds ) ) {
			$this->subscribed_feeds = PF_Subscribed_Feeds::init();
		}
	}

	/**
	 * Fire the pressforward_init action, to let plugins know that our
	 * libraries are available
	 */
	function pressforward_init() {
		do_action( 'pressforward_init' );
	}

	/**
	 * Locate and load modules
	 *
	 * This method supports loading our packaged modules, as well as those
	 * provided by plugins
	 */
	function setup_modules() {

		$module_args = array();

		// Scrape the built-in modules
		$module_dirs = scandir( PF_ROOT . '/modules/' );
		foreach ( $module_dirs as $module_dir ) {
			// Skip hidden items
			if ( '.' == substr( $module_dir, 0, 1 ) ) {
				continue;
			}

			if ( file_exists( PF_ROOT . "/modules/{$module_dir}/{$module_dir}.php" ) ) {
				include_once( PF_ROOT . "/modules/{$module_dir}/{$module_dir}.php" );

				// Prepare the class name
				$tmp = explode( '-', $module_dir );
				$tmp = array_map( 'ucwords', $tmp );
				$class_name = 'PF_' . implode( '_', $tmp );

				$module_args[] = array(
					'slug' => $module_dir,
					'class' => $class_name
				);
			}
		}

		// Plugins should not filter this array directly. Use
		// pressforward_register_module() instead
		$plugin_module_args = apply_filters( 'pressforward_register_modules', array() );

		$module_args = array_merge( $module_args, $plugin_module_args );
		foreach ( $module_args as $module ) {
			$this->modules[ $module['slug'] ] = new $module['class'];
		}

		do_action( 'pf_setup_modules', $this );
	}

	/**
	 * Get the feed item post type
	 *
	 * @since 1.7
	 *
	 * @return string
	 */
	public function get_feed_item_post_type() {
		if ( isset( $this->schema ) ) {
			return $this->schema->feed_item_post_type;
		}

		return '';
	}

	/**
	 * Get the feed post type
	 *
	 * @since 1.7
	 *
	 * @return string
	 */
	public function get_feed_post_type() {
		if ( isset( $this->pf_feeds ) ) {
			return $this->pf_feeds->post_type;
		}

		return '';
	}

	/**
	 * Get the feed item tag taxonomy
	 *
	 * @since 1.7
	 *
	 * @return string
	 */
	public function get_feed_item_tag_taxonomy() {
		if ( isset( $this->schema ) ) {
			return $this->schema->feed_item_tag_taxonomy;
		}

		return '';
	}


	/**
	 * Get the folder category taxonomy
	 *
	 * @since 1.7
	 *
	 * @return string
	 */
	public function get_feed_folder_taxonomy() {
		if ( isset( $this->pf_folders ) ) {
			return $this->pf_folders->tag_taxonomy;
		}

		return '';
	}

	/**
	 * Set up first feed and other install/upgrade tasks
	 * Code via Boone
	 *
	 * @since 3.1.1
	 *
	 *
	 */
	public function check_installed() {
		global $wp_rewrite;
		$current_version = PF_VERSION; // define this constant in the loader file
		$saved_version = get_option( 'pf_version' );

		// This is a new installation
		if ( ! $saved_version ) {
			// Do whatever you need to do during first installation
			$check = pressforward()->pf_feeds->create(
				'http://pressforward.org/feed/',
				array(
					'title'         => 'PressForward',
					'htmlUrl'       => 'http://pressforward.org/',
					'description'   => 'The news feed for the PressForward project.',
					'type'          => 'rss',
					'module_added'  => 'rss-import'
				)
			);

			$wp_rewrite->flush_rules(false);

		// This is an upgrade
		} else if ( version_compare( $saved_version, $current_version, '<' ) ) {
			// Do whatever you need to do on an upgrade

		// Version is up to date - do nothing
		} else {
			return;
		}

		// Update the version number stored in the db (so this does not run again)
		update_option( 'pf_version', PF_VERSION );
	}
}

/**
 * Bootstrap
 *
 * You can also use this to get a value out of the global, eg
 *
 *    $foo = pressforward()->bar;
 *
 * @since 1.7
 */
function pressforward() {
	return PressForward::init();
}

// Start me up!
pressforward();
