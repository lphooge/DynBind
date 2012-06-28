<?php
require_once 'Hostname.php';
class User{
	public $name = null;
	public $credentials = null;
	protected $hostnames = array();

	public function __construct($name = null, $credentials = null, $hostnames = array()){
		if($name){
			$this->name = $name;
		}
		if($credentials){
			$this->credentials = $credentials;
		}
		$this->addHostnames($hostnames);
	}

	public function removeHostNames(){
		$this->hostnames = array();
	}

	public function addHostNames($hostnames){
		if(!is_array($hostnames)){
			$hostnames = array($hostnames);
		}
		foreach($hostnames as $hostname){
			try{
				if(is_string($hostname)){
					$hostname = new Hostname($hostname);
				}
				if($hostname instanceof Hostname){
					$hostname->validate();
					$this->hostnames[] = $hostname;
				}
			} catch(Exception $e){
				// todo: logging?
			}
		}
	}

	public function validate(){
		foreach($this->hostnames as $hostname){
			$hostname->validate();
		}
	}

	public function ownsHost(Hostname $host){
		foreach($this->hostnames as $hostname){
			if($host->isContainedIn($hostname)){
				return true;
			}
		}
		return false;
	}

	public function ownsDnsEntry(DnsEntry $entry){
		try{
			return $this->ownsHost($entry->toHostname());
		} catch(Exception $e){
			return false;
		}
	}

	public function __toString(){
		return (string) $this->name;
	}
}