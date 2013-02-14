#!/usr/bin/env php
<?php
/*
 * HTTP proxy TFTPServer example
 *
 * Copyright (c) 2011 Business Technology Group http://www.btg.co.nz/
 * Copyright (c) 2011 <mattias.wadman@gmail.com>
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

class HTTPProxyTFTPServer extends TFTPServer
{
  private $_url_template;

  /*
   * Note: URI may contain: %filename, %ip, %port and %mode
   * All fields will be URL encoded.
   * Currently no support for escaping a %.
   */

  function __construct($server_url, $url_template, $logger = NULL)
  {
    parent::__construct($server_url, $logger);
    $this->_url_template = $url_template;
  }

  public function get($peer, $filename, $mode)
  {
    $p = explode(":", $peer);
    $replacements = array(
      "%filename" => urlencode($filename),
      "%ip" => urlencode($p[0]),
      "%port" => urlencode($p[1]),
      "%mode" => urlencode($mode)
    );

    $url = str_replace(
      array_keys($replacements),
      array_values($replacements),
      $this->_url_template);

    $this->log_debug($peer, "Fetching URL $url");
    $contents = @file_get_contents($url);
    // note: $http_response_header is automatically populated.
    if($contents === false) {
      $this->log_warning($peer, "Failed to fetch $url");
      return false;
    }

    foreach ($http_response_header AS $headerline) {
        $this->log_debug($peer,"http header: $headerline");
    }

    // note: there may be more than one http response; e.g. a 302 first, then a 200. Get the last one.
    $http_result=array_pop(preg_grep('/^HTTP\/\d+\.\d+ \d+ .*/',$http_response_header));
    if ($http_result===NULL) {
        $this->log_error($peer,"HTTP did not contain a response: $http_response_header[0]");
        return false;
    }
    preg_match('/^HTTP\/\d+\.\d+ (\d+) .*/',$http_result,$result);
    if (!in_array($result[1],array("200"))) {
        $this->log_debug($peer,"HTTP response indicated failure: $result[0]");
        return false;
    }

    $this->log_debug($peer, "HTTP response success: $result[0]");

    return $contents;
  }
}

if(count($_SERVER["argv"]) < 3)
  die("Usage: {$_SERVER["argv"][0]} bind_ip uri [user] [debug] [foreground]\n");

$debug = false;
if(isset($_SERVER["argv"][4]))
  $debug = (bool)$_SERVER["argv"][4];

if(isset($_SERVER["argv"][5])) {
  $foreground = (bool)$_SERVER["argv"][5];
} else {
  $foreground = $debug;
}

if (!$foreground) {
  if (function_exists('posix_setsid') && function_exists('pcntl_fork')) {
    $pid = daemonize("/tmp/httptftpserver.pid", "/");
    if($pid === false)
      die("Failed to daemonize\n");
    if($pid != 0)
      exit(0);
  } else {
    echo "POSIX Functions don't exist. So we can't run this in the background.\n" .
      "You Might want to think about running 'yum install php-process' or something equivalent\n" .
      "For now we will just run in the foreground\n";
  }
}

$user = null;
if(isset($_SERVER["argv"][3]) && function_exists('posix_setsid'))
  $user = posix_getpwnam($_SERVER["argv"][3]);

if ($debug) {
  $logger = new Logger_Stdout(LOG_DEBUG);
} else {
  $logger = new Logger_Syslog(LOG_NOTICE);
}

$server = new HTTPProxyTFTPServer('udp://'.$_SERVER["argv"][1].':69', $_SERVER["argv"][2], $logger);
if(!$server->loop(&$error, $user))
  die("$error\n");
