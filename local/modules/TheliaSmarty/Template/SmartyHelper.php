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

namespace TheliaSmarty\Template;

use Thelia\Core\Template\ParserHelperInterface;

/**
 * Helper class for smarty templates.
 *
 * Class SmartyHelper
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SmartyHelper implements ParserHelperInterface
{
    /**
     * Parse a string and get all smarty function and block with theirs arguments.
     * some smarty functions are not supported : if, for, ...
     *
     * @param string $content   the template content
     * @param array  $functions the only functions we want to parse
     *
     * @return array array of functions with 2 index name and attributes an array of name, value
     */
    public function getFunctionsDefinition($content, array $functions = [])
    {
        $strlen = \strlen($content);

        // init
        $buffer = '';
        $name = '';
        $attributeName = '';
        $waitfor = '';

        $inFunction = false;
        $hasName = false;
        $inAttribute = false;
        $inInnerFunction = false;

        $ldelim = '{';
        $rdelim = '}';
        $skipFunctions = ['if', 'for'];
        $skipCharacters = ["\t", "\r", "\n"];

        $store = [];
        $attributes = [];

        for ($pos = 0; $pos < $strlen; ++$pos) {
            $char = $content[$pos];

            if (\in_array($char, $skipCharacters)) {
                continue;
            }

            if (!$inFunction) {
                if ($char === $ldelim) {
                    $inFunction = true;
                    $inInnerFunction = false;
                }
                continue;
            }

            // get function name
            if (!$hasName) {
                if ($char === ' ' || $char === $rdelim) {
                    $name = $buffer;
                    // we catch this name ?
                    $hasName = $inFunction = (!\in_array($name, $skipFunctions) && (0 === \count($functions) || \in_array($name, $functions)));
                    $buffer = '';
                    continue;
                }
                // skip {
                if (\in_array($char, ['/', '$', '#', "'", '"'])) {
                    $inFunction = false;
                } else {
                    $buffer .= $char;
                }
                continue;
            }

            // inner Function ?
            if ($char === $ldelim) {
                $inInnerFunction = true;
                $buffer .= $char;
                continue;
            }

            // end ?
            if ($char === $rdelim) {
                if ($inInnerFunction) {
                    $inInnerFunction = false;
                    $buffer .= $char;
                } else {
                    if ($inAttribute) {
                        if ('' === $attributeName) {
                            $attributes[trim($buffer)] = '';
                        } else {
                            $attributes[$attributeName] = $buffer;
                        }
                        $inAttribute = false;
                    }
                    $store[] = [
                        'name' => $name,
                        'attributes' => $attributes,
                    ];
                    $inFunction = false;
                    $inAttribute = false;
                    $inInnerFunction = false;
                    $hasName = false;
                    $name = '';
                    $buffer = '';
                    $waitfor = '';
                    $attributes = [];
                }
                continue;
            }

            // attributes
            if (!$inAttribute) {
                if ($char !== ' ') {
                    $inAttribute = true;
                    $buffer = $char;
                    $attributeName = '';
                }
            } else {
                if ('' === $attributeName) {
                    if (\in_array($char, [' ', '='])) {
                        $attributeName = trim($buffer);
                        if (' ' === $char) {
                            $attributes[$attributeName] = '';
                            $inAttribute = false;
                        }
                        $buffer = '';
                    } else {
                        $buffer .= $char;
                    }
                } else {
                    if ('' === $waitfor) {
                        if (\in_array($char, ["'", '"'])) {
                            $waitfor = $char;
                        } else {
                            $waitfor = ' ';
                            $buffer .= $char;
                        }
                        continue;
                    }
                    if ($inInnerFunction) {
                        $buffer .= $char;
                    } else {
                        // end of attribute ?
                        if ($char === $waitfor) {
                            $attributes[$attributeName] = $buffer;
                            $inAttribute = false;
                            $waitfor = '';
                        } else {
                            $buffer .= $char;
                        }
                    }
                }
            }
        }

        return $store;
    }
}
