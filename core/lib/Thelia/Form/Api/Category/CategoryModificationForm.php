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

namespace Thelia\Form\Api\Category;

use Thelia\Form\CategoryModificationForm as BaseCategoryModificationForm;

/**
 * Class CategoryModificationForm
 * @package Thelia\Form\Api\Category
 * @author manuel raynaud <manu@raynaud.io>
 */
class CategoryModificationForm extends BaseCategoryModificationForm
{
    public function getName()
    {
        return '';
    }
}
