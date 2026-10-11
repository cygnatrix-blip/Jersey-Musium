<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use Tests\TestCase;

final class EnvTest extends TestCase
{
    public function testParsesQuotesCommentsAndBooleans(): void
    {
        $f = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($f, implode("\n", [
            '# comment line',
            'JMT_PLAIN=value   # trailing comment',
            'JMT_QUOTED="Jersey Musium # not a comment"',
            "JMT_SINGLE='a b c'",
            'JMT_BOOL=true',
            'JMT_EMPTY=',
            'JMT_SPACES="abcd efgh"',
        ]));
        Env::load($f);
        unlink($f);
        $this->assertSame('value', Env::get('JMT_PLAIN'));
        $this->assertSame('Jersey Musium # not a comment', Env::get('JMT_QUOTED'));
        $this->assertSame('a b c', Env::get('JMT_SINGLE'));
        $this->assertTrue(Env::get('JMT_BOOL'));
        $this->assertSame('fallback', Env::get('JMT_EMPTY', 'fallback'));
        $this->assertSame('abcd efgh', Env::get('JMT_SPACES'));
        $this->assertSame('dflt', Env::get('JMT_MISSING', 'dflt'));
    }

    public function testRealEnvironmentOverridesFile(): void
    {
        putenv('JMT_OVERRIDE=from-env');
        $f = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($f, 'JMT_OVERRIDE=from-file');
        Env::load($f);
        unlink($f);
        $this->assertSame('from-env', Env::get('JMT_OVERRIDE'));
        putenv('JMT_OVERRIDE');
    }
}
