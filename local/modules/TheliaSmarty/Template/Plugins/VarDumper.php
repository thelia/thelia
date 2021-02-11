<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaSmarty\Template\Plugins;

use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 * @since Thelia v 2.4
 */
class VarDumper extends AbstractSmartyPlugin
{
    /** @var bool */
    protected $debug;

    /**
     * VarDumper constructor.
     * @param bool $kernelDebug
     */
    public function __construct($kernelDebug)
    {
        $this->debug = $kernelDebug;
    }

    public function dump($params, $template = null)
    {
        if (!$this->debug) {
            throw new \Exception('The smarty function "dump" is available only in debug mode.');
        }

        if (!\function_exists('dump')) {
            throw new \Exception('The function "dump" was no available. Check that this project has the package symfony/var-dumper in the composer.json file,'
            . ' and that you have installed dev dependencies : composer.phar install --dev');
        }

        ob_start();
        foreach ($params as $name => $param) {
            $type = \gettype($param);
            echo '<div class="sf-dump" style="background-color: #1b1b1b;color: #FFFFFF;padding-left: 5px;">'
                . $name
                . ' : '
                . ($type === 'object' ? \get_class($param) : $type)
                . '</div>';
            dump($param);
        }
        $dump = ob_get_contents();
        ob_end_clean();

        return $dump;
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'dump', $this, 'dump')
        ];
    }
}
