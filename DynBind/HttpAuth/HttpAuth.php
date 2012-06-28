<?php
require_once 'HttpAuthException.php';

abstract class HttpAuth {

	const CREDENTIALS_PLAIN = 1; // plaintext password
	const CREDENTIALS_DIGEST = 2; // md5(user:realm:password)

	public $send_realm = "protected area"; // name of password protected area

	public abstract function getUsername();
	public abstract function authenticate($username, $credentials, $credential_type=null);
	public abstract function sendSuccessHeaders($response_body=null);
	public abstract function sendFailedHeaders();

	public function __construct($env=null, $realm=null, $request_body = null){
		if($env === null){
			$env = $_SERVER;
		}
		if($request_body === null){
			$request_body = (string) @file_get_contents('php://input');
		}
		if($realm){
			$this->setRealm($realm);
		}
		$this->setServerEnvironent($env, $request_body);
	}

	public function setServerEnvironent($env, $request_body = ''){
		return $this;
	}

	public function setRealm($realm){
		$this->send_realm = $realm;
		return $this;
	}

	public function assertUser($username, $credentials, $credential_type=null){
		try{
			if($username === $this->getUsername()){
				$this->authenticate($username, $credentials, $credential_type);
				$this->sendSuccessHeaders();
				return;
			}
		} catch(HttpAuthException $e){
			$this->sendFailedHeaders();
			throw $e;
		}
		$this->sendFailedHeaders();
		throw new HttpAuthException("HTTP-Auth failed", HttpAuthException::USER_INVALID);
	}

	/**
	 * Returns an as random as possible SHA1-Hash, using /dev/urandom, CAPICOM.Utilities.GetRandom or uniqid as a fallback
	 *
	 * @return string
	 */
	protected function getRandom(){
		$pr_bits = '';
		// Unix/Linux platform?
		$fp = @fopen('/dev/urandom','rb');
		if ($fp !== FALSE) {
		    $pr_bits .= @fread($fp,20);
		    @fclose($fp);
		}
		// MS-Windows platform?
		if (@class_exists('COM')) {
		    // http://msdn.microsoft.com/en-us/library/aa388176(VS.85).aspx
		    try {
		        $CAPI_Util = new COM('CAPICOM.Utilities.1');
		        $pr_bits .= $CAPI_Util->GetRandom(20,0);
		    } catch (Exception $ex) {
		    }
		}
		if (strlen($pr_bits) < 20) {
			$pr_bits = uniqid(null, true);
			if(function_exists('microtime')){ // use microtime as an additional entropy source if available
				$pr_bits .= microtime();
			}
		}
		return sha1($pr_bits);
	}
}