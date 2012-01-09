#!/bin/sh
# $1 = testserver hostname
# $2 = testserver port

echo "Generating random files"
dd bs=1000000 count=1 if=/dev/urandom of=/tmp/phptftpserver_random1
dd bs=1000000 count=40 if=/dev/urandom of=/tmp/phptftpserver_random40

echo "Put and get random file using tftp"
echo "connect $1 $2\nmode binary\nput /tmp/phptftpserver_random1 tftp\nget tftp /tmp/phptftpserver_tftp\nquit\n" | tftp
echo

echo "Put and get random file using atftp"
atftp -p -l /tmp/phptftpserver_random1 -r atftp $1 $2
atftp -g -l /tmp/phptftpserver_atftp -r atftp $1 $2

echo "Put and get random file using atftp and block size 65464"
atftp -p -l /tmp/phptftpserver_random40 -r atftp65464 --option "blksize 65464" $1 $2
atftp -g -l /tmp/phptftpserver_atftp65464 -r atftp65464 --option "blksize 65464" $1 $2

echo "Comparing to original file"
cmp /tmp/phptftpserver_random1 /tmp/phptftpserver_tftp || echo "!! tftp not same"
cmp /tmp/phptftpserver_random1 /tmp/phptftpserver_atftp || echo "!! atftp not same"
cmp /tmp/phptftpserver_random40 /tmp/phptftpserver_atftp65464 || echo "!! atftp65464 not same"

echo "Cleaning up"
rm \
  /tmp/phptftpserver_random1 \
  /tmp/phptftpserver_random40 \
  /tmp/phptftpserver_tftp \
  /tmp/phptftpserver_atftp \
  /tmp/phptftpserver_atftp65464

