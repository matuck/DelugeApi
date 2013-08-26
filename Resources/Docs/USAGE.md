Usage
-----

1. Define connection parameters array

```
$params = array(  
    'host' => '192.168.0.123', // Required. The IP or hostname.  
    'port' => 8181,            // Optional. The port for XBMC.(Defaults to 8181)  
    'user' => 'deluge',          // Optional. This is not used right now maybe for future versions(Defaults to NULL)  
    'pass' => 'password'       // Optional. The password for Deluge. (Defaults to NULL)  
); 
``` 


2. Create the client object

```
use matuck\DelugeApi\Client\HTTPClient;

...

$params = array(  
    'host' => '192.168.0.123', // Required. The IP or hostname.  
    'port' => 8181,            // Optional. The port for XBMC.(Defaults to 8181)  
    'user' => 'deluge',          // Optional. This is not used right now maybe for future versions(Defaults to NULL)  
    'pass' => 'password'       // Optional. The password for Deluge. (Defaults to NULL)  
); 

try {
    $client = new TCPClient($params);
} catch (ConnectionException $e) {
    die($e->getMessage());
}

$client->daemon->info();
$client->daemon->get_method_list();
```

A list of available commands is at 
http://deluge-torrent.org/docs/1.2/modindex.html
