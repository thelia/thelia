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
use Twig\Environment;

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

        // Render pages the way production renders them. The Symfony skeleton
        // turns on Twig's strict_variables in the test environment, where a
        // theme reading an optional variable it never defines raises a
        // RuntimeError instead of resolving to null. Every themed page then
        // answers a 500 that says nothing about the page itself, and a smoke
        // test can only tolerate that instead of asserting a 200.
        $twig = $container->has('twig') ? $container->get('twig') : null;
        if ($twig instanceof Environment) {
            $twig->disableStrictVariables();
        }

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
     * Asserts that a page of the installed theme is served with a 200.
     *
     * Exceptions are surfaced instead of being turned into a 500 error page, so
     * a failure names the template and the line that broke rather than reporting
     * "failed asserting that 500 is 200".
     *
     * One outcome is not a defect: a theme installed by Composer ships no built
     * assets, and rendering reads them — the Encore entrypoints file, or the
     * vendor assets AssetMapper expects under the theme. The core CI builds
     * neither, so that single failure is reported as a skipped test. Everything
     * else — a broken template, a controller error, an unexpected status — fails.
     */
    protected function assertPageRenders(string $url): void
    {
        $this->client->catchExceptions(false);

        try {
            $this->client->request('GET', $url);
        } catch (\Throwable $failure) {
            if (!self::isMissingAssetBuild($failure)) {
                throw $failure;
            }

            self::markTestSkipped(\sprintf(
                '"%s" cannot be rendered: the theme assets are not built (npm run build, importmap:install).',
                $url,
            ));
        } finally {
            $this->client->catchExceptions(true);
        }

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            \sprintf('"%s" must be served with a 200.', $url),
        );
    }

    /**
     * True when the whole asset build is missing. An entry missing from a built
     * manifest raises a different error and stays a failure.
     */
    private static function isMissingAssetBuild(\Throwable $failure): bool
    {
        // A theme builds its assets with Encore, which reads an entrypoints file, or
        // with AssetMapper, which reads the vendor assets `importmap:install` writes
        // into the theme. Both are produced by the theme build, never by the core.
        $missingBuildMessages = [
            'Could not find the entrypoints file',
            'vendor asset is missing',
        ];

        for ($cause = $failure; null !== $cause; $cause = $cause->getPrevious()) {
            foreach ($missingBuildMessages as $missingBuildMessage) {
                if (str_contains($cause->getMessage(), $missingBuildMessage)) {
                    return true;
                }
            }
        }

        return false;
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
        // fixtures. Listeners may access $requestStack->getCurrentRequest()->getContent()
        // during Propel model events. Without a request in the stack,
        // this crashes with "Call to a member function getContent() on null".
        $requestStack = static::getContainer()->get(RequestStack::class);
        if (null === $requestStack->getCurrentRequest()) {
            // Use a JSON body '{}' so that listeners parsing
            // $request->getContent() (e.g. PropelPersistProcessor) don't crash on empty content.
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
