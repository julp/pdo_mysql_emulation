--TEST--
Test FETCH_UNIQUE
--SKIPIF--
<?php if (!extension_loaded('mysql')) echo 'skip'; ?>
--FILE--
<?php
require(__DIR__ . '/../EPDO.php');
require(__DIR__ . '/dbh.inc');

$stmt = $dbh->query('SELECT * FROM utilisateurs');
print_r($stmt->fetchAll(EPDO::FETCH_ASSOC | EPDO::FETCH_UNIQUE));

$stmt = $dbh->query('SELECT u.id, u.* FROM utilisateurs AS u WHERE id = 1');
print_r($stmt->fetchAll(EPDO::FETCH_OBJ | EPDO::FETCH_UNIQUE));

$stmt = $dbh->query('SELECT u.id, u.* FROM utilisateurs AS u WHERE id = 1');
print_r($stmt->fetchAll(EPDO::FETCH_ASSOC | EPDO::FETCH_UNIQUE));

$stmt = $dbh->query('SELECT u.id, u.login, u.id, u.*, u.id FROM utilisateurs AS u WHERE id = 1');
print_r($stmt->fetchAll(EPDO::FETCH_NAMED | EPDO::FETCH_UNIQUE));

$stmt = $dbh->query('SELECT u.id, u.* FROM utilisateurs AS u WHERE id = 1');
print_r($stmt->fetchAll(EPDO::FETCH_BOTH | EPDO::FETCH_UNIQUE));
?>
--EXPECT--
Array
(
    [1] => Array
        (
            [login] => Croche.Sarah
            [mot_de_passe] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
            [email] => croche.sarah@bidule.fr
            [type] => Util
            [verrouille] => 0
        )

    [2] => Array
        (
            [login] => Rouge.Georges
            [mot_de_passe] => 17eddb1b2c4c81fdda668c43199b4424da09fa99
            [email] => rouge.georges@bidule.fr
            [type] => Util
            [verrouille] => 0
        )

    [3] => Array
        (
            [login] => Dupont.Albert
            [mot_de_passe] => 79013525bcfbac62b587a58a54ae029eaec81aac
            [email] => dupont.albert@bidule.fr
            [type] => Admin
            [verrouille] => 0
        )

)
Array
(
    [1] => stdClass Object
        (
            [id] => 1
            [login] => Croche.Sarah
            [mot_de_passe] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
            [email] => croche.sarah@bidule.fr
            [type] => Util
            [verrouille] => 0
        )

)
Array
(
    [1] => Array
        (
            [id] => 1
            [login] => Croche.Sarah
            [mot_de_passe] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
            [email] => croche.sarah@bidule.fr
            [type] => Util
            [verrouille] => 0
        )

)
Array
(
    [1] => Array
        (
            [login] => Array
                (
                    [0] => Croche.Sarah
                    [1] => Croche.Sarah
                )

            [id] => Array
                (
                    [0] => 1
                    [1] => 1
                    [2] => 1
                )

            [mot_de_passe] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
            [email] => croche.sarah@bidule.fr
            [type] => Util
            [verrouille] => 0
        )

)
Array
(
    [1] => Array
        (
            [id] => 1
            [0] => 1
            [login] => Croche.Sarah
            [1] => Croche.Sarah
            [mot_de_passe] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
            [2] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
            [email] => croche.sarah@bidule.fr
            [3] => croche.sarah@bidule.fr
            [type] => Util
            [4] => Util
            [verrouille] => 0
            [5] => 0
        )

)
