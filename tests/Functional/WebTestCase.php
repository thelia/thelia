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

namespace Thelia\Tests\Functional;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\DomCrawler\Form;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpKernel\Client;
use Thelia\Model\AdminQuery;
use Thelia\Model\Map\ProductTableMap;

class WebTestCase extends BaseWebTestCase
{
    /** @var ConnectionInterface */
    protected static $connection;

    /** @var Client */
    protected static $client;

    /** @var Session|null */
    protected static $session;

    protected function setUp(): void
    {
        parent::setUp();

        if (null === self::$client) {
            self::$client = static::createClient();
        }

        // todo add logger propel queries in CI
        // Propel::getServiceContainer()->setLogger('defaultLogger', $logger);

        /** @var ConnectionWrapper $connection */
        $connection = Propel::getConnection(ProductTableMap::DATABASE_NAME);
        self::$connection = $connection->getWrappedConnection();
        //        self::$connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        //        self::$connection->rollBack();

        if (self::$session === null) {
            self::$session = self::$client->getRequest()->getSession();
        }

        self::$session->clearAdminUser();

        static::$client = null;

        parent::tearDown();
    }

    protected function loginAdmin(string $username = null, string $password = null): void
    {
        if (self::$session === null) {
            $crawler = self::$client->request('GET', '/admin/login');

            $formCrawlerNode = $crawler->filter('form');

            $form = $formCrawlerNode->form([
                'thelia_admin_login[username]' => $username ?? 'thelia',
                'thelia_admin_login[password]' => $password ?? 'thelia',
            ]);

            self::$client->submit($form);

            return;
        }

        $admin = AdminQuery::create()->filterByLogin($username ?? 'thelia')->findOne();

        self::$session->setAdminUser($admin);
    }

    protected function assertUrl(string $expected, string $actual): void
    {
        $expected = '/'.preg_quote($expected, '/').'$/';

        $this->assertMatchesRegularExpression($expected, $actual);
    }

    protected function assertRequestUrl(string $expected): void
    {
        $this->assertUrl($expected, self::$client->getRequest()->getUri());
    }

    protected function assertHeaderLocationUrl(string $expected): void
    {
        if (!self::$client->getResponse()->isRedirection()) {
            throw new \LogicException('The request was not redirected.');
        }

        $this->assertUrl($expected, self::$client->getResponse()->headers->get('location'));
    }

    protected function assertFormSameValues(array $expected, Form $form): void
    {
        $actual = array_filter($form->getValues(), static function (string $key) {
            return !str_contains($key, '[_token]');
        }, \ARRAY_FILTER_USE_KEY);

        $expected = array_filter($expected, static function (string $key) {
            return !str_contains($key, '[_token]');
        }, \ARRAY_FILTER_USE_KEY);

        $this->assertSame($expected, $actual);
    }
}
