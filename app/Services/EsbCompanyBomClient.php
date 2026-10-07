<?php

namespace App\Services;

use Illuminate\Http\Client\Response;

/**
 * Lets the Bill of Material service talk to one specific ESB company instead of the default account.
 */
class EsbCompanyBomClient extends EsbGlobalCoreClient
{
    public function __construct(private readonly EsbCoreClient $core, private readonly string $companyCode) {}

    public function request(string $method, string $path, array $data = []): Response
    {
        return $this->core->request($this->companyCode, $method, $path, $data);
    }

    /** @return array<string, mixed> */
    public function successfulResult(Response $response, string $action): array
    {
        return $this->core->successfulResult($response, $action, $this->companyCode, '');
    }
}
