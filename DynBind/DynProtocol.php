<?php
require_once "UserBackend.php";
require_once "User.php";

abstract class DynProtocol{
	protected $password = null;
	protected $user = null;
	protected $entries = array();

	protected $userbackend = null;

	public function __construct(UserBackend  $userbackend){
		$this->userbackend = $userbackend;
	}

	/**
	 * @return UserBackend
	 */
	public function getUserBackend(){
		if(!$this->userbackend instanceof UserBackend){
			throw new Exception("no user backend found");
		}
		return $this->userbackend;
	}

	/**
	 * Parse the Request
	 * @param $env $_SERVER
	 * @param $get $_GET
	 * @param $post $_POST
	 */
	public abstract function parseRequest($env=array(), $get=array(), $post=array());

	/**
	 * Returns the given Password
	 *
	 * @return User
	 */
	public function getUser(){return $this->user;}

	/**
	 * Returns DnsEntries that the user wants to updated
	 *
	 * @return array of DnsEntry
	 */
	public function getEntries(){return $this->entries;}

	/**
	 * Output an apropriate answer to the request, for given statuses
	 *
	 * @param array $statusmsgs
	 */
	public abstract function answerRequest($statusmsgs);
}