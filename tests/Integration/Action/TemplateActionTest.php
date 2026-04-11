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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Template\TemplateAddAttributeEvent;
use Thelia\Core\Event\Template\TemplateCreateEvent;
use Thelia\Core\Event\Template\TemplateDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class TemplateActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsTemplate(): void
    {
        $event = new TemplateCreateEvent();
        $event
            ->setLocale('en_US')
            ->setTemplateName('Electronics');

        $this->dispatch($event, TheliaEvents::TEMPLATE_CREATE);

        $template = $event->getTemplate();
        self::assertNotNull($template);
        self::assertSame('Electronics', $template->setLocale('en_US')->getName());
    }

    public function testAddAttributeLinksAttributeToTemplate(): void
    {
        $template = $this->dispatch(
            (new TemplateCreateEvent())->setLocale('en_US')->setTemplateName('Clothing'),
            TheliaEvents::TEMPLATE_CREATE,
        )->getTemplate();
        $attribute = $this->factory->attribute();

        $this->dispatch(
            new TemplateAddAttributeEvent($template, $attribute->getId()),
            TheliaEvents::TEMPLATE_ADD_ATTRIBUTE,
        );

        $link = AttributeTemplateQuery::create()
            ->filterByTemplateId($template->getId())
            ->filterByAttributeId($attribute->getId())
            ->count();
        self::assertSame(1, $link);
    }

    public function testDeleteRemovesTemplate(): void
    {
        $template = $this->dispatch(
            (new TemplateCreateEvent())->setLocale('en_US')->setTemplateName('ToRemove'),
            TheliaEvents::TEMPLATE_CREATE,
        )->getTemplate();
        $templateId = $template->getId();

        $this->dispatch(new TemplateDeleteEvent($templateId), TheliaEvents::TEMPLATE_DELETE);

        self::assertNull(TemplateQuery::create()->findPk($templateId));
    }
}
