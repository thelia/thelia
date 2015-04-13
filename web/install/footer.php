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

?>
</div>
</div>
</div>
</div>
</div>
<hr />
<footer class="footer">
    <div class="container">
        <p>&copy; Thelia 2013
            - <a href="http://www.openstudio.fr/" target="_blank">Édité par OpenStudio</a>
            - <a href="http://forum.thelia.net/" target="_blank">Forum Thelia</a>
            - <a href="http://contrib.thelia.net/" target="_blank">Contributions Thelia</a>
        </p>
    </div>
</footer>
<script src="http://code.jquery.com/jquery-2.0.3.min.js"></script>
<?php if (isset($scriptHook)) { echo $scriptHook; } ?>
</body>
</html>
<?php echo ob_end_flush(); ?>