#!/bin/sh
if [ "$1" == "ZIP" ]; then
    tar czf /var/passwd -C /tmp passwd
    rm /tmp/passwd
elif [ "$1" == "UNZIP" ]; then
    tar xzf /var/passwd -C /tmp/
fi
exit 0
