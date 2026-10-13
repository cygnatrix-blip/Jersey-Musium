<?php

declare(strict_types=1);

/*
 * PHPUnit bootstrap: boots the app against the TEST database (see phpunit.xml),
 * drops every table, runs all migrations and seeds the base data (roles, categories, pages, coupons).
 */

require dirname(__DIR__) . '/bootstrap/app.php';

// The suite DROPS every table, so it only ever runs on a database whose name ends in "_test".
if (!str_ends_with((string) config('database.database'), '_test')) {
    fwrite(STDERR, "Refusing to run tests: DB_DATABASE must end in _test (got '" . config('database.database') . "').\n");
    exit(1);
}

$pdo = db()->pdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $pdo->exec("DROP TABLE `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$out = static fn (string $s) => null;
$opts = [];
$files = glob(BASE_PATH . '/database/migrations/*.sql');
sort($files);
foreach ($files as $file) {
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
    foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
}
require BASE_PATH . '/database/seeds/seed.php';
