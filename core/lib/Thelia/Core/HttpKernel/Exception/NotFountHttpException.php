<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\HttpKernel\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException as BaseNotFountHttpException;
/**
 * Class NotFountHttpException
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class NotFountHttpException extends BaseNotFountHttpException
{
    protected $adminContext = false;

    public function __construct($message = null, \Exception $previous = null, $code = 0, $adminContext = false)
    {
        $this->adminContext = $adminContext;

        parent::__construct($message, $previous, $code);
    }

    public function isAdminContext()
    {
        return $this->adminContext === true;

    }
}
