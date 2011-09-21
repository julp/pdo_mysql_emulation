--TEST--
Test FETCH_CLASS (FETCH_CLASSTYPE)
--SKIPIF--
<?php if (!extension_loaded('mysql')) echo 'skip'; ?>
--FILE--
<?php
require(__DIR__ . '/../EPDO.php');
require(__DIR__ . '/dbh.inc');

class Utilisateur {
    protected $id;
    protected $login;
    protected $verrouille;
    protected $mot_de_passe;
    protected $type;
    protected $email;
}

echo "FETCH_CLASS alone:\n";
$stmt = $dbh->query('SELECT * FROM utilisateurs WHERE id = 1');
$stmt->setFetchMode(EPDO::FETCH_CLASS, 'Utilisateur');
print_r($stmt->fetch());
$stmt->closeCursor();

class Util {
    protected $id;
    protected $verrouille;
    protected $login;
    protected $email;
}

class Admin extends Util {
}

echo "\nFETCH_CLASS + FETCH_CLASSTYPE:\n";
$stmt = $dbh->query('SELECT type, id, login, email, verrouille FROM utilisateurs AS u WHERE id IN(1, 3)');
print_r($stmt->fetch(EPDO::FETCH_CLASS | EPDO::FETCH_CLASSTYPE));
print_r($stmt->fetch(EPDO::FETCH_CLASS | EPDO::FETCH_CLASSTYPE));
$stmt->closeCursor();

echo "\nCheck homonyms are kept:\n";
$stmt = $dbh->query('SELECT u.type, u.* FROM utilisateurs AS u WHERE type = "Admin"');
print_r($stmt->fetch(EPDO::FETCH_CLASS | EPDO::FETCH_CLASSTYPE));
?>
--EXPECT--
FETCH_CLASS alone:
Utilisateur Object
(
    [id:protected] => 1
    [login:protected] => Croche.Sarah
    [verrouille:protected] => 0
    [mot_de_passe:protected] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
    [type:protected] => Util
    [email:protected] => croche.sarah@bidule.fr
)

FETCH_CLASS + FETCH_CLASSTYPE:
Util Object
(
    [id:protected] => 1
    [verrouille:protected] => 0
    [login:protected] => Croche.Sarah
    [email:protected] => croche.sarah@bidule.fr
)
Admin Object
(
    [id:protected] => 3
    [verrouille:protected] => 0
    [login:protected] => Dupont.Albert
    [email:protected] => dupont.albert@bidule.fr
)

Check homonyms are kept:
Admin Object
(
    [id:protected] => 3
    [verrouille:protected] => 0
    [login:protected] => Dupont.Albert
    [email:protected] => dupont.albert@bidule.fr
    [mot_de_passe] => 79013525bcfbac62b587a58a54ae029eaec81aac
    [type] => Admin
)
