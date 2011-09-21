--TEST--
Test PARAM_LOB
--SKIPIF--
<?php if (!extension_loaded('mysql')) echo 'skip'; ?>
<?php if (!version_compare(PHP_VERSION, '5.1.0', '>=')) echo 'skip'; ?>
--FILE--
<?php
require(__DIR__ . '/../EPDO.php');
require(__DIR__ . '/dbh.inc');

$dbh->exec('CREATE TEMPORARY TABLE IF NOT EXISTS `utlob`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `data` BLOB NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;');

$date = date('d/m/Y');
$infp = fopen('php://memory', 'w+');
fwrite($infp, $date);

$stmt = $dbh->prepare('INSERT INTO `utlob`(`data`) VALUES(:bin)');
$stmt->bindParam('bin', $infp, EPDO::PARAM_LOB);
$stmt->execute();
fclose($infp);
$stmt->closeCursor();

$stmt = $dbh->prepare('SELECT * FROM `utlob`');
$stmt->bindColumn('data', $outfp, EPDO::PARAM_LOB);
$stmt->execute();
$stmt->fetch();
var_dump($date === stream_get_contents($outfp));
fclose($outfp);
$stmt->closeCursor();

$dbh->exec('DROP TEMPORARY TABLE `utlob`');
?>
--EXPECT--
bool(true)
