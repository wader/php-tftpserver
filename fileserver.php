<?php

/*
 * Filesystem TFTPServer example
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
 * Usage:
 * php fileserver.php udp://127.0.0.1:1234 /some/root/path
 *
 * Or more complex, listen on port 69, change to nobody, read-write and debug:
 * sudo php fileserver.php udp://127.0.0.1:69 /tmp nobody 1 1
 *
 */

require_once("tftpserver.php");

class FileTFTPServer extends TFTPServer
{
  private $_root;
  private $_debug;

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
    $path = $this->resolv_path($filename);
    if($path === false)
      return false;

    if(!file_exists($path))
      return false;

    return true;
  }

  public function readable($peer, $filename)
  {
    $path = $this->resolv_path($filename);
    if($path === false)
      return false;

    if(!file_exists($path))
      return false;

    if(!is_readable($path))
      return false;

    return true;
  }

  public function get($peer, $filename, $mode)
  {
    $path = $this->resolv_path($filename);
    if($path === false)
      return false;

    $content = @file_get_contents($path);
    if($content === false)
      return false;

    return $content;
  }

  public function writable($peer, $filename)
  {
    if(!$this->_rw)
      return false;

    $path = $this->resolv_path($filename);
    if($path === false)
      return false;

    if(!file_exists($path))
      $path = dirname($path);

    if(!is_writable($path))
      return false;

    return true;
  }

  public function put($peer, $filename, $mode, $content)
  {
    if(!$this->_rw)
      return;

    $path = $this->resolv_path($filename);
    if($path === false)
      return false;

    @file_put_contents($path, $content);
  }
}

if(count($_SERVER["argv"]) < 3)
  die("Usage: {$_SERVER["argv"][0]} server_url root [user] [rw] [debug]\n");

$user = null;
if(isset($_SERVER["argv"][3]))
  $user = posix_getpwnam($_SERVER["argv"][3]);
$rw = false;
if(isset($_SERVER["argv"][4]))
  $rw = (bool)$_SERVER["argv"][4];
$debug = false;
if(isset($_SERVER["argv"][5]))
  $debug = (bool)$_SERVER["argv"][5];

$server = new FileTFTPServer($_SERVER["argv"][1],
			     $_SERVER["argv"][2],
			     $rw, $debug);
if(!$server->loop(&$error, $user))
  die("$error\n");

?>
