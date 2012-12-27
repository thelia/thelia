<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Log\Destination;

use Thelia\Log\AbstractTlogDestination;

class TlogDestinationHtml extends AbstractTlogDestination {

            // Nom des variables de configuration
            // ----------------------------------
            const VAR_STYLE = "tlog_destinationhtml_style";
            const VALEUR_STYLE_DEFAUT = "text-align: left; font-size: 12px; font-weight: normal; line-height: 14px; float: none; display:block; color: #000; background-color: #fff; font-family: Courier New, courier,fixed;";

            private $style;

            public function __construct() {
                    parent::__construct();

            }

            public function configurer($config = false) {
                    $this->style = $this->get_config(self::VAR_STYLE);
            }

            public function get_titre() {
                    return "Affichage direct dans la page, en HTML";
            }

            public function get_description() {
                    return "Permet d'afficher les logs directement dans la page resultat, avec une mise en forme HTML.";
            }

            public function get_configs() {
//                    return array(
//                            new TlogDestinationConfig(
//                                            self::VAR_STYLE,
//                                            "Style d'affichage direct dans la page",
//                                            "Vous pouvez aussi laisser ce champ vide, et créer un style \"tlog-trace\" dans votre feuille de style.",
//                                            self::VALEUR_STYLE_DEFAUT,
//                                            TlogDestinationConfig::TYPE_TEXTAREA
//                            )
//                    );
            }

    public function ecrire(&$res) {

                    $block = sprintf('<pre class="tlog-trace" style="%s">%s</pre>', $this->style, htmlspecialchars(implode("\n", $this->_logs)));

                    $this->inserer_apres_body($res, $block);
    }
}