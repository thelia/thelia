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

namespace Thelia\Core\Propel\Generator\Builder\Om;

use Propel\Generator\Builder\Om\QueryInheritanceBuilder as PropelQueryInheritanceBuilder;
use Thelia\Core\Propel\Generator\Builder\Om\Mixin\ImplementationClassTrait;

class QueryInheritanceBuilder extends PropelQueryInheritanceBuilder
{
    use ImplementationClassTrait;
}
