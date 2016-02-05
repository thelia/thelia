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

$step=4;
include("header.php");

try {
    if (isset($_POST['host']) && isset($_POST['username'])  && isset($_POST['password']) && isset($_POST['port'])){

        $_SESSION['install']['host'] = $_POST['host'];
        $_SESSION['install']['username'] = $_POST['username'];
        $_SESSION['install']['password'] = $_POST['password'];
        $_SESSION['install']['port'] = $_POST['port'];

        $checkConnection = new \Thelia\Install\CheckDatabaseConnection($_POST['host'], $_POST['username'], $_POST['password'], $_POST['port']);
        if(!$checkConnection->exec()) {
            header('location: connection.php?err=1');
            exit;
        }
        $databases = $checkConnection->getConnection()->query('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA');

        if(false === $databases){
            header('location: connection.php?err=1');
            exit;
        }
    }
    elseif($_SESSION['install']['step'] >=3) {

        $checkConnection = new \Thelia\Install\CheckDatabaseConnection($_SESSION['install']['host'], $_SESSION['install']['username'], $_SESSION['install']['password'], $_SESSION['install']['port']);
    }
    else {
        header('location: connection.php?err=1');
        exit;
    }
    $_SESSION['install']['step'] = 4;
    $connection = $checkConnection->getConnection();

    ?>
    <div class="well">
        <form action="config.php" method="post">
            <fieldset>
                <legend><?php echo $trans->trans('Choose your database'); ?></legend>
                <p>
                    <?php echo $trans->trans('The SQL server contains multiple databases.'); ?><br/>
                    <?php echo $trans->trans('Select below the one you want to use.'); ?>
                </p>
                <?php foreach($databases as $database): ?>
                    <?php if ($database['SCHEMA_NAME'] == 'information_schema') continue; ?>
                    <?php
                        $connection->exec(sprintf('use %s', $database['SCHEMA_NAME']));

                        $tables = $connection->query('SHOW TABLES');

                        $found = false;
                        foreach($tables as $table) {
                            if($table[0] == 'cart_item') {
                                $found = true;
                                break;
                            }
                        }

                    ?>
                <div class="radio">
                    <label for="database_<?php echo $database['SCHEMA_NAME']; ?>">
                        <input type="radio" name="database" id="database_<?php echo $database['SCHEMA_NAME']; ?>" value="<?php echo $database['SCHEMA_NAME']; ?>" <?php if($found){ echo "disabled"; } ?>>
                        <?php echo $database['SCHEMA_NAME']; ?>
                    </label>
                </div>
                <?php endforeach; ?>
                <?php
                    $connection->exec('use information_schema');

                    $permissions = $connection->query("SELECT COUNT( * ) FROM  `USER_PRIVILEGES`
                WHERE PRIVILEGE_TYPE =  'CREATE'
                AND GRANTEE LIKE  '%".$_SESSION['install']['username']."%'
                AND IS_GRANTABLE =  'YES';");

                $writePermission = false;
                if($permissions->fetchColumn(0) > 0) {
                ?>
                <p>
                    <?php echo $trans->trans('or'); ?>
                </p>

                <div class="radio">
                    <label>
                        <?php echo $trans->trans('Create an other database'); ?>
                    </label>
                </div>

                <div class="form-group">
                    <input type="text" name="database_create" class="form-control">
                </div>
                <?php } ?>
            </fieldset>
            <div class="clearfix">
                <div class="control-btn">
                    <button type="submit" class="pull-right btn btn-default btn-primary"><?php echo $trans->trans('Continue'); ?></button>
                </div>

            </div>
        </form>
    </div>
    <?php
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
}

include 'footer.php';
