<?php
class ApiCest
{
    public function tryApi(ApiTester $I)
    {
        $I->sendGet('/');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":"ok","message":"Kolesa Academy!"}');
    }
}
