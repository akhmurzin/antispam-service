<?php

use Codeception\Example;

class ApiCest
{
    /**
     * Проверка работоспособности сервиса
     *
     * @param \ApiTester $I
     */
    public function tryApi(ApiTester $I)
    {
        $I->sendGet('/');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    /**
     * Негативный сценарий
     *
     * @param \ApiTester $I
     */
    public function negative(ApiTester $I)
    {
        $I->sendPost('/is_spam');
        $I->seeResponseCodeIsClientError();
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'error', 'message' => 'field text required']);
    }

    /**
     * Позитивный сценарий
     *
     * @dataProvider positiveDataProvider
     *
     * @param \ApiTester           $I
     * @param \Codeception\Example $example
     */
    public function positive(ApiTester $I, Example $example)
    {
        $I->sendPost('/is_spam', ['text' => $example['text'], 'check_rate' => 0]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson([
            'status'          => 'ok',
            'spam'            => $example['spam'],
            'normalized_text' => $example['normalized_text'],
            'reason'          => $example['reason'],
        ]);
    }

    /**
     * Сценарий проверки лимитера
     *
     * @param \ApiTester $I
     */
    public function checkRate(ApiTester $I)
    {
        $I->comment('Отправляем два сообщения подряд, ожидаем 3 секунды между отправками');
        $I->sendPost('/is_spam', [
            'text'       => 'Буря мглою небо кроет, Вихри снежные крутя;',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(['status' => 'ok', 'spam' => false]);

        sleep(3);

        $I->sendPost('/is_spam', [
            'text'       => 'То, как зверь, она завоет, То заплачет, как дитя',
            'check_rate' => 1,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(['status' => 'ok', 'spam' => false]);

        $I->comment('Отправляем третье сообщение без ожидания');
        $I->sendPost('/is_spam', [
            'text'       => 'То по кровле обветшалой, Вдруг соломой зашумит',
            'check_rate' => 1,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(['status' => 'ok', 'spam' => true, 'reason' => 'check_rate']);
    }

    /**
     * Сценарий проверки дубликатов
     *
     * @param \ApiTester $I
     */
    public function duplicates(ApiTester $I)
    {
        $I->comment('Отправляем два сообщения подряд, но они не похожи достаточно');
        $I->sendPost('/is_spam', [
            'text'       => 'Однажды, в студеную зимнюю пору. Я из лесу вышел; был сильный мороз.',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(['status' => 'ok', 'spam' => false]);

        $I->sendPost('/is_spam', [
            'text'       => 'Однажды, в веселую летнюю пору. Я из лесу вышел; было тепло',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(['status' => 'ok', 'spam' => false]);

        $I->comment('Отправляем два сообщения подряд, они похожи достаточно');
        $I->sendPost('/is_spam', [
            'text'       => 'Однажды, в студеную зимнюю пору. Я из лесу вышел; был сильный мороз.',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(['status' => 'ok', 'spam' => false]);

        $I->sendPost('/is_spam', [
            'text'       => 'Вчера, в зимнюю пору. Мы из лесу пошли! был сильный мороз',
            'check_rate' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'status' => 'ok',
            'spam'   => true,
            'reason' => 'duplicate',
        ]);
    }

    /**
     * Провайдер позитивных данных
     *
     * @return array
     */
    protected function positiveDataProvider(): array
    {
        return [
            'Обычное сообщение'                    => [
                'text'            => 'Удачной продажи брат!',
                'spam'            => false,
                'normalized_text' => 'брат продажи удачной',
                'reason'          => '',
            ],
            'Обычное сообщение с числами'          => [
                'text'            => 'Цена 200 нормальная!',
                'spam'            => false,
                'normalized_text' => 'нормальная цена',
                'reason'          => '',
            ],
            'Фильтрация стоп слов'                 => [
                'text'            => 'Привет! Разве много? Хорошее состояние!',
                'spam'            => false,
                'normalized_text' => 'состояние хорошее',
                'reason'          => '',
            ],
            'Сообщение содержит эл. почту'         => [
                'text'            => 'Привет! Пиши на электронную почту hacker@gaga.com',
                'spam'            => true,
                'normalized_text' => 'hacker@gaga.com пиши почту электронную',
                'reason'          => 'block_list',
            ],
            'Сообщение содержит запрещенные слова' => [
                'text'            => 'Вы уже получили свою компенсацию?',
                'spam'            => true,
                'normalized_text' => 'компенсацию получили',
                'reason'          => 'block_list',
            ],
            'Сообщение содержит разные раскладки'  => [
                'text'            => 'Вы уже получили свою kомпенсацию?',
                'spam'            => true,
                'normalized_text' => 'kомпенсацию получили',
                'reason'          => 'mixed_words',
            ],
        ];
    }
}
