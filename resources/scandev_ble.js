var request = require('request');
var noble = require('noble');
var urlJeedom = process.env.URL;
var name = process.env.NAME;

var RSSI_THRESHOLD    = -100;
var EXIT_GRACE_PERIOD = 30000; // milliseconds

var inRange = [];

noble.on('discover', function(peripheral) {
  if (peripheral.rssi < RSSI_THRESHOLD) {
    // ignore
    return;
  }
  var id = peripheral.id;
  var entered = !inRange[id];
  if (entered) {
    inRange[id] = {
      peripheral: peripheral
    };
    console.log('"' + peripheral.advertisement.localName + '" entered (RSSI ' + peripheral.rssi + ') ' + new Date());
		request({
			url: urlJeedom,
			method: 'PUT',
			json: {"device": peripheral.advertisement.localName,
      "rssi": peripheral.rssi,
			"status": "1",
      "address": peripheral.address,
			"type": "ble",
			"scanner": name,
      },
		},
		function (error, response, body) {
			  if (!error && response.statusCode == 200) {
				//console.log( response.statusCode);
			  }else{
			  	console.log( error );
			  }
			});
  }
  inRange[id].lastSeen = Date.now();
});

setInterval(function() {
  for (var id in inRange) {
    if (inRange[id].lastSeen < (Date.now() - EXIT_GRACE_PERIOD)) {
      var peripheral = inRange[id].peripheral;

      console.log('"' + peripheral.advertisement.localName + '" exited (RSSI ' + peripheral.rssi + ') ' + new Date());
      console.log('"' + peripheral.advertisement.localName + '" entered (RSSI ' + peripheral.rssi + ') ' + new Date());

  		request({
  			url: urlJeedom,
  			method: 'PUT',
  			json: {"device": peripheral.advertisement.localName,
        "rssi": "0",
				"status": "0",
        "address": peripheral.address,
				"type": "ble",
				"scanner": name,
        },
  		},
  		function (error, response, body) {
  			  if (!error && response.statusCode == 200) {
  				//console.log( response.statusCode);
  			  }else{
  			  	console.log( error );
  			  }
  			});
      delete inRange[id];
    }
  }
}, EXIT_GRACE_PERIOD / 2);

noble.on('stateChange', function(state) {
  if (state === 'poweredOn') {
    noble.startScanning([], true);
  } else {
    noble.stopScanning();
  }
});
