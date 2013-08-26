<?php
namespace matuck\DelugeApi\Client;

use matuck\DelugeApi\Client\Command;
use matuck\DelugeApi\Client\Response;
use matuck\DelugeApi\Client\Server;
use matuck\DelugeApi\Client\DelugeNamespace;
use matuck\DelugeApi\Exception\DelugeException;
use matuck\DelugeApi\Exception\DelugeClientException;
use matuck\DelugeApi\Exception\DelugeCommandException;
use matuck\DelugeApi\Exception\DelugeConnectionException;
use matuck\DelugeApi\Exception\DelugeInvalidCommandException;
use matuck\DelugeApi\Exception\DelugeInvalidNamespaceException;
use matuck\DelugeApi\Exception\DelugeRequestException;
use matuck\DelugeApi\Exception\DelugeResponseException;
use matuck\DelugeApi\Exception\DelugeServerException;

abstract class Client
{
    
    /**
     * @var Server A server object instance representing the server
     * to be used for remote procedure calls.
     * @access protected
     */
    protected $server;
    
    /**
     * @var DelugeNamespace The root namespace instance.
     * @access private
     */
    private $rootNamespace;
    
    /**
     * Constructor.
     *
     * Connects to the server and populates a list of available commands by
     * having the server introspect.
     *
     * @param mixed $parameters An associative array of connection parameters
     * If supplying an array, the following paramters are accepted: 
     * host, port, user and pass. Any other parameters are discarded.
     * @exception DelugeConnectionException if it is not possible to connect to
     * the server.
     * @access public
     */
    public function __construct($parameters) {
        try {
            $server = new Server($parameters);
        } catch (DelugeServerException $e) {
            throw new DelugeConnectionException($e->getMessage(), $e->getCode(), $e);
        }
        $this->server = $server;
        $this->prepareConnection();
        $this->assertCanConnect();
        $this->createRootNamespace();
    }
    
    /**
     * Delegates any direct Command calls to the root namespace.
     *
     * @param string $name The name of the called command.
     * @param mixed $arguments An array of arguments used to call the command.
     * @return The result of the command call as returned from the namespace.
     * @exception DelugeInvalidCommandException if the called command does not
     * exist in the root namespace.
     * @access public
     */
    public function __call($name, array $arguments) {
        return call_user_func_array(array($this->rootNamespace, $name), $arguments);
    }
    
    /**
     * Delegates namespace accesses to the root namespace.
     *
     * @param string $name The name of the requested namespace.
     * @return DelugeNamespace The requested namespace.
     * @exception DelugeInvalidNamespaceException if the namespace does not
     * exist in the root namespace.
     * @access public
     */
    public function __get($name) {
        return $this->rootNamespace->$name;
    }
    
    /**
     * Executes a remote procedure call using the supplied Command
     * object.
     *
     * @param Command The command to execute.
     * @return Response The response from the remote procedure call.
     * @access public
     */
    public function executeCommand(Command $command) {
        return $this->sendRpc($command->getFullName(), $command->getArguments());
    }
    
    /**
     * Asserts that the server is reachable and a connection can be made.
     *
     * @return void
     * @exception DelugeConnectionException if it is not possible to connect to
     * the server.
     * @abstract
     * @access protected
     */
    protected abstract function assertCanConnect();
    
    /**
     * Prepares for a connection to Deluge.
     *
     * Should be used by child classes for any pre-connection logic which is necessary.
     *
     * @return void
     * @exception DelugeClientException if it was not possible to prepare for
     * connection successfully.
     * @abstract
     * @access protected
     */
    protected abstract function prepareConnection();
    
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
    protected abstract function sendRequest($json, $rpcId);
    
    /**
     * Build a JSON-RPC 2.0 compatable json_encoded string representing the
     * specified command, parameters and request id.
     *
     * @param string $command The name of the command to be called.
     * @param mixed $params An array of paramters to be passed to the command.
     * @param string $rpcId A unique string used for identifying the request.
     * @access private
     */
    private function buildJson($command, $params, $rpcId) {
        $data = array(
            'jsonrpc' => '2.0',
            'method' => $command,
            'params' => $params,
            'id' => $rpcId
        );
        return json_encode($data);
    }
    
