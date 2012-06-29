<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
require_once "User.php";

interface UserBackend {
	/**
	 * searches a user by name, returns the user or throws exception if it isnt found
	 *
	 * @param $username
	 * @throws Exception
	 * return User
	 */
	function searchUserByName($username);

	/**
	 * returns all known Users
	 *
	 * return array
	 */
	function getUsers();
}
