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

$step = 5;
include("header.php");
global $thelia;
$err = isset($_GET['err']) && $_GET['err'];

if (!$err && $_SESSION['install']['step'] != $step) {
    $checkConnection = new \Thelia\Install\CheckDatabaseConnection($_SESSION['install']['host'], $_SESSION['install']['username'], $_SESSION['install']['password'], $_SESSION['install']['port']);
    $connection = $checkConnection->getConnection();
    $connection->exec("SET NAMES UTF8");
    $database = new \Thelia\Install\Database($connection);

    if (isset($_POST['database'])) {
        $_SESSION['install']['database'] = $_POST['database'];
    }

    if (isset($_POST['database_create']) && $_POST['database_create'] != "") {
        $_SESSION['install']['database'] = $_POST['database_create'];
        $database->createDatabase($_SESSION['install']['database']);
    }

    $database->insertSql($_SESSION['install']['database']);

    if(!file_exists(THELIA_ROOT . "/local/config/database.yml")) {
        $fs = new \Symfony\Component\Filesystem\Filesystem();

        $sampleConfigFile = THELIA_ROOT . "/local/config/database.yml.sample";
        $configFile = THELIA_ROOT . "/local/config/database.yml";

        $fs->copy($sampleConfigFile, $configFile, true);

        $configContent = file_get_contents($configFile);

        $configContent = str_replace("%DRIVER%", "mysql", $configContent);
        $configContent = str_replace("%USERNAME%", $_SESSION['install']['username'], $configContent);
        $configContent = str_replace("%PASSWORD%", $_SESSION['install']['password'], $configContent);
        $configContent = str_replace(
            "%DSN%",
            sprintf("mysql:host=%s;dbname=%s;port=%s", $_SESSION['install']['host'], $_SESSION['install']['database'], $_SESSION['install']['port']),
            $configContent
        );

        file_put_contents($configFile, $configContent);

        // FA - no, as no further install will be possible
        // $fs->remove($sampleConfigFile);

        $fs->remove($thelia->getContainer()->getParameter("kernel.cache_dir"));
    }
}

$_SESSION['install']['step'] = $step;

// Retrieve the website url
$url = $_SERVER['PHP_SELF'];
$website_url = preg_replace("#/install/[a-z](.*)#" ,'', $url);

?>
<form action="end.php" method="POST" >
    <div class="well">
        <div class="form-group">
            <label for="admin_login"><?php echo $trans->trans('Administrator login :'); ?></label>
            <input id="admin_login" class="form-control" type="text" name="admin_login" placeholder="admin" value="" required>
        </div>
        <div class="form-group">
            <label for="admin_password"><?php echo $trans->trans('Administrator password :'); ?></label>
            <input id="admin_password" class="form-control" type="password" name="admin_password" value="" required>
        </div>
        <div class="form-group">
            <label for="admin_password_verif"><?php echo $trans->trans('Administrator password verification :'); ?></label>
            <input id="admin_password_verif" class="form-control" type="password" name="admin_password_verif" value="" required>
        </div>
        <div class="form-group">
            <label for="admin_locale"><?php echo $trans->trans('Administrator preferred locale :'); ?></label>
            <select id="admin_locale" name="admin_locale" class="form-control" required>
                <option value="en_US">English</option>
                <option value="fr_FR">Français</option>
            </select>
        </div>
        <div class="form-group">
            <label for="email_contact"><?php echo $trans->trans('Contact email :'); ?></label>
            <input id="email_contact" class="form-control" type="text" name="store_email" placeholder="foo@bar.com" value="" required>
        </div>
        <div class="form-group">
            <label for="site_name"><?php echo $trans->trans('Company name :'); ?></label>
            <input id="site_name" class="form-control" type="text" name="store_name" placeholder="" value="" required>
        </div>
        <div class="form-group">
            <label for="site_name"><?php echo $trans->trans('website url :'); ?></label>
            <input id="site_name" class="form-control" type="text" name="url_site" placeholder="" value="http://<?php echo $_SERVER['SERVER_NAME'].$website_url; ?>" required>
        </div>
        <div class="clearfix">
            <div class="control-btn">
                <button type="submit" class="pull-right btn btn-default btn-primary"><?php echo $trans->trans('Continue'); ?></button>
            </div>

        </div>
    </div>

</form>

<?php include('footer.php'); ?>

