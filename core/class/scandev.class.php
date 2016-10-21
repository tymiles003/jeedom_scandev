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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class scandev extends eqLogic {
  public static function deamon_info() {
    $return = array();
    //$return['log'] = 'scandev_node';
    $return['state'] = 'nok';
    $pid = trim( shell_exec ('ps ax | grep "/opt/jeedom_scandev" | grep -v "grep" | wc -l') );
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
    log::add('scandev', 'info', 'Lancement des démons scandev');
    $url = 'URL=http://127.0.0.1' . config::byKey('internalComplement') . '/plugins/airmon/core/api/jeeScandev.php?apikey=' . jeedom::getApiKey('scandev');

    if (config::byKey('portble', 'scandev',0) != '0') {
      $name = 'NAME=blemaster';
      $port = 'NOBLE_HCI_DEVICE_ID=' . str_replace('hci', '', jeedom::getBluetoothMapping(config::byKey('portble', 'scandev',0)));
      $cmd = $port . ' ' . $url . ' ' . $name . ' ' . 'nodejs /opt/jeedom_scandev/scandev_ble.js';
      scandev::execute_service('ble', $cmd);
    }
    if (config::byKey('portwifi', 'scandev',0) != '0') {
      $name = 'NAME=wifimaster';
      $port = 'WLAN=' . config::byKey('portwifi', 'scandev',0);
      $cmd = $port . ' ' . $url . ' ' . $name . ' ' . 'nodejs /opt/jeedom_scandev/scandev_wifi.js';
      scandev::execute_service('wifi', $cmd);
    }
  }

  public static function execute_service($service, $cmd) {
    log::add('scandev', 'info', 'Lancement service ' . $service);
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
    exec('kill $(ps aux | grep "/opt/jeedom_scandev" | awk \'{print $2}\')');
    log::add('scandev', 'info', 'Arrêt des services scandev');
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
      sleep(1);
      exec('kill -9 $(ps aux | grep "/opt/jeedom_scandev" | awk \'{print $2}\')');
    }
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
      sleep(1);
      exec('sudo kill -9 $(ps aux | grep "/opt/jeedom_scandev" | awk \'{print $2}\')');
    }
  }

  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'scandev_dep';
    $return['progress_file'] = '/tmp/scandev_dep';
    $noble = '/opt/jeedom_scandev/node_modules/noble');
    $request = '/opt/jeedom_scandev/node_modules/request');
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
}

class scandevCmd extends cmd {
}
