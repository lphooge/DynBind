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
	public $logfile = "log.txt";

	public function __construct($nameserver, $zone, $keyfile){
		$this->nameserver = $nameserver;
		$this->zone = $zone;
		$this->keyfile = $keyfile;
	}

	public function update(DnsEntry $entry){
		try{
			$entry->validate();
		} catch(Exception $e){
			echo $e->getMessage(); // FIXME
			return new UpdateStatus(UpdateStatus::STATUS_UPDATE_ERROR, $entry);
		}

		$updatefile = uniqid('zupd-');
		$nl = "\n";
		$update =
			"server $this->nameserver".$nl.
			"zone $this->zone".$nl.
			"update delete $entry->name $entry->type".$nl.
			"update add $entry->name $entry->ttl $entry->type $entry->entry".$nl.
			"show".$nl.
			"send".$nl.
			"";
		if(!file_put_contents($updatefile, $update)){
			return new UpdateStatus(UpdateStatus::STATUS_UPDATE_ERROR, $entry);
		}
		$cmd = "nsupdate ".($this->keyfile?'-k '.$this->keyfile:'')." -v $updatefile";
		if($this->dryrun){
			if($this->logfile){
				file_put_contents($this->logfile, $update, FILE_APPEND);
				file_put_contents($this->logfile, $cmd."\n", FILE_APPEND);
			}
			unlink($updatefile);
			return new UpdateStatus(UpdateStatus::STATUS_NO_CHANGE, $entry);
		} else {
			exec($cmd, $output, $return_var);
			unlink($updatefile);
			if($this->logfile){
				file_put_contents($this->logfile, implode("\n",$output)."\n", FILE_APPEND);
			}
			if($return_var == 0){
				return new UpdateStatus(UpdateStatus::STATUS_SUCCESS, $entry);
			}
		}
		return new UpdateStatus(UpdateStatus::STATUS_INTERNAL_ERROR, $entry);
	}
}