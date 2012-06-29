<?php
/**
 * Insert as followed into FritzBox: somedomain.net/path/dynbind.php?hostname=<domain>&myip=<ipaddr>
 * user and password will be automatically send
 *
 *
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
 */

require_once 'DynBind/Config.php';
require_once 'DynBind/NsUpdateDnsUpdater.php';
require_once 'DynBind/DynDotComProtocol.php';

$conf = new Config();
foreach(array('dynbind.conf.xml', '/etc/dynbind.conf.xml') as $conffile){
	try{
		$conf->load($conffile);
		break;
	} catch(Exception $e){
		continue;
	}
}
if(!$conf->isLoaded()){
	die(UpdateStatus::STATUS_INTERNAL_ERROR." - service not configured");
}

// Init DNS-Update Tool
$dnsupdater = new NsUpdateDnsUpdater($conf->getNameserver(), $conf->getZone(), $conf->getKeyfile());
$dnsupdater->logfile = $conf->getLogfile();
$dnsupdater->dryrun = $conf->getDryrun();

// Init Input Protocols
$protocol = new DynDotComProtocol($conf);
$protocol->setAuthMethod($conf->getAuthMethod()=='basic'?DynDotComProtocol::AUTH_BASIC:DynDotComProtocol::AUTH_DIGEST);

try{
	$protocol->parseRequest($_SERVER, $_GET, $_POST);

	$user = $protocol->getUser();

	$update_stati = array();
	foreach($protocol->getEntries() as $entry){
		if($user->ownsDnsEntry($entry)){
			$update_stati[] = $dnsupdater->update($entry);
		} else {
			$update_stati[] = new UpdateStatus(UpdateStatus::STATUS_AUTH_ERROR, $entry);
		}
	}
	$protocol->answerRequest($update_stati);
	exit();
} catch(Exception $e){
	if($e->getCode() == UpdateStatus::STATUS_UNHANDLED_REQUEST){
		continue;
	} else {
		$protocol->answerRequest(array(new UpdateStatus($e->getCode())));
		exit();
	}
}

echo $e->getMessage(); // FIXME: log instead
