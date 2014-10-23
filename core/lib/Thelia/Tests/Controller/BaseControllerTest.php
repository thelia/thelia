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

namespace Thelia\Tests\Controller;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Validator\ValidatorBuilder;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Tests\Resources\Form\Type\TestType;

/**
 * Class BaseControllerTest
 * @package Thelia\Tests\Controller
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class BaseControllerTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Thelia\Controller\BaseController */
    protected $controller;

    protected function setUp()
    {
        $this->controller = $this->getMock(
            "Thelia\\Controller\\BaseController",
            [
                "getParser",
                "render",
                "renderRaw"
            ]
        );

        /**
         * Add the test type to the factory and
         * the form to the container
         */
        $factory = new FormFactoryBuilder();
        $factory->addExtension(new CoreExtension());
        $factory->addType(new TestType());

        /**
         * Construct the container
         */
        $container = new Container();
        $container->set("thelia.form_factory_builder", $factory);
        $container->set("thelia.translator", new Translator($container));
        $container->setParameter(
            "thelia.parser.forms",
            array(
                "test_form" => "Thelia\Tests\Resources\Form\TestForm",
            )
        );

        $container->set("request", new Request());
        $container->set("thelia.forms.validator_builder", new ValidatorBuilder());

        $container->set("event_dispatcher", new EventDispatcher());

        $this->controller->setContainer($container);
    }

    public function testCreateFormWithoutType()
    {
        /**
         * If we build the form without type, we only have
         * the defined fields
         */
        $form = $this->controller->createForm("test_form");

        $this->assertTrue(
            $form->getForm()->has("test_field")
        );

        $this->assertFalse(
            $form->getForm()->has("test_a")
        );

        $this->assertFalse(
            $form->getForm()->has("test_b")
        );
    }

    public function testCreateFormWithType()
    {
        /**
         * If we use a type, we have that type's fields.
         * -> The implementation is correct.
         */
        $form = $this->controller->createForm("test_form", "test_type");

        $this->assertTrue(
            $form->getForm()->has("test_field")
        );

        $this->assertTrue(
            $form->getForm()->has("test_a")
        );

        $this->assertTrue(
            $form->getForm()->has("test_b")
        );
    }
}
