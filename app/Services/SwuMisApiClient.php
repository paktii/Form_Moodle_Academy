<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class SwuMisApiClient
{
    /** @return array<int, array<string, mixed>> */
    public function search(string $endpoint, string $search, string $value): array
    {
        return $this->data($this->request($endpoint, [
            'psearch' => $search,
            'pvalue' => $value,
        ]));
    }

    /** @return array<int, array<string, mixed>> */
    public function all(string $endpoint): array
    {
        $page = 1;
        $pageSize = max(1, (int) config('swu_mis_api.page_size', 100));
        $records = [];

        do {
            $payload = $this->request($endpoint, [
                'psearch' => 'loadalldata',
                'pvalue' => 'all',
                'pagination' => [
                    'pageNumber' => $page,
                    'pageSize' => $pageSize,
                ],
            ]);

            $records = array_merge($records, $this->data($payload));
            $hasNext = (bool) data_get($payload, 'pagination.hasNext', false);
            $page++;

            if ($page > 1000) {
                throw new RuntimeException('SWU MIS API pagination exceeded the safety limit.');
            }
        } while ($hasNext);

        return $records;
    }

    /** @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $body): array
    {
        $username = trim((string) config('swu_mis_api.username'));
        $password = (string) config('swu_mis_api.password');

        if ($username === '' || $password === '') {
            throw new RuntimeException('SWU MIS API credentials are not configured.');
        }

        $baseUrl = rtrim((string) config('swu_mis_api.base_url'), '/').'/';
        $url = $baseUrl.ltrim($endpoint, '/');

        $response = Http::withBasicAuth($username, $password)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) config('swu_mis_api.connect_timeout', 5)))
            ->timeout(max(1, (int) config('swu_mis_api.timeout', 10)))
            ->post($url, $body);

        if ($response->status() === 404) {
            return ['data' => [], 'pagination' => null];
        }

        $response->throw();

        return $this->json($response);
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnexpectedValueException('SWU MIS API returned an invalid JSON response.');
        }

        if (($payload['success'] ?? false) !== true) {
            throw new RuntimeException((string) ($payload['message'] ?? 'SWU MIS API request failed.'));
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function data(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (! is_array($data)) {
            throw new UnexpectedValueException('SWU MIS API returned an invalid data field.');
        }

        return array_values(array_filter($data, 'is_array'));
    }
}
