<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
class HttpAuthException extends Exception{
	const MISC = 0;
	const USER_INVALID = 2;
	const CREDENTIALS_INVALID = 3;
}