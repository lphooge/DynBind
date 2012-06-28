<?php
require_once 'DynBind/DnsEntry.php';

abstract class DnsUpdater{
	public abstract function update(DnsEntry $entry);
}