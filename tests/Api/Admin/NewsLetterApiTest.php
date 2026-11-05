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

namespace Thelia\Tests\Api\Admin;

use Thelia\Model\ConfigQuery;
use Thelia\Model\NewsletterQuery;
use Thelia\Test\ApiTestCase;

/**
 * The newsletter switch only controls what visitors are offered: the back office keeps
 * recording subscribers through the admin API.
 */
final class NewsLetterApiTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAnAdministratorStillCreatesASubscriberWhenTheNewsletterIsOff(): void
    {
        $token = $this->authenticateAsAdmin();
        ConfigQuery::write('newsletter_enabled', '0');

        $response = $this->jsonRequest('POST', '/api/admin/news_letters', [
            'email' => 'api-off+nltest@example.com',
            'firstname' => 'Api',
            'lastname' => 'Off',
            'locale' => 'en_US',
            'unsubscribed' => false,
        ], $token);

        self::assertJsonResponseSuccessful($response);
        $subscriber = NewsletterQuery::create()->findOneByEmail('api-off+nltest@example.com');
        self::assertNotNull($subscriber);
        self::assertSame(0, (int) $subscriber->getUnsubscribed());
    }
}
