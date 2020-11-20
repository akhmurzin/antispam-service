<?php
header('Content-type: application/json; charset=utf-8');

/**
 * Функция преобразующая данные файла в массив
 * @param string $path Путь до файла
 */
function process_file($path)
{
    $wordArray = explode("\n", file_get_contents($path));
    foreach ($wordArray as &$eachWord) {
        $eachWord = trim($eachWord);
    }
    unset($eachWord);

    return $wordArray;
}

/**
 * Преорбразуем входящее сообщение в массив нормализовнных токенов
 * @param array $post Глобальный массив _POST
 */
function normalize($post)
{
    if (!empty($post["text"])) {
        $txt = $post["text"];
    }
    $tokensArray = [];
    $tok         = strtok($txt, " .,!?[]()<>:;-\n'\r\"/*|");
    while ($tok !== false) {
        array_push($tokensArray, $tok);
        $tok = strtok(" .,!?[]()<>:;-\n'\r\"/*|");
    }

    foreach ($tokensArray as &$eachToken) {
        $eachToken = mb_strtolower($eachToken);
    }
    unset($eachToken);
//    echo "tokensArray to lower case:\n";
//    var_dump($tokensArray);

//    $stopWords = explode("\n", file_get_contents('/code/docs/stopwords.txt'));
//
//    foreach ($stopWords as &$eachWord) {
//        $eachWord = trim($eachWord);
//    }
//    unset($eachWord);
//    echo "stopWords without spaces:\n";
//    var_dump($stopWords);

    $stopWords = process_file('/code/docs/stopwords.txt');
//    echo "stopWords without spaces:\n";
//    var_dump($stopWords);

    $tokensArray = array_diff($tokensArray, $stopWords);
//    echo "После удаления стоп слов:\n";
//    var_dump($tokensArray);

    $tokensArray = preg_grep('/\d+/', $tokensArray, PREG_GREP_INVERT);
//    echo "Минус слова из чисел:\n";
//    var_dump($tokensArray);

    sort($tokensArray);
    echo "Отсортированный массив:\n";
//    print_r($tokensArray);

    return $tokensArray;
}

/**
 * Проверка на наличие слов из запрещенного списка
 * @param array $checkArray Массив для проверки
 */
function block_list($checkArray)
{
    $blockWords = process_file('/code/docs/blocklist.txt');
//    print_r($blockWords);
    $blockFound = (bool) count(array_intersect($blockWords, $checkArray));
//    print_r($blockFound);
    if ($blockFound) {
//        return "Block word is found";
        return [
            'status'          => 'ok',
            'spam'            => 'true',
            'reason'          => 'block_list',
            'normalized_text' => "placeholder for array",
        ];
    }
}

if (!empty($_POST)) {
    $normalizedArray = normalize($_POST);
    $isSpam          = block_list($normalizedArray);
    echo json_encode($isSpam);
} else {
    echo json_encode(['status' => 'error', 'message' => 'no text']);
}
