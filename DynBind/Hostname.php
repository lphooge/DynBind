<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
class Hostname{
	public $name = null;

	public function __construct($name = null){
		if($name){
			$this->name = $name;
			$this->validate();
		}
	}

	public function validate(){
		validate::host($this->name.'.', validate::HOST_FQDN_YES);
	}

	public function __toString(){
		return (string) $this->name;
	}

	public function isWildCard(){
		return strlen($this->name)>0 AND $this->name{0} == '*';
	}

	/**
	 * Checks of this host is contained in $super. Super must be a wildcard host or equal to $this
	 *
	 * @param Hostname $super
	 * return bool
	 */
	public function isContainedIn(Hostname $super){
		// special case: equal
		if($this->name === $super->name){
			return true;
		}

		// if super isn't a wildcard and not equal to this it cant be true
		if(!$super->isWildCard()){
			return false;
		}

		if(strlen($this->name) < strlen($super->name)){
			return false;
		}

		$matchlength = strlen($super) - 1;
		$this_reverse = strrev($this->name);
		$super_reverse = strrev($super->name);

		if(substr($this_reverse,0,$matchlength) !== substr($super_reverse,0,$matchlength)){
			return false;
		}
		return true;
	}
}