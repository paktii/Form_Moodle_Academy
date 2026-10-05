<?php

namespace Tests\Feature;

use App\Services\SwuMisApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SwuMisApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('swu_mis_api.base_url', 'https://api.swu.ac.th/mis/v1/');
        config()->set('swu_mis_api.username', 'api-user');
        config()->set('swu_mis_api.password', 'api-password');
        config()->set('swu_mis_api.page_size', 100);
    }

    public function test_search_uses_basic_auth_and_the_documented_request_shape(): void
    {
        Http::fake([
            'https://api.swu.ac.th/mis/v1/Officer' => Http::response([
                'success' => true,
                'statusCode' => 200,
                'message' => 'Data found.',
                'data' => [['personId' => '123']],
                'pagination' => null,
            ]),
        ]);

        $records = (new SwuMisApiClient)->search('Officer', 'buasri', 'someone');

        $this->assertSame([['personId' => '123']], $records);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.swu.ac.th/mis/v1/Officer'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('api-user:api-password'))
                && $request['psearch'] === 'buasri'
                && $request['pvalue'] === 'someone';
        });
    }

    public function test_all_follows_api_pagination(): void
    {
        Http::fakeSequence()
            ->push([
                'success' => true,
                'data' => [['deptCd' => '1000']],
                'pagination' => ['hasNext' => true],
            ])
            ->push([
                'success' => true,
                'data' => [['deptCd' => '2000']],
                'pagination' => ['hasNext' => false],
            ]);

        $records = (new SwuMisApiClient)->all('Department');

        $this->assertSame([['deptCd' => '1000'], ['deptCd' => '2000']], $records);
        Http::assertSentCount(2);
    }

    public function test_not_found_is_an_empty_result(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'statusCode' => 404,
                'message' => 'Data not found.',
                'data' => null,
                'pagination' => null,
            ], 404),
        ]);

        $this->assertSame([], (new SwuMisApiClient)->search('Officer', 'buasri', 'missing'));
    }
}
