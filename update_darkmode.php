<?php
session_start();
if (isset($_GET['darkmode'])) {
    $_SESSION['darkmode'] = $_GET['darkmode'] === 'on' ? true : false;
    echo json_encode(['success' => true, 'darkmode' => $_SESSION['darkmode']]);
}
?>