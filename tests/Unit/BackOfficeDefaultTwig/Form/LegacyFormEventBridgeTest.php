<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Unit\BackOfficeDefaultTwig\Form;

use BackOfficeDefaultTwigBundle\Form\Legacy\LegacyFormEventBridge;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\Event\UpdateSeoEvent;

final class LegacyFormEventBridgeTest extends TestCase
{
    public function testModuleListenerFieldEndsUpOnTheBridgedForm(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            TheliaEvents::FORM_BEFORE_BUILD.'.thelia_product_modification',
            static function (TheliaFormEvent $event): void {
                $event->getForm()->getFormBuilder()->add('sitemapPriority', TextType::class, ['required' => false]);
            },
        );

        $builder = Forms::createFormFactory()->createNamedBuilder('thelia_product_modification');

        $bridge = new LegacyFormEventBridge($dispatcher, new RequestStack());
        $bridge->dispatchBuildEvents('thelia_product_modification', $builder);

        $this->assertTrue($builder->has('sitemapPriority'));
    }

    public function testSeoFormsAreReplayedUnderTheLegacyAlias(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            TheliaEvents::FORM_AFTER_BUILD.'.thelia_seo',
            static function (TheliaFormEvent $event): void {
                $event->getForm()->getFormBuilder()->add('canonical', TextType::class);
            },
        );

        $builder = Forms::createFormFactory()->createNamedBuilder('thelia_product_seo');

        $bridge = new LegacyFormEventBridge($dispatcher, new RequestStack());
        $bridge->dispatchBuildEvents('thelia_product_seo', $builder);

        $this->assertTrue($builder->has('canonical'));
    }

    public function testBindExposesUnmappedFieldsAndSkipsMappedOnes(): void
    {
        $form = Forms::createFormFactory()->createNamedBuilder('thelia_product_modification')
            ->add('locale', TextType::class)
            ->add('sitemapPriority', TextType::class)
            ->getForm();
        $form->submit(['locale' => 'fr_FR', 'sitemapPriority' => '0.3']);

        $event = new UpdateSeoEvent(722);
        $event->setLocale('en_US');

        $bridge = new LegacyFormEventBridge(new EventDispatcher(), new RequestStack());
        $bridge->bindUnmappedFields($event, $form);

        $this->assertSame('0.3', $event->sitemapPriority);
        $this->assertSame('en_US', $event->getLocale(), 'fields with a real setter stay owned by the event factory');
    }
}
