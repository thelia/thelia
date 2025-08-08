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

namespace Thelia\Type;

/**
 * This class manages boolea expression, in the form:
 * number:expr, number:expr, ...
 * expr: string|(expr oper expr)
 * oper: | or &
 * Special characters , : ( ) & | should be escaped in strings, for example a\(b\).
 *
 * Sample expression : 1: foo & bar | (fooo &baar), 4: *, 67: (foooo & baaar), 11:(abc & def\&ghi\|ttt)
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 */
class IntToCombinedStringsListType extends BaseType
{
    public function getType()
    {
        return 'Int to combined strings list type';
    }

    public function isValid($values)
    {
        if (null === $values) {
            return false;
        }
        // Explode expession parts, ignoring escaped characters, (\, and \:)
        foreach (preg_split('#(?<!\\\),#', $values) as $intToCombinedStrings) {
            $parts = preg_split('#(?<!\\\):#', $intToCombinedStrings);

            if (\count($parts) != 2) {
                return false;
            }

            if (filter_var($parts[0], \FILTER_VALIDATE_INT) === false) {
                return false;
            }

            if (false === $this->checkLogicalFormat($parts[1])) {
                return false;
            }
        }

        return true;
    }

    public function getFormattedValue($values)
    {
        if ($this->isValid($values)) {
            $return = [];

            foreach (preg_split('/(?<!\\\),/', $values) as $intToCombinedStrings) {
                $parts = preg_split('/(?<!\\\):/', $intToCombinedStrings);

                $return[trim($parts[0])] = [
                    'values' => array_map(
                        function ($item) {
                            return trim(self::unescape($item));
                        },
                        preg_split(
                            '#(?<!\\\)[&|]#',
                            preg_replace(
                                '#(?<!\\\)[\(\)]#',
                                '',
                                $parts[1]
                            )
                        )
                    ),
                    'expression' => trim(self::unescape($parts[1])),
                ];
            }

            return $return;
        }

        return null;
    }

    /**
     * Escape a string to use it safely in an expression. abc:def => abc\:def.
     * Escapes characters are , : ( ) | &.
     *
     * @return string
     */
    public static function escape($string)
    {
        return preg_replace('/([,\:\(\)\|\&])/', '\\\$1', $string);
    }

    /**
     * Unescape a string and remove avai escape symbols. abc\:def => abc:def.
     *
     * @return string
     */
    public static function unescape($string)
    {
        return preg_replace('/\\\([,\:\(\)\|\&])/', '\1', $string);
    }

    protected function checkLogicalFormat($string)
    {
        // Delete escaped characters
        $string = preg_replace('/\\\[,\:\(\)\|\&]/', '', $string);

        /* delete  all spaces and parentheses */
        $noSpaceString = preg_replace('#[\s]#', '', $string);
        $noParentheseString = preg_replace('#[\(\)]#', '', $noSpaceString);

        if (!preg_match('#^([a-zA-Z0-9_\-]+([\&\|][a-zA-Z0-9_\-]+)*|\*)$#', $noParentheseString)) {
            return false;
        }

        /* check parenteses use */
        $openingParenthesesCount = 0;
        $closingParenthesesCount = 0;

        $length = \strlen($noSpaceString);
        for ($i = 0; $i < $length; ++$i) {
            $char = $noSpaceString[$i];
            if ($char == '(') {
                /* must be :
                 * - after a &| or () or at the begining of expression
                 * - before a number or ()
                 * must not be :
                 * - at the end of expression
                 */
                if (($i != 0 && !preg_match('#[\(\)\&\|]#', $noSpaceString[$i - 1])) || !isset($noSpaceString[$i + 1]) || !preg_match('#[\(\)a-zA-Z0-9_\-]#', $noSpaceString[$i + 1])) {
                    return false;
                }
                ++$openingParenthesesCount;
            } elseif ($char == ')') {
                /* must be :
                 * - after a number or ()
                 * - before a &| or () or at the end of expression
                 * must not be :
                 * - at the begining of expression
                 * - if no ( remain unclose
                 */
                if ($i == 0 || !preg_match('#[\(\)a-zA-Z0-9_\-]#', $noSpaceString[$i - 1]) || (isset($noSpaceString[$i + 1]) && !preg_match('#[\(\)\&\|]#', $noSpaceString[$i + 1])) || $openingParenthesesCount - $closingParenthesesCount == 0) {
                    return false;
                }
                ++$closingParenthesesCount;
            }
        }

        if ($openingParenthesesCount != $closingParenthesesCount) {
            return false;
        }

        return true;
    }

    public function getFormOptions()
    {
        return [];
    }
}
