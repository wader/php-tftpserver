#!/usr/bin/php
<?php
//this doesnt matter
date_default_timezone_set('America/Los_Angeles');

/*
 * HTTPproxy TFTPServer example
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
 *
 */

require_once("tftpserver.php");
require_once("daemon.php");

class FileTFTPServer extends TFTPServer
{
  private $_root;
  private $_debug;
  private $contents;

  function __construct($server_url,
		       $root = null, $rw = false, $debug = false)
  {
    parent::__construct($server_url);
    $this->_root = $root;
    $this->_rw = $rw;
    $this->_debug = $debug;
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
    if(!$this->_debug)
      return;

    $this->log($peer, "D", $message);
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

  private function resolv_path($path)
  {
    if(strstr($path, "../") != false ||
       strstr($path, "/..") != false)
      return false;

    $abs = "{$this->_root}/$path";
    if(substr($abs, 0, strlen($this->_root)) != $this->_root)
      return false;

    return $abs;
  }

  public function exists($peer, $filename)
  {
    $this->log_warning($peer, 'Checking if file exists');
      
    $path = $this->resolv_path($filename);
    if($path === false)
      return false;
    
    $contents = @file_get_contents($path);
    if($contents === false) {
      $this->log_warning($peer, "function file_get_contents($path) returned false");
      return false;
    }
    
    $this->log_warning($peer, 'HTTP Server Replied With: '.$http_response_header[0]);
    if($http_response_header[0] != 'HTTP/1.1 200 OK')
        return false;    
    
    $this->contents = $contents;
        return true;
  }

  public function readable($peer, $filename)
  {
    return true;
  }

  public function get($peer, $filename, $mode)
  {
    return $this->contents;
  }

  public function writable($peer, $filename)
  {
    return false;
  }

  public function put($peer, $filename, $mode, $content)
  {
    return;
  }
}

if(count($_SERVER["argv"]) < 3)
  die("Usage: {$_SERVER["argv"][0]} bind_ip web_url(http://<serverip>/bluebox/index.php/endpointmanager/config) [user] [rw] [debug]\n");

  
$debug = false;
if(isset($_SERVER["argv"][5]))
  $debug = (bool)$_SERVER["argv"][5];
  
if((!$debug) AND (function_exists('posix_setsid'))) {
  $pid = daemonize("tftpserver.pid", "/");
if($pid === false)
  die("Failed to daemonize\n");
if($pid != 0)
  exit(0);
} elseif(!function_exists('posix_setsid')) {
    print "POSIX Functions don't exist. So we can't run this in the background.\n".
        "You Might want to think about running 'yum install php-process' or something equivalent\n".
        "For now we will just run in the foreground\n";
}

$user = null;
if(isset($_SERVER["argv"][3]) AND function_exists('posix_setsid'))
  $user = posix_getpwnam($_SERVER["argv"][3]);
$rw = false;
if(isset($_SERVER["argv"][4]))
  $rw = (bool)$_SERVER["argv"][4];


$server = new FileTFTPServer('udp://'.$_SERVER["argv"][1].':69',
			     $_SERVER["argv"][2],
			     $rw, $debug);
if(!$server->loop(&$error, $user))
  die("$error\n");
