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

namespace Thelia\Form\Api\Product;

use Thelia\Form\ProductModificationForm as BaseProductModificationForm;

/**
 * Class ProductModificationForm
 * @package Thelia\Form\Api\Product
 * @author manuel raynaud <manu@raynaud.io>
 */
class ProductModificationForm extends BaseProductModificationForm
{
    public function getName()
    {
        return '';
    }
}
