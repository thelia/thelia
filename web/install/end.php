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
$step=6;
include "header.php";

if($_SESSION['install']['step'] != $step && (empty($_POST['admin_login']) || empty($_POST['admin_password']) || ($_POST['admin_password'] != $_POST['admin_password_verif']))) {
    header('location: config.php?err=1');
}

if($_SESSION['install']['step'] == 5) {
    $admin = new \Thelia\Model\Admin();
    $admin->setLogin($_POST['admin_login'])
        ->setPassword($_POST['admin_password'])
        ->setFirstname('admin')
        ->setLastname('admin')
        ->save();


    \Thelia\Model\ConfigQuery::create()
        ->filterByName('contact_email')
        ->update(array('Value' => $_POST['email_contact']));

    \Thelia\Model\ConfigQuery::create()
        ->filterByName('company_name')
        ->update(array('Value' => $_POST['company_name']));
}

//clean up cache directories
$fs = new \Symfony\Component\Filesystem\Filesystem();

$fs->remove(THELIA_ROOT . '/cache/prod');
$fs->remove(THELIA_ROOT . '/cache/dev');


$request = \Thelia\Core\HttpFoundation\Request::createFromGlobals();
$_SESSION['install']['step'] = $step;
?>

    <div class="well">
        <p class="lead text-center">
            Thanks, you have installed Thelia
        </p>
        <p class="lead text-center">
            Don't forget to delete the web/install directory.
        </p>

        <p class="lead text-center">
            <a href="<?php echo $request->getSchemeAndHttpHost(); ?>/admin">Go to back office</a>
        </p>

    </div>
<?php include "footer.php"; ?>