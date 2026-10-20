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

namespace Thelia\Core\Template\Parser;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * The parser picked last is kept statically, for the callers that read it through
 * getCurrentParser() without the service at hand. PHP-FPM throws it away with the
 * request, a process serving several requests in a row (a worker, a test sending
 * several requests through one kernel) does not: it is forgotten when a main request
 * begins and when the kernel resets its services, so a request never renders with
 * the parser the previous one left.
 */
#[AsAlias(id: 'thelia.parser.resolver', public: true)]
class ParserResolver implements ResetInterface
{
    private static ?ParserInterface $currentParser = null;

    public function __construct(
        #[AutowireIterator('thelia.parser.template', exclude: [ParserFallback::class])]
        private readonly iterable $parsers,
        #[AutowireIterator('thelia.parser.asset', exclude: [ParserAssetResolverFallback::class])]
        private readonly iterable $assetResolvers,
        private readonly RequestStack $requestStack,
        private readonly TemplateHelperInterface $templateHelper,
    ) {
    }

    /**
     * No parser able to render the named template means the template does not exist in the
     * active theme: an unknown view, not a broken installation. It is reported as a missing
     * resource, the same way a parser reports a template file it cannot load, so that callers
     * decide what it means for them - the front-office view chain answers 404, the back-office
     * catch-all does the same, and the mail and PDF renderers, which also run from the command
     * line, are free to treat it as the misconfiguration it is for them.
     *
     * @throws ResourceNotFoundException when no registered parser can render the template
     */
    public function getParser(string $pathTemplate, ?string $templateName): ParserInterface
    {
        if ('' === (string) $templateName || '/' === $templateName) {
            $templateName = 'index';
        }

        /** @var ParserInterface $parser */
        foreach ($this->parsers as $parser) {
            if ($parser->supportTemplateRender($pathTemplate, $templateName)) {
                self::$currentParser = $parser;

                return self::$currentParser;
            }
        }

        throw new ResourceNotFoundException(\sprintf('Parser for template %s not found', $templateName));
    }

    /**
     * @throws ResourceNotFoundException when no registered parser can render the requested view
     */
    public function getParserByCurrentRequest(): ?ParserInterface
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
            return null;
        }

        $view = $request->attributes->get('_view');
        $templateDefinition = $this->templateHelper->isAdmin($request)
            ? $this->templateHelper->getActiveAdminTemplate()
            : $this->templateHelper->getActiveFrontTemplate();

        $templatePath = $templateDefinition->getAbsolutePath();

        $parser = $this->getParser($templatePath, $view);
        $parser->setTemplateDefinition($templateDefinition, true);

        self::$currentParser = $parser;

        return $parser;
    }

    /**
     * @throws \Exception
     */
    public function getCurrentTemplateDefinition(): ?TemplateDefinition
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
            return null;
        }

        return $this->templateHelper->isAdmin($request)
            ? $this->templateHelper->getActiveAdminTemplate()
            : $this->templateHelper->getActiveFrontTemplate();
    }

    public function getAssetResolver(ParserInterface $parser): AssetResolverInterface
    {
        /* @var AssetResolverInterface $parserAsset */
        foreach ($this->assetResolvers as $assetResolvers) {
            if ($assetResolvers->supportParser($parser)) {
                return $assetResolvers;
            }
        }

        throw new \Exception('Assets parser not found');
    }

    public function getParsers(): iterable
    {
        return $this->parsers;
    }

    public static function getCurrentParser(): ?ParserInterface
    {
        return self::$currentParser;
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            self::$currentParser = null;
        }
    }

    public function reset(): void
    {
        self::$currentParser = null;
    }

    public function getDefaultParser(): ParserInterface
    {
        $defaultParser = null;

        /** @var ParserInterface $parser */
        foreach ($this->parsers as $parser) {
            if (
                null === $defaultParser
                || $defaultParser::getDefaultPriority() < $parser::getDefaultPriority()
            ) {
                $defaultParser = $parser;
            }
        }

        if (null === $defaultParser) {
            throw new \Exception('No parser found.');
        }

        return $defaultParser;
    }
}
