<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace Thelia\Core\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class TheliaFormFactory
 * @package Thelia\Core\Form
 * @author Benjamin Perche <benjamin@thelia.net>
 */
class TheliaFormFactory implements TheliaFormFactoryInterface
{
    /** @var RequestStack  */
    protected $requestStack;

    /** @var ContainerInterface  */
    protected $container;

    /** @var array */
    protected $formDefinition;

    public function __construct(RequestStack $requestStack, ContainerInterface $container, array $formDefinition)
    {
        $this->requestStack = $requestStack;
        $this->container = $container;
        $this->formDefinition = $formDefinition;
    }

    /**
     * @param  string                $name
     * @param  string                $type
     * @param  array                 $data
     * @param  array                 $options
     * @return \Thelia\Form\BaseForm
     */
    public function createForm($name, $type = "form", array $data = array(), array $options = array())
    {
        if (!isset($this->formDefinition[$name])) {
            throw new \OutOfBoundsException(
                sprintf("The form '%s' doesn't exist", $name)
            );
        }

        return new $this->formDefinition[$name]($this->requestStack->getCurrentRequest(), $type, $data, $options, $this->container);
    }
}
