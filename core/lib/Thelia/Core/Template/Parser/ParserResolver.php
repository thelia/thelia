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
use Thelia\Core\Template\ParserInterface;

#[AsAlias(id: 'thelia.parser.resolver', public: true)]
class ParserResolver
{
    public function __construct(
        #[TaggedIterator('thelia.parser.template', exclude: [ParserFallback::class])]
        private readonly iterable $parsers
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getParser(?string $templateName): ParserInterface
    {
        if ('' === (string) $templateName) {
            $templateName = 'index';
        }
        /** @var ParserInterface $parser */
        foreach ($this->parsers as $parser) {
            if ($parser->supportTemplateRender($templateName)) {
                return $parser;
            }
        }

        throw new \Exception(sprintf('Parser %s not found', $templateName));
    }

    public function getParsers(): iterable
    {
        return $this->parsers;
    }
}
