<?php

/*
 * Functional test for TFTPServer
 *
 * Copyright (c) 2010 <mattias.wadman@gmail.com>
 *
 * MIT License:
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */

require_once("tftpserver.php");

class TestTFTPServer extends TFTPServer
{
  private $_files = array();

  function __construct($server_url)
  {
    parent::__construct($server_url);
  }

  private function log($peer, $level, $message)
  {
    echo
      date("H:i:s") . " " .
      $level . " " .
      $peer . " " .
      $message . "\n";
  }

  public function log_debug($peer, $message)
  {
    //$this->log($peer, "D", $message);
  }

  public function log_info($peer, $message)
  {
    $this->log($peer, "I", $message);
  }

  public function log_warning($peer, $message)
  {
    $this->log($peer, "W", $message);
  }

  public function log_error($peer, $message)
  {
    $this->log($peer, "E", $message);
  }

  public function exists($peer, $filename)
  {
    if($filename == "not_writable" || $filename == "not_readable")
      return true;

    return isset($this->_files[$filename]);
  }

  public function readable($peer, $filename)
  {
    if($filename == "not_readable")
      return false;

    return isset($this->_files[$filename]);
  }

  public function get($peer, $filename, $mode)
  {
    if(isset($this->_files[$filename]))
      return $this->_files[$filename];
    else
      return false;
  }

  public function writable($peer, $filename)
  {
    if($filename == "not_writable")
      return false;

    return true;
  }

  public function put($peer, $filename, $mode, $content)
  {
    $this->_files[$filename] = $content;
  }
}

function test($server_url, $name, $packet_pairs) 
{
  $timeout = 100000;

  $s = stream_socket_client($server_url, $errno, $errstr);
  if($s === false) {
    echo "FAILED\n";
    echo "Socket failed: $errno: $errstr\n";
    return false;
  }

  $step = 1;
  foreach($packet_pairs as $pair) {
    $send = $pair[0];
    $expect = $pair[1];

    if($send != null)
      stream_socket_sendto($s, $send);

    $read = array($s);
    stream_select($read, $write = null, $excpt = null, 0, $timeout);
    if(count($read) > 0)
      $result = stream_socket_recvfrom($s, 1500);
    else
      $result = null;

    if($result != $expect) {
      echo "FAILED at step $step\n";
      echo "Send  : " . TFTPServer::escape_string($send) . "\n";
      echo "Expect: " . TFTPServer::escape_string($expect) . "\n";
      echo "Result: " . TFTPServer::escape_string($result) . "\n";
      return false;
    }

    $step++;
  }

  $read = array($s);
  stream_select($read, $write = null, $excpt = null, 0, $timeout);
  if(count($read) > 0) {
    $result = stream_socket_recvfrom($s, 1500);
    echo "FAILED\n";
    echo "Got after end: " . TFTPServer::escape_string($result) . "\n";
    return false;
  }

  fclose($s);

  return true;
}

$host = "127.0.0.1";
$port = 1234;
$url = "udp://$host:$port";

$pid = pcntl_fork();

