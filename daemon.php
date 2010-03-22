<?php

/*
 * Daemonize helper function
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

// required for php 4.3.0 >
declare(ticks = 1);

function _daemonize_term()
{
  exit(0);
}

function _daemonize_output($buffer)
{
}

function daemonize($pidfile = false, $path = "/")
{
  $pid = pcntl_fork();
  if($pid < 0)
    return false;

  if($pid != 0)
    return $pid;

  posix_setsid();
  pcntl_signal(SIGTERM, "_daemonize_term");
  pcntl_signal(SIGHUP, SIG_IGN);
  // redirect normal output to null function
  // cant find any other way to redirect stdout and stderr
  ob_start("_daemonize_output");
  chdir($path);

  if($pidfile !== false)
    file_put_contents($pidfile, posix_getpid());

  return 0;
}

?>
