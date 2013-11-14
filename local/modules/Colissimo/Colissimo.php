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

namespace Colissimo;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Country;
use Thelia\Module\BaseModule;
use Thelia\Module\DeliveryModuleInterface;

class Colissimo extends BaseModule implements DeliveryModuleInterface
{
    protected $request;
    protected $dispatcher;

    protected $prices = array(
        // area 1 : France
        "1" => array(
            "slices" => array( // max_weight => price
                '0.25'    => 5.23,
                '0.5'     => 5.8,
                '0.75'    => 6.56,
                '1'       => 7.13,
                '2'       => 8.08,
                '3'       => 9.22,
                '5'       => 11.31,
                '7'       => 13.40,
                '10'      => 16.53,
                '15'      => 19.14,
                '30'      => 26.93,
            ),
        ),
        /*
         * area 2 : A Zone
         * Union Européenne et Suisse
        */
        "2" => array(
            "slices" => array( // max_weight => price
                '1'       => 15.34,
                '2'       => 16.96,
                '3'       => 20.47,
                '4'       => 23.99,
                '5'       => 27.50,
                '6'       => 31.02,
                '7'       => 34.53,
                '8'       => 38.05,
                '9'       => 41.56,
                '10'      => 45.08,
                '15'      => 51.92,
                '20'      => 58.76,
                '25'      => 65.60,
                '30'      => 72.44,
            ),
        ),
        /*
         * area 3 : B Zone
         * Pays de l’Europe de l’Est (hors Union Européenne), Norvège, Maghreb
        */
        "3" => array(
            "slices" => array( // max_weight => price
                '1'       => 18.81,
                '2'       => 20.62,
                '3'       => 24.94,
                '4'       => 29.26,
                '5'       => 33.58,
                '6'       => 37.91,
                '7'       => 42.23,
                '8'       => 46.55,
                '9'       => 50.87,
                '10'      => 55.20,
                '15'      => 65.08,
                '20'      => 74.96,
            ),
        ),
        /*
         * area 4 : C Zone
         * Pays d’Afrique hors Maghreb, Canada, Etats-Unis, Proche et Moyen Orient
        */
        "4" => array(
            "slices" => array( // max_weight => price
                '1'       => 22.04,
                '2'       => 29.55,
                '3'       => 38.86,
                '4'       => 48.17,
                '5'       => 57.48,
                '6'       => 66.79,
                '7'       => 76.10,
                '8'       => 85.41,
                '9'       => 94.72,
                '10'      => 104.03,
                '15'      => 126.92,
                '20'      => 149.82,
            ),
        ),
        /*
         * area 5 : D Zone
         * Autres destinations
        */
        "5" => array(
            "slices" => array( // max_weight => price
                '1'       => 25.08,
                '2'       => 37.72,
                '3'       => 50.26,
                '4'       => 62.80,
                '5'       => 75.34,
                '6'       => 87.88,
                '7'       => 100.42,
                '8'       => 112.96,
                '9'       => 125.50,
                '10'      => 138.04,
                '15'      => 162.74,
                '20'      => 187.44,
            ),
        ),
        "6" => array( // area 6 : France OM1
            "slices" => array( // max_weight => price
                '0.5'     => 8.27,
                '1'       => 12.49,
                '2'       => 17.05,
                '3'       => 21.61,
                '4'       => 26.17,
                '5'       => 30.73,
                '6'       => 35.29,
                '7'       => 39.85,
                '8'       => 44.41,
                '9'       => 48.97,
                '10'      => 53.53,
                '15'      => 76.33,
                '20'      => 99.13,
                '25'      => 121.93,
                '30'      => 144.73,
            ),
        ),
        "7" => array( // area 7 : France OM2
            "slices" => array( // max_weight => price
                '0.5'     => 9.88,
                '1'       => 14.92,
                '2'       => 26.32,
                '3'       => 37.72,
                '4'       => 49.12,
                '5'       => 60.52,
                '6'       => 71.92,
                '7'       => 83.32,
                '8'       => 94.72,
                '9'       => 106.12,
                '10'      => 117.52,
                '15'      => 174.52,
                '20'      => 231.52,
                '25'      => 288.52,
                '30'      => 345.52,
            ),
        ),
    );

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     *
     * calculate and return delivery price
     *
     * @param Country $country
     * @return mixed
     */
    public function getPostage(Country $country)
    {
        // TODO: Implement getPostage() method.
        return 2;
    }

    public function getCode()
    {
        return 'Colissimo';
    }

}
