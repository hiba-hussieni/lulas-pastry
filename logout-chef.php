<?php
session_start();
session_unset();
session_destroy();
header("Location: chef-login.php?reset=1");
exit();
?>