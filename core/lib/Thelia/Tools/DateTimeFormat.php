<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

namespace Thelia\Tools;

use Symfony\Component\HttpFoundation\Request;

class DateTimeFormat
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public static function getInstance(Request $request)
    {
        return new DateTimeFormat($request);
    }

    public function getFormat($output = null)
    {
        $lang = $this->request->getSession()->getLang();

        $format = null;

        if ($lang) {
            switch ($output) {
                case "date" :
                    $format = $lang->getDateFormat();
                    break;
                case "time" :
                    $format = $lang->getTimeFormat();
                    break;
                default:
                case "datetime" :
                    $format = $lang->getDateTimeFormat();
                    break;
            }
        }

        return $format;
    }
}
