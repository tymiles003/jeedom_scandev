#!/bin/bash
cd $1
touch /tmp/scandev_dep
echo "Début de l'installation"

echo 0 > /tmp/scandev_dep
DIRECTORY="/var/www"
if [ ! -d "$DIRECTORY" ]; then
  echo "Création du home www-data pour npm"
  sudo mkdir $DIRECTORY
fi
sudo chown -R www-data $DIRECTORY
DIRECTORY="/opt/jeedom_scandev"
if [ ! -d "$DIRECTORY" ]; then
  echo "Création du répertoire /opt/jeedom_scandev"
  sudo mkdir $DIRECTORY
fi
cd ../resources/
cp scandev* /opt/jeedom_scandev
sudo chown -R www-data $DIRECTORY
echo 10 > /tmp/scandev_dep
actual=`nodejs -v`;
echo "Version actuelle : ${actual}"

sudo apt-get -y install bluetooth bluez libbluetooth-dev
echo 20 > /tmp/scandev_dep
sudo apt-get -y install aircrack-ng
echo 30 > /tmp/scandev_dep

if [[ $actual == *"4."* || $actual == *"5."* ]]
then
  echo "Ok, version suffisante";
else
  echo "KO, version obsolète à upgrader";
  echo "Suppression du Nodejs existant et installation du paquet recommandé"
  sudo apt-get -y --purge autoremove nodejs npm
  arch=`arch`;
  echo 30 > /tmp/scandev_dep

  if [[ $arch == "armv6l" ]]
  then
    echo "Raspberry 1 détecté, utilisation du paquet pour armv6"
    sudo rm /etc/apt/sources.list.d/nodesource.list
    wget http://node-arm.herokuapp.com/node_latest_armhf.deb
    sudo dpkg -i node_latest_armhf.deb
    sudo ln -s /usr/local/bin/node /usr/local/bin/nodejs
    rm node_latest_armhf.deb
  fi

  if [[ $arch == "aarch64" ]]
  then
    wget http://dietpi.com/downloads/binaries/c2/nodejs_5-1_arm64.deb
    sudo dpkg -i nodejs_5-1_arm64.deb
    sudo ln -s /usr/local/bin/node /usr/local/bin/nodejs
    rm nodejs_5-1_arm64.deb
  fi

  if [[ $arch != "aarch64" && $arch != "armv6l" ]]
  then
    echo "Utilisation du dépot officiel"
    curl -sL https://deb.nodesource.com/setup_5.x | sudo -E bash -
    sudo apt-get install -y nodejs
  fi

  new=`nodejs -v`;
  echo "Version actuelle : ${new}"
fi

echo 70 > /tmp/scandev_dep

cd /opt/jeedom_scandev
npm cache clean
sudo npm cache clean
sudo rm -rf node_modules

echo 75 > /tmp/scandev_dep
sudo npm install --unsafe-perm noble
echo 80 > /tmp/scandev_dep
sudo npm install --unsafe-perm request
echo 85 > /tmp/scandev_dep
sudo npm install xml2json
echo 90 > /tmp/scandev_dep
sudo npm install JSON2
echo 95 > /tmp/scandev_dep
sudo npm install watch
echo 99 > /tmp/scandev_dep

sudo chown -R www-data *

rm /tmp/scandev_dep

echo "Fin de l'installation"
