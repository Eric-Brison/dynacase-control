<?php


/**
 * RegistrationClient Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

class RegistrationClient  {
	private $registration_url = 'https://eec.anakeen.com/registration';

	private $ua_string = null;
	private $timeout = 60;
	private $proxy_host = null;
	private $proxy_port = null;
	private $proxy_user = null;
	private $proxy_pass = null;

	public $last_error = null;

	/**
	 * RegistrationClient constructor
	 */
	public function __construct() {
		$this->ua_string = sprintf("RegistrationClient/%s", PHP_VERSION);
		return $this;
	}

	/**
	 * Set the registration URL
	 *
	 * @param string $url the registration URL
	 */
	public function setRegistrationUrl($url) {
		$this->registration_url = $url;
		return $this;
	}

	/**
	 * Get the registration URL
	 */
	public function getRegistrationUrl() {
		return $this->registration_url;
	}

	/**
	 * Set proxy
	 *
	 * @param string $host the proxy hostname or ip address (required)
	 * @param string $port the proxy port (required)
	 * @param string $user username for HTTP Basic proxy auth (default=null)
	 * @param string $pass password for HTTP Basic proxy auth (default=null)
	 *
	 * @return the current object ($this)
	 */
	public function setProxy($host, $port, $user = null, $pass = null) {
		$this->proxy_host = $host;
		$this->proxy_port = $port;
		$this->proxy_user = $user;
		$this->proxy_pass = $pass;
		return $this;
	}

	/**
	 * Set network operation timeout
	 *
	 * @param int $timeout timeout in seconds
	 *
	 * @return the current object ($this)
	 */
	public function setTimeout($timeout = 60) {
		if( is_numeric($timeout) ) {
			$this->timeout = $timeout;
		}
		return $this;
	}

	/**
	 * Post an XML request to the given url
	 *
	 * @param string $url the URL on which the data will be POST'ed
	 * @param string $data the XML to POST
	 *
	 * @return boolean false on error or an array() on success
	 *
	 * The returned array has the following structure :
	 *
	 *   array(
	 *     'code' => $http_code,
	 *     'content-type' => , $content_type
	 *     'data' => $data
	 *   )
	 *
	 *  int $http_code the response HTTP code (200, 500, 404, etc.)
	 *  string $content_type the response Content-type (text/xml, etc.)
	 *  string $data the XML response document
	 *
	 */
	public function post($url, $data) {
		if( ! function_exists('curl_init') ) {
			$err = sprintf("php-curl extension is not loaded!");
			error_log(__CLASS__."::".__FUNCTION__." ".$err);
			$this->last_error = $err;
			return false;
		}

		$ch = curl_init($url);

		// 'POST' method
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		if( $this->ua_string !== null ) {
			curl_setopt($ch, CURLOPT_USERAGENT, $this->ua_string);
		}

		// Load the data
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		// Setup proxy if required
		if( $this->proxy_host !== null ) {
			curl_setopt($ch, CURLOPT_PROXY, sprintf("http://%s:%s", $this->proxy_host, $this->proxy_port));
			if( $this->proxy_user !== null ) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, sprintf("%s:%s", $this->proxy_user, $this->proxy_pass));
			}
		}

		$data = curl_exec($ch);
		if( curl_errno($ch) ) {
			$err = sprintf("curl_exec() returned with error: %s", curl_error($ch));
			error_log(__CLASS__."::".__FUNCTION__." ".$err);
			$this->last_error = $err;
			curl_close($ch);
			return false;
		}

		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

		curl_close($ch);

		return array(
			'code' => $code,
			'content-type' => $content_type,
			'data' => $data
		);
	}

	/**
	 * Extract the XML DOMDocument from the HTTP response data
	 *
	 * @param array() $response returned from post() methode
	 *
	 * @return boolean false on error or a string containing the response message form the XML response data
	 */
	private function _getResponse($response) {
		$content_type = $response['content-type'];
		if( $content_type != 'text/xml' ) {
			$err = sprintf("Bad content-type '%s' in response with HTTP code '%s'.", $content_type, $response['code']);
			error_log(__CLASS__."::".__FUNCTION__." ".$err);
			$this->last_error = $err;
			return false;
		}

		$xml = $response['data'];

		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = true;
		$ret = $dom->loadXML($xml);
		if( $ret === false ) {
			$err = sprintf("Error loading XML from response with code '%s'.", $response['code']);
			error_log(__CLASS__."::".__FUNCTION__." ".$err);
			$this->last_error = $err;
			return false;
		}

		if( $dom->documentElement->tagName != 'response' ) {
			$err = sprintf("Malformed XML (response tag not found) in response with code '%s'.", $response['code']);
			error_log(__CLASS__."::".__FUNCTION__." ".$err);
			$this->last_error = $err;
			return false;
		}

		$msg = $dom->documentElement->textContent;

		return $msg;
	}

	/**
	 * Register a new {mid, ctrlid} into database
	 *
	 * @param string $mid the machine ID
	 * @param string $ctrlid the control ID
	 * @param string $login the login/username of the client
	 * @param string $password the password of the client
	 *
	 * @return boolean false on error or an array() on success
	 *
	 * The returned array() has the following structure :
	 *
	 *   array(
	 *     'code' => $code,
	 *     'response' => $msg
	 *   )
	 *
	 * int $code the HTTP response code
	 * string $msg the XML response message
	 *
	 */
	public function register($mid, $ctrlid, $login, $password) {
		$dom = new DOMDocument('1.0', 'utf-8');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$rootNode = $dom->createElement('register');
		$dom->appendChild($rootNode);

		foreach( array('mid' => $mid, 'ctrlid' => $ctrlid, 'login' => $login, 'password' => $password) as $key => $value ) {
			$node = $dom->createElement($key);
			$text = $dom->createTextNode($value);
			$node->appendChild($text);
			$rootNode->appendChild($node);
		}
		/*
		$node = $dom->createElement('mid');
		$text = $dom->createTextNode($uuid);
		$node->appendChild($text);
		$rootNode->appendChild($node);

		$node = $dom->createElement('login');
		$text = $dom->createTextNode($login);
		$node->appendChild($text);
		$rootNode->appendChild($node);

		$node = $dom->createElement('password');
		$text = $dom->createTextNode($password);
		$node->appendChild($text);
		$rootNode->appendChild($node);
		*/
		
		/*
		 $xml = <<<EOXML
		 <?xml version="1.0" encoding="utf-8"?>
		 <register>
		 <uuid>%s</uuid>
		 <login>%s</login>
		 <password>%s</password>
		 </register>
		 EOXML;

		 $xml = sprintf($xml, htmlspecialchars($uuid), htmlspecialchars($login), htmlspecialchars($password));
		 */

		$xml = $dom->saveXML();

		$ret = $this->post($this->registration_url, $xml);
		if( $ret === false ) {
			return false;
		}

		$msg = $this->_getResponse($ret);
		if( $msg === false ) {
			return false;
		}

		/*
		if( $ret['code'] == 409 ) {
			return array(
				'conflict' => true,
				'code' => 409,
				'response' => $msg
			);
		}
		*/

		return array(
			/* 'conflict' => false, */
			'code' => $ret['code'],
			'response' => $msg
		);
	}

	/**
	 * Add/update statistics for a context
	 *
	 * @param string $mid the machine ID
	 * @param string $ctrlid the control ID
	 * @param DOMDocument $stats the DOMDocument from StatCollector::getXML()
	 *
	 * @ return boolean false on error of an array() on success (see ::register())
	 */
	public function add_context($mid, $ctrlid, $contextid, &$stats) {
		$dom = new DOMDocument('1.0', 'utf-8');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		$rootNode = $dom->createElement('add-context');
		$dom->appendChild($rootNode);

		foreach( array('mid' => $mid, 'ctrlid' => $ctrlid, 'contextid' => $contextid) as $key => $value ) {
			$node = $dom->createElement($key);
			$text = $dom->createTextNode($value);
			$node->appendChild($text);
			$rootNode->appendChild($node);
		}

		if( is_string($stats) ) {
			$domStats = new DOMDocument();
			$ret = $domStats->loadXML($stats);
			if( $ret === false ) {
				$this->last_error = sprintf("Error loading XML stats.");
				return false;
			}
			$stats = $domStats->documentElement;
		}

		$importedStats = $dom->importNode($stats, true);
		$rootNode->appendChild($importedStats);

		$xml = $dom->saveXML();

		$ret = $this->post($this->registration_url, $xml);
		if( $ret === false ) {
			return false;
		}

		$msg = $this->_getResponse($ret);
		if( $msg === false ) {
			return false;
		}

		return array(
			'code' => $ret['code'],
			'response' => $msg
		);
	}

	/**
	 * Delete the statistics of a context
	 *
	 * @param string $mid the machine ID
	 * @param string $ctrlid the control ID
	 * @param string $contextid the client context name
	 *
	 * @return boolean false on error or an array() on success (see ::register())
	 */
	public function delete_context($mid, $ctrlid, $contextid) {
		$dom = new DOMDocument('1.0', 'utf-8');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$rootNode = $dom->createElement('delete-context');
		$dom->appendChild($rootNode);

		foreach( array('mid' => $mid, 'ctrlid' => $ctrlid, 'contextid' => $contextid) as $key => $value ) {
			$node = $dom->createElement($key);
			$text = $dom->createTextNode($value);
			$node->appendChild($text);
			$rootNode->appendChild($node);
		}
		
		$xml = $dom->saveXML();

		$ret = $this->post($this->registration_url, $xml);
		if( $ret === false ) {
			return false;
		}

		$msg = $this->_getResponse($ret);
		if( $msg === false ) {
			return false;
		}

		return array(
			'code' => $ret['code'],
			'response' => $msg
		);

	}
	
}

?>