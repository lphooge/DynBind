<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
class log {
	const CRITICAL = 0;
	const IMPORTANT = 1;
	const NORMAL = 2;
	const DEBUG = 3;

	public static $loglevel = 2;
	public static $logfile = null;

	protected static $buffer = array();

	public static function setFile($logfile){
		if(!file_exists($logfile) AND !touch($logfile)){
			throw new Exception("cannot create logfile $logfile");
		}
		if(!is_writeable($logfile)){
			throw new Exception("logfile $logfile is not writable");
		}
		self::$logfile = $logfile;
		self::clearbuffer();
	}

	public static function setLevel($loglevel){
		self::$loglevel = (int) $loglevel;
	}

	protected static function clearbuffer(){
		foreach(self::$buffer as $k=>$m){
			self::write($m, self::$loglevel);
			unset(self::$buffer[$k]);
		}
	}

	public static function write($msg, $level=self::NORMAL){
		if($level <= self::$loglevel){
			if(self::$logfile){
				@file_put_contents(self::$logfile, $msg."\n", FILE_APPEND);
			} else {
				self::$buffer[] = $msg;
				if($level >= self::CRITICAL){
					error_log("DynBind critical error: ".$msg);
				}
			}
		}
	}
}
