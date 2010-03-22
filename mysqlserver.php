<?php

/*
 * MySQL TFTPServer example
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

require 'tftpserver.php';
require 'daemon.php';

class MySQLTFTPServer extends TFTPServer
{
  private $_config;
  private $_mysql = false;

  function __construct($server_url, $config)
  {
    parent::__construct($server_url);
    $this->_config = $config;
  }

  private function reconnect()
  {
    if($this->_mysql !== false)
      mysql_close($this->_mysql);

    $this->_mysql = @mysql_connect($this->_config["hostname"],
				   $this->_config["username"],
				   $this->_config["password"],
				   $this->_config["database"]);
    if($this->_mysql === false) {
      echo "mysql_connect: " . mysql_error() . "\n";
      return false;
    }

    return true;
  }

  private function query($query)
  {
    $result = @mysql_query($query, $this->_mysql);
    if($result === false) {
      if(!$this->reconnect())
	return false;

      $result = @mysql_query($query, $this->_mysql);
    }

    return $result;
  }

  public function get($peer, $filename, $mode)
  {
    $result = $this->query("SELECT NOW()");
    if($result === false)
      return false;

    $row = mysql_fetch_array($result);
    if($row === false)
      return false;

    return "ok {$row[0]}\n";
  }
}

// daemonize, write pidfile and change working directory
// SIGTERM will terminate
$pid = daemonize("/tmp/mysqltftpserver.pid", "/");
if($pid === false)
  die("Failed to daemonize\n");
if($pid != 0)
  exit(0);

$config = array("hostname" => "localhost",
		"username" => "",
		"password" => "",
		"database" => "");

$server = new MySQLTFTPServer("udp://0.0.0.0:69", $config);
if(!$server->loop(&$error, posix_getpwnam("nobody")));
  die("$error\n");

?>
