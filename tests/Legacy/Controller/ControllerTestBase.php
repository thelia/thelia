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

namespace Thelia\Tests\Controller;

use Thelia\Controller\BaseController;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * Class ControllerTestBase.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ControllerTestBase extends ContainerAwareTestCase
{
    /** @var BaseController */
    protected $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = $this->getController();
        $this->controller->setContainer($this->container);
    }

    /**
     * @return \Thelia\Controller\BaseController The controller you want to test
     */
    abstract protected function getController();
}
