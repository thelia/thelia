<?php
/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Element\Pagination;

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: relaisexpert
 * Date: 04/03/2025
 */
interface PaginationInterface
{
    public function getLastPage(): int;
    public function getNbResults(): int;
    public function getPage(): int;
}
