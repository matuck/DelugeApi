<?php

namespace matuck\DelugeApi\Client;

use matuck\DelugeApi\Client\Client;
use matuck\DelugeApi\Exception\DelugeException;
use matuck\DelugeApi\Exception\DelugeClientException;
use matuck\DelugeApi\Exception\DelugeConnectionException;
use matuck\DelugeApi\Exception\DelugeRequestException;
use matuck\DelugeApi\Exception\DelugeServerException;

class HTTPClient extends Client
{
    
    /**
     * @var string The URI of the Deluge web  server to which requests will be made.
     */
    private $uri;
    
    /**
     * @var resource The curl resource used for making requests.
     */
    private $curlResource;
    
    /**
     * @var int The amount of time for which the script will try to connect before giving up.
     */
    private $timeout = 15;
    
    /**
     * Destructor.
     *
     * Cleans up the curl resource if necessary.
     */
    public function __destruct() {
        if (is_resource($this->curlResource)) {
            curl_close($this->curlResource);
        }
    }
    
    /**
     * Sets the number of seconds the script will wait while trying to connect
     * to the server before giving up.
     *
     * @param int $seconds The number of seconds to wait.
     * @return void
     */
    public function setTimeout($seconds) {
        $this->timeout = (int) $seconds;
        if ($this->timeout < 0) {
            $this->timeout = 0;
        }
    }
    
    /**
     * Asserts that the server is reachable and a connection can be made.
     *
     * @return void
     * @exception DelugeConnectionException if it is not possible to connect to
     * the server.
     */
    protected function assertCanConnect() {
        /*if (extension_loaded('curl')) {
            $ch = curl_init();
            print($this->uri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $this->uri);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_COOKIESESSION, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->server->getParameters()['cookie']);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->server->getParameters()['cookie']);
            curl_setopt($ch, CURLOPT_COOKIE, $this->server->getParameters()['cookie']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_ENCODING, "gzip");
            $res = curl_exec($ch);
            print($res);
            if (!$res || !in_array(curl_getinfo($ch, CURLINFO_HTTP_CODE), array('200', '401'))) {
                throw new DelugeConnectionException('Unable to connect to Deluge server via HTTP');
            }
        } else {
            throw new DelugeConnectionException('Cannot test if Deluge server is reachable via HTTP because cURL is not installed');
        }*/
    }
    
    /**
     * Prepares for a connection to Deluge via HTTP.
     *
     * @exception DelugeClientException if it was not possible to prepare for
     * connection successfully.
     * @access protected
     */
    protected function prepareConnection() {
        if (!$uri = $this->buildUri()) {
            throw new DelugeClientException('Unable to parse server parameters into valid URI string');
        }
        $this->uri = $uri;
    }
    
    /**
     * Sends a JSON-RPC request to Deluge and returns the result.
     *
     * @param string $json A JSON-encoded string representing the remote procedure call.
     * This string should conform to the JSON-RPC 2.0 specification.
     * @param string $rpcId The unique ID of the remote procedure call.
     * @return string The JSON-encoded response string from the server.
     * @exception DelugeRequestException if it was not possible to make the request.
     * @access protected
     * @link http://groups.google.com/group/json-rpc/web/json-rpc-2-0 JSON-RPC 2.0 specification
     */
    protected function sendRequest($json, $rpcId)
    {
        if($this->checkconnect())
        {
            return $this->_sendRequest($json, $rpcId);
        }
        else
        {
            if($this->auth())
            {
                
                if($this->checkconnect())
                {
                    return $this->_sendRequest($json, $rpcId);
                }
                else
                {
                    throw new DelugeServerException('Login was succssful to the deluge server, but unable to save/read cookie.');
                }
            }
            else
            {
                throw new DelugeServerException('Not able to login to login to the deluge server.  Please check your server details and password.');
            }
        }
    }
    private function _sendRequest($json, $rpcId) {
        if (empty($this->curlResource)) {
            $this->curlResource = $this->createCurlResource();
        }
        curl_setopt($this->curlResource, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curlResource, CURLOPT_COOKIESESSION, true);
        curl_setopt($this->curlResource, CURLOPT_COOKIEJAR, $this->server->getParameters()['cookie']);
        curl_setopt($this->curlResource, CURLOPT_COOKIEFILE, $this->server->getParameters()['cookie']);
        curl_setopt($this->curlResource, CURLOPT_COOKIE, $this->server->getParameters()['cookie']);
        curl_setopt($this->curlResource, CURLOPT_POST, 1);
        curl_setopt($this->curlResource, CURLOPT_ENCODING, "gzip");
        curl_setopt($this->curlResource, CURLOPT_POSTFIELDS, $json);
        curl_setopt($this->curlResource, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        if (!$response = curl_exec($this->curlResource)) {
            throw new DelugeRequestException('Could not make a request the server');
        }
        return $response;
    }
    
    private function auth()
     {
         $request = json_encode(array('method' => 'auth.login', 'params' => array($this->server->getParameters()['pass']),'id' => "1"));
         $json = json_decode($this->_sendRequest($request, uniqid()));
         if(isset($json->result) && $json->result == 1)
         {
             return true;
         }
         else
         {
             return false;
         }
     }
     
     private function checkconnect()
     {
        $request = json_encode(array('method' => 'web.connected', 'params' => array(),'id' => "1"));
        $json = json_decode($this->_sendRequest($request, uniqid()));
        if(isset($json->error))
        {
            return false;
        }
        else
        {
            return true;
        }
     }
     
    /**
     *
     * @return string The server URI.
     * @access private
     */
    private function buildUri() {
        $parameters = $this->server->getParameters();
        $credentials = '';
        return sprintf('http://%s:%d/json', $parameters['host'], $parameters['port']);
    }
    
    /**
     * Creates a curl resource with the correct settings for making JSON-RPC calls
     * to Deluge.
     *
     * @return resource A new curl resource.
     * @access private
     */
    private function createCurlResource() {
        $curlResource = curl_init();
        curl_setopt($curlResource, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlResource, CURLOPT_POST, 1);
        curl_setopt($curlResource, CURLOPT_URL, $this->uri);
        return $curlResource;
    }
}
