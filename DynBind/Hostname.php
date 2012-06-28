<?php
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
}