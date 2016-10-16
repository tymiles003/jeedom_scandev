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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class scandev extends eqLogic {


  public static function deamon_info() {
    $return = array();
    $return['log'] = 'scandev_node';
    $return['state'] = 'nok';
    $pid = trim( shell_exec ('ps ax | grep "scandev/node/scandev.js" | grep -v "grep" | wc -l') );
    if ($pid != '' && $pid != '0') {
      $return['state'] = 'ok';
    }
    $return['launchable'] = 'ok';
    return $return;
  }

  public static function deamon_start($_debug = false) {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    log::add('scandev', 'info', 'Lancement du démon scandev');

    $service_path = realpath(dirname(__FILE__) . '/../../node/');

    $port = str_replace('hci', '', jeedom::getBluetoothMapping(config::byKey('port', 'scandev',0)));

    if (!config::byKey('internalPort')) {
      $url = config::byKey('internalProtocol') . '127.0.0.1' . config::byKey('internalComplement') . '/core/api/jeeApi.php?api=' . config::byKey('api');
    } else {
      $url = config::byKey('internalProtocol') . '127.0.0.1' . ':' . config::byKey('internalPort') . config::byKey('internalComplement') . '/core/api/jeeApi.php?api=' . config::byKey('api');
    }
    $name = 'master';

    $cmd = 'nodejs ' . $service_path . '/scandev.js "' . $url . '" "' . $name . '"';
    $cmd = 'NOBLE_HCI_DEVICE_ID=' . $port . ' ' . $cmd;

    log::add('scandev', 'debug', $cmd);
    $result = exec('sudo ' . $cmd . ' >> ' . log::getPathToLog('scandev_node') . ' 2>&1 &');
    if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
      log::add('scandev', 'error', $result);
      return false;
    }

    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add('scandev', 'error', 'Impossible de lancer le démon scandev, vérifiez le port', 'unableStartDeamon');
      return false;
    }
    message::removeAll('scandev', 'unableStartDeamon');
    log::add('scandev', 'info', 'Démon scandev lancé');
    return true;

  }

  public static function deamon_stop() {
    exec('kill $(ps aux | grep "scandev/node/scandev.js" | awk \'{print $2}\')');
    log::add('scandev', 'info', 'Arrêt du service scandev');
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
      sleep(1);
      exec('kill -9 $(ps aux | grep "scandev/node/scandev.js" | awk \'{print $2}\')');
    }
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
      sleep(1);
      exec('sudo kill -9 $(ps aux | grep "scandev/node/scandev.js" | awk \'{print $2}\')');
    }
  }

  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'scandev_dep';
    $return['progress_file'] = '/tmp/scandev_dep';
    $noble = realpath(dirname(__FILE__) . '/../../node/node_modules/noble');
    $request = realpath(dirname(__FILE__) . '/../../node/node_modules/request');
    $return['progress_file'] = '/tmp/scandev_dep';
    if (is_dir($noble) && is_dir($request)) {
      $return['state'] = 'ok';
    } else {
      $return['state'] = 'nok';
    }
    return $return;
  }

  public static function dependancy_install() {
    log::add('scandev','info','Installation des dépéndances nodejs');
    $resource_path = realpath(dirname(__FILE__) . '/../../resources');
    passthru('/bin/bash ' . $resource_path . '/nodejs.sh ' . $resource_path . ' > ' . log::getPathToLog('scandev_dep') . ' 2>&1 &');
  }

  public static function event() {
    $reader = init('name');
    $id = init('id');
    $json = file_get_contents('php://input');
    log::add('scandev', 'debug', 'Body ' . print_r($json,true));
    $body = json_decode($json, true);
    $rssi = $body['rssi'];
    if (!isset($body['device'])) {
      log::add('scandev', 'debug', 'Equipement sans nom, pas de création');
      die;
    }
    $device = $body['device'];
    $scandev = self::byLogicalId($id, 'scandev');
    if (!is_object($scandev)) {
      if (config::byKey('include_mode','scandev') != 1) {
        return false;
      }
      $scandev = new scandev();
      $scandev->setEqType_name('scandev');
      $scandev->setLogicalId($id);
      $scandev->setConfiguration('addr', $id);
      $scandev->setName($device);
      $scandev->setIsEnable(true);
      event::add('scandev::includeDevice',
      array(
        'state' => $state
      )
    );
  }
  $scandev->setConfiguration('lastCommunication', date('Y-m-d H:i:s'));
  if ($device != $scandev->getConfiguration('device')) {
    $scandev->setConfiguration('device', $device);
  }
  $scandev->save();
  $scandevCmd = scandevCmd::byEqLogicIdAndLogicalId($scandev->getId(),$reader);
  if ($rssi != "off") {
    $value = 1;
  } else {
    $value = 0;
  }
  if (!is_object($scandevCmd)) {
    $scandevCmd = new scandevCmd();
    $scandevCmd->setName($reader);
    $scandevCmd->setEqLogic_id($scandev->getId());
    $scandevCmd->setLogicalId($reader);
    $scandevCmd->setType('info');
    $scandevCmd->setSubType('binary');
  }
  $scandevCmd->setConfiguration('value', $value);
  $scandevCmd->setConfiguration('rssi', $rssi);
  $scandevCmd->setConfiguration('reader', $reader);
  $scandevCmd->save();
  $scandevCmd->event($value);

}

}


class scandevCmd extends cmd {

}
