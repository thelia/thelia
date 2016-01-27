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

namespace Thelia\Tests\Core\Form;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\ValidatorBuilder;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Tests\Resources\Form\Type\TestType;

/**
 * Class TheliaFormFactoryTest
 * @package Thelia\Tests\Controller
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TheliaFormFactoryTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Thelia\Core\Form\TheliaFormFactory */
    protected $factory;

    protected function setUp()
    {
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
            $definition = array(
                "test_form" => "Thelia\\Tests\\Resources\\Form\\TestForm",
            )
        );

        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->set("request", $request);
        $container->set("request_stack", $requestStack);
        $container->set("thelia.forms.validator_builder", new ValidatorBuilder());
        $container->set("event_dispatcher", new EventDispatcher());

        $this->factory = new TheliaFormFactory($requestStack, $container, $definition);
    }

    public function testCreateFormWithoutType()
    {
        /**
         * If we build the form without type, we only have
         * the defined fields
         */
        $form = $this->factory->createForm("test_form");

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
        $form = $this->factory->createForm("test_form", "test_type");

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
