<?php
session_start();

// Destroy session
session_unset();
session_destroy();

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Simple redirect to index.php in the same directory
header('Location: ./index.php?logged_out=1');
exit();
?> 