var fs = require('fs'),
    watch = require('watch'),
    request = require('request'),
    parser = require('xml2json'),
    spawn = require('child_process').spawn,
    path = require('path'),
    urlJeedom =process.env.URL,
    name =process.env.NAME,
    config = {
  interface: 'wlan0',
  dumpName: 'dump',
  endpoint: urlJeedom,
};

var requestOptions = {
  method: 'post',
  json: true,
  url: config.endpoint
};

function init() {
  console.log('Attempt to execute airodump-ng');

  //ls.stdout.pipe(process.stdout);

  var cmd = spawn('airodump-ng', [
    '-w ' + config.dumpName,
    config.interface
  ], {cwd: '/tmp/airmon'});

  //var cmd = spawn('top',['-l 0']);
  //console.log(cmd.connected);

  cmd.stdout.on('data', function (data) {
    //console.log('stdout: ' + data);
  });

  cmd.stderr.on('data', function (data) {
    //console.log('stderr: ' + data);
  });

  cmd.on('close', function (code) {
    console.log('child process exited with code ' + code + '. Make sure your wifi device is set to monitor mode.');
  });

  // TODO: Start this when cmd is connected instead of on a timeout
  setTimeout(function() {startWatching();}, 5000);
}


function startWatching() {
  console.log('Watching for changes to airodump data');

  // Watch for file changes in data folder
  watch.createMonitor('/tmp/airmon', function (monitor) {
    monitor.on('changed', function (file, curr, prev) {

      // Filter out netxml files
      if (path.extname(file) === '.netxml') {
        parseData(file);
      }

    });
  });
}


function parseData(file) {
  console.log('Parsing data for: ' + file);

  try {
    var xml = fs.readFileSync(file),
      data = parser.toJson(xml, {
        sanitize: true
      });

    postData(data);
  } catch(e) {
    console.log('There was an error parsing your xml');
    console.log(e);
  }

}


function postData(json) {
  console.log('Posting data to: ' + config.endpoint);

  try {
    requestOptions.body = JSON.parse(json);

    request(requestOptions, function(err, response, body) {
      if (err) throw err
      console.log('Response from API -------------------');
      console.log(body);
    });
  } catch (e) {
    console.log('There was an error in the request to the API');
    console.log(e);
  }

}
