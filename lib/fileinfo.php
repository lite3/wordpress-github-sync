<?php


/**
 *
 */
class Writing_On_GitHub_File_Info {

	public function __construct( stdClass $data ) {
		$this->sha 			= $data->sha;
		// content api have status
		$this->status 		= isset( $data->status ) ? $data->status : '';

		if ( isset( $data->path ) ) {
			// tree api have path
			$this->path = $data->path;
		} elseif ( $isset( $data->filename ) ) {
			// content api have filename
			$this->path = $data->filename;
		}
	}

	public $sha;
	public $path;
	public $status;  // added removed modified
}
