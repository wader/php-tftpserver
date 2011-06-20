#!/bin/sh
# $1 = testserver hostname
# $2 = testserver port

echo "Generating random file"
dd bs=1000000 count=10 if=/dev/urandom of=/tmp/phptftpserver_random

echo "Put and get random file using tftp"
echo "connect $1 $2\nmode binary\nput /tmp/phptftpserver_random tftp\nget tftp /tmp/phptftpserver_tftp\nquit\n" | tftp
echo

echo "Put and get random file using atftp"
atftp -p -l /tmp/phptftpserver_random -r atftp $1 $2
atftp -g -l /tmp/phptftpserver_atftp -r atftp $1 $2

echo "Comparing to original file"
cmp /tmp/phptftpserver_random /tmp/phptftpserver_tftp || echo "!! tftp not same"
cmp /tmp/phptftpserver_random /tmp/phptftpserver_atftp || echo "!! atftp not same"

echo "Cleaning up"
rm /tmp/phptftpserver_random /tmp/phptftpserver_tftp /tmp/phptftpserver_atftp

