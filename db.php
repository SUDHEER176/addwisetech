<?php
$db = new mysqli("localhost", "root", "", "addwise");
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
?>