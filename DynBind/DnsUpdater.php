<?php
/**
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
*/
require_once 'DynBind/DnsEntry.php';

abstract class DnsUpdater{
	public abstract function update(DnsEntry $entry);
}