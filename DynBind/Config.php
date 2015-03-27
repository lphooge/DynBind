<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
require "UserBackend.php";
require "Zone.php";

class Config implements UserBackend {
	protected $xmlfile = null;
	/**
	 * @var SimpleXMLElement
	 */
	protected $sxml = null;

	/**
	 * @var DOMDocument
	 */
	protected $dom = null;

	protected $loaded = false;

	public function __construct($xmlfile=null){
		if($xmlfile){
			$this->load($xmlfile);
		}
	}

	public function isLoaded(){
		return $this->loaded;
	}

	/**
	 * @return SimpleXMLElement
	 */
	protected function getUserElements(){
		return $this->getSXML()->users->children();
	}

	/**
	 * @param SimpleXMLElement $ue
	 * return array of string
	 */
	protected function getUsersHostnames(SimpleXMLElement $ue){
		$hosts = array();
		foreach($ue->xpath('hostname') as $host){
			$hosts[] = (string) $host;
		}
		return $hosts;
	}

	/**
	 * @return array of User
	 */
	public function getUsers(){
		$users = array();
		foreach($this->getUserElements() as $ue){ /* var $ue SimpleXMLElement */
			$name = (string) $ue->attributes()->name;
			$password = (string) $ue->attributes()->password;
			$user = new User($name, $password, $this->getUsersHostnames($ue));
			$users[] = $user;
		}
		return $users;
	}

	/**
	 * @throws Exception
	 * @return User
	 */
	public function searchUserByName($username){
		$_username = str_replace("'", '',$username); // just disallow ' in usernames, as proper escaping doesnt't seem to be reasonably possible
		foreach($this->getSXML()->xpath('/config/users/user[@name="'.$_username.'"]') as $ue){ /* var $ue SimpleXMLElement */
			$name = (string) $ue->attributes()->name;
			$password = (string) $ue->attributes()->password;
			$user = new User($name, $password, $this->getUsersHostnames($ue));
			return $user;
		}
		throw new Exception("User $username not found");
	}

	/**
	 * @return array of Zones
	 */
	public function getZones(){
		$zones = array();
		$global_dryrun = $this->getDryrun();
		foreach($this->getSXML()->zones->children() as $e){ /* var $e SimpleXMLElement */
			$name = (string) $e->attributes()->name;
			$updater = (string) $e->updater;
			$nameserver = (string) $e->nameserver;
			$keyfile = (string) $e->keyfile;
			$ttl = (int) (string) $e->ttl;
			$ttl = $ttl?$ttl:1800;

			$dryrun = $global_dryrun?true:((bool) (int) $e->dryrun);

			$zone = new Zone($name, $nameserver);
			$zone->dryrun = $dryrun;
			if($keyfile){
				$zone->keyfile = $keyfile;
			}
			if($ttl){
				$zone->ttl = $ttl;
			}
			if($updater){
				$zone->updaterclass = $updater;
			}
			$zones[] = $zone;
		}
		return $zones;
	}

	public function getAuthMethods(){
		$methods = array();
		foreach($this->getSXML()->general->authentication->method as $em){
			$methods[] = strtolower(trim((string) $em));
		}
		if(empty($methods)){
			return array('digest', 'basic');
		}
		return $methods;
	}

	public function getAuthRealm(){
		$realm = trim((string) $this->getSXML()->general->authentication->realm);
		return $realm?$realm:'DynBind Dynamic DNS Updater';
	}

	public function getLogfile(){
		return (string) $this->getSXML()->general->logfile;
	}

	public function getLoglevel(){
		$llv = (int) $this->getSXML()->general->loglevel;
		return $llv?$llv:2;
	}

	public function getDryrun(){
		return (bool) (int) $this->getSXML()->general->dryrun;
	}

	public function load($xmlfile){
		$this->loaded = false;
		if(!file_exists($xmlfile)){
			throw new Exception("config file doesn't exist");
		}
		$this->xmlfile = $xmlfile;
		$sxml = simplexml_load_file($xmlfile);
		if(!$sxml){
			throw new Exception("could not load config XML file");
		}
		$this->sxml = $sxml;

		$dom = new DOMDocument();
		$dom->load($xmlfile);
		$this->dom = $dom;
		$this->loaded = true;

		if(!$this->validate($validation_errors)){
			foreach($validation_errors as $error){
				log::write("error in $xmlfile: ".$error, 1);
			}
		}
		return $this->loaded;
	}

	protected function validate(&$errors){
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		$valid = true;
		if(!$this->dom->schemaValidate(dirname(__FILE__).'/../conf.xsd')){
			$valid = false;
			foreach(libxml_get_errors() as $error){
				$errors[] = $error->message;
			}
		}
		libxml_clear_errors();
		return $valid;
	}

	public function save(){
		$this->getSXML()->asXML($this->xmlfile);
	}

	public function getSXML(){
		if($this->sxml instanceof SimpleXMLElement){
			return $this->sxml;
		}
		throw new Exception("Konfiguration ist nicht geladen");
	}
}
