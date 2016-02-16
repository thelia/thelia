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

try {
    if ($_SESSION['install']['step'] != $step && (empty($_POST['admin_login']) || empty($_POST['admin_password']) || ($_POST['admin_password'] != $_POST['admin_password_verif']) || strlen($_POST['admin_login']) < 3)) {
        $query = $_POST;
        $query["err"] = 0;
        if (empty($_POST['admin_login'])) {
            $query["err"] |= 1;
        }
        if (empty($_POST['admin_email'])) {
            $query["err"] |= 3;
        }
        if (empty($_POST['admin_password'])) {
            $query["err"] |= 2;
        }
        if ($_POST['admin_password'] != $_POST['admin_password_verif']) {
            $query["err"] |= 4;
        }
        if (isset($query["admin_password"])) {
            unset($query["admin_password"]);
        }
        if (isset($query["admin_password_verif"])) {
            unset($query["admin_password_verif"]);
        }
        header(sprintf('location: config.php?%s', http_build_query($query)));
        exit; // Don't forget to exit, otherwise, the script will continue to run.
    }

    if($_SESSION['install']['step'] == 5){

        // Check now if we can create the App.
        $thelia = new \Thelia\Core\Thelia("install", true);
        $thelia->boot();

        $admin = new \Thelia\Model\Admin();
        $admin->setLogin($_POST['admin_login'])
            ->setPassword($_POST['admin_password'])
            ->setFirstname('admin')
            ->setLastname('admin')
            ->setLocale(empty($_POST['admin_locale']) ? 'en_US' : $_POST['admin_locale'])
            ->setEmail($_POST['admin_email'])
            ->save();


        \Thelia\Model\ConfigQuery::create()
            ->filterByName('store_email')
            ->update(array('Value' => $_POST['store_email']));

        \Thelia\Model\ConfigQuery::create()
            ->filterByName('store_notification_emails')
            ->update(array('Value' => $_POST['store_email']));

        \Thelia\Model\ConfigQuery::create()
            ->filterByName('store_name')
            ->update(array('Value' => $_POST['store_name']));

        \Thelia\Model\ConfigQuery::create()
            ->filterByName('url_site')
            ->update(array('Value' => $_POST['url_site']));

        $lang = \Thelia\Model\LangQuery::create()
            ->findOneByLocale(empty($_POST['shop_locale']) ? "en_US" : $_POST['shop_locale'])
        ;

        if (null !== $lang) {
            $lang->toggleDefault();
        }
        $secret = \Thelia\Tools\TokenProvider::generateToken();

        \Thelia\Model\ConfigQuery::write('form.secret', $secret, 0, 0);
    }

    //clean up cache directories
    $fs = new \Symfony\Component\Filesystem\Filesystem();

    $fs->remove(THELIA_ROOT . '/cache/prod');
    $fs->remove(THELIA_ROOT . '/cache/dev');
    $fs->remove(THELIA_ROOT . '/cache/install');


    $request = \Thelia\Core\HttpFoundation\Request::createFromGlobals();
    $_SESSION['install']['step'] = $step;

    // Retrieve the website url
    $url = $_SERVER['PHP_SELF'];
    $website_url = preg_replace("#/install/[a-z](.*)#" ,'', $url);

    ?>
    <div class="well">
        <p class="lead text-center">
            <?php echo $trans->trans('Thelia is now installed. Thank you !'); ?>
        </p>

<?php
$scriptHook = <<<SCRIPT
<script>
    $(document).ready(function() {
        var current_site_url = "{$website_url}";
        var admin_link = $("#admin_url");

        $.ajax(current_site_url + "/empty")
         .success(function() {
            admin_link.attr("href", current_site_url + "/admin");
         })
    });
</script>
SCRIPT;

ob_start();
include('footer.php');
$footerContent = ob_get_clean();

    // Remove the install wizard
    /*try {
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->remove(THELIA_WEB_DIR . DS . 'install');

        ?>
        <div class="alert alert-success"><p><?php
        echo $trans->trans('The install wizard directory will be removed');
        ?></p></div><?php
    } catch (\Symfony\Component\Filesystem\Exception\IOException $ex) {
        ?>
        <p class="lead text-center">
            <?php echo $trans->trans('Don\'t forget to delete the web/install directory.'); ?>
        </p>
        <?php
    }*/

    ?>
    <p class="lead text-center">
        <?php echo $trans->trans('Don\'t forget to delete the web/install directory.'); ?>
    </p>

    <p class="lead text-center">
        <a href="<?php echo $website_url; ?>/index.php/admin" id="admin_url"><?php echo $trans->trans('Go to back office'); ?></a>
    </p>
    <?php

    echo $footerContent;
}
catch (\Exception $ex) {
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

include('footer.php');
}

