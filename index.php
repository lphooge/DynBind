<?php
/**
 * Insert as followed into FritzBox: somedomain.net/path/dynbind.php?hostname=<domain>&myip=<ipaddr>
 * user and password will be automatically send
 *
 *
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
 */

require_once 'DynBind/log.php';
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
log::setFile($conf->getLogfile());
log::setLevel($conf->getLoglevel());

// Init Input Protocols
$protocol = new DynDotComProtocol($conf);
foreach($conf->getAuthMethods() as $method){
	$protocol->addAuthMethod($method=='basic'?DynDotComProtocol::AUTH_BASIC:DynDotComProtocol::AUTH_DIGEST);
}
$protocol->setAuthRealm($conf->getAuthRealm());

try{
	$protocol->parseRequest($_SERVER, $_GET, $_POST);

	$user = $protocol->getUser();

	$update_stati = array();
	foreach($protocol->getEntries() as $entry){
		if($user->ownsDnsEntry($entry)){ /* @var $entry DnsEntry */
			foreach($conf->getZones() as $zone){  /* @var $zone Zone */
				if($zone->containsDnsEntry($entry)){
					$update_stati[] = $zone->getUpdater()->update($entry);
					break;
				}
			}
		} else {
			log::write("denied user $user->name updating $entry->name to $entry->entry", 3);
			$update_stati[] = new UpdateStatus(UpdateStatus::STATUS_AUTH_ERROR, $entry);
		}
	}
	$protocol->answerRequest($update_stati);
	log::write("send headers:\n".implode("\n",headers_list()), 4);
	exit();
} catch(Exception $e){
	log::write($e->getMessage(), 3);
	$protocol->answerRequest(array(new UpdateStatus($e->getCode())));
	log::write("send headers:\n".implode("\n",headers_list()), 4);
	exit();
}


