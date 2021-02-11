<?php

?>
</div>
</div>
</div>
</div>
</div>
<hr />
<footer class="footer">
    <div class="container">
        <p>&copy; Thelia <?php echo date('Y'); ?>
            - <a href="http://www.openstudio.fr/" target="_blank"><?php echo $trans->trans('Published by OpenStudio'); ?></a>
            - <a href="http://forum.thelia.net/" target="_blank"><?php echo $trans->trans('Thelia support forum'); ?></a>
            - <a href="http://thelia.net/modules/" target="_blank"><?php echo $trans->trans('Thelia contributions'); ?></a>
        </p>
    </div>
</footer>
<script src="http://code.jquery.com/jquery-2.0.3.min.js"></script>
<?php if (isset($scriptHook)) { echo $scriptHook; } ?>
</body>
</html>
<?php
if (ob_get_level() < 2) {
    echo ob_end_flush();
}
