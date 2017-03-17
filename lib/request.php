<?php
/**
 * Request management object.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Request
 */
class WordPress_GitHub_Sync_Request {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Raw request data.
	 *
	 * @var string
	 */
	protected $raw_data;

	/**
	 * Headers
	 * @var array
	 */
	protected $headers;

	/**
	 * WordPress_GitHub_Sync_Request constructor.
	 *
	 * @param WordPress_GitHub_Sync $app Application container.
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Validates the header's secret.
	 *
	 * @return true|WP_Error
	 */
	public function is_secret_valid() {
		$headers = $this->headers();

		$this->raw_data = $this->read_raw_data();

		// Validate request secret.
		$hash = hash_hmac( 'sha1', $this->raw_data, $this->secret() );
		if ( 'sha1=' . $hash !== $headers['X-Hub-Signature'] ) {
			return false;
		}

		// 		[X-Hub-Signature] => sha1=3cf3da70de401f7dfff053392f60cc534efed3b4
		//     [Content-Type] => application/json
		//     [X-Github-Delivery] => b2102500-0acf-11e7-8acb-fd86a3497c2f
		//     [X-Github-Event] => ping

		return true;
	}

	/**
	 * Validates the ping event.
	 * @return boolean
	 */
	public function is_ping() {
		$headers = $this->headers();

		print_r($headers);

		foreach ($headers as $key => $value) {
			echo "key:$key    value:$value\n";
			echo strlen($value);
			echo "\n";
		}
		echo "ping: " . $headers['X-Github-Event'];
		echo "\nheaders ping $headers{'X-GitHub-Event'}\n";

		// echo "headers\n";
		// var_dump($headers);
		// echo "\nheaders end\n";

			echo "ping: " . $headers['X-Github-Event'];
			echo "\ntype: ";
			echo gettype($headers['X-GitHub-Event']);
			echo "\n";
		// if ( isset( $headers['X-GitHub-Event'] ) ) {
			// return 'ping' === $headers['X-GitHub-Event'];
		// }
		if ( 'ping' === $headers['X-GitHub-Event'] ) {
			echo "isping\n";
			return true;
		}

		return false;

		// return false;
	}

	/**
	 * Validates the push event.
	 * @return boolean
	 */
	public function is_push() {
		$headers = $this->headers();

		if ( isset( $headers['X-GitHub-Event'] ) ) {
			return 'push' == $headers['X-GitHub-Event'];
		}

		return false;
	}

	/**
	 * Returns a payload object for the given request.
	 *
	 * @return WordPress_GitHub_Sync_Payload
	 */
	public function payload() {
		return new WordPress_GitHub_Sync_Payload( $this->app, $this->raw_data );
	}

	/**
	 * Cross-server header support.
	 *
	 * Returns an array of the request's headers.
	 *
	 * @return array
	 */
	protected function headers() {
		// if ( $this->headers ) {
		// 	return $this->headers;
		// }

		if ( function_exists( 'getallheaders' ) ) {

			$this->headers = getallheaders();
			return $this->headers;
		}
		/**
		 * Nginx and pre 5.4 workaround.
		 * @see http://www.php.net/manual/en/function.getallheaders.php
		 */
		$this->headers = array();
		foreach ( $_SERVER as $name => $value ) {
			if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
				$this->headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
			}
		}

		return $this->headers;
	}

	/**
	 * Reads the raw data from STDIN.
	 *
	 * @return string
	 */
	protected function read_raw_data() {
		return file_get_contents( 'php://input' );
	}

	/**
	 * Returns the Webhook secret
	 *
	 * @return string
	 */
	protected function secret() {
		return get_option( 'wpghs_secret' );
	}
}
