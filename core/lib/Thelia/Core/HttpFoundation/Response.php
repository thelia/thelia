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
namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Thelia\Log\Tlog;

/**
 * extends Thelia\Core\HttpFoundation\Response for adding some helpers
 *
 * Class Response
 * @package Thelia\Core\HttpFoundation
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Response extends BaseResponse
{
    /**
     * Allow Tlog to write log stuff in the fina content.
     *
     * @see \Thelia\Core\HttpFoundation\Response::sendContent()
     */
    public function sendContent()
    {
        Tlog::getInstance()->write($this->content);

        parent::sendContent();
    }
}
