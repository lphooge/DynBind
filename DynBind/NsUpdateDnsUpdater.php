<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
require_once 'DnsUpdater.php';

/**
 * @see http://linux.yyz.us/nsupdate/
 * Class for Updating an DNS Server
 *
 */
class NsUpdateDnsUpdater extends DnsUpdater{

	public $nameserver = "ns.example.invalid";
	public $zone = "dyn.example.invalid";
	public $keyfile = null;
	public $dryrun = false;
	public $ttl = 3600;

	public function __construct($nameserver, $zone, $keyfile){
		$this->nameserver = $nameserver;
		$this->zone = $zone;
		$this->keyfile = $keyfile;
	}

	public function update(DnsEntry $entry){
		try{
			$entry->validate();
		} catch(Exception $e){
			log::write('rejected invalid dns entry: '.$e->getMessage(), 2);
			return new UpdateStatus(UpdateStatus::STATUS_UPDATE_ERROR, $entry);
		}

		$updatefile = tempnam(sys_get_temp_dir(),'zupd-');
		$nl = "\n";
		$ttl = $entry->ttl?$entry->ttl:$this->ttl;
		$update =
			"server $this->nameserver".$nl.
			"zone $this->zone".$nl.
			"update delete $entry->name $entry->type".$nl.
			"update add $entry->name $ttl $entry->type $entry->entry".$nl.
			"show".$nl.
			"send".$nl.
			"";
		if(!file_put_contents($updatefile, $update)){
			log::write("could not write temp-file for nsupdate ($updatefile)", 1);
			return new UpdateStatus(UpdateStatus::STATUS_UPDATE_ERROR, $entry);
		}
		$cmd = 'nsupdate '.($this->keyfile?'-k '.escapeshellarg($this->keyfile):'').' -v '.escapeshellarg($updatefile);
		if($this->dryrun){
			log::write($cmd, 3);
			log::write($update, 2);
			unlink($updatefile);
			return new UpdateStatus(UpdateStatus::STATUS_NO_CHANGE, $entry);
		} else {
			log::write($cmd, 3);
			log::write($update, 3);
			exec($cmd, $output, $return_var);
			unlink($updatefile);
			log::write(implode("\n",$output), 2);
			if($return_var == 0){
				return new UpdateStatus(UpdateStatus::STATUS_SUCCESS, $entry);
			}
			log::write("nsupdate returned error code $return_var", 1);
		}
		return new UpdateStatus(UpdateStatus::STATUS_INTERNAL_ERROR, $entry);
	}
}