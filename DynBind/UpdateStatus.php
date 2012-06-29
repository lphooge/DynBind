<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
class UpdateStatus{
	const STATUS_SUCCESS = 1; // ok
	const STATUS_NO_CHANGE = 8; // ok, but nothing changed
	const STATUS_AUTH_ERROR = 2; // user/password wrong
	const STATUS_PERMISSION_ERROR = 3; // login ok, but update not allowed
	const STATUS_UPDATE_ERROR = 4; // Updating the DNS record failed
	const STATUS_UPDATE_PARTIAL_ERROR = 5; // Some operations failed
	const STATUS_UNHANDLED_REQUEST = 6; // didn't know what to do with the data
	const DNSENTRY_INVALID = 7; // Invalid or unsupported DNS entry
	const STATUS_INTERNAL_ERROR = 0; // misc internal error

	public function __construct($code, $entry = null){
		$this->statuscode = $code;
		$this->entry = $entry;
	}

	/**
	 * return DnsEntry
	 */
	public function getEntry(){
		if($this->entry instanceof DnsEntry){
			return $this->entry;
		}
		throw new Exception("Error $this->statuscode has no assigned DnsEntry", self::STATUS_INTERNAL_ERROR);
	}

	/**
	 * @var DnsEntry
	 */
	public $entry;

	/**
	 * @var int
	 */
	public $statuscode;
}
