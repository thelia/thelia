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

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
interface TypeInterface
{
    public function getType();

    public function isValid($value);

    public function getFormattedValue($value);

    public function getFormType();
    public function getFormOptions();
    public function verifyForm($value, ExecutionContextInterface $context);
}
