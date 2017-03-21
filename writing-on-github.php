<?php
/**
 * Plugin Name: Writing on GitHub
 * Plugin URI: https://github.com/mAAdhaTTah/wordpress-github-sync
 * Description: A WordPress plugin to sync content with a GitHub repository (or Jekyll site).
 * Version: 1.7.5
 * Author:  James DiGioia, Ben Balter
 * Author URI: http://jamesdigioia.com
 * License: GPLv2
 * Domain Path: /languages
 * Text Domain: writing-on-github
 */

/*  Copyright 2014  James DiGioia  (email : jamesorodig@gmail.com)

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

// If the functions have already been autoloaded, don't reload.
// This fixes function duplication during unit testing.
// $path = dirname( __FILE__ ) . '/vendor/autoload_52.php';
// if ( ! function_exists( 'get_the_github_view_link' ) && file_exists( $path ) ) {
// 	require_once $path;
// }


require_once(dirname(__FILE__) . '/vendor/mustangostang/spyc/Spyc.php');
require_once(dirname(__FILE__) . '/lib/cache.php');
require_once(dirname(__FILE__) . '/lib/database.php');
require_once(dirname(__FILE__) . '/lib/admin.php');
require_once(dirname(__FILE__) . '/lib/payload.php');
require_once(dirname(__FILE__) . '/lib/post.php');
// require_once(dirname(__FILE__) . '/lib/cli.php');
require_once(dirname(__FILE__) . '/lib/controller.php');
require_once(dirname(__FILE__) . '/lib/export.php');
require_once(dirname(__FILE__) . '/lib/semaphore.php');
require_once(dirname(__FILE__) . '/lib/request.php');
require_once(dirname(__FILE__) . '/lib/client/base.php');
require_once(dirname(__FILE__) . '/lib/client/fetch.php');
require_once(dirname(__FILE__) . '/lib/client/persist.php');
require_once(dirname(__FILE__) . '/lib/import.php');
require_once(dirname(__FILE__) . '/lib/api.php');
require_once(dirname(__FILE__) . '/lib/fileinfo.php');
require_once(dirname(__FILE__) . '/lib/blob.php');
require_once(dirname(__FILE__) . '/lib/response.php');
// require_once(dirname(__FILE__) . '/views/setting-field.php');
// require_once(dirname(__FILE__) . '/views/options.php');
// require_once(dirname(__FILE__) . '/views/user-setting-field.php');

add_action( 'plugins_loaded', array( new Writing_On_GitHub, 'boot' ) );

class Writing_On_GitHub {

	/**
	 * Object instance
	 * @var self
	 */
	public static $instance;

	/**
	 * Language text domain
	 * @var string
	 */
	public static $text_domain = 'writing-on-github';

	/**
	 * Current version
	 * @var string
	 */
	public static $version = '1.7.5';

	/**
	 * Controller object
	 * @var Writing_On_GitHub_Controller
	 */
	public $controller;

	/**
	 * Controller object
	 * @var Writing_On_GitHub_Admin
	 */
	public $admin;

	/**
	 * CLI object.
	 *
	 * @var Writing_On_GitHub_CLI
	 */
	protected $cli;

	/**
	 * Request object.
	 *
	 * @var Writing_On_GitHub_Request
	 */
	protected $request;

	/**
	 * Response object.
	 *
	 * @var Writing_On_GitHub_Response
	 */
	protected $response;

	/**
	 * Api object.
	 *
	 * @var Writing_On_GitHub_Api
	 */
	protected $api;

	/**
	 * Import object.
	 *
	 * @var Writing_On_GitHub_Import
	 */
	protected $import;

	/**
	 * Export object.
	 *
	 * @var Writing_On_GitHub_Export
	 */
	protected $export;

	/**
	 * Semaphore object.
	 *
	 * @var Writing_On_GitHub_Semaphore
	 */
	protected $semaphore;

	/**
	 * Database object.
	 *
	 * @var Writing_On_GitHub_Database
	 */
	protected $database;

	/**
	 * Cache object.
	 *
	 * @var Writing_On_GitHub_Cache
	 */
	protected $cache;

	/**
	 * Called at load time, hooks into WP core
	 */
	public function __construct() {
		self::$instance = $this;

		if ( is_admin() ) {
			$this->admin = new Writing_On_GitHub_Admin;
		}

		$this->controller = new Writing_On_GitHub_Controller( $this );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wogh', $this->cli() );
		}
	}

	/**
	 * Attaches the plugin's hooks into WordPress.
	 */
	public function boot() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );

		add_action( 'init', array( $this, 'l10n' ) );

		// Controller actions.
		add_action( 'save_post', array( $this->controller, 'export_post' ) );
		add_action( 'delete_post', array( $this->controller, 'delete_post' ) );
		add_action( 'wp_ajax_nopriv_wogh_push_request', array( $this->controller, 'pull_posts' ) );
		add_action( 'wogh_export', array( $this->controller, 'export_all' ) );
		add_action( 'wogh_import', array( $this->controller, 'import_master' ) );
		add_filter( 'get_edit_post_link', array( $this, 'edit_post_link' ), 10, 3 );

		do_action( 'wogh_boot', $this );
	}

	public function edit_post_link($link, $postID, $context) {
		if ( ! wp_is_post_revision( $postID ) ) {
			$post = new Writing_On_GitHub_Post( $postID, Writing_On_GitHub::$instance->api() );
			if ( $post->is_on_github() ) {
				return $post->github_edit_url();
			}
		}

	    return $link;
	}

	/**
	 * Init i18n files
	 */
	public function l10n() {
		load_plugin_textdomain( self::$text_domain, false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Sets and kicks off the export cronjob
	 */
	public function start_export() {
		$this->export()->set_user( get_current_user_id() );
		$this->start_cron( 'export' );
	}

	/**
	 * Sets and kicks off the import cronjob
	 */
	public function start_import() {
		$this->start_cron( 'import' );
	}

	/**
	 * Enables the admin notice on initial activation
	 */
	public function activate() {
		if ( 'yes' !== get_option( '_wogh_fully_exported' ) ) {
			set_transient( '_wogh_activated', 'yes' );
		}
	}

	/**
	 * Displays the activation admin notice
	 */
	public function activation_notice() {
		if ( ! get_transient( '_wogh_activated' ) ) {
			return;
		}

		delete_transient( '_wogh_activated' );

		?><div class="updated">
			<p>
				<?php
					printf(
						__( 'To set up your site to sync with GitHub, update your <a href="%s">settings</a> and click "Export to GitHub."', 'writing-on-github' ),
						admin_url( 'options-general.php?page=' . static::$text_domain)
					);
				?>
			</p>
		</div><?php
	}

	/**
	 * Get the Controller object.
	 *
	 * @return Writing_On_GitHub_Controller
	 */
	public function controller() {
		return $this->controller;
	}

	/**
	 * Lazy-load the CLI object.
	 *
	 * @return Writing_On_GitHub_CLI
	 */
	public function cli() {
		if ( ! $this->cli ) {
			$this->cli = new Writing_On_GitHub_CLI;
		}

		return $this->cli;
	}

	/**
	 * Lazy-load the Request object.
	 *
	 * @return Writing_On_GitHub_Request
	 */
	public function request() {
		if ( ! $this->request ) {
			$this->request = new Writing_On_GitHub_Request( $this );
		}

		return $this->request;
	}

	/**
	 * Lazy-load the Response object.
	 *
	 * @return Writing_On_GitHub_Response
	 */
	public function response() {
		if ( ! $this->response ) {
			$this->response = new Writing_On_GitHub_Response( $this );
		}

		return $this->response;
	}

	/**
	 * Lazy-load the Api object.
	 *
	 * @return Writing_On_GitHub_Api
	 */
	public function api() {
		if ( ! $this->api ) {
			$this->api = new Writing_On_GitHub_Api( $this );
		}

		return $this->api;
	}

	/**
	 * Lazy-load the Import object.
	 *
	 * @return Writing_On_GitHub_Import
	 */
	public function import() {
		if ( ! $this->import ) {
			$this->import = new Writing_On_GitHub_Import( $this );
		}

		return $this->import;
	}

	/**
	 * Lazy-load the Export object.
	 *
	 * @return Writing_On_GitHub_Export
	 */
	public function export() {
		if ( ! $this->export ) {
			$this->export = new Writing_On_GitHub_Export( $this );
		}

		return $this->export;
	}

	/**
	 * Lazy-load the Semaphore object.
	 *
	 * @return Writing_On_GitHub_Semaphore
	 */
	public function semaphore() {
		if ( ! $this->semaphore ) {
			$this->semaphore = new Writing_On_GitHub_Semaphore;
		}

		return $this->semaphore;
	}

	/**
	 * Lazy-load the Database object.
	 *
	 * @return Writing_On_GitHub_Database
	 */
	public function database() {
		if ( ! $this->database ) {
			$this->database = new Writing_On_GitHub_Database( $this );
		}

		return $this->database;
	}

	/**
	 * Lazy-load the Cache object.
	 *
	 * @return Writing_On_GitHub_Cache
	 */
	public function cache() {
		if ( ! $this->cache ) {
			$this->cache = new Writing_On_GitHub_Cache;
		}

		return $this->cache;
	}

	/**
	 * Print to WP_CLI if in CLI environment or
	 * write to debug.log if WP_DEBUG is enabled
	 * @source http://www.stumiller.me/sending-output-to-the-wordpress-debug-log/
	 *
	 * @param mixed $msg
	 * @param string $write
	 */
	public static function write_log( $msg, $write = 'line' ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( is_array( $msg ) || is_object( $msg ) ) {
				WP_CLI::print_value( $msg );
			} else {
				WP_CLI::$write( $msg );
			}
		} elseif ( true === WP_DEBUG ) {
			if ( is_array( $msg ) || is_object( $msg ) ) {
				error_log( print_r( $msg, true ) );
			} else {
				error_log( $msg );
			}
		}
	}

	/**
	 * Kicks of an import or export cronjob.
	 *
	 * @param $type
	 */
	protected function start_cron( $type ) {
		update_option( '_wogh_' . $type . '_started', 'yes' );
		wp_schedule_single_event( time(), 'wogh_' . $type . '' );
		spawn_cron();
	}
}
