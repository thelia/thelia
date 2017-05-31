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

namespace Thelia\Type;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */

class IntToCombinedIntsListType extends BaseType
{
    public function getType()
    {
        return 'Int to combined ints list type';
    }

    public function isValid($values)
    {
        foreach (explode(',', $values) as $intToCombinedInts) {
            $parts = explode(':', $intToCombinedInts);
            if (count($parts) != 2) {
                return false;
            }
            if (filter_var($parts[0], FILTER_VALIDATE_INT) === false) {
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

            $values = preg_replace('#[\s]#', '', $values);
            foreach (explode(',', $values) as $intToCombinedInts) {
                $parts = explode(':', $intToCombinedInts);

                $return[trim($parts[0])] = array(
                    "values"        =>  preg_split("#(&|\|)#", preg_replace('#[\(\)]#', '', $parts[1])),
                    "expression"    =>  $parts[1],
                );
            }

            return $return;
        } else {
            return null;
        }
    }

    protected function checkLogicalFormat($string)
    {
        /* delete  all spaces and parentheses */
        $noSpaceString = preg_replace('#[\s]#', '', $string);
        $noParentheseString = preg_replace('#[\(\)]#', '', $noSpaceString);

        if (!preg_match('#^([0-9]+([\&\|][0-9]+)*|\*)$#', $noParentheseString)) {
            return false;
        }

        /* check parenteses use */
        $openingParenthesesCount = 0;
        $closingParenthesesCount = 0;

        $length = strlen($noSpaceString);
        for ($i=0; $i< $length; $i++) {
            $char = $noSpaceString[$i];
            if ($char == '(') {
                /* must be :
                 * - after a &| or () or at the begining of expression
                 * - before a number or ()
                 * must not be :
                 * - at the end of expression
                 */
                if (($i!=0 && !preg_match('#[\(\)\&\|]#', $noSpaceString[$i-1])) || !isset($noSpaceString[$i+1]) || !preg_match('#[\(\)0-9]#', $noSpaceString[$i+1])) {
                    return false;
                }
                $openingParenthesesCount++;
            } elseif ($char == ')') {
                /* must be :
                 * - after a number or ()
                 * - before a &| or () or at the end of expression
                 * must not be :
                 * - at the begining of expression
                 * - if no ( remain unclose
                 */
                if ($i == 0 || !preg_match('#[\(\)0-9]#', $noSpaceString[$i-1]) || (isset($noSpaceString[$i+1]) && !preg_match('#[\(\)\&\|]#', $noSpaceString[$i+1])) || $openingParenthesesCount-$closingParenthesesCount==0) {
                    return false;
                }
                $closingParenthesesCount++;
            }
        }

        if ($openingParenthesesCount != $closingParenthesesCount) {
            return false;
        }

        return true;
    }

    public function getFormType()
    {
        return 'text';
    }

    public function getFormOptions()
    {
        return array();
    }
}
