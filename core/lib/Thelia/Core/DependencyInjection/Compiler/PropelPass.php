<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\DependencyInjection\Compiler;

use Composer\Autoload\ClassMapGenerator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

/**
 * Build a list of Propel table map classes.
 */
class PropelPass implements CompilerPassInterface
{
    /**
     * Container parameter for the list of table map classes.
     * @var string
     */
    protected static $PARAMETER_TABLE_MAP_CLASSES = 'thelia.propel.table_map_classes';

    /**
     * Path to the model directory.
     * @var string
     */
    protected $modelDirectory;

    /**
     * @param string $modelDirectory Path to the model directory.
     */
    public function __construct($modelDirectory)
    {
        $this->modelDirectory = $modelDirectory;
    }

    public function process(ContainerBuilder $container)
    {
        if ($container->hasParameter(static::$PARAMETER_TABLE_MAP_CLASSES)) {
            $tableMapClasses = $container->getParameter(static::$PARAMETER_TABLE_MAP_CLASSES);
        } else {
            $tableMapClasses = [];
        }

        $tableMapFiles = new Finder();

        $tableMapFiles
            ->files()
            ->name('*.php');

        try {
            $tableMapFiles->in("{$this->modelDirectory}/*/Model/Map/");
        } catch (\InvalidArgumentException $e) {
            $container->setParameter(static::$PARAMETER_TABLE_MAP_CLASSES, $tableMapClasses);
            return;
        }

        $classmap = ClassMapGenerator::createMap($tableMapFiles->getIterator());

        foreach (array_keys($classmap) as $class) {
            if (is_subclass_of($class, '\Propel\Runtime\Map\TableMap')
                && !in_array($class, $tableMapClasses)
            ) {
                $tableMapClasses[] = $class;
            }
        }

        $container->setParameter(static::$PARAMETER_TABLE_MAP_CLASSES, $tableMapClasses);
    }
}
