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

namespace Thelia\Tests\Unit\BackOfficeDefaultTwig\Service\Admin;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormErrorRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;

/**
 * What a back-office action that failed shows the administrator: the refusal of a rule
 * as the rule words it, never the inside of a database driver or of an HTTP client —
 * that goes to the log.
 */
final class AdminFormErrorRendererTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        // The CI installs the theme from its main branch, which may predate the renderer
        // that keeps technical failures out of the flash messages.
        if (!class_exists(AdminFormErrorRenderer::class) || !method_exists(AdminFormErrorRenderer::class, 'isTechnical')) {
            self::markTestSkipped('The installed back-office theme predates the technical failure filter.');
        }

        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testABusinessRefusalIsShownAsItIsWorded(): void
    {
        $refusal = new CaptureExceedsAuthorizationException('ORD1', '100.000000', '60.000000');

        $this->renderer()->setup('Order payment captured', $refusal->getMessage(), null, $refusal);

        self::assertSame([$refusal->getMessage()], $this->session->getFlashBag()->get('danger'));
    }

    public function testADatabaseErrorIsNotShownButLogged(): void
    {
        $failure = new \PDOException('SQLSTATE[42S02]: Base table or view not found: db.admin_two_factor');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::stringContains('admin_two_factor'));

        $this->renderer($logger)->setup('Order payment captured', $failure->getMessage(), null, $failure);

        $shown = $this->session->getFlashBag()->get('danger');
        self::assertCount(1, $shown);
        self::assertStringNotContainsString('SQLSTATE', $shown[0]);
        self::assertStringContainsString('log', $shown[0]);
    }

    public function testAnErrorWrappingADatabaseErrorIsNotShownEither(): void
    {
        $failure = new \RuntimeException('Unable to execute statement [SELECT secret FROM admin_two_factor]', 0, new \PDOException('SQLSTATE[42S02]'));

        $this->renderer()->setup('Order payment captured', $failure->getMessage(), null, $failure);

        self::assertStringNotContainsString('admin_two_factor', $this->session->getFlashBag()->get('danger')[0]);
    }

    public function testAPhpErrorIsNotShown(): void
    {
        $failure = new \TypeError('Argument #1 ($amount) must be of type float, string given, called in /var/www/html/src/Foo.php');

        $this->renderer()->setup('Order payment captured', $failure->getMessage(), null, $failure);

        self::assertStringNotContainsString('/var/www/html', $this->session->getFlashBag()->get('danger')[0]);
    }

    private function renderer(?LoggerInterface $logger = null): AdminFormErrorRenderer
    {
        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => strtr($id, $parameters));

        return new AdminFormErrorRenderer($requestStack, $translator, $logger ?? $this->createMock(LoggerInterface::class));
    }
}
