<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
require_once 'DynProtocol.php';
require_once 'UpdateStatus.php';
require_once 'HttpAuth/DigestHttpAuth.php';
require_once 'HttpAuth/BasicHttpAuth.php';

/**
 * @see http://dyn.com/support/developers/api/perform-update/
 * Form: http://username:password@members.dyndns.org/nic/update?hostname=yourhostname&myip=ipaddress&wildcard=NOCHG&mx=NOCHG&backmx=NOCHG
 */
class DynDotComProtocol extends DynProtocol {

	const RETURN_GOOD = 'good';
	const RETURN_NO_CHANGE = 'nochg';

	const RETURN_BADAUTH = 'badauth';
	const RETURN_NO_DONATOR = '!donator';
	const RETURN_NOT_FQDN = 'notfqdn';
	const RETURN_NO_HOSTS = 'nohost'; // host not allowed
	const RETURN_TOO_MAY_HOSTS = 'numhost';
	const RETURN_ABUSE = 'abuse';
	const RETURN_BAD_USERAGENT = 'badagent';
	const RETURN_DNS_ERROR = 'dnserr';
	const RETURN_PROBLEM = '911';

	const AUTH_BASIC = 'BasicHttpAuth';
	const AUTH_DIGEST = 'DigestHttpAuth';

	protected $authenticatorClass = self::AUTH_DIGEST;
	protected $authenticator = null;

	public function setAuthMethod($methodClass){
		if(!is_subclass_of($methodClass, 'HttpAuth')){
			throw new Exception("invalid authenticator $methodClass");
		}
		$this->authenticatorClass = $methodClass;
	}

	public function parseRequest($env=array(), $get=array(), $post=array()){
		$this->user = null;

		$this->authenticator = new $this->authenticatorClass($env); // / DigestAuth
		$auth = $this->authenticator;
		$user = $this->getUserBackend()->searchUserByName($auth->getUsername());
		log::write("user {$auth->getUsername()} requests login...", 3);
		$auth->authenticate($user->name, $user->credentials);
		log::write("user {$auth->getUsername()} logged in.", 3);
		$this->user = $user;

		$hosts = empty($get['hostname'])?array():explode(',', $get['hostname']);
		if(empty($hosts)){
			$hosts = empty($post['hostname'])?array():explode(',', $post['hostname']);
		}
		$hosts = array_unique($hosts);
		$ip = empty($get['myip'])?null:$get['myip'];
		if(empty($ip) and !empty($env['HTTP_X_FORWARDED_FOR'])){
			$ip = $env['HTTP_X_FORWARDED_FOR'];
		}
		if(empty($ip) and !empty($env['REMOTE_ADDR'])){
			$ip = $env['REMOTE_ADDR'];
		}

		foreach($hosts as $i=>$host){
			$host = trim($host).'.';
			$hosts[$i] = $host;
		}

		$use_wildcards = false;
		if(!empty($get['wildcard']) or !empty($post['wildcard'])){
			$use_wildcards = true;
		}
		// mx=..., backmax=YES|NOCHG|NO currently ignored

		// offline = YES|NOCHG offline mode currently ignored
		if($ip){
			$this->entries = array();
			foreach($hosts as $host){
				$this->entries[] = new DnsEntry($host, $ip);
				if($use_wildcards){
					$this->entries[] = new DnsEntry('*.'.$host, $host, 'CNAME');
				}
			}
		}

		if(empty($this->entries)){
			throw new Exception("Could not parse Request", UpdateStatus::STATUS_UNHANDLED_REQUEST);
		}
	}

	/**
	 * @param array $statusmsgs
	 */
	public function answerRequest($statusmsgs){
		header('Content-Type: text/plain');
		if(empty($this->user)){
			$this->authenticator->sendFailedHeaders();
		} else {
			$this->authenticator->sendSuccessHeaders();
		}

		foreach($statusmsgs as $statusmsg){ /* var $statusmsg UpdateStatus */
			switch($statusmsg->statuscode){
				case UpdateStatus::STATUS_SUCCESS:
					echo self::RETURN_GOOD."\n";
					break;
				case UpdateStatus::STATUS_NO_CHANGE:
					echo self::RETURN_NO_CHANGE."\n";
					break;
				case UpdateStatus::STATUS_UPDATE_PARTIAL_ERROR:
					echo self::RETURN_GOOD."\n"; // we dont want to block the client because of this
					break;
				case UpdateStatus::STATUS_AUTH_ERROR:
					echo self::RETURN_BADAUTH."\n";
					break;
				case UpdateStatus::STATUS_PERMISSION_ERROR:
					echo self::RETURN_NO_HOSTS."\n";
					break;
				case UpdateStatus::STATUS_UPDATE_ERROR:
					echo self::RETURN_DNS_ERROR."\n";
					break;
				default:
					echo self::RETURN_PROBLEM."\n";
			}
		}
		if(empty($statusmsgs)){
			echo self::RETURN_NO_CHANGE." (no hosts given)\n";
		}
	}
}