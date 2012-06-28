<?php
class validate{

	const HOST_FQDN_OK = 0;
	const HOST_FQDN_YES = 1;
	const HOST_FQDN_NO = 2;

	static function isempty($value, $errormsg = "field is not empty", $errorcode=0){
		if($value === false or $value==='' or $value===null){
			return true;
		}
		throw new Exception($errormsg, $errorcode);
	}
	static function eq($value, $expected_value, $errormsg = "unexpected value", $errorcode=0){
		if($value === $expected_value){
			return true;
		}
		throw new Exception($errormsg, $errorcode);
	}

	static function int_between($value, $start, $end, $errormsg = "unexpected value", $errorcode=0){
		if((int) $value === $value){
			if($value>=$start AND $value <=$end){
				return true;
			}
		}
		throw new Exception($errormsg, $errorcode);
	}

	static function host($value, $fqdn = self::HOST_FQDN_OK, $errormsg = "unexpected value", $errorcode=0){
		$value = (string) $value;
		$parts = explode('.', $value);
		$n = count($parts);
		$is_fqdn = false;
		if($n > 1 AND $parts[$n-1] == ''){ // ending with . => (fqdn)
			unset($parts[$n-1]);
			$n--;
			$is_fqdn = true;
		}
		if(!$is_fqdn AND $fqdn == self::HOST_FQDN_YES){
			throw new Exception($errormsg, $errorcode);
		}
		if($is_fqdn AND $fqdn == self::HOST_FQDN_NO){
			throw new Exception($errormsg, $errorcode);
		}
		foreach($parts as $i=>$part){
			if($part == ''){
				throw new Exception($errormsg, $errorcode);
			}
			if($part == '*' AND $i==0){
				continue;
			}
			if(!preg_match('/^[a-z][a-z0-9\-]*$/mi', $part)){
				throw new Exception($errormsg, $errorcode);
			}
		}
		return true;
	}
}