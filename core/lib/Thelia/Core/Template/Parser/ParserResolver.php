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

namespace Thelia\Core\Template\Parser;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Template\ParserInterface;

#[AsAlias(id: 'thelia.parser.resolver', public: true)]
class ParserResolver
{
    private static ?ParserInterface $currentParser = null;

    public function __construct(
        #[TaggedIterator('thelia.parser.template', exclude: [ParserFallback::class])]
        private readonly iterable $parsers,
        #[TaggedIterator('thelia.parser.asset', exclude: [ParserAssetResolverFallback::class])]
        private readonly iterable $assetResolvers
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getParser(string $pathTemplate, ?string $templateName): ParserInterface
    {
        if ('' === (string) $templateName) {
            $templateName = 'index';
        }
        /** @var ParserInterface $parser */
        foreach ($this->parsers as $parser) {
            if ($parser->supportTemplateRender($pathTemplate, $templateName)) {
                self::$currentParser = $parser;

                return $parser;
            }
        }

        throw new \Exception(sprintf('Parser %s not found', $templateName));
    }

    /**
     * @throws \Exception
     */
    public function getAssetResolver(ParserInterface $parser): AssetResolverInterface
    {
        if (null === self::$currentParser) {
            throw new \Exception('Parser not found');
        }
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
}
