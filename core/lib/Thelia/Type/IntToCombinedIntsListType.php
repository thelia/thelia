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
namespace Thelia\Type;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class IntToCombinedIntsListType extends BaseType
{
    public function getType(): string
    {
        return 'Int to combined ints list type';
    }

    public function isValid($values): bool
    {
        if (null === $values) {
            return false;
        }

        foreach (explode(',', $values) as $intToCombinedInts) {
            $parts = explode(':', $intToCombinedInts);
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

            $values = preg_replace('#[\s]#', '', (string) $values);
            foreach (explode(',', (string) $values) as $intToCombinedInts) {
                $parts = explode(':', $intToCombinedInts);

                $return[trim($parts[0])] = [
                    'values' => preg_split("#(&|\|)#", (string) preg_replace('#[\(\)]#', '', $parts[1])),
                    'expression' => $parts[1],
                ];
            }

            return $return;
        }

        return null;
    }

    protected function checkLogicalFormat($string)
    {
        /* delete  all spaces and parentheses */
        $noSpaceString = preg_replace('#[\s]#', '', (string) $string);
        $noParentheseString = preg_replace('#[\(\)]#', '', (string) $noSpaceString);

        if (!preg_match('#^(\d+([\&\|]\d+)*|\*)$#', (string) $noParentheseString)) {
            return false;
        }

        /* check parenteses use */
        $openingParenthesesCount = 0;
        $closingParenthesesCount = 0;

        $length = \strlen((string) $noSpaceString);
        for ($i = 0; $i < $length; ++$i) {
            $char = $noSpaceString[$i];
            if ($char == '(') {
                /* must be :
                 * - after a &| or () or at the begining of expression
                 * - before a number or ()
                 * must not be :
                 * - at the end of expression
                 */
                if (($i != 0 && !preg_match('#[\(\)\&\|]#', $noSpaceString[$i - 1])) || !isset($noSpaceString[$i + 1]) || !preg_match('#[\(\)0-9]#', $noSpaceString[$i + 1])) {
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
                if ($i == 0 || !preg_match('#[\(\)0-9]#', $noSpaceString[$i - 1]) || (isset($noSpaceString[$i + 1]) && !preg_match('#[\(\)\&\|]#', $noSpaceString[$i + 1])) || $openingParenthesesCount - $closingParenthesesCount == 0) {
                    return false;
                }

                ++$closingParenthesesCount;
            }
        }

        return $openingParenthesesCount === $closingParenthesesCount;
    }

    public function getFormOptions(): array
    {
        return [];
    }
}
