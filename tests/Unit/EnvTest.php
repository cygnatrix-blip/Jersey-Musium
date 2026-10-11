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

    /** Builds a fake hosting tree under a temp dir and returns its root. */
    private function tree(array $files): string
    {
        $root = str_replace('\\', '/', sys_get_temp_dir()) . '/jmenv_' . bin2hex(random_bytes(4));
        foreach ($files as $f) {
            @mkdir(dirname("$root/$f"), 0777, true);
            str_ends_with($f, '/') ? @mkdir("$root/$f", 0777, true) : file_put_contents("$root/$f", 'X=1');
        }
        return $root;
    }

    public function testLocatesSecretsFileForSubdomainInsideMainSiteFolder(): void
    {
        // Real Hostinger layout of jerseymusium.cygnatrix.com: project in .../cygnatrix.com/public_html/jerseymusium
        $root = $this->tree(['domains/cygnatrix.com/jersey-musium.env', 'domains/cygnatrix.com/public_html/jerseymusium/']);
        $this->assertSame("$root/domains/cygnatrix.com/jersey-musium.env",
            str_replace('\\', '/', Env::locate("$root/domains/cygnatrix.com/public_html/jerseymusium")));
    }

    public function testMainDomainLayout(): void
    {
        $root = $this->tree(['domains/example.com/jersey-musium.env', 'domains/example.com/public_html/']);
        $this->assertSame("$root/domains/example.com/jersey-musium.env", str_replace('\\', '/', Env::locate("$root/domains/example.com/public_html")));
    }

    public function testPrefersCopyOutsidePublicHtml(): void
    {
        // A copy in the main site's public_html (downloadable!) must lose against one above public_html.
        $root = $this->tree(['domains/x.com/public_html/jersey-musium.env', 'domains/x.com/jersey-musium.env', 'domains/x.com/public_html/shop/']);
        $this->assertSame("$root/domains/x.com/jersey-musium.env", str_replace('\\', '/', Env::locate("$root/domains/x.com/public_html/shop")));
    }

    public function testFallsBackToProjectDotEnv(): void
    {
        $root = $this->tree(['site/project/']);
        $this->assertSame("$root/site/project/.env", str_replace('\\', '/', Env::locate("$root/site/project")));
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
