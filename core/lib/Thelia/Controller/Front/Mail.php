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

namespace Thelia\Controller\Front;


/**
 * Class Mail
 * @package Thelia\Controller\Front
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Mail extends BaseFrontController
{
    /**
     * This is a demo how to send a mail using swiftmailer + smarty
     */
    public function test()
    {
        $message = \Swift_Message::newInstance('Wonderful Subject')
            ->setFrom(array('john@doe.com' => 'John Doe'))
            ->setTo(array('mraynaud@openstudio.fr' => 'name'))
            ->setBody($this->render('Here is the message itself'))
        ;

        $this->getMailer()->send($message);

        exit;
    }
}