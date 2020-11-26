<?php
require __DIR__ . '/../vendor/autoload.php';
use RateLimit\Exception\LimitExceeded;
use RateLimit\PredisRateLimiter;
use RateLimit\Rate;

header('Content-type: application/json; charset=utf-8', false);

/**
 * Функция преобразующая данные файла в массив
 *
 * @param string $path
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
 * Преорбразуем входящее сообщение в массив нормализованных токенов
 *
 * @param string $text
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

    $stopWords   = process_file('/code/docs/stopwords.txt');
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
    $workArray        = normalize($textWithoutEmail);
    array_push($workArray, $email);
    sort($workArray);

    return $workArray;
}

/**
 * Функция для проверки на спам
 *
 * @param  string $checkText
 * @param  bool   $checkRate
 */
function spam_check(string $checkText, bool $checkRate)
{
    define('TOKEN_LIMIT_FOR_DUPLICATES', 3);
    define('TOKEN_RATIO_FOR_DUPLICATES', 0.6);

    try {
        $redis = new Predis\Client([
            'scheme' => 'tcp',
            'host'   => 'redis',
            'port'   => 6379,
        ]);
    } catch (Exception $e) {
        echo 'Could not connect to Redis';
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
                'normalized_text' => implode(' ', $normalizedEmailArray),
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
            'normalized_text' => implode(' ', $normalizedArray),
        ];
    }

    //mixed words check
    foreach ($normalizedArray as $word) {
        if (preg_match('/[\p{Cyrillic}]/u', $word) && preg_match('/[\p{Latin}]/u', $word)) {
            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'mixed_words',
                'normalized_text' => implode(' ', $normalizedArray),
            ];
        }
    }

    if (count($normalizedArray) >= TOKEN_LIMIT_FOR_DUPLICATES) {
        if ($redis->exists('lastRequest')) {
            //normalized array of prev request
            $prevText = json_decode($redis->get('lastRequest'));
            $prevSize = count($prevText);

            $redis->set('lastRequest', json_encode($normalizedArray));

            $count = 0;

            foreach ($normalizedArray as $token) {
                $found = array_search($token, $prevText);

                if ($found !== false) {
                    $count++;
                }
            }

            $ratio = $count / $prevSize;

            if ($ratio >= TOKEN_RATIO_FOR_DUPLICATES) {
                return ['status' => 'ok', 'spam' => true, 'reason' => 'duplicate'];
            }
        } else {
            $redis->set('lastRequest', json_encode($normalizedArray));
        }
    }

    if ($checkRate) {
        $rateLimiter = new PredisRateLimiter($redis);
        $apiKey      = 'request';

        try {
            $rateLimiter->limit($apiKey, Rate::custom(1, 2));
        } catch (LimitExceeded $exception) {
            return ['status' => 'ok', 'spam' => true, 'reason' => 'check_rate'];
        }
    }

    return ['status' => 'ok', 'spam' => false, 'reason' => '', 'normalized_text' => implode(' ', $normalizedArray)];
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode(['status' => 'ok', 'message' => 'Kolesa Academy!']);
        break;
    case 'POST':
        if (!empty($_POST)) {
            $txt       = $_POST['text'];
            $checkRate = $_POST['check_rate'];

            if ($_SERVER['REQUEST_URI'] == '/is_spam') {
                $jsonSpam    = spam_check($txt, $checkRate);
                echo json_encode($jsonSpam);
            }
        } else {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
            echo json_encode(['status' => 'error', 'message' => 'field text required']);
        }

        break;
}
