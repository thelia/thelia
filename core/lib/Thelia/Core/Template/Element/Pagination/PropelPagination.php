<?php
/*************************************************************************************/
/*      Copyright (c) OpenStudio                                                     */
/*      web : https://www.openstudio.fr                                              */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: relaisexpert
 * Date: 04/03/2025
 */

namespace Thelia\Core\Template\Element\Pagination;

use Propel\Runtime\Util\PropelModelPager;

class PropelPagination implements PaginationInterface
{
    public function __construct(protected PropelModelPager $pager)
    {
    }

    public function getPropelPager(): PropelModelPager
    {
        return $this->pager;
    }

    public function getPage(): int
    {
        return (int) $this->pager->getPage();
    }

    public function getLastPage(): int
    {
        return (int) $this->pager->getLastPage();
    }

    public function getNbResults(): int
    {
        return (int) $this->pager->getNbResults();
    }
}
