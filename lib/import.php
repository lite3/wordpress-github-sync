<?php
/**
 * GitHub Import Manager
 *
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Import
 */
class WordPress_GitHub_Sync_Import {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Initializes a new import manager.
	 *
	 * @param WordPress_GitHub_Sync $app Application container.
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Imports a payload.
	 *
	 * @param WordPress_GitHub_Sync_Payload $payload GitHub payload object.
	 *
	 * @return string|WP_Error
	 */
	// public function payload( WordPress_GitHub_Sync_Payload $payload ) {
	// 	/**
	// 	 * Whether there's an error during import.
	// 	 *
	// 	 * @var false|WP_Error $error
	// 	 */
	// 	$error = false;

	// 	$result = $this->commit( $this->app->api()->fetch()->commit( $payload->get_commit_id() ) );

	// 	if ( is_wp_error( $result ) ) {
	// 		$error = $result;
	// 	}

	// 	$removed = array();
	// 	foreach ( $payload->get_commits() as $commit ) {
	// 		$removed = array_merge( $removed, $commit->removed );
	// 	}
	// 	foreach ( array_unique( $removed ) as $path ) {
	// 		$result = $this->app->database()->delete_post_by_path( $path );

	// 		if ( is_wp_error( $result ) ) {
	// 			if ( $error ) {
	// 				$error->add( $result->get_error_code(), $result->get_error_message() );
	// 			} else {
	// 				$error = $result;
	// 			}
	// 		}
	// 	}

	// 	if ( $error ) {
	// 		return $error;
	// 	}

	// 	return __( 'Payload processed', 'wp-github-sync' );
	// }

	public function payload( WordPress_GitHub_Sync_Payload $payload ) {
		/**
		 * Whether there's an error during import.
		 *
		 * @var false|WP_Error $error
		 */
		$error = false;
		$delete_ids = false;

		$result = $this->compare( $this->app->api()->fetch()->compare( $payload->get_before_commit_id() ), $delete_ids );

		if ( is_wp_error( $result ) ) {
			$error = $result;
		}

		$removed = array();
		foreach ( $payload->get_commits() as $commit ) {
			$removed = array_merge( $removed, $commit->removed );
		}

		if ( ! empty( $delete_ids ) ) {
			foreach ($delete_ids as $id) {
				$result = $this->app->database()->delete_post( $id );
				if ( is_wp_error( $result ) ) {
					if ( $error ) {
						$error->add( $result->get_error_code(), $result->get_error_message() );
					} else {
						$error = $result;
					}
				}
			}
		}

		if ( $error ) {
			return $error;
		}

		return __( 'Payload processed', 'wp-github-sync' );
	}

	/**
	 * Imports the latest commit on the master branch.
	 *
	 * @return string|WP_Error
	 */
	public function master() {
		return $this->commit( $this->app->api()->fetch()->master() );
	}

	/**
	 * Imports a provided commit into the database.
	 *
	 * @param WordPress_GitHub_Sync_Commit|WP_Error $commit Commit to import.
	 *
	 * @return string|WP_Error
	 */
	// protected function commit( $commit ) {
	// 	if ( is_wp_error( $commit ) ) {
	// 		return $commit;
	// 	}

	// 	if ( $commit->already_synced() ) {
	// 		return new WP_Error( 'commit_synced', __( 'Already synced this commit.', 'wp-github-sync' ) );
	// 	}

	// 	$posts = array();
	// 	$new   = array();

	// 	foreach ( $commit->tree()->blobs() as $blob ) {
	// 		if ( ! $this->importable_blob( $blob ) ) {
	// 			continue;
	// 		}

	// 		$posts[] = $post = $this->blob_to_post( $blob );

	// 		if ( $post->is_new() ) {
	// 			$new[] = $post;
	// 		}
	// 	}

	// 	$result = $this->app->database()->save_posts( $posts, $commit->author_email() );

	// 	if ( is_wp_error( $result ) ) {
	// 		return $result;
	// 	}

	// 	if ( $new ) {
	// 		$result = $this->app->export()->new_posts( $new );

	// 		if ( is_wp_error( $result ) ) {
	// 			return $result;
	// 		}
	// 	}

	// 	return $posts;
	// }

