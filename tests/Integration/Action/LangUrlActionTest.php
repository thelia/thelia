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

use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Lang\LangUrlEvent;
use Thelia\Model\LangQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The URL of each language, used when every language is served on its own domain.
 */
final class LangUrlActionTest extends ActionIntegrationTestCase
{
    public function testStoresTheUrlOfEveryLanguageOfTheEvent(): void
    {
        $languages = LangQuery::create()->orderById()->limit(2)->find();
        self::assertCount(2, $languages, 'The demo data ships at least two languages.');

        $event = new LangUrlEvent();
        foreach ($languages as $lang) {
            $event->addUrl($lang->getId(), 'https://'.$lang->getCode().'.shop.test');
        }

        $this->dispatch($event, TheliaEvents::LANG_URL);

        foreach ($languages as $lang) {
            self::assertSame(
                'https://'.$lang->getCode().'.shop.test',
                LangQuery::create()->findPk($lang->getId())?->getUrl(),
            );
        }
    }
}
