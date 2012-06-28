<?php
require_once 'HttpAuth.php';

class BasicHttpAuth extends HttpAuth{
	protected $username = null;
	protected $password = null;

	public function setServerEnvironent($env, $request_body=''){
		parent::setServerEnvironent($env);
		$this->password = null;
		$this->username = null;
		if(isset($env['PHP_AUTH_USER'])){
			$this->username = $env['PHP_AUTH_USER'];
			if(isset($env['PHP_AUTH_PW'])){
				$this->password = $env['PHP_AUTH_PW'];
			}
		} elseif(isset($env['HTTP_AUTHENTICATION'])){
			if (strpos(strtolower($env['HTTP_AUTHENTICATION']), 'basic' ) === 0){
				@list($username,$password) = explode(':',base64_decode(substr($this->env['HTTP_AUTHORIZATION'], 6)));
				$this->username = $username?$username:null;
				$this->password = $password?$password:null;
			}
		}
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

	public function authenticate($username, $credentials, $credential_type = self::CREDENTIALS_PLAIN){
		$credential_type = $credential_type===null?self::CREDENTIALS_PLAIN:$credential_type;
		switch($credential_type){
			case self::CREDENTIALS_PLAIN:
				if($this->getUsername() !== $username OR $this->getCredentials() !== $credentials){
					throw new HttpAuthException("invalid credentials", HttpAuthException::MISC);
				}
				break;
			case self::CREDENTIALS_DIGEST:
				$ha1 = md5("$username:$this->send_realm:$credentials");
				$in_ha1 = md5($this->getUsername().":$this->send_realm:".$this->getCredentials());
				if($this->getUsername() !== $username OR $ha1 !== $in_ha1){
					throw new HttpAuthException("invalid credentials", HttpAuthException::MISC);
				}
				break;
			default:
				throw new HttpAuthException("unsupported credentials type", HttpAuthException::CREDENTIALS_INVALID);
		}
	}

	public function sendFailedHeaders(){
		header('WWW-Authenticate: Basic realm="'.$this->send_realm.'"', false, 401);
	}

	public function sendSuccessHeaders($response_body=null){
		// nothing to do
	}
}