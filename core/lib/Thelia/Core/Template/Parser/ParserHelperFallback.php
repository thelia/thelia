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

use Thelia\Core\Template\ParserHelperInterface;

/**
 * Class ParserHelperFallback.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class ParserHelperFallback implements ParserHelperInterface
{
    /**
     * Parse a string and get all parser's function and block with theirs arguments.
     *
     * @param string $content   the template content
     * @param array  $functions the only functions we want to parse
     *
     * @return array array of functions with 2 index name and attributes an array of name, value
     */
    public function getFunctionsDefinition($content, array $functions = []): never
    {
        throw new \RuntimeException('if you want to use a parser, please register one');
    }
}
