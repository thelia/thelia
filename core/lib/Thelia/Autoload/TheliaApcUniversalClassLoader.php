<?php

namespace Thelia\Autoload;

/**
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class TheliaApcUniversalClassLoader extends TheliaUniversalClassLoader
{
    private $prefix;

    /**
     * Constructor
     *
     * Come from Symfony\Component\ClassLoader\ApcUniversalClassLoader
     *
     * @param  string            $prefix
     * @throws \RuntimeException
     */
    public function __construct($prefix)
    {
        if (!extension_loaded('apc')) {
            throw new \RuntimeException('Unable to use ApcUniversalClassLoader as APC is not enabled.');
        }

        $this->prefix = $prefix;
    }

    /**
     * Finds a file by class name while caching lookups to APC.
     *
     * Come from Symfony\Component\ClassLoader\ApcUniversalClassLoader
     *
     * @param string $class A class name to resolve to file
     *
     * @return string|null The path, if found
     */
    public function findFile($class)
    {
        if (false === $file = apc_fetch($this->prefix.$class)) {
            apc_store($this->prefix.$class, $file = parent::findFile($class));
        }

        return $file;
    }
}
