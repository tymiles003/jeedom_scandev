<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'scandev')) {
	echo __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (scandev)', __FILE__);
	die();
}

$json = file_get_contents('php://input');
log::add('scandev', 'debug', 'Body ' . print_r($json,true));
$body = json_decode($json, true);
if (!isset($body['device'])) {
	log::add('scandev', 'debug', 'Equipement sans nom, pas de création');
	die;
}
$rssi = $body['rssi'];
$device = $body['device'];
$addr = $body['address'];
$scanner = $body['scanner'];
$type = $body['type'];
$status = $body['status'];
$scandev = scandev::byLogicalId($addr, 'scandev');
if (!is_object($scandev)) {
	if (config::byKey('include_mode','scandev') != 1) {
		return false;
	}
	$scandev = new scandev();
	$scandev->setEqType_name('scandev');
	$scandev->setLogicalId($type . $addr);
	$scandev->setConfiguration('addr', $addr);
	$scandev->setConfiguration('type', $type);
	$scandev->setName($scanner . ' ' . $device);
	$scandev->setIsEnable(true);
	event::add('scandev::includeDevice',
	array(
		'state' => 1
	)
);
}
$scandev->setConfiguration('lastCommunication', date('Y-m-d H:i:s'));
if ($device != $scandev->getConfiguration('device')) {
	$scandev->setConfiguration('device', $device);
}
$scandev->save();
$scandevCmd = scandevCmd::byEqLogicIdAndLogicalId($scandev->getId(),$scanner);
if (!is_object($scandevCmd)) {
	$scandevCmd = new scandevCmd();
	$scandevCmd->setName($scanner);
	$scandevCmd->setEqLogic_id($scandev->getId());
	$scandevCmd->setLogicalId($scanner);
	$scandevCmd->setType('info');
	$scandevCmd->setSubType('binary');
}
$scandevCmd->setConfiguration('value', $status);
$scandevCmd->setConfiguration('reader', $scanner);
$scandevCmd->save();
$scandevCmd->event($status);
$scandevCmd = scandevCmd::byEqLogicIdAndLogicalId($scandev->getId(),$scanner . 'rssi');
if (!is_object($scandevCmd)) {
	$scandevCmd = new scandevCmd();
	$scandevCmd->setName($scanner . ' rssi');
	$scandevCmd->setEqLogic_id($scandev->getId());
	$scandevCmd->setLogicalId($scanner . 'rssi');
	$scandevCmd->setType('info');
	$scandevCmd->setSubType('numeric');
}
$scandevCmd->setConfiguration('value', $rssi);
$scandevCmd->setConfiguration('reader', $scanner);
$scandevCmd->save();
$scandevCmd->event($rssi);

/*
$airmon = airmon::byLogicalId($mac, 'airmon');
if (!is_object($airmon)) {
if (config::byKey('include_mode','airmon') != 1) {
return false;
}
$airmon = new airmon();
$airmon->setEqType_name('airmon');
$airmon->setLogicalId($mac);
$airmon->setName($mac);
$airmon->setIsEnable(true);
$airmon->setConfiguration('mac',$mac);
$airmon->save();
event::add('airmon::includeDevice',
array(
'state' => 1
)
);
}
$airmon->setConfiguration('lastCommunication', date('Y-m-d H:i:s'));
$airmon->save();
$airmonCmd = airmonCmd::byEqLogicIdAndLogicalId($airmon->getId(),$scanner);
if (!is_object($airmonCmd)) {
$airmonCmd = new airmonCmd();
$airmonCmd->setName($scanner);
$airmonCmd->setEqLogic_id($airmon->getId());
$airmonCmd->setLogicalId($scanner);
$airmonCmd->setType('info');
$airmonCmd->setSubType('binary');
$airmonCmd->setConfiguration('returnStateValue',0);
$airmonCmd->setConfiguration('returnStateTime',1);
}
$airmonCmd->setConfiguration('value', 1);
$airmonCmd->save();
$airmonCmd->event(1);
*/

return true;
?>
