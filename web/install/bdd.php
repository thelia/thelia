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

$step=4;
include("header.php");

if (isset($_POST['host']) && isset($_POST['username'])  && isset($_POST['password']) && isset($_POST['port'])){

    $_SESSION['install']['host'] = $_POST['host'];
    $_SESSION['install']['username'] = $_POST['username'];
    $_SESSION['install']['password'] = $_POST['password'];
    $_SESSION['install']['port'] = $_POST['port'];

    $checkConnection = new \Thelia\Install\CheckDatabaseConnection($_POST['host'], $_POST['username'], $_POST['password'], $_POST['port']);
    if(! $checkConnection->exec() || $checkConnection->getConnection()->query('show databases') === false){
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

$databases = $connection->query('show databases');
?>
    <div class="well">
        <form action="config.php" method="post">
            <fieldset>
                <legend>Choose your database</legend>
                <p>
                    The SQL server contains multiple databases.<br/>
                    Select below the one you want to use.
                </p>
                <?php foreach($databases as $database): ?>
                    <?php if ($database['Database'] == 'information_schema') continue; ?>
                    <?php
                        $connection->exec(sprintf('use %s', $database['Database']));

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
                    <label for="database_<?php echo $database['Database']; ?>">
                        <input type="radio" name="database" id="database_<?php echo $database['Database']; ?>" value="<?php echo $database['Database']; ?>" <?php if($found){ echo "disabled"; } ?>>
                        <?php echo $database['Database']; ?>
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
                    or
                </p>

                <div class="radio">
                    <label>
                        Create an other database
                    </label>
                </div>

                <div class="form-group">
                    <input type="text" name="database_create" class="form-control">
                </div>
                <?php } ?>
            </fieldset>
            <div class="clearfix">
                <div class="control-btn">
                    <button type="submit" class="pull-right btn btn-default btn-primary">Continue</button>
                </div>

            </div>
        </form>
    </div>

<?php include 'footer.php'; ?>