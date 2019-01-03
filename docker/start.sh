#!/bin/bash

export DBPREFIX="$(pwgen 6 1)_"
ISDBREADY=1

sed -i -- "s/emc_/$DBPREFIX/g" /var/www/html/easyminercenter/app/config/config.local.neon

while [ $ISDBREADY -ne 0 ]; do
    php /root/db.php $DBPREFIX
    let ISDBREADY=$?
    if [ $ISDBREADY -ne 0 ]
    then
        echo "Unsuccessful database creation. Waiting 2 seconds for next attempt..."
        sleep 2s
    fi
done

apache2-foreground