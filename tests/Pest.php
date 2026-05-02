<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentWebhookPayload(array $overrides = []): array
{
    return array_merge([
        'payer_name' => 'Joao Silva',
        'payer_document' => '123.456.789-00',
        'amount_in_cents' => 15000,
        'bank_code' => '001',
        'branch_number' => '1234',
        'account_number' => '56789-0',
    ], $overrides);
}

function paymentWebhookRoute(int $phase): string
{
    return sprintf('/api/webhooks/payments/phase-%d', $phase);
}

function providerWebhookIdempotencyKey(string $providerKey): string
{
    return 'webhook:provider-idempotency:'.hash('sha256', $providerKey);
}
