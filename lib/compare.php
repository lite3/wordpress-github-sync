<?php
/**
 * API commit model.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Compare
 */
class WordPress_GitHub_Sync_Compare {

	/**
	 * Raw compare data.
	 *
	 * @var stdClass
	 */
	protected $data;

	/**
	 * Base commit sha.
	 *
	 * @var string
	 */
	protected $base_sha;

	/**
	 * Commit api url.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Commit api url.
	 *
	 * @var Array
	 */
	protected $files;

	/**
	 * Added and modified files.
	 *
	 * @var Array
	 */
	protected $updated_files;

	/**
	 * Removed files.
	 *
	 * @var Array
	 */
	protected $removed_files;

	/**
	 * Instantiates a new Commit object.
	 *
	 * @param stdClass $data Raw commit data.
	 */
	public function __construct( stdClass $data ) {
		$this->data = $data;

		$this->interpret_data();
	}

	/**
	 * Returns the commit sha.
	 *
	 * @return string
	 */
	public function sha() {
		return $this->sha;
	}

	/**
	 * Return the commit's API url.
	 *
	 * @return string
	 */
	public function url() {
		return $this->url;
	}

	public function status() {
		return $this->status;
	}

	public function base_sha() {
		return $this->base_sha;
	}

	public function files() {
		return $this->files;
	}

	public function updated_files() {
		if (empty($this->updated_files)) {
			$this->split_files();
		}
		return $this->updated_files;
	}

	public function removed_files() {
		if (empty($this->updated_files)) {
			$this->split_files();
		}
		return $this->removed_files;
	}

	protected function split_files() {
		$this->updated_files = array();
		$this->removed_files = array();
		foreach ($this->files as $file) {
			if ( $file->status == 'removed' ) {
				$this->removed_files[] = $files;
			} else {
				$this->updated_files[] = $files;
			}
		}
	}

	/**
	 * Interprets the raw data object into commit properties.
	 */
	protected function interpret_data() {
		$this->url       = isset( $this->data->url ) ? $this->data->url : '';
		$this->status    = isset( $this->data->status ) ? $this->data->status : '';
		$this->base_sha  = isset( $this->data->base_commit ) ? $this->data->base_commit->sha : '';
		$this->files = array();
		foreach ($this->data->files as $file) {
			$this->files[] = new Writing_On_GitHub_File_Info($file);
		}
	}

}
