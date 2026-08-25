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

namespace Thelia\Tests\Unit\Core\Template;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\ParserTemplateTrait;
use Thelia\Core\Template\TemplateDefinition;

/**
 * A parser may render outside any HTTP request: an email sent by a scheduled task, a PDF
 * built by a console command. Asking it for the request then has to answer null, as the
 * ParserInterface contract says, not fail on converting a request that does not exist.
 */
class ParserTemplateTraitRequestTest extends TestCase
{
    public function testThereIsNoRequestOutsideAnHttpRequest(): void
    {
        $parser = $this->createParser(new RequestStack());

        $this->assertNull($parser->getRequest());
    }

    public function testTheMainRequestIsReturnedAsAsTheliaRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(SymfonyRequest::create('/some-page'));

        $request = $this->createParser($requestStack)->getRequest();

        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame('/some-page', $request->getPathInfo());
    }

    public function testATheliaRequestIsReturnedUntouched(): void
    {
        $requestStack = new RequestStack();
        $theliaRequest = Request::create('/some-page');
        $requestStack->push($theliaRequest);

        $this->assertSame($theliaRequest, $this->createParser($requestStack)->getRequest());
    }

    private function createParser(RequestStack $requestStack): object
    {
        $parser = new class {
            use ParserTemplateTrait;

            public function setTemplateDefinition(TemplateDefinition|string $templateDefinition, bool $fallbackToDefaultTemplate = false): void
            {
            }
        };

        $parser->requestStack = $requestStack;

        return $parser;
    }
}
