<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Http\Identity\IdentityApiScope;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class IdentityEngineeringContractTest extends TestCase
{
    public function test_derivative_contract_matches_exact_cookie_scope_and_safe_dtos(): void
    {
        $path = dirname(__DIR__, 2).'/resources/contracts/identity-v1.openapi.json';
        self::assertFileExists($path);
        $contract = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        self::assertFalse($contract['x-studentity']['activationAllowed']);
        self::assertCount(7, $contract['paths']);
        self::assertArrayNotHasKey('bearerToken', $contract['components']['securitySchemes']);
        foreach ($contract['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                self::assertTrue(IdentityApiScope::matches(Request::create('/api/v1'.$path, strtoupper($method))));
                if (in_array($path, ['/me', '/auth/sessions/current'], true)) {
                    self::assertSame([['sessionCookie' => []]], $operation['security']);
                }
                foreach (['400', '409', '500', '503'] as $status) {
                    self::assertArrayHasKey($status, $operation['responses']);
                }
                if ($method !== 'get') {
                    self::assertArrayHasKey('419', $operation['responses']);
                }
            }
        }
        $schemas = $contract['components']['schemas'];
        self::assertContains('departmentId', $schemas['RegisterInput']['required']);
        self::assertContains('yearLevel', $schemas['RegisterInput']['required']);
        self::assertSame('integer', $schemas['RegisterInput']['properties']['yearLevel']['type']);
        self::assertSame(['string', 'null'], $schemas['SelfUser']['properties']['systemId']['type']);
        self::assertContains('systemId', $schemas['SelfUser']['required']);
        self::assertSame(['type', 'title', 'status', 'code', 'correlationId'], $schemas['Problem']['required']);
        self::assertArrayNotHasKey('detail', $schemas['Problem']['properties']);
        foreach ($schemas as $schema) {
            $this->checkReferences($schema, $schemas);
        }
        foreach ($contract['paths'] as $methods) {
            $this->checkReferences($methods, $schemas);
        }
    }

    private function checkReferences(array $node, array $schemas): void
    {
        foreach ($node as $key => $value) {
            if ($key === '$ref') {
                self::assertStringStartsWith('#/components/schemas/', $value);
                self::assertArrayHasKey(substr($value, strlen('#/components/schemas/')), $schemas);
            } elseif (is_array($value)) {
                $this->checkReferences($value, $schemas);
            }
        }
    }

    public function test_scope_cannot_be_broadened_by_path_prefix_method_or_client_claims(): void
    {
        foreach ([['GET', '/api/v1/me/extra'], ['POST', '/api/v1/me'], ['GET', '/api/v1/me/'], ['GET', '/api/v1/me%2fextra'], ['GET', '/sanctum/csrf-cookie'], ['GET', '/'], ['POST', '/api/v1/auth/sessions/mfa-verifications']] as [$method, $path]) {
            $request = Request::create($path, $method, ['scope' => 'identity'], [], [], ['HTTP_X_IDENTITY_SCOPE' => 'approved']);
            self::assertFalse(IdentityApiScope::matches($request));
        }
        self::assertTrue(IdentityApiScope::matches(Request::create('/api/v1/me?untrusted=scope', 'GET')));
    }
}
