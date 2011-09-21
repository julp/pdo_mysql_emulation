--TEST--
Test statement behavior with non select query
--SKIPIF--
<?php if (!extension_loaded('mysql')) echo 'skip'; ?>
--FILE--
<?php
require(__DIR__ . '/../EPDO.php');
require(__DIR__ . '/dbh.inc');

$dbh->beginTransaction();
$stmt = $dbh->prepare('UPDATE utilisateurs SET verrouille = 1 WHERE login LIKE ?');
$stmt->execute(array('%'));

var_dump($stmt->rowCount());
var_dump($stmt->fetch(EPDO::FETCH_OBJ));

$dbh->rollback();
?>
--EXPECT--
int(3)
bool(false)
