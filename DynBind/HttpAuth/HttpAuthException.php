<?php
class HttpAuthException extends Exception{
	const MISC = 0;
	const USER_INVALID = 2;
	const CREDENTIALS_INVALID = 3;
}