if($pid == 0) {
  $server = new TestTFTPServer($url);
  if(!$server->loop(&$error))
    die("$error\n");
} else {
  usleep(100000);

  $s500 = str_pad("", 500, "a");
  $s511 = str_pad("", 511, "a");

  $tests = array(
    "Write file 513 bytes",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::ACK, 0)),
	  array(pack("nn", TFTPOpcode::DATA, 1) . $s511 . "a",
		pack("nn", TFTPOpcode::ACK, 1)),
	  array(pack("nn", TFTPOpcode::DATA, 2) . "a",
		pack("nn", TFTPOpcode::ACK, 2))
	  ),
    "Read file 513 bytes",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::DATA, 1) . $s511 . "a"),
	  array(pack("nn", TFTPOpcode::ACK, 1),
		pack("nn", TFTPOpcode::DATA, 2). "a"),
	  array(pack("nn", TFTPOpcode::ACK, 2),
		null)
	  ),
    "Write file 512 bytes",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::ACK, 0)),
	  array(pack("nn", TFTPOpcode::DATA, 1) . $s511 . "a",
		pack("nn", TFTPOpcode::ACK, 1)),
	  array(pack("nn", TFTPOpcode::DATA, 2),
		pack("nn", TFTPOpcode::ACK, 2))
	  ),
    "Read file 512 bytes",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::DATA, 1) . $s511 . "a"),
	  array(pack("nn", TFTPOpcode::ACK, 1),
		pack("nn", TFTPOpcode::DATA, 2)),
	  array(pack("nn", TFTPOpcode::ACK, 2),
		null)
	  ),
    "Write file 511 bytes",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::ACK, 0)),
	  array(pack("nn", TFTPOpcode::DATA, 1) . $s511,
		pack("nn", TFTPOpcode::ACK, 1))
	  ),
    "Read file 511 bytes",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::DATA, 1) . $s511),
	  array(pack("nn", TFTPOpcode::ACK, 1),
		null)
	  ),
    "Read file with malformed extensions",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0bla",
		null)
	  ),
    "Read file with unknown extensions",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0bla\0bla\0",
		pack("nn", TFTPOpcode::DATA, 1) . $s511),
	  array(pack("nn", TFTPOpcode::ACK, 1),
		null)
	  ),
    "Read file with extensions, 500 byte block, 5 sec timeout",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0timeout\0005\0blksize\000500\0",
		pack("n", TFTPOpcode::OACK) . "timeout\0005\0blksize\000500\0"),
	  array(pack("nn", TFTPOpcode::ACK, 0),
		pack("nn", TFTPOpcode::DATA, 1) . $s500),
	  array(pack("nn", TFTPOpcode::ACK, 1),
		pack("nn", TFTPOpcode::DATA, 2) . "aaaaaaaaaaa"),
	  array(pack("nn", TFTPOpcode::ACK, 2),
		null)
	  ),
    "Read file with extensions then dont accept them",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0timeout\0005\0",
		pack("n", TFTPOpcode::OACK) . "timeout\0005\0"),
	  array(pack("nn", TFTPOpcode::ERROR, 8),
		null)
	  ),
    "Write file 511 bytes with extensions, 500 byte block, 5 sec timeout",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "test\0octet\0timeout\0005\0blksize\000500\0",
		pack("n", TFTPOpcode::OACK) . "timeout\0005\0blksize\000500\0"),
	  array(pack("nn", TFTPOpcode::DATA, 1) . $s500,
		pack("nn", TFTPOpcode::ACK, 1)),
	  array(pack("nn", TFTPOpcode::DATA, 2) . "aaaaaaaaaaa",
		pack("nn", TFTPOpcode::ACK, 2))
	  ),
    "Read file 511 bytes, after write with 500 byte block",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::DATA, 1) . $s511),
	  array(pack("nn", TFTPOpcode::ACK, 1),
		null)
	  ),
    "Write file with extensions then dont accept them",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "test\0octet\0timeout\0005\0",
		pack("n", TFTPOpcode::OACK) . "timeout\0005\0"),
	  array(pack("nn", TFTPOpcode::ERROR, 8),
		null)
	  ),
    "Write too big file with tsize extension",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "test\0octet\0tsize\00099999999\0",
		pack("nn", TFTPOpcode::ERROR,
		     TFTPError::DISK_FULL) . "File too big, 99999999(tsize) > 10485760\0")
	  ),
    "Read non-existing file",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "non_existing\0octet\0",
		pack("nn", TFTPOpcode::ERROR, TFTPError::FILE_NOT_FOUND) .
		     "File non_existing does not exist\0")
	  ),
    "Read non-readable file",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "not_readable\0octet\0",
		pack("nn", TFTPOpcode::ERROR, TFTPError::ACCESS_VIOLATION) .
		     "File not_readable is not readable\0")
	  ),
    "Write not-writable file",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "not_writable\0octet\0",
		pack("nn", TFTPOpcode::ERROR, TFTPError::ACCESS_VIOLATION) .
		     "File not_writable is not writable\0")
	 ),
    "Send read in write transfer",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::ACK, 0)),
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::ERROR,
		     TFTPError::ILLEGAL_OPERATION) . "Illegal operation\0")
	 ),
    "Send write in read transfer",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::DATA, 1) . $s511),
	  array(pack("n", TFTPOpcode::WRQ) ." test\0octet\0",
		pack("nn", TFTPOpcode::ERROR,
		     TFTPError::ILLEGAL_OPERATION) . "Illegal operation\0")
	 ),
    "Send data in read transfer",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::DATA, 1) . $s511),
	  array(pack("nn", TFTPOpcode::DATA, 1) . "bla",
		pack("nn", TFTPOpcode::ERROR,
		     TFTPError::ILLEGAL_OPERATION) . "Illegal operation\0")
	 ),
    "Send ack in write transfer",
    array(
	  array(pack("n", TFTPOpcode::WRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::ACK, 0)),
	  array(pack("nn", TFTPOpcode::ACK, 1),
		pack("nn", TFTPOpcode::ERROR,
		     TFTPError::ILLEGAL_OPERATION) . "Illegal operation\0")
	 ),
    "Error from client",
    array(
	  array(pack("n", TFTPOpcode::RRQ) . "test\0octet\0",
		pack("nn", TFTPOpcode::DATA, 1) . $s511),
	  array(pack("nn", TFTPOpcode::ERROR, 1) . "bla\0",
		null)
	 ),
    "Send short", array(array("bla", null)),
    "Send unknown opcode", array(array("blaa", null))
    );

  $failed = 0;
  foreach(array_chunk($tests, 2, false) as $pair) {
    echo "Test: {$pair[0]}\n";
    if(!test($url, $pair[0], $pair[1]))
      $failed++;
  }

  echo (count($tests) / 2) . " total, $failed failed\n";

  // kill server
  posix_kill($pid, SIGINT);
}

?>
