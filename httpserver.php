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
  private $_uri;
  private $_debug;
  private $contents;
  private $logger;

  /* Note: uri may contain: $f for filename $i for peer ip,  $p for peer port.
     all fields will be url_encoded.
     if no $f is found, $f will be appended. There is no way to say you 
     don't want the filename included
     There is no way to send a literal $f, $i or $p - if you want to implement 
     that, feel free to submit a patch back.

     note that dollar signs are meaningfull to shells, so escape them \$ or
     quote them 'http://blah/?file=$f'.
  */

  function __construct($server_url,
		       $uri, $rw = false, $debug = false, $logger=NULL)
  {
    parent::__construct($server_url,$logger);
    if (strpos($uri,'$f')===FALSE) {
      $uri.='$f';
    }
    $this->_uri= $uri;
    $this->_rw = $rw;
    $this->_debug = $debug;
  }

  private function resolv_path($path,$peer_ip,$peer_port)
  {
    $replacements=array(
	'$f'=>urlencode($path),
	'$i'=>urlencode($peer_ip),
 	'$p'=>urlencode($peer_port),
    );
    return str_replace(array_keys($replacements),array_values($replacements),$this->_uri);
  }

  public function exists($peer, $filename)
  {
    $this->log_warning($peer, 'Checking if file exists');

    $p=explode(":",$peer);
      
    $path = $this->resolv_path($filename,$p[0],$p[1]);
    if($path === false)
      return false;

    $this->log_debug($peer,"Fetching url $path");
 
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
  die("Usage: {$_SERVER["argv"][0]} bind_ip web_url(http://<serverip>/bluebox/index.php/endpointmanager/config) [user] [rw] [debug] [foreground]\n");

  
$debug = false;
if(isset($_SERVER["argv"][5]))
  $debug = (bool)$_SERVER["argv"][5];

if(isset($_SERVER["argv"][6])) {
  $foreground = (bool)$_SERVER["argv"][6];
} else {
  $foreground = $debug;
}

if (!$foreground) {
	if (function_exists('posix_setsid') AND function_exists('pcntl_fork')) {
		$pid = daemonize("tftpserver.pid", "/");
		if($pid === false)
			die("Failed to daemonize\n");
		if($pid != 0)
			exit(0);
	} else {
		print "POSIX Functions don't exist. So we can't run this in the background.\n".
			"You Might want to think about running 'yum install php-process' or something equivalent\n".
			"For now we will just run in the foreground\n";
	}
}

$user = null;
if(isset($_SERVER["argv"][3]) AND function_exists('posix_setsid'))
  $user = posix_getpwnam($_SERVER["argv"][3]);
$rw = false;
if(isset($_SERVER["argv"][4]))
  $rw = (bool)$_SERVER["argv"][4];

if ($debug) {
	$logger=new Logger_Stdout(LOG_DEBUG);
} else {
	$logger=new Logger_Syslog(LOG_NOTICE);
}

$server = new FileTFTPServer('udp://'.$_SERVER["argv"][1].':69',
			     $_SERVER["argv"][2],
			     $rw, $debug,$logger);
if(!$server->loop(&$error, $user))
  die("$error\n");
