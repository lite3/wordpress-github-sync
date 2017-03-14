<?php
/**
 * GitHub Export Manager.
 *
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Export
 */
class WordPress_GitHub_Sync_Export {

	/**
	 * Option key for export user.
	 */
	const EXPORT_USER_OPTION = '_wpghs_export_user_id';

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Initializes a new export manager.
	 *
	 * @param WordPress_GitHub_Sync $app Application container.
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Updates all of the current posts in the database on master.
	 *
	 * @return string|WP_Error
	 */
	public function full() {
		$posts = $this->app->database()->fetch_all_supported();

		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$master = $this->app->api()->fetch()->master();

		if ( is_wp_error( $master ) ) {
			return $master;
		}

		foreach ( $posts as $post ) {
			$master->tree()->add_post_to_tree( $post );
		}

		$master->set_message(
			apply_filters(
				'wpghs_commit_msg_full',
				sprintf(
					'Full export from WordPress at %s (%s)',
					site_url(),
					get_bloginfo( 'name' )
				)
			) . $this->get_commit_msg_tag()
		);

		$result = $this->app->api()->persist()->commit( $master );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->update_shas( $posts );
	}


	/**
	 * Check if it exists in github
	 * @param  int  $post_id
	 * @return boolean
	 */
	protected function github_path( $post_id ) {
		$github_path = get_post_meta( $post_id, '_wogh_github_path', true );

		if ( $github_path && $this->app->fetch->exists( $github_path ) ) {
			return $github_path;
		}

		return false;
	}

	/**
	 * Updates the provided post ID in master.
	 *
	 * @param int $post_id Post ID to update.
	 *
	 * @return string|WP_Error
	 */
	public function update( $post_id ) {
		$post = $this->app->database()->fetch_by_id( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( 'trash' === $post->status() ) {
			return $this->delete( $post_id );
		}

		if ( $old_github_path = $this->github_path( $post->id() ) ) {
			$post->set_old_github_path($old_github_path);
		}

		$result = $this->new_posts( array( $post ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return __( 'Export to GitHub completed successfully.', 'writing-on-github' );
	}

	/**
	 * Updates GitHub-created posts with latest WordPress data.
	 *
	 * @param array<WordPress_GitHub_Sync_Post> $posts Array of Posts to create.
	 *
	 * @return string|WP_Error
	 */
	public function new_posts( array $posts ) {
		// $master = $this->app->api()->fetch()->master();

		// if ( is_wp_error( $master ) ) {
		// 	return $master;
		// }

		$message = apply_filters(
			'wpghs_commit_msg_new_posts',
			sprintf(
				'Updating new posts from WordPress at %s (%s)',
				site_url(),
				get_bloginfo( 'name' )
			)
		) . $this->get_commit_msg_tag();

		$error = false;

		$persist = $this->app->api()->persist();

		foreach ( $posts as $post ) {
			$result = $this->new_post( $post, $message, $persist );
			if ( is_wp_error( $result ) ) {
				if ( $error ) {
					$error->add( $result->get_error_code(), $result->get_error_message() );
				} else {
					$error = $result;
				}
			}
		}



		// $result = $this->app->api()->persist()->commit( $master );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// return $this->update_shas( $posts );
		return true;
	}

	protected function new_post( $post, $message, $persist ) {
		$github_path = $post->github_path();
		$old_github_path = $post->old_github_path();
		$blob = $post->to_blob();
		$result = false;

		// delete old file
		if ( $old_github_path && $old_github_path != $github_path ) {
			$result = $persist->delete_file( $post->old_github_path(), $blob->sha(), $message );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$old_github_path = false;
		}

		// create file
		if ( ! $old_github_path ) {
			$result = $persist->create_file( $blob, $message );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// update file
		if ( $old_github_path ) {
			$result = $persist->update_file( $blob, $message );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$sha = $result->content->sha;
		$post->set_sha($sha);
		$post->set_old_github_path($github_path);

		return true;
	}

	/**
	 * Deletes a provided post ID from master.
	 *
	 * @param int $post_id Post ID to delete.
	 *
	 * @return string|WP_Error
	 */
	public function delete( $post_id ) {
		$post = $this->app->database()->fetch_by_id( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$master = $this->app->api()->fetch()->master();

		if ( is_wp_error( $master ) ) {
			return $master;
		}

		$master->tree()->remove_post_from_tree( $post );
		$master->set_message(
			apply_filters(
				'wpghs_commit_msg_delete',
				sprintf(
					'Deleting %s via WordPress at %s (%s)',
					$post->github_path(),
					site_url(),
					get_bloginfo( 'name' )
				),
				$post
			) . $this->get_commit_msg_tag()
		);

		$result = $this->app->api()->persist()->commit( $master );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return __( 'Export to GitHub completed successfully.', 'wp-github-sync' );
	}

	/**
	 * Use the new tree to save sha data
	 * for all the updated posts.
	 *
	 * @param WordPress_GitHub_Sync_Post[] $posts Posts to fetch updated shas for.
	 *
	 * @return string|WP_Error
	 */
	protected function update_shas( array $posts ) {
		$master   = $this->app->api()->fetch()->master();
		$attempts = 1;

		while ( is_wp_error( $master ) && $attempts < 5 ) {
			$master = $this->app->api()->fetch()->master();
			$attempts ++;
		}

		if ( is_wp_error( $master ) ) {
			// @todo throw a big warning! not having the latest shas is BAD
			// Solution: Show error message and link to kick off sha importing.
			return $master;
		}

		foreach ( $posts as $post ) {
			$blob = $master->tree()->get_blob_by_path( $post->github_path() );

			if ( $blob ) {
				$this->app->database()->set_post_sha( $post, $blob->sha() );
			}
		}

		return __( 'Export to GitHub completed successfully.', 'wp-github-sync' );
	}

	/**
	 * Saves the export user to the database.
	 *
	 * @param int $user_id User ID to export with.
	 *
	 * @return bool
	 */
	public function set_user( $user_id ) {
		return update_option( self::EXPORT_USER_OPTION, (int) $user_id );
	}

	/**
	 * Gets the commit message tag.
	 *
	 * @return string
	 */
	protected function get_commit_msg_tag() {
		$tag = apply_filters( 'wpghs_commit_msg_tag', 'wpghs' );

		if ( ! $tag ) {
			throw new Exception( __( 'Commit message tag not set. Filter `wpghs_commit_msg_tag` misconfigured.', 'wp-github-sync' ) );
		}

		return ' - ' . $tag;
	}
}
