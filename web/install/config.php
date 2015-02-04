<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

$step = 5;
include("header.php");

try {
    $err = isset($_GET['err']) && $_GET['err'];

    if (!$err && $_SESSION['install']['step'] != $step) {
        try {
            $checkConnection = new \Thelia\Install\CheckDatabaseConnection(
                $_SESSION['install']['host'],
                $_SESSION['install']['username'],
                $_SESSION['install']['password'],
                $_SESSION['install']['port']
            );
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

            if (!file_exists(THELIA_ROOT . "/local/config/database.yml")) {
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
                    sprintf(
                        "mysql:host=%s;dbname=%s;port=%s",
                        $_SESSION['install']['host'],
                        $_SESSION['install']['database'],
                        $_SESSION['install']['port']
                    ),
                    $configContent
                );

                file_put_contents($configFile, $configContent);
            }
        } catch(\exception $ex) {
            ?>
            <div class="alert alert-danger"><?php echo $trans->trans('Unexpected error occured: %err', ['%err' => $ex->getMessage()]); ?></div>
            <?php
            exit;
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
                <label for="shop_locale"><?php echo $trans->trans('Shop preferred locale :'); ?></label>
                <select id="shop_locale" name="shop_locale" class="form-control" required>
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
<?php
} catch (\Exception $ex) {
    ?>
    <div class="alert alert-danger">
        <?php echo $trans->trans(
            '<p><strong>Sorry, an unexpected error occured</strong>: %err</p><p>Error details:</p><p>%details</p>',
            [
                '%err' => $ex->getMessage(),
                '%details' => nl2br($ex->getTraceAsString())
            ]
        ); ?>
    </div>
<?php
}

include 'footer.php';