<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
require_once 'DynBind/DnsEntry.php';

abstract class DnsUpdater{
	protected $options = array();
	public abstract function update(DnsEntry $entry);
	
	public function setOptions(array $options){
		$this->options = $options;
	}
	
	public function getOption($key){
		return array_key_exists($key, $this->options)?$this->options[$key]:null;
	}
}