	protected function compare( $compare, &$delete_ids ) {
		if ( is_wp_error( $compare ) ) {
			return $compare;
		}

		$posts = array();
		$new   = array();

		$idsmap = array();

		foreach ( $compare->files() as $file ) {
			if ( ! $this->importable_file( $file ) ) {
				continue;
			}

			$blob = $this->app->api()->fetch()->blob($file);
			// network error ?
			if ( is_wp_error($blob) ) {
				continue;
			}

			if ( ! $this->importable_blob($blob) ) {
				continue;
			}

			$post = $this->blob_to_post( $blob );

			if ( $file->status == 'removed' ) {
				$id = $blob->id();
				if ( ! empty($id) ) {
					$idsmap[$id] = true;
				}
			} elseif ( $post != false ) {
				$posts[] = $post;
				if ( $post->is_new() ) {
					$new[] = $post;
				}
			}
		}

		foreach ($posts as $post) {
			if ( $post->id() && isset($idsmap[$post->id()]) ) {
				unset($idsmap[$post->id()]);
			}
		}
		$delete_ids = array();
		foreach ($idsmap as $id => $value) {
			$delete_ids[] = $id;
		}

		// $this->app->database()->save_posts( $posts, $commit->author_email() );

		$result = $this->app->database()->save_posts( $posts );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $new ) {
			$result = $this->app->export()->new_posts( $new );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $posts;
	}

	/**
	 * Checks whether the provided blob should be imported.
	 *
	 * @param WordPress_GitHub_Sync_Blob $blob Blob to validate.
	 *
	 * @return bool
	 */
	protected function importable_file( WordPress_GitHub_Sync_Compare_File $file ) {


		// only _pages and _posts
		if ( strncasecmp($file->path, '_pages/', strlen('_pages/') ) != 0 &&
			 strncasecmp($file->path, '_posts/', strlen('_posts/') ) != 0 ) {
			return false;
		}


		// if ( ! $file->has_frontmatter() ) {
		// 	return false;
		// }

		return true;
	}

	/**
	 * Checks whether the provided blob should be imported.
	 *
	 * @param WordPress_GitHub_Sync_Blob $blob Blob to validate.
	 *
	 * @return bool
	 */
	protected function importable_blob( WordPress_GitHub_Sync_Blob $blob ) {
		// global $wpdb;

		// // Skip the repo's readme.
		// if ( 'readme' === strtolower( substr( $blob->path(), 0, 6 ) ) ) {
		// 	return false;
		// }

		// // If the blob sha already matches a post, then move on.
		// if ( ! is_wp_error( $this->app->database()->fetch_by_sha( $blob->sha() ) ) ) {
		// 	return false;
		// }

		if ( ! $blob->has_frontmatter() ) {
			return false;
		}

		return true;
	}

	/**
	 * Imports a single blob content into matching post.
	 *
	 * @param WordPress_GitHub_Sync_Blob $blob Blob to transform into a Post.
	 *
	 * @return WordPress_GitHub_Sync_Post
	 */
	protected function blob_to_post( WordPress_GitHub_Sync_Blob $blob ) {
		$args = array( 'post_content' => $blob->content_import() );
		$meta = $blob->meta();

		$id = false;

		if ( $meta ) {
			if ( array_key_exists( 'layout', $meta ) ) {
				$args['post_type'] = $meta['layout'];
				unset( $meta['layout'] );
			}

			if ( array_key_exists( 'published', $meta ) ) {
				$args['post_status'] = true === $meta['published'] ? 'publish' : 'draft';
				unset( $meta['published'] );
			}

			if ( array_key_exists( 'post_title', $meta ) ) {
				$args['post_title'] = $meta['post_title'];
				unset( $meta['post_title'] );
			}

			if ( array_key_exists( 'ID', $meta ) ) {
				$id = $args['ID'] = $meta['ID'];
				$blob->set_id($id);
				unset( $meta['ID'] );
			}
		}

		$meta['_wogh_sha'] = $blob->sha();

		if ( $id ) {
			$old_sha = get_post_meta( $id, '_wogh_sha', true );

			// dont save post when has same sha
			if ( $old_sha  && $old_sha == $meta['_wogh_sha'] ) {
				return false;
			}
		}

		$post = new WordPress_GitHub_Sync_Post( $args, $this->app->api() );
		$post->set_old_github_path( $blob->path() );
		$post->set_meta( $meta );
		$blob->set_id( $post->id() );

		return $post;
	}
}
