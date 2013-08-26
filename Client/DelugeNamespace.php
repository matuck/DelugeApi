<?php

namespace matuck\DelugeApi\Client;

use matuck\DelugeApi\Client\Client;
use matuck\DelugeApi\Client\Command;
use matuck\DelugeApi\Client\Response;
use matuck\DelugeApi\Client\DelugeNamespace;
use matuck\DelugeApi\Exception\DelugeInvalidCommandException;
use matuck\DelugeApi\Exception\DelugeInvalidNamespaceException;

/**
 * A collection of commands and namespaces.
 */
class DelugeNamespace {
    
    /**
     * @var string The name of the namespace.
     * @access private
     */
    private $name;
    
    /**
     * @var Command The parent namespace of the current instance.
     * @access private
     */
    private $parentNamespace;
    
    /**
     * @var The tree of commands and namespaces which are children of the current
     * instance.
     * @access private
     */
    public $children = array();
    
    /**
     * @var A cache of child Command and DelugeNamespace objects.
     * @access private
     */
    private $objectCache = array();
    
    /**
     * @var An instance of Client to which this DelugeNamespace
     * instance belongs.
     * @access private
     */
    private $client;
    
    /**
     * Constructor.
     *
     * @param string $name The name of the namespace.
     * @param mixed $children An array of commands and namespaces which are
     * children of the current instance.
     * @param Client $client The client instance to which this instance
     * belongs.
     * @param mixed $parent The parent DelugeNamespace object, or null if this
     * instance is the root namespace.
     * @access public
     */
    public function __construct($name, array $children, Client $client, DelugeNamespace $parent = null) {
        $this->name = $name;
        $this->children = $children;
        $this->client = $client;
        $this->parentNamespace = $parent;
    }
    
    /**
     * Executes the called command.
     *
     * @param string $name The name of the command to call.
     * @arguments mixed An array of arguments to be send with the remote
     * procedure call.
     * @return Response The response of the remote procedure call.
     * @exception DelugeInvalidCommandException if the requested command does
     * not exist in this namespace.
     * @access public
     */
    public function __call($name, array $arguments) {
        $this->assertHasChildCommand($name);
        if (empty($this->objectCache[$name])) {
            $this->objectCache[$name] = new Command($name, $this->client, $this);
        }
        return $this->objectCache[$name]->execute($arguments);
    }
    
    /**
     * Gets the requested child namespace.
     *
     * @param string $name The name of the namespace to get.
     * @return DelugeNamespace The requested child namespace.
     * @exception DelugeInvalidNamespaceException if the requested namespace does
     * not exist in this namespace.
     * @access public
     */
    public function __get($name) {
        $this->assertHasChildNamespace($name);
        if (empty($this->objectCache[$name])) {
            $this->objectCache[$name] = new DelugeNamespace($name, $this->children[$name], $this->client, $this);
        }
        return $this->objectCache[$name];
    }
    
    /**
     * Gets the full dot-delimited string representing the path from the root
     * namespace to the current namespace.
     *
     * @return string The dot-delimited string.
     * @access public
     */
    public function getFullName() {
        $name = '';
        if (!empty($this->parentNamespace)) {
            $name = $this->parentNamespace->getFullName() . '.' . $this->name;
        }
        return trim($name, '.');
    }
    
    /**
     * Asserts that the the namespace contains the specified command as a direct
     * child.
     *
     * @param string $name The name of the command to check for.
     * @exception DelugeInvalidCommandException if the command is not a direct
     * child of this namespace.
     * @access private
     */
    private function assertHasChildCommand($name) {
        if (!$this->hasChildCommand($name)) {
            throw new DelugeInvalidCommandException("Command $name does not exist in namespace $this->name");
        }
    }
    
    /**
     * Asserts that the the namespace contains the specified namespace as a direct
     * child.
     *
     * @param string $name The name of the namespace to check for.
     * @exception DelugeInvalidNamespaceException if the namespace is not a direct
     * child of this namespace.
     * @access private
     */
    private function assertHasChildNamespace($name) {
        if (!$this->hasChildNamespace($name)) {
            throw new DelugeInvalidNamespaceException("Namespace $name does not exist in namespace $this->name");
        }
    }
    
    /**
     * Checks if the namespace has the specified command as a direct child.
     *
     * @param string $name The name of the command to check for.
     * @return bool True if the command exists in this namespace, false if not.
     * @access private
     */
    private function hasChildCommand($name) {
        return in_array($name, $this->children);
    }
    
    /**
     * Checks if the namespace has the specified namespace as a direct child.
     *
     * @param string $name The name of the namespace to check for.
     * @return bool True if the namespace exists in this namespace, false if not.
     * @access private
     */
    private function hasChildNamespace($name) {
        return array_key_exists($name, $this->children);
    }
    
}
