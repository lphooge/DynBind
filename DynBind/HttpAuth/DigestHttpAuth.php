<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
require_once 'HttpAuth.php';
/**
 * @author Lutz-Peter Hooge
 *
 * TODO:
 * - replace regexp-parsing with own code
 */
class DigestHttpAuth extends HttpAuth{

	const QOP_NONE = null;
	const QOP_AUTH = 'auth';
	const QOP_AUTH_INT = 'auth-int';

	const ALGO_MD5 = 'md5';
	const ALGO_MD5_SESS = 'md5-sess';

	private $nonce_salt = null;
	protected $nonce_session_directory = null;
	protected $nonce_max_uses = 50;
	protected $nonce_max_age = 300;
	protected $nonce_gc_divisor = 100;
	protected $nonce_gc_probability = 1;
	protected $available_qops = array(self::QOP_NONE, self::QOP_AUTH, self::QOP_AUTH_INT);
	protected $send_algorithm = self::ALGO_MD5;
	protected $send_authentication_info = true;

	protected $entity_body = null;
	protected $qop = null;
	protected $username = null;
	protected $nonce = null;
	protected $uri = null;
	protected $response = null;
	protected $cnonce = null;
	protected $nc = null;
	protected $algorithm = null;
	protected $method = null;
	protected $opaque = null;

	private $is_stale = false;
	private $regenerate_nonce = false;
	private $ha1 = null; // stored h(a1) from the authentication

	public function __construct($env = null, $realm=null){
		parent::__construct($env, $realm);
		if($this->nonce_salt === null){
			$this->setSalt(md5(php_uname().filemtime(__FILE__))); // generate some random ID that is semi-constant but hard to guess
		}
	}

	public function setNonceGc($probability, $divisor){
		$this->nonce_gc_divisor = max(1, (int) $divisor);
		$this->nonce_gc_probability = max(0, (int) $probability);
		return $this;
	}

	public function setNonceMaxAge($ttl){
		$this->nonce_max_age = (int) $ttl;
		return $this;
	}

	public function setNonceMaxUses($max_nc){
		$this->nonce_max_uses = (int) $max_nc;
		return $this;
	}

	public function setSalt($k){
		$this->nonce_salt = $k;
		return $this;
	}

	public function setAvailableQops(array $qops){
		$this->available_qops = array_intersect(array(self::QOP_NONE, self::QOP_AUTH, self::QOP_AUTH_INT), $qops);
	}

	public function setAlgorithm($algo){
		$this->send_algorithm = $algo;
	}

	public function setSessionDirectory($dir){
		if(file_exists($dir) and is_dir($dir) and is_writable($dir) and is_readable($dir)){
			$this->nonce_session_directory = realpath($dir);
			return $this;
		}
		throw new HttpAuthException("session directory is not accessible", HttpAuthException::MISC);
		return $this;
	}

	public function setSendAuthenticationInfo($doit = true){
		$this->send_authentication_info = (bool) $doit;
		return $this;
	}

	public function setServerEnvironent($env, $request_body = ''){
		parent::setServerEnvironent($env);

		$this->username = null;
		$this->entity_body = $request_body;

		$digest = null;
		if (isset($env['PHP_AUTH_DIGEST'])) {
	        $digest = $env['PHP_AUTH_DIGEST'];
		} elseif (isset($env['HTTP_AUTHENTICATION'])) {
			if(strpos(strtolower($env['HTTP_AUTHENTICATION']),'digest') === 0){
				$digest = substr($env['HTTP_AUTHORIZATION'], 7);
			}
		}
		$attribs = $this->parseAttributeString($digest, array('username', 'realm', 'uri', 'algorithm', 'nonce', 'opaque', 'cnonce', 'qop', 'nc', 'response'));
		foreach($attribs as $k=>$v){
			$this->$k = $v;
		}
		$this->algorithm = $this->algorithm?strtolower($this->algorithm):'md5';
		$this->qop = $this->qop?strtolower($this->qop):null;
		$this->method = $env['REQUEST_METHOD'];
		return $this;
	}

	protected function parseAttributeString($str, $attributes){ // regexp from http://php.net/manual/de/features.http-auth.php
		$attr_match = implode('|', $attributes);
		$data = array();
		preg_match_all('/('.$attr_match.')=(?:([\'"])([^\2]+?)\2|([^\s,]+))/', $str, $matches, PREG_SET_ORDER);
		foreach($matches as $match){
			$key = $match[1];
			$value = isset($match[4])?$match[4]:$match[3];
			$data[$key] = $value;
		}
		foreach($attributes as $a){ // make sure every attribute is set
			if(!array_key_exists($a, $data)){
				$data[$a] = null;
			}
		}
		return $data;
	}

	public function getUsername(){
		if($this->username === null){
			throw new HttpAuthException("no user specified", HttpAuthException::USER_INVALID);
		}
		return $this->username;
	}

	public function getCredentials(){
		if($this->password === null){
			throw new HttpAuthException("no password specified", HttpAuthException::CREDENTIALS_INVALID);
		}
		return $this->password;
	}

	protected function validateData(){
		if(!$this->validateQop()){
			throw new HttpAuthException("Qop is invalid", HttpAuthException::MISC);
		}
		if(!$this->validateAlgorithm()){
			throw new HttpAuthException("Algorithm is invalid", HttpAuthException::MISC);
		}
		if(!$this->validateNonce($this->nonce)){
			$this->is_stale = true;
			throw new HttpAuthException("Nonce is invalid or expired", HttpAuthException::MISC);
		}
	}

	public function authenticate($username, $credentials, $credential_type = self::CREDENTIALS_PLAIN){
		$credential_type = $credential_type===null?self::CREDENTIALS_HASHED:$credential_type;

		$this->validateData();

		switch($credential_type){
			case self::CREDENTIALS_PLAIN:
				$ha1 = $this->hash("$username:$this->send_realm:$credentials");
				break;
			case self::CREDENTIALS_DIGEST:
				$ha1 = $credentials;
				break;
			default:
				throw new HttpAuthException("unsupported credentials type", HttpAuthException::CREDENTIALS_INVALID);
		}

		if($this->algorithm == self::ALGO_MD5_SESS){
			$a1 = $ha1.":$this->nonce:$this->cnonce";
			$ha1 = $this->hash($a1);
		}
		$this->ha1 = $ha1;

		$a2 = "$this->method:$this->uri";
		if($this->qop == self::QOP_AUTH_INT){
			$a2 .= ':'.$this->hash($this->entity_body);
		}

		if($this->qop != self::QOP_NONE){
			$digest = $this->hash("$ha1:$this->nonce:$this->nc:$this->cnonce:$this->qop:".$this->hash($a2));
		} else {
			$digest = $this->hash("$ha1:$this->nonce:".$this->hash($a2));
		}
		if($digest != $this->response){
			throw new HttpAuthException("Digest is invalid ($digest != $this->response)", HttpAuthException::CREDENTIALS_INVALID);
		}
	}

	public function hash($value){
		switch(strtolower($this->algorithm)){
			case self::ALGO_MD5:
			case self::ALGO_MD5_SESS:
				return md5($value);
			default:
				throw new HttpAuthException("unsupported algorithm '$this->algorithm'", HttpAuthException::MISC);
		}
	}

	public function sendFailedHeaders(){
		$nonce = $this->generateNonce();

		$options = array();
		$options[] = 'realm="'.$this->send_realm.'"';
		$qops = implode(',', array_diff($this->available_qops, array(self::QOP_NONE)));
		if($qops){
			$options[] = 'qop="'.$qops.'"';
		}
		$options[] = 'algorithm="'.$this->send_algorithm.'"';
		$options[] = 'nonce="'.$nonce.'"';
		if($this->opaque){
			$options[] = 'opaque="'.$this->opaque.'"'; // just pasthrough this if existing
		}
		if($this->is_stale){
			$options[] = 'stale=TRUE'; // stale means, the once was invalid, but everything ales ok, so the ua just encrypt a new message
		}

		$header = 'WWW-Authenticate: Digest '.implode(', ', $options);
		header($header, false, 401);
	}

	public function sendSuccessHeaders($response_body = null){
		if(!$this->send_authentication_info){
			return;
		}
		$options = array();

		if($this->qop == self::QOP_AUTH_INT AND $response_body !== null){ // we can only use auth-int if we know the message body
			$qop = self::QOP_AUTH_INT;
			$a2 = ':'.$this->uri.':'.$this->hash($response_body);
		} else {
			$qop = self::QOP_AUTH;
			$a2 = ':'.$this->uri;
		}
		$digest = $this->hash( "$this->ha1:$this->nonce:$this->nc:$this->cnonce:$qop:".$this->hash($a2));

		if($this->regenerate_nonce){
			$options[] = 'nextnonce="'.$this->generateNonce().'"';
		}
		if($this->qop){ // only send if qop is present in request, because cnonce und nc are required for response authenticiation
			$options[] = 'qop="'.$qop.'"';
			$options[] = 'cnonce="'.$this->cnonce.'"';
			$options[] = 'nc="'.$this->nc.'"';
			$options[] = 'rspauth="'.$digest.'"';
		}
		if(!empty($options)){
			$header = 'Authentication-Info: '.implode(', ', $options);
			header($header);
		}
	}


	protected function validateAlgorithm(){
		return $this->algorithm ==$this->send_algorithm;
	}

	protected function validateQop(){
		return in_array($this->qop, $this->available_qops);
	}

	protected function generateNonce(){
		// rfc2617 suggests timestamp + hash(timestamp.etag.privkey), see http://tools.ietf.org/html/rfc2617#section-1
		// however etag is not known for generated data
		$r = $this->getRandom();
		if($this->nonce_max_age){
			$r = time().'-'.$r;
		}
		$h = sha1($r.$this->nonce_salt);
		$nonce = $r.':'.$h;
		if($this->nonce_session_directory){
			$this->storeNonce($nonce);
		}
		return $nonce;
	}

	protected function validateNonce(){
		$nonce = $this->nonce;
		@list($r,$h) = explode(':', $nonce);
		if($this->nonce_max_age){
			@list($ts) = explode('-', $r);
			if($ts + $this->nonce_max_age < time()){
				error_log("nonce invalid, reason: age");
				return false;
			}
			if($ts + $this->nonce_max_age*2/3 < time()){ //send a new nonce with the success header of time runs out
				$this->regenerate_nonce = true;
			}
		}
		if($h !== sha1($r.$this->nonce_salt)){
			error_log("nonce invalid, reason: hash");
			return false;
		}
		if($this->nonce_session_directory){
			$nc = $this->getStoredNonceCount($nonce);
			if($nc === false){ // not found in file
				error_log("nonce invalid, reason: unknown");
				return false;
			}
			if($this->qop AND hexdec($this->nc) <= hexdec($nc)){ // check if this nonce with this nc has been used before
				error_log("nonce invalid, reason: repeated nc");
				return false;
			}
			if($this->nonce_max_uses AND $this->nonce_max_uses < hexdec($this->nc)){ // check if nc is bigger than allowed
				error_log("nonce invalid, reason: used too often");
				return false;
			}
			if($this->nonce_max_uses AND $this->nonce_max_uses*2/3 < hexdec($this->nc)){ // refresh if about to be used up
				$this->regenerate_nonce = true;
			}

			$this->storeNonce($nonce, $this->nc); // update
		}
		return true;
	}

	protected function storeNonce($nonce, $nc='0'){
		@list($r) = explode(':', $nonce);
		if($r AND ($filename = $this->getNonceFile($r)) !== false){
			$data = array($nonce, (string) $nc);
			file_put_contents($filename, serialize($data));
		}
		if( rand(0,$this->nonce_gc_divisor) <= $this->nonce_gc_probability){
			$this->cleanNonceFiles();
		}
	}

	protected function getStoredNonceCount($nonce){
		@list($r) = explode(':', $nonce);
		if($r){
			$file = $this->getNonceFile($r);
			$data = unserialize(file_get_contents($file));
			if(!empty($data) and $data[0] == $nonce){
				return (string) $data[1];
			}
		}
		return false;
	}

	protected function cleanNonceFiles(){
		if(!$this->nonce_session_directory){
			return;
		}
		$dh  = opendir($this->nonce_session_directory);
		$now = time();
		while (false !== ($file = readdir($dh))) {
			$filename = $this->nonce_session_directory.DIRECTORY_SEPARATOR.$file;
			if(is_dir($filename)){
				continue;
			}
			if(substr($file,0,6) == 'hsess_' AND filemtime($filename) + $this->nonce_max_age < $now){
				unlink($filename);
			}
		}
	}

	protected function getNonceFile($r){
		return $this->nonce_session_directory.DIRECTORY_SEPARATOR.'hsess_'.$r;
	}
}