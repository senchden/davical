#!/bin/sh

set -e

TZCODEFILE="tzcode-latest.tar.gz"
TZDATAFILE="tzdata-latest.tar.gz"

# if [ ! -f $TZCODEFILE ]; then
  (
    wget --continue -O $TZCODEFILE 'ftp://ftp.iana.org/tz/'$TZCODEFILE
    rm -rf tzcode
    mkdir -p tzcode && cd tzcode && tar -xzf ../$TZCODEFILE
  )
# fi

# if [ ! -f $TZDATAFILE ]; then
  (
    wget --continue -O $TZDATAFILE 'ftp://ftp.iana.org/tz/'$TZDATAFILE
    rm -rf tzdata
    mkdir -p tzdata && cd tzdata && tar -xzf ../$TZDATAFILE
  )
# fi

# From: https://github.com/libical/vzic
vzic --pure --olson-dir tzdata --output-dir vtimezones
echo "Olson `echo $TZDATAFILE | cut -f1 -d.`" >vtimezones/primary-source
