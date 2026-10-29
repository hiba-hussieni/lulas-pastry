<?php
session_start();
session_unset();
session_destroy();
header("Location: customer-login.php?reset=1");
exit();
?>
