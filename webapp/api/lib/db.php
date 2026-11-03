<?php
/** PDO singleton — connection lười, prepared statements bắt buộc ở route. */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $CFG;
        $d = $CFG['db'];
        $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";
        try {
            $pdo = new PDO($dsn, $d['user'], $d['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            fail('Không kết nối được CSDL. Kiểm tra api/config.php.', 500);
        }
    }
    return $pdo;
}
