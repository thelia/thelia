<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Type;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */

class IntToCombinedStringsListType extends BaseType
{
    public function getType()
    {
        return 'Int to combined strings list type';
    }

    public function isValid($values)
    {
        foreach (explode(',', $values) as $intToCombinedStrings) {
            $parts = explode(':', $intToCombinedStrings);
            if(count($parts) != 2)

                return false;
            if(filter_var($parts[0], FILTER_VALIDATE_INT) === false)

                return false;

            if(false === $this->checkLogicalFormat($parts[1]))

                return false;
        }

        return true;
    }

    public function getFormattedValue($values)
    {
        if ( $this->isValid($values) ) {
            $return = '';

            $values = preg_replace('#[\s]#', '', $values);
            foreach (explode(',', $values) as $intToCombinedStrings) {
                $parts = explode(':', $intToCombinedStrings);

                $return[trim($parts[0])] = array(
                    "values"        =>  preg_split( "#(&|\|)#", preg_replace('#[\(\)]#', '', $parts[1])),
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

        if(!preg_match('#^([a-zA-Z0-9_\-]+([\&\|][a-zA-Z0-9_\-]+)*|\*)$#', $noParentheseString))

            return false;

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
                if (($i!=0 && !preg_match('#[\(\)\&\|]#', $noSpaceString[$i-1])) || !isset($noSpaceString[$i+1]) || !preg_match('#[\(\)a-zA-Z0-9_\-]#', $noSpaceString[$i+1])) {
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
                if ($i == 0 || !preg_match('#[\(\)a-zA-Z0-9_\-]#', $noSpaceString[$i-1]) || (isset($noSpaceString[$i+1]) && !preg_match('#[\(\)\&\|]#', $noSpaceString[$i+1])) || $openingParenthesesCount-$closingParenthesesCount==0) {
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
