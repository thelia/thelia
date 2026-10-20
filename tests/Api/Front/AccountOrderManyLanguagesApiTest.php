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

namespace Thelia\Tests\Api\Front;

use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * An order reaches about ten translatable relations (address titles, countries,
 * modules, status...), and each of them joined its translation table once per
 * active language: from five languages on, the read went past the 61 tables
 * MySQL and MariaDB accept in a join and answered 500.
 */
final class AccountOrderManyLanguagesApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private const int MAX_JOINED_TABLES = 61;

    protected function tearDown(): void
    {
        Lang::resetActiveLangsCache();

        parent::tearDown();
    }

    public function testAnOrderIsReadWithEveryLanguageActive(): void
    {
        LangQuery::create()->update(['Active' => 1], $this->getPropelConnection());
        Lang::resetActiveLangsCache();
        $locales = array_map(static fn (Lang $lang): string => $lang->getLocale(), Lang::getActiveLangs()->getData());
        self::assertGreaterThanOrEqual(5, \count($locales), 'The test database ships enough languages to pass the limit.');

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $factory->order($customer, ['statusCode' => 'paid']);

        $orderStatus = $order->getOrderStatus();
        foreach ($locales as $locale) {
            $orderStatus->setLocale($locale)->setTitle('Paid '.$locale);
        }
        $orderStatus->save($this->getPropelConnection());

        $token = $this->authenticateAsCustomer($customer);
        $payload = [];

        $statements = $this->recordSqlQueries(function () use ($order, $token, &$payload): void {
            $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $token);
            self::assertJsonResponseSuccessful($response);
            $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        });

        foreach ($statements as $statement) {
            self::assertLessThanOrEqual(self::MAX_JOINED_TABLES, 1 + substr_count($statement, ' JOIN '), $statement);
        }

        $translations = $payload['orderStatus']['i18ns'] ?? [];
        ksort($translations);
        sort($locales);
        self::assertSame($locales, array_keys($translations), 'A relation keeps the translations of every active language.');
        self::assertSame('Paid '.$locales[0], $translations[$locales[0]]['title'] ?? null);
    }
}
