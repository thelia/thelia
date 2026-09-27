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

namespace Thelia\Tests\Integration\Tools;

use Thelia\Model\RewritingUrl;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\URL;

final class UrlTest extends IntegrationTestCase
{
    /**
     * TheliaHttpKernel is the only thing that instantiates URL: a console
     * command never does. RewritingUrl::postSave()/postDelete() and
     * UrlRewritingTrait clear the rewritten url cache unconditionally on
     * every write, which used to crash any write made outside a request
     * (module postActivation() creating pages, an import command...) with
     * "URL instance is not initialized.".
     */
    public function testSavingARewritingUrlDoesNotCrashWithoutAUrlInstance(): void
    {
        $this->withoutUrlInstance(function (): void {
            self::assertNull($this->getUrlInstance(), 'Sanity check: the scenario is a missing instance.');

            $rewritingUrl = (new RewritingUrl())->setUrl('url-instance-console-probe.html');
            $rewritingUrl->save();

            self::assertNotNull($rewritingUrl->getId(), 'The row must be written: postSave() clearing the cache must not roll back the save.');

            $rewritingUrl->delete();

            self::assertTrue($rewritingUrl->isDeleted(), 'postDelete() clearing the cache must not roll back the delete either.');
        });
    }

    public function testClearInstanceRewritingUrlCacheIsANoopWithoutAnInstance(): void
    {
        $this->withoutUrlInstance(function (): void {
            URL::clearInstanceRewritingUrlCache();

            self::assertNull($this->getUrlInstance(), 'Nothing to clear must not conjure an instance.');
        });
    }

    /**
     * Simulates the console: no request ever built a URL instance.
     */
    private function withoutUrlInstance(callable $body): void
    {
        $property = new \ReflectionProperty(URL::class, 'instance');
        $previousInstance = $property->getValue();

        $property->setValue(null, null);

        try {
            $body();
        } finally {
            $property->setValue(null, $previousInstance);
        }
    }

    private function getUrlInstance(): ?URL
    {
        return (new \ReflectionProperty(URL::class, 'instance'))->getValue();
    }

    public function testAbsoluteUrlExpandsArrayParameters(): void
    {
        $url = URL::getInstance()->absoluteUrl('?view=category', [
            'tfilters' => ['attribute' => [1 => [0 => 2]]],
        ]);

        self::assertStringContainsString('tfilters%5Battribute%5D%5B1%5D%5B0%5D=2', $url);
        self::assertStringNotContainsString('=Array', $url);
    }

    public function testAbsoluteUrlSkipsEmptyArrayParameters(): void
    {
        $url = URL::getInstance()->absoluteUrl('?view=category', [
            'empty' => [],
            'page' => 2,
        ]);

        self::assertStringContainsString('page=2', $url);
        self::assertStringNotContainsString('empty', $url);
        self::assertStringNotContainsString('&&', $url);
    }

    /**
     * Scalar parameters keep their historical encoding: urlencode() spacing, '1' for true and an
     * empty value for both false and null.
     */
    public function testAbsoluteUrlKeepsScalarParameterEncoding(): void
    {
        $url = URL::getInstance()->absoluteUrl('?view=category', [
            'title' => 'a b',
            'yes' => true,
            'no' => false,
            'nothing' => null,
            'count' => 3,
        ]);

        self::assertStringContainsString('title=a+b', $url);
        self::assertStringContainsString('yes=1', $url);
        self::assertStringContainsString('no=&', $url);
        self::assertStringContainsString('nothing=&', $url);
        self::assertStringContainsString('count=3', $url);
    }
}
