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

namespace Thelia\Condition;

use Thelia\Core\Translation\Translator;

/**
 * Represent available Operations in condition checking
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
abstract class Operators
{
    /** Param1 is inferior to Param2 */
    const INFERIOR          =    '<';
    /** Param1 is inferior to Param2 */
    const INFERIOR_OR_EQUAL =    '<=';
    /** Param1 is equal to Param2 */
    const EQUAL             =     '==';
    /** Param1 is superior to Param2 */
    const SUPERIOR_OR_EQUAL =     '>=';
    /** Param1 is superior to Param2 */
    const SUPERIOR          =     '>';
    /** Param1 is different to Param2 */
    const DIFFERENT         =     '!=';
    /** Param1 is in Param2 */
    const IN                =     'in';
    /** Param1 is not in Param2 */
    const OUT               =     'out';

    /**
     * Get operator translation
     *
     * @param Translator $translator Provide necessary value from Thelia
     * @param string     $operator   Operator const
     *
     * @return string
     */
    public static function getI18n(Translator $translator, $operator)
    {
        $ret = $operator;
        switch ($operator) {
            case self::INFERIOR:
                $ret = $translator->trans(
                    'Less than',
                    []
                );
                break;
            case self::INFERIOR_OR_EQUAL:
                $ret = $translator->trans(
                    'Less than or equals',
                    []
                );
                break;
            case self::EQUAL:
                $ret = $translator->trans(
                    'Equal to',
                    []
                );
                break;
            case self::SUPERIOR_OR_EQUAL:
                $ret = $translator->trans(
                    'Greater than or equals',
                    []
                );
                break;
            case self::SUPERIOR:
                $ret = $translator->trans(
                    'Greater than',
                    []
                );
                break;
            case self::DIFFERENT:
                $ret = $translator->trans(
                    'Not equal to',
                    []
                );
                break;
            case self::IN:
                $ret = $translator->trans(
                    'In',
                    []
                );
                break;
            case self::OUT:
                $ret = $translator->trans(
                    'Not in',
                    []
                );
                break;
            default:
        }

        return $ret;
    }
}
