--TEST--
Test FETCH_PROPS_LATE
--SKIPIF--
<?php if (!extension_loaded('mysql')) echo 'skip'; ?>
<?php if (!method_exists('ReflectionClass', 'newInstanceWithoutConstructor')) echo 'skip'; ?>
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

    public function __construct() {
        var_dump($this->verrouille);
        $this->verrouille = 'non';
    }
}

$stmt = $dbh->query('SELECT * FROM utilisateurs ORDER BY id LIMIT 2');

$stmt->setFetchMode(EPDO::FETCH_CLASS, 'Utilisateur');
print_r($stmt->fetch());

$stmt->setFetchMode(EPDO::FETCH_CLASS|EPDO::FETCH_PROPS_LATE, 'Utilisateur');
print_r($stmt->fetch());
?>
--EXPECT--
string(1) "0"
Utilisateur Object
(
    [id:protected] => 1
    [login:protected] => Croche.Sarah
    [verrouille:protected] => non
    [mot_de_passe:protected] => 890b6550d408ff39bf1d5a9d28f9afec90d70e07
    [type:protected] => Util
    [email:protected] => croche.sarah@bidule.fr
)
NULL
Utilisateur Object
(
    [id:protected] => 2
    [login:protected] => Rouge.Georges
    [verrouille:protected] => 0
    [mot_de_passe:protected] => 17eddb1b2c4c81fdda668c43199b4424da09fa99
    [type:protected] => Util
    [email:protected] => rouge.georges@bidule.fr
)
