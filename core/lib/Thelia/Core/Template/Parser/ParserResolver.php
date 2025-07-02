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

use Exception;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;

#[AsAlias(id: 'thelia.parser.resolver', public: true)]
class ParserResolver
{
    private static ?ParserInterface $currentParser = null;

    public function __construct(
        #[TaggedIterator('thelia.parser.template', exclude: [ParserFallback::class])]
        private readonly iterable $parsers,
        #[TaggedIterator('thelia.parser.asset', exclude: [ParserAssetResolverFallback::class])]
        private readonly iterable $assetResolvers,
        private readonly RequestStack $requestStack,
        private readonly TemplateHelperInterface $templateHelper,
    ) {
    }

    /**
     * @throws Exception
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

        throw new Exception(sprintf('Parser for template %s not found', $templateName));
    }

    /**
     * @throws Exception
     */
    public function getParserByCurrentRequest(): ?ParserInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return null;
        }

        $view = $request->attributes->get('_view');
        $templateDefinition = $request->fromAdmin()
            ? $this->templateHelper->getActiveAdminTemplate()
            : $this->templateHelper->getActiveFrontTemplate();

        $templatePath = $templateDefinition->getAbsolutePath();

        $parser = $this->getParser($templatePath, $view);
        $parser->setTemplateDefinition($templateDefinition, true);

        self::$currentParser = $parser;
        return $parser;
    }

    /**
     * @throws Exception
     */
    public function getAssetResolver(ParserInterface $parser): AssetResolverInterface
    {
        if (!self::$currentParser instanceof ParserInterface) {
            throw new Exception('Parser not found');
        }

        /* @var AssetResolverInterface $parserAsset */
        foreach ($this->assetResolvers as $assetResolvers) {
            if ($assetResolvers->supportParser($parser)) {
                return $assetResolvers;
            }
        }

        throw new Exception('Assets parser not found');
    }

    public function getParsers(): iterable
    {
        return $this->parsers;
    }

    public static function getCurrentParser(): ?ParserInterface
    {
        return self::$currentParser;
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
            throw new Exception('No parser found.');
        }

        return $defaultParser;
    }
}
