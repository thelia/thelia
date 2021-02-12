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

namespace Thelia\Tests\Core\Form;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\ValidatorBuilder;
use Thelia\Core\EventDispatcher\EventDispatcher;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Tests\Resources\Form\Type\TestType;

/**
 * Class TheliaFormFactoryTest.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TheliaFormFactoryTest extends TestCase
{
    /** @var \Thelia\Core\Form\TheliaFormFactory */
    protected $factory;

    protected function setUp(): void
    {
        /**
         * Add the test type to the factory and
         * the form to the container.
         */
        $factory = new FormFactoryBuilder();
        $factory->addExtension(new CoreExtension());
        $factory->addType(new TestType());

        /**
         * Construct the container.
         */
        $container = new Container();
        $container->set('thelia.form_factory_builder', $factory);
        $formDefinitions = ['test_form' => 'Thelia\\Tests\\Resources\\Form\\TestForm'];
        $container->setParameter(
            'Thelia.parser.forms',
            $formDefinitions
        );

        $dispatcher = new EventDispatcher();

        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $translator = new Translator($requestStack);

        $formFactoryBuilder = new FormFactoryBuilder();
        $validatorBuilder = new ValidatorBuilder();

        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->set('thelia.translator', $translator);
        $container->set('request', $request);
        $container->set('request_stack', $requestStack);
        $container->set('thelia.forms.validator_builder', $validatorBuilder);
        $container->set('event_dispatcher', $dispatcher);

        $this->factory = new TheliaFormFactory(
            $requestStack,
            $dispatcher,
            $translator,
            $formFactoryBuilder,
            $validatorBuilder,
            $formDefinitions
        );
    }

    public function testCreateFormWithoutType(): void
    {
        /**
         * If we build the form without type, we only have
         * the defined fields.
         */
        $form = $this->factory->createForm('test_form');

        $this->assertTrue(
            $form->getForm()->has('test_field')
        );

        $this->assertFalse(
            $form->getForm()->has('test_a')
        );

        $this->assertFalse(
            $form->getForm()->has('test_b')
        );
    }

    public function testCreateFormWithType(): void
    {
        /**
         * If we use a type, we have that type's fields.
         * -> The implementation is correct.
         */
        $form = $this->factory->createForm('test_form', TestType::class);

        $this->assertTrue(
            $form->getForm()->has('test_field')
        );

        $this->assertTrue(
            $form->getForm()->has('test_a')
        );

        $this->assertTrue(
            $form->getForm()->has('test_b')
        );
    }
}
