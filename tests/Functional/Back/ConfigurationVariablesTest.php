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

namespace Functional\Back;

use Thelia\Tests\Functional\WebTestCase;

class ConfigurationVariablesTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/variables');

        self::assertResponseIsSuccessful();
    }

    public function testSave(): void
    {
        $this->loginAdmin();

        $crawler = self::$client->request('GET', '/admin/configuration/variables');

        $formCrawlerNode = $crawler->filter('#page-wrapper form');

        $form = $formCrawlerNode->form([]);

        self::$client->submit($form);

        self::$client->followRedirect();

        self::assertResponseIsSuccessful();

        $this->assertRequestUrl('/admin/configuration/variables');
    }
}
