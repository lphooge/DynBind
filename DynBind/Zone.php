<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
class Zone{
	public $name = null;
	public $updaterclass = null;
	public $nameserver = null;
	public $keyfile = null;
	public $ttl = null;
	public $dryrun = false; // don't do real updates on this zone

	protected $updater = null;

	public function __construct($name, $nameserver){
		$this->name = $name;
		$this->nameserver = $nameserver;
	}

	/**
	 * @return DnsUpdater
	 */
	public function getUpdater(){
		if($this->updater === null){
			if(empty($this->updaterclass) OR $this->updaterclass == 'NsUpdateDnsUpdater'){
				$dnsupdater = new NsUpdateDnsUpdater($this->nameserver, $this->name, $this->keyfile);
			} else {
				$classname = $this->updaterclass;
				$include_path = dirname(__FILE__).DIRECTORY_SEPARATOR.'Plugins'.DIRECTORY_SEPARATOR.$classname.'.php';
				if(file_exists($include_path)){
					include $include_path;
				}
				if(!class_exists($classname)){
					throw new Exception("Updater type '$classname' was not found");
				}
				$dnsupdater = new $classname($this->nameserver, $this->name, $this->keyfile);
			}
			$dnsupdater->ttl = $this->ttl;
			$dnsupdater->dryrun = $this->dryrun;
			$this->updater = $dnsupdater;
		}
		return $this->updater;
	}


	public function containsHost(Hostname  $host){
		$zonehost = new Hostname('*.'.$this->name);
		return $host->isContainedIn($zonehost);
	}

	public function containsDnsEntry(DnsEntry $entry){
		try{
			return $this->containsHost($entry->toHostname());
		} catch(Exception $e){
			return false;
		}
	}
}