--TEST--
Test Iterator
--SKIPIF--
<?php if (!extension_loaded('mysql')) echo 'skip'; ?>
--FILE--
<?php
require(__DIR__ . '/../EPDO.php');
require(__DIR__ . '/dbh.inc');

$stmt = $dbh->query('SELECT * FROM utilisateurs ORDER BY id');
$stmt->setFetchMode(EPDO::FETCH_OBJ);
foreach ($stmt as $u) {
    print_r($u);
}
?>
--EXPECT--
stdClass Object
(
    [id] => 1
    [login] => Croche.Sarah
    [mot_de_passe] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
    [email] => croche.sarah@bidule.fr
    [type] => Util
    [verrouille] => 0
)
stdClass Object
(
    [id] => 2
    [login] => Rouge.Georges
    [mot_de_passe] => 17eddb1b2c4c81fdda668c43199b4424da09fa99
    [email] => rouge.georges@bidule.fr
    [type] => Util
    [verrouille] => 0
)
stdClass Object
(
    [id] => 3
    [login] => Dupont.Albert
    [mot_de_passe] => 79013525bcfbac62b587a58a54ae029eaec81aac
    [email] => dupont.albert@bidule.fr
    [type] => Admin
    [verrouille] => 0
)
