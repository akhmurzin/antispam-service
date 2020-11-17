<?php
header('Content-type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok', 'message' => 'Kolesa Academy!']);

if (!empty($_POST)) {
    $text = $_POST['text'];
    echo "$text";
} else {
    echo "Array is empty";
}

//function normalize($post) {
//    $tok = strtok($post, "\.\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|");
//    while ($tok !== false) {
//        echo "Word=$tok<br />";
//        $tok = strtok("\.\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|");
//    }
//}
//$norm = normalize($_POST['text']);
