<?php
session_start();
session_unset();
session_destroy();
// Mogawas sa php/ folder ug mosulod sa html/ folder paingon sa index.html
header("Location: ../html/index.html");
exit();
?>