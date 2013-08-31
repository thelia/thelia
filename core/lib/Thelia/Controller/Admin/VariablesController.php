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

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\ConfigDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Tools\URL;
class VariablesController extends BaseAdminController
{
    public function defaultAction() {
        return $this->render('variables');
    }

    public function createAction() {
    }

    public function deleteAction() {
        $event = new ConfigDeleteEvent($this->getRequest()->get('id'));

        $this->dispatch(TheliaEvents::CONFIG_DELETE, $event);

        $this->redirect(URL::adminViewUrl('variables'));
    }

    public function updateAction() {
    }
}
