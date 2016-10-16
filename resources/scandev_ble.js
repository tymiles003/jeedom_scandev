var request = require('request');

var urlJeedom = '';
var name = '';

process.env.NODE_TLS_REJECT_UNAUTHORIZED = "0";

// print process.argv
process.argv.forEach(function(val, index, array) {

	switch ( index ) {
		case 2 : urlJeedom = val; break;
		case 3 : name = val; break;
	}

});
var noble = require('noble');

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
    url = urlJeedom + "&type=btsniffer&name=" + name + "&id=" + peripheral.address;

		request({
			url: url,
			method: 'PUT',
			json: {"device": peripheral.advertisement.localName,
      "rssi": peripheral.rssi,
      "address": peripheral.address,
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
      url = urlJeedom + "&type=btsniffer&name=" + name + "&id=" + peripheral.address;

  		request({
  			url: url,
  			method: 'PUT',
  			json: {"device": peripheral.advertisement.localName,
        "rssi": "off",
        "address": peripheral.address,
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
