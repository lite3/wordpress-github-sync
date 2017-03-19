<?php
/**
 * API Persist client.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Persist_Client
 */
class WordPress_GitHub_Sync_Persist_Client extends WordPress_GitHub_Sync_Base_Client {

	/**
	 * Get the data for the current user.
	 *
	 * @return array
	 */
	protected function export_user() {
		// @todo constant/abstract out?
		if ( $user_id = (int) get_option( '_wpghs_export_user_id' ) ) {
			delete_option( '_wpghs_export_user_id' );
		} else {
			$user_id = get_current_user_id();
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			// @todo is this what we want to include here?
			return array(
				'name'  => 'Anonymous',
				'email' => 'anonymous@users.noreply.github.com',
			);
		}

		return array(
			'name'  => $user->display_name,
			'email' => $user->user_email,
		);
	}

	/**
	 * Delete the file.
	 *
	 * @return array
	 */
	public function delete_file( $path, $sha, $message ) {
		$body = new stdClass();
		$body->message = $message;
		$body->sha = $sha;
		$body->branch = 'master';

		if ( $author = $this->export_user() ) {
			$body->author = $author;
		}

		return $this->call( 'DELETE', $this->content_endpoint( $path ), $body );
	}

	/**
	 * Create the file.
	 *
	 * @return array
	 */
	public function create_file( $blob, $message ) {
		$body = $blob->to_body();
		$body->message = $message;
		$body->branch = 'master';
		unset($body->sha);

		if ( $author = $this->export_user() ) {
			$body->author = $author;
		}

		return $this->call( 'PUT', $this->content_endpoint( $blob->path() ), $body );
	}

	/**
	 * Update the file.
	 *
	 * @return array
	 */
	public function update_file( $blob, $message ) {
		$body = $blob->to_body();
		$body->message = $message;
		$body->branch = 'master';

		if ( $author = $this->export_user() ) {
			$body->author = $author;
		}

		return $this->call( 'PUT', $this->content_endpoint( $blob->path() ), $body );
	}
}
