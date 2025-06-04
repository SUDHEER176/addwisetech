<?php
session_start();
if (isset($_POST['access_token'])) {
    $_SESSION['supabase_access_token'] = $_POST['access_token'];
    // Optionally, fetch user info from Supabase here
}