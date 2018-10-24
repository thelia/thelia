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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\PropelInitService;

/**
 * Actions related to Propel.
 */
class PropelListener implements EventSubscriberInterface
{
    /**
     * Propel initialization service.
     * @var PropelInitService
     */
    protected $propelInitService;

    /**
     * Table map classes to be built.
     * @var string[]
     */
    protected $tableMapClasses = [];

    /**
     * @param PropelInitService $propelInitService Propel initialization service.
     * @param string[] $tableMapClasses Table map classes to be built.
     */
    public function __construct(PropelInitService $propelInitService, array $tableMapClasses = [])
    {
        $this->propelInitService = $propelInitService;
        $this->tableMapClasses = $tableMapClasses;
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::BOOT => 'buildTableMaps'
        ];
    }

    /**
     * Build table maps.
     */
    public function buildTableMaps()
    {
        foreach ($this->tableMapClasses as $tableMapClass) {
            call_user_func([$tableMapClass, 'buildTableMap']);
        }
    }

    /**
     * Rebuild the cache.
     */
    public function rebuildCache()
    {
        $this->propelInitService->init();
    }
}
