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

namespace Thelia\Tests\Core\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;

/**
 * Class ActionEventTest.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class ActionEventTest extends TestCase
{
    /**
     * @var \Symfony\Component\Form\Form $form
     */
    protected static $form;

    public static function setUpBeforeClass(): void
    {
        $formBuilder = Forms::createFormFactoryBuilder()
            ->getFormFactory()
            ->createNamedBuilder(
                'text',
                FormType::class,
                null,
                ['attr' => [
                    'thelia_name' => 'test',
                ]]
            );

        $formBuilder
            ->add('foo', TextType::class)
            ->add('bar', TextType::class);

        self::$form = $formBuilder->getForm();
    }

    public function testBindForm()
    {
        $form = self::$form;

        $form->submit([
            'foo' => 'fooValue',
            'bar' => 'barValue',
        ]);

        $event = new FooEvent();
        $event->bindForm($form);

        $this->assertEquals('fooValue', $event->getFoo());
        $this->assertEquals('barValue', $event->getBar());
    }
}