    /**
     * Ensures that the recieved response from a remote procedure call is valid.
     *
     * $param Response $response A response object encapsulating remote
     * procedure call response data as returned from Client::sendRequest().
     * @return bool True of the reponse is valid, false if not.
     * @access private
     */
    private function checkResponse(Response $response, $rpcId) {
        return ($response->getId() == $rpcId);
    }
    
    /**
     * Creates the root namespace instance.
     *
     * @return void
     * @access private
     */
    private function createRootNamespace() {
        $commands = $this->loadAvailableCommands();
        $this->rootNamespace = new DelugeNamespace('root', $commands, $this);
    }
    
    /**
     * Generates a unique string to be used as a remote procedure call ID.
     *
     * @return string A unique string.
     * @access private
     */
    private function getRpcId() {
        return uniqid();
    }
    
    /**
     * Retrieves an array of commands by requesting the RPC server to introspect.
     *
     * @return mixed An array of available commands which may be executed on the server.
     * @exception DelugeRequestException if it is not possible to retrieve a list of
     * available commands.
     * @access private
     */
    private function loadAvailableCommands() {
        try {
            $response = $this->sendRpc('daemon.get_method_list');
        } catch (DelugeException $e) {
            throw new DelugeRequestException(
                'Unable to retrieve list of available commands: ' . $e->getMessage()
            );
        }
        $commands = array();
        foreach ($response as $command) {
            $array = $this->commandStringToArray($command);
            $commands = $this->mergeCommandArrays($commands, $array);
        }
        return $commands;
    }
    
    /**
     * Converts a dot-delimited command name to a multidimensional array format.
     *
     * @return mixed An array representing the command.
     * @access private
     */
    private function commandStringToArray($command) {
        $path = explode('.', $command);
        if (count($path) === 1) {
            $commands[] = $path[0];
            continue;
        }
        $command = array_pop($path);
        $array = array();
        $reference =& $array;
        foreach ($path as $i => $key) {
            if (is_numeric($key) && intval($key) > 0 || $key === '0') {
                $key = intval($key);
            }
            if ($i === count($path) - 1) {
                $reference[$key] = array($command);
            } else {
                if (!isset($reference[$key])) {
                    $reference[$key] = array();
                }
                $reference =& $reference[$key];
            }
        }
        return $array;
    }
    
    /**
     * Recursively merges the supplied arrays whilst ensuring that commands are
     * not duplicated within a namespace.
     *
     * Note that array_merge_recursive is not suitable here as it does not ensure
     * that values are distinct within an array.
     *
     * @param mixed $base The base array into which $append will be merged.
     * @param mixed $append The array to merge into $base.
     * @return mixed The merged array of commands and namespaces.
     * @access private
     */
    private function mergeCommandArrays(array $base, array $append) {
        foreach ($append as $key => $value) {
            if (!array_key_exists($key, $base) && !is_numeric($key)) {
                $base[$key] = $append[$key];
                continue;
            }
            if (is_array($value) || is_array($base[$key])) {
                $base[$key] = $this->mergeCommandArrays($base[$key], $append[$key]);
            } elseif (is_numeric($key)) {
                if (!in_array($value, $base)) {
                    $base[] = $value;
                }
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
    
    /**
     * Executes a remote procedure call using the supplied command name and parameters.
     *
     * @param string $command The full, dot-delimited name of the command to call.
     * @param mixed $params An array of parameters to be passed to the called method.
     * @return mixed The data returned from the response.
     * @exception DelugeRequestException if it was not possible to make the request.
     * @exception DelugeResponseException if the response was not being properly received.
     * @access private
     */
    private function sendRpc($command, $params = array()) {
        $rpcId = $this->getRpcId();
        $json = $this->buildJson($command, $params, $rpcId);
        $response = new Response($this->sendRequest($json, $rpcId));
        if (!$this->checkResponse($response, $rpcId)) {
            throw new DelugeResponseException('JSON RPC request/response ID mismatch');
        }
        return $response->getData();
    }
    
    /**
     * Returns an array of the available commands.
     * @return array of commands
     */
    public function help()
    {
        return $this->rootNamespace->children;
    }
}