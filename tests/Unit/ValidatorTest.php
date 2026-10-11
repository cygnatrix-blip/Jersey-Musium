<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    #[DataProvider('cases')]
    public function testRules(string $rule, mixed $value, bool $valid): void
    {
        $v = Validator::make(['f' => $value], ['f' => $rule]);
        $this->assertSame(!$valid, $v->fails(), "rule '$rule' with " . var_export($value, true));
    }

    public static function cases(): array
    {
        return [
            ['required', '', false], ['required', '  ', false], ['required', 'x', true],
            ['email', 'a@b.co', true], ['email', 'not-an-email', false], ['email', "a@b.co\r\nBcc: x@y.z", false],
            ['phone', '9830012345', true], ['phone', '5830012345', false], ['phone', '98300123', false],
            ['pincode', '700001', true], ['pincode', '070001', false], ['pincode', '70001', false],
            ['integer|min:1', '0', false], ['integer|min:1', '3', true], ['integer', '1.5', false],
            ['numeric|between:1,5', '6', false], ['numeric|between:1,5', '2.5', true],
            ['max:5', 'abcdef', false], ['min:8', 'short', false],
            ['in:a,b', 'b', true], ['in:a,b', 'c', false],
            ['slug', 'good-slug-1', true], ['slug', 'Bad Slug', false], ['slug', '-x', false],
            ['url', 'https://ok.example', true], ['url', '/relative/path', true], ['url', '//evil.com', false],
            ['url', 'javascript://x%0aalert(1)', false], ['url', 'data:text/html,x', false],
            ['nullable|email', '', true], ['date', '2026-10-11', true], ['date', 'not a date', false],
        ];
    }

    public function testConfirmedAndValidatedData(): void
    {
        $v = Validator::make(['password' => 'secret123', 'password_confirmation' => 'secret12'], ['password' => 'required|confirmed']);
        $this->assertTrue($v->fails());

        $v = Validator::make(['name' => '  Messi  ', 'active' => 'on', 'extra' => 'ignored'], ['name' => 'required', 'active' => 'boolean']);
        $this->assertFalse($v->fails());
        $this->assertSame(['name' => 'Messi', 'active' => 1], $v->validated(), 'only declared fields, trimmed, booleans normalised (no mass assignment)');
    }

    public function testUnknownRuleIsAProgrammingError(): void
    {
        $this->expectException(\LogicException::class);
        Validator::make(['x' => '1'], ['x' => 'nonsense']);
    }
}
