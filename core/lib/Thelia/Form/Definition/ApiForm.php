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

namespace Thelia\Form\Definition;

/**
 * Class ApiForm
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @package TheliaFormDefinition
 */
final class ApiForm
{
    const EMPTY_FORM = 'thelia.api.empty';

    const CUSTOMER_CREATE = 'thelia.api.customer.create';
    const CUSTOMER_UPDATE = 'thelia.api.customer.update';
    const CUSTOMER_LOGIN = 'thelia.api.customer.login';

    const CATEGORY_CREATION = 'thelia.api.category.create';
    const CATEGORY_MODIFICATION = 'thelia.api.category.update';

    const PRODUCT_SALE_ELEMENTS = 'thelia.api.product_sale_elements';

    const PRODUCT_CREATION = 'thelia.api.product.creation';
    const PRODUCT_MODIFICATION = 'thelia.api.product.modification';
}
