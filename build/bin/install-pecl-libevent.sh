#!/bin/bash

#
# basend on https://gist.github.com/2351174
#

sudo apt-get install libevent-dev
wget http://pecl.php.net/get/libevent-0.0.5.tgz
tar -xzf libevent-0.0.5.tgz
sh -c "cd libevent-0.0.5 && phpize && ./configure && sudo make install"
echo "extension=libevent.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`