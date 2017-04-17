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
    $errCode = isset($_GET['err']) ? $_GET['err'] : 0;

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
    <?php if ($errCode & 1) { ?>
	<div class="alert alert-danger">
	    <?php echo $trans->trans('Missing or invalid login'); ?>
	</div>
	<?php } if ($errCode & 2) { ?>
	<div class="alert alert-danger">
	    <?php echo $trans->trans('Missing password'); ?>
	</div>
	<?php } if ($errCode & 3) { ?>
        <div class="alert alert-danger">
            <?php echo $trans->trans('Missing email-address'); ?>
        </div>
    <?php } if ($errCode & 4) { ?>
	<div class="alert alert-danger">
	    <?php echo $trans->trans("The given passwords do not match"); ?>
	</div>
	<?php } ?>
    <form action="end.php" method="POST" >
        <div class="well">
            <div class="form-group">
                <label for="admin_login"><?php echo $trans->trans('Administrator login :'); ?></label>
                <input id="admin_login" class="form-control" type="text" name="admin_login" placeholder="admin" value="<?php if(isset($_GET["admin_login"])) { echo htmlspecialchars(addslashes($_GET["admin_login"])); } ?>" required>
            </div>
            <div class="form-group">
                <label for="admin_email"><?php echo $trans->trans('Administrator email :'); ?></label>
                <input id="admin_email" class="form-control" type="email" name="admin_email" placeholder="admin" value="<?php if(isset($_GET["admin_email"])) { echo htmlspecialchars(addslashes($_GET["admin_email"])); } ?>" required>
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
                    <option value="en_US"><?php echo $trans->trans('English'); ?></option>
                    <option value="fr_FR"><?php echo $trans->trans('French'); ?></option>
                    <option value="de_DE"><?php echo $trans->trans('German'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="shop_locale"><?php echo $trans->trans('Shop preferred locale :'); ?></label>
                <select id="shop_locale" name="shop_locale" class="form-control" required>
                    <option value="en_US"<?php if(isset($_GET["admin_locale"]) && $_GET["admin_locale"] === "en_US") { echo " selected=\"\""; } ?>><?php echo $trans->trans('English'); ?></option>
                    <option value="fr_FR"<?php if(isset($_GET["admin_locale"]) && $_GET["admin_locale"] === "fr_FR") { echo " selected=\"\""; } ?>><?php echo $trans->trans('French'); ?></option>
                    <option value="de_DE"<?php if(isset($_GET["admin_locale"]) && $_GET["admin_locale"] === "de_DE") { echo " selected=\"\""; } ?>><?php echo $trans->trans('German'); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label for="email_contact"><?php echo $trans->trans('Contact email :'); ?></label>
                <input id="email_contact" class="form-control" type="text" name="store_email" placeholder="foo@bar.com" value="<?php if(isset($_GET["store_email"])) { echo htmlspecialchars(addslashes($_GET["store_email"])); } ?>" required>
            </div>
            <div class="form-group">
                <label for="site_name"><?php echo $trans->trans('Company name :'); ?></label>
                <input id="site_name" class="form-control" type="text" name="store_name" placeholder="" value="<?php if(isset($_GET["store_name"])) { echo htmlspecialchars(addslashes($_GET["store_name"])); } ?>" required>
            </div>
            <div class="form-group">
                <label for="site_name"><?php echo $trans->trans('website url :'); ?></label>
                <input id="site_name" class="form-control" type="text" name="url_site" placeholder="" value="<?php if(isset($_GET["url_site"])) { echo htmlspecialchars(addslashes($_GET["url_site"])); } else { echo "http://".$_SERVER['SERVER_NAME'].$website_url; } ?>" required>
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
