--TEST--
Test FETCH_BOUND
--SKIPIF--
<?php if (!extension_loaded('mysql')) echo 'skip'; ?>
--FILE--
<?php
require(__DIR__ . '/../EPDO.php');
require(__DIR__ . '/dbh.inc');

$stmt = $dbh->query('SELECT * FROM utilisateurs WHERE id = 1');
$stmt->bindColumn(2, $username/*, EPDO::PARAM_STR*/);
$stmt->bindColumn('email', $usermail, EPDO::PARAM_STR);
$stmt->bindColumn(6, $locked, EPDO::PARAM_BOOL);
$stmt->bindColumn('id', $userid, EPDO::PARAM_INT);
$stmt->fetch();

var_dump($username, $usermail, $locked, $userid);
?>
--EXPECT--
string(12) "Croche.Sarah"
string(22) "croche.sarah@bidule.fr"
bool(false)
int(1)
