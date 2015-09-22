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

namespace Thelia\Tests\Core\Event;

use Symfony\Component\Form\Forms;

/**
 * Class ActionEventTest
 * @package Thelia\Tests\Core\Event
 * @author manuel raynaud <manu@raynaud.io>
 */
class ActionEventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Form\Form $form
     */
    protected static $form;

    public static function setUpBeforeClass()
    {
        $formBuilder = Forms::createFormFactoryBuilder()
            ->getFormFactory()
            ->createNamedBuilder(
                'test',
                'form',
                null,
                ['attr' =>[
                    'thelia_name' => 'test'
                ]]
            );

        $formBuilder
            ->add('foo', 'text')
            ->add('bar', 'text');

        self::$form = $formBuilder->getForm();
    }

    public function testBindForm()
    {
        $form = self::$form;

        $form->bind([
            'foo' => 'fooValue',
            'bar' => 'barValue'
        ]);

        $event = new FooEvent();
        $event->bindForm($form);

        $this->assertEquals('fooValue', $event->getFoo());
        $this->assertEquals('barValue', $event->getBar());
    }
}
