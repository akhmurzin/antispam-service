<?php
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
}
