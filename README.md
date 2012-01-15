PHP TFTP server
---------------

A TFTP server class written in PHP, some example server implementations are also included

*   <code>fileserver.php</code>Serves files from the standard file system, read and write support
*   <code>mysqlserver.php</code>Example server how to query a MySQL databas on read requests
*   <code>httpproxyserver.php</code>Example server redirects requests to http server. All valid filenames are said to exist, and are readable. No files are writable.
*   <code>testserver.php</code>Functional tests for <code>tftpserver.php</code>, run <code>php testserver.php</code> to verify them
