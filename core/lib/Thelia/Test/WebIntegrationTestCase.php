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

namespace Thelia\Test;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\TheliaKernel;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

/**
 * Base class for HTTP integration tests.
 *
 * Boots a single kernel via `createClient()`, disables reboots so the Propel
 * transaction is shared between the test body and the HTTP handlers, and
 * wraps every test in a transaction that is rolled back in tearDown().
 *
 * Unlike {@see IntegrationTestCase}, this class does NOT push a synthetic
 * Request onto the stack: the `KernelBrowser` will push real requests on
 * every `$this->client->request(...)` call. Pushing a manual one here would
 * leave two requests in the stack and confuse modules that read from it.
 *
 * Prerequisites (run before the test suite):
 *   php bin/test-prepare
 *
 * Constraints:
 *   - Propel auto-increment values are NOT rolled back: never hardcode IDs.
 *   - The kernel reboot is disabled: do NOT mutate the container in a test.
 *   - Tests that need DDL/TRUNCATE must set $useTransaction = false and
 *     clean up manually.
 */
abstract class WebIntegrationTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected bool $useTransaction = true;

    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        if (!TheliaKernel::isInstalled()) {
            $this->markTestSkipped(
                'Test database not available. Run: php bin/test-prepare',
            );
        }

        $container = static::getContainer();

        // Initialize singletons that business code accesses statically.
        // The try/catch mirrors IntegrationTestCase so a detached kernel
        // does not leave a stale instance behind.
        try {
            Translator::getInstance();
        } catch (\RuntimeException) {
            $container->get('thelia.translator');
        }

        try {
            URL::getInstance();
        } catch (\RuntimeException) {
            $container->get('thelia.url.manager');
        }

        if ($this->useTransaction) {
            $this->connection = Propel::getConnection('TheliaMain');
            $this->connection->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection instanceof ConnectionInterface && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        $this->connection = null;

        parent::tearDown();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function getService(string $id): object
    {
        return static::getContainer()->get($id);
    }

    protected function getPropelConnection(): ConnectionInterface
    {
        return Propel::getConnection('TheliaMain');
    }

    protected function createFixtureFactory(): FixtureFactory
    {
        // Ensure a Request exists in the RequestStack when creating
        // fixtures. Vendor modules (e.g. CustomerFamily/OpenApiListener)
        // may access $requestStack->getCurrentRequest()->getContent()
        // during Propel model events. Without a request in the stack,
        // this crashes with "Call to a member function getContent() on null".
        $requestStack = static::getContainer()->get(RequestStack::class);
        if (null === $requestStack->getCurrentRequest()) {
            // Use a JSON body '{}' so that listeners parsing
            // $request->getContent() (e.g. PropelPersistProcessor,
            // CustomerFamily/OpenApiListener) don't crash on empty content.
            $requestStack->push(TheliaRequest::create(
                '/',
                'GET',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                '{}',
            ));
        }

        return new FixtureFactory($this->getPropelConnection());
    }
}
