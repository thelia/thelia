<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Template\Parser;

use Thelia\Core\Template\ParserHelperInterface;

/**
 * Class ParserHelperFallback
 * @package Thelia\Core\Template\Parser
 * @author manuel raynaud <manu@raynaud.io>
 */
class ParserHelperFallback implements ParserHelperInterface
{
    /**
     * Parse a string and get all parser's function and block with theirs arguments.
     *
     *
     *
     * @param string $content the template content
     * @param array $functions the only functions we want to parse
     *
     * @return array array of functions with 2 index name and attributes an array of name, value
     */
    public function getFunctionsDefinition($content, array $functions = array())
    {
        throw new \RuntimeException('if you want to use a parser, please register one');
    }
}
