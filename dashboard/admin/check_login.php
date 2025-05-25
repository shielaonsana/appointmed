<?php
session_start();
echo "<pre>";
echo "Session Data:\n";
print_r($_SESSION);
echo "\n\nCookies:\n";
print_r($_COOKIE);
echo "</pre>";
?> 