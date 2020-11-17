<?php
header('Content-type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok', 'message' => 'Kolesa Academy!']);

require_once('is_spam.php');

$norm = normalize($_POST['text']);
if (!empty($_POST)) {

}
