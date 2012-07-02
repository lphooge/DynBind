<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
require_once 'validate.php';
class DnsEntry{

	const TTL_MIN = 10;
	const TTL_MAX = 99999999;

	public function __construct($name=null, $entry=null, $type=null){
		$this->name = $name;
		$this->entry = $entry;
		if($type){
			$this->type = $type;
		}
	}
	public $name = null; // eg hostname
	public $entry = null; // eg IP adress, adress for A records
	public $class = 'IN';
	public $type = 'A';
	public $ttl = null; // null = default

	public $pref = null; // only for MX

	public function validate(){
		switch($this->type){
			case 'A':
				if(!filter_var($this->entry, FILTER_VALIDATE_IP, array(FILTER_FLAG_IPV4))){
					throw new Exception("entry is not a IPv4 adress", UpdateStatus::DNSENTRY_INVALID);
				}
				validate::eq($this->class, "IN", "class must be IN", UpdateStatus::DNSENTRY_INVALID);
				if($this->ttl !== null)
					validate::int_between($this->ttl, self::TTL_MIN, self::TTL_MAX, "TTL must between ".self::TTL_MIN." and ".self::TTL_MAX." seks", UpdateStatus::DNSENTRY_INVALID);
				validate::host($this->name, validate::HOST_FQDN_OK, "invalid host: $this->name", UpdateStatus::DNSENTRY_INVALID);
				break;
			case 'AAA':
				if(!filter_var($this->entry, FILTER_VALIDATE_IP, array(FILTER_FLAG_IPV6))){
					throw new Exception("entry $this->entry is not a IPv4 adress",UpdateStatus::DNSENTRY_INVALID);
				}
				validate::eq($this->class, "IN", "class must be IN", UpdateStatus::DNSENTRY_INVALID);
				if($this->ttl !== null)
					validate::int_between($this->ttl, self::TTL_MIN, self::TTL_MAX, "TTL must between ".self::TTL_MIN." and ".self::TTL_MAX." seks", UpdateStatus::DNSENTRY_INVALID);
				validate::host($this->name, validate::HOST_FQDN_OK, "invalid host: $this->name", UpdateStatus::DNSENTRY_INVALID);
				break;
			case 'CNAME':
				validate::host($this->entry, validate::HOST_FQDN_OK, "invalid host: $this->name", UpdateStatus::DNSENTRY_INVALID);
				validate::eq($this->class, "IN", "class must be IN", UpdateStatus::DNSENTRY_INVALID);
				if($this->ttl !== null)
					validate::int_between($this->ttl, self::TTL_MIN, self::TTL_MAX, "TTL must between ".self::TTL_MIN." and ".self::TTL_MAX." seks", UpdateStatus::DNSENTRY_INVALID);
				validate::host($this->name, validate::HOST_FQDN_OK, "invalid host: $this->name", UpdateStatus::DNSENTRY_INVALID);
			break;
			default:
				throw new Exception("Unsupported Entry Type", UpdateStatus::DNSENTRY_INVALID);
		}
	}

	/**
	 * Returns the name of the entry as a Hostname Object
	 *
	 * @throws Exception
	 * @return Hostname
	 */
	public function toHostname(){
		validate::host($this->name, validate::HOST_FQDN_YES, "dns entry has no hostname", UpdateStatus::DNSENTRY_INVALID);
		return new Hostname(trim($this->name, '.'));
	}
}