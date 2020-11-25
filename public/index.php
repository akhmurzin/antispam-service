<?php
require __DIR__ . '/../vendor/autoload.php';
use RateLimit\Exception\LimitExceeded;
use RateLimit\PredisRateLimiter;
use RateLimit\Rate;

header('Content-type: application/json; charset=utf-8', false);

/**
 * Функция преобразующая данные файла в массив
 *
 * @param string $path Путь до файла
 */
function process_file($path)
{
    $words = explode("\n", file_get_contents($path));

    foreach ($words as &$word) {
        $word = trim($word);
    }
    unset($word);

    return $words;
}

/**
 * Преорбразуем входящее сообщение в массив нормализовнных токенов
 *
 * @param string $text Сообщение в параметре 'text'
 */
function normalize($text)
{
    $tokensArray   = [];
    $token         = strtok($text, " .,!?[]()<>:;-\n'\r\"/*|");

    while ($token !== false) {
        array_push($tokensArray, $token);
        $token = strtok(" .,!?[]()<>:;-\n'\r\"/*|");
    }

    foreach ($tokensArray as &$eachToken) {
        $eachToken = mb_strtolower($eachToken);
    }
    unset($eachToken);

    $stopWords = process_file('/code/docs/stopwords.txt');

    $tokensArray = array_diff($tokensArray, $stopWords);

    $tokensArray = preg_grep('/\d+/', $tokensArray, PREG_GREP_INVERT);

    sort($tokensArray);

    return $tokensArray;
}

/**
 * Функция для правильной нормализации текста содержащего электронную почту
 *
 * @param string $email
 * @param string $string
 */
function handle_email($email, $string)
{
    $textWithoutEmail = str_replace($email, '', $string);
    $workArray        = normalize($textWithoutEmail);//Нормализованный массив без почты
    array_push($workArray, $email);
    sort($workArray);//Нормализованный массив с почтой

    return $workArray;
}

/**
 * Проверка на наличие слов из запрещенного списка
 *
 * @param string $checkText Текст для проверки
 * @param mixed  $checkRate
 */
function spam_check($checkText, $checkRate)
{
    try {
        $redis = new Predis\Client([
            "scheme" => "tcp",
            "host"   => "redis",
            "port"   => 6379,
        ]);
    } catch (Exception $e) {
        echo "Couldn't connected to Redis";
        echo $e->getMessage();
    }

    //email check
    if (preg_match('/\b[^\s]+@[^\s]+/', $checkText, $match)) {
        $email = filter_var($match[0], FILTER_VALIDATE_EMAIL);

        if ($email) {
            $normalizedEmailArray = handle_email($email, $checkText);

            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'block_list',
                'normalized_text' => implode(" ", $normalizedEmailArray),
            ];
        }
    }

    $normalizedArray = normalize($checkText);

    //blockwords check
    $blockWords = process_file('/code/docs/blocklist.txt');

    $blockFound = (bool) count(array_intersect($blockWords, $normalizedArray));

    if ($blockFound) {
        return [
            'status'          => 'ok',
            'spam'            => true,
            'reason'          => 'block_list',
            'normalized_text' => implode(" ", $normalizedArray),
        ];
    }

    //mixed words check
    foreach ($normalizedArray as $word) {
        if (preg_match('/[\p{Cyrillic}]/u', $word) && preg_match('/[\p{Latin}]/u', $word)) {
            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'mixed_words',
                'normalized_text' => implode(" ", $normalizedArray),
            ];
        }
    }

    if (count($normalizedArray) >= 3) {

        if ($redis->exists('lastRequest')){
            //normalized array of prev request
            $prevText = json_decode($redis->get('lastRequest'));
            $prevSize = count($prevText);
            $redis->set('lastRequest', json_encode($normalizedArray));
            $count = 0;

            foreach($normalizedArray as $token) {
                $found = array_search($token, $prevText);

                if ($found !== false) {
                    $count = $count + 1;
                }
            }

            $ratio = $count / $prevSize;

            if ($ratio >= 0.6) {
                return ['status' => 'ok', 'spam' => true, 'reason' => 'duplicate'];
            }

        } else {
            $redis->set('lastRequest', json_encode($normalizedArray));
        }
    }

    if ($checkRate) {
        $rateLimiter = new PredisRateLimiter($redis);

        $apiKey = 'request';

        try {
            $rateLimiter->limit($apiKey, Rate::custom(1, 2));
            //лимит не превышен
        } catch (LimitExceeded $exception) {
            //лимит превышен
            return ['status' => 'ok', 'spam' => true, 'reason' => 'check_rate'];
        }
    }

    return ['status' => 'ok', 'spam' => false, 'reason' => '', 'normalized_text' => implode(" ", $normalizedArray)];
}

switch($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode(['status' => 'ok', 'message' => 'Kolesa Academy!']);
        break;
    case 'POST':

        if(!empty($_POST)) {
            $txt       = $_POST["text"];
            $checkRate = $_POST["check_rate"];
            $isSpam    = spam_check($txt, $checkRate);

            echo json_encode($isSpam);
        } else {
            header($_SERVER["SERVER_PROTOCOL"] . " 400 OK");
            echo json_encode(['status' => 'error', 'message' => 'field text required']);
        }

        break;
}
