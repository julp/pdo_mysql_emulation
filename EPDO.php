<?php
/**
 * CREDITS: based on initial work of stealth35 (https://github.com/stealth35/mysql_prepare)
 **/

/**
 * TODO:
 * - fetchAll
 * - transaction (beginTransaction, commit, rollback)
 * - checks (query results, fetch* and setFetchMode arguments, etc)
 * - PARAM_LOB ?
 **/

class EPDOException extends Exception {}

class EPDO {
    const FETCH_BOTH       = 1;
    const FETCH_NUM        = 2;
    const FETCH_ASSOC      = 3;
    const FETCH_NAMED      = 4;
    const FETCH_OBJ        = 5;
    const FETCH_CLASS      = 6;
    const FETCH_INTO       = 7;
    const FETCH_KEY_PAIR   = 8;
    const FETCH_FUNC       = 9;
    const FETCH_LAZY       = 10;
    const FETCH_BOUND      = 11;
    const FETCH_COLUMN     = 12;

    const FETCH_FLAGS      = 0xFFFF0000;
    const FETCH_GROUP      = 0x00010000;
    const FETCH_UNIQUE     = 0x00030000;
    const FETCH_CLASSTYPE  = 0x00040000;
    const FETCH_SERIALIZE  = 0x00080000;
    const FETCH_PROPS_LATE = 0x00100000;

    const PARAM_NULL         = __LINE__;
    const PARAM_BOOL         = __LINE__;
    const PARAM_INT          = __LINE__;
    const PARAM_STR          = __LINE__;
    const PARAM_LOB          = __LINE__;
    const PARAM_STMT         = __LINE__;
    const PARAM_INPUT_OUTPUT = __LINE__;

    const ATTR_ERRMODE            = __LINE__;
    const ATTR_CLIENT_VERSION     = __LINE__;
    const ATTR_SERVER_VERSION     = __LINE__;
    const ATTR_CONNECTION_STATUS  = __LINE__;
    const ATTR_AUTOCOMMIT         = __LINE__;
    const ATTR_STATEMENT_CLASS    = __LINE__;
    const ATTR_DEFAULT_FETCH_MODE = __LINE__;
    const ATTR_DRIVER_NAME        = __LINE__;

    // array_change_key_case
    const ATTR_CASE    = __LINE__;
    const CASE_NATURAL = __LINE__;
    const CASE_LOWER   = __LINE__;
    const CASE_UPPER   = __LINE__;

    const ERRMODE_SILENT    = __LINE__;
    const ERRMODE_WARNING   = __LINE__;
    const ERRMODE_EXCEPTION = __LINE__;

    private $link;
    private $default_mode;
    private $attributes = array(
        self::ATTR_ERRMODE            => self::ERRMODE_SILENT,
        self::ATTR_DEFAULT_FETCH_MODE => self::FETCH_BOTH,
    );

    public function checkError($retval = FALSE) {
        if (FALSE === $retval) {
            // SQLSTATE[%s]: %s: %s ; SQLSTATE[%s]: %s: %ld %s
            switch ($this->attributes[self::ATTR_ERRMODE]) {
                case self::ERRMODE_EXCEPTION:
                    throw new EPDOException(mysql_error($this->link), mysql_errno($this->link));
                    break;
                case self::ERRMODE_WARNING:
                    trigger_error(mysql_error($this->link), E_USER_WARNING);
                    break;
                default:
                    /* NOP */
            }
        }
    }

    public function __construct($dsn, $username = NULL, $password = NULL, $driver_options = array()) {
        if (strpos($dsn, 'mysql:') !== 0) {
            throw new EPDOExeception('could not find driver');
        }
        $params = array();
        preg_match_all('/([^=]+)=([^;]*)(?:;|$)/', $dsn, $matches, PREG_SET_ORDER, strlen('mysql:'));
        foreach ($matches as $p) {
            $params[$p[1]] = $p[2];
        }
        /*if (array_diff_key(array('host'), $params)) {
            throw new EPDOException('');
        }*/
        $this->link = mysql_connect($params['host'], $username, $password);
        if (!empty($params['dbname'])) {
            mysql_select_db($params['dbname'], $this->link);
        }
        if (!empty($params['charset'])) {
            mysql_set_charset($params['charset'], $this->link);
        }
    }

    public function errorCode() {
        return mysql_errno($this->link);
    }

    public function errorInfo() {
        return array(
            mysql_errno($this->link),
            mysql_errno($this->link),
            mysql_error($this->link),
        );
    }

    public function getLink() {
        return $this->link;
    }

    public function getAvailableDrivers() {
        return array('mysql');
    }

    public function getAttribute($attribute) {
        switch ($attribute) {
            case self::ATTR_DRIVER_NAME:
                return 'mysql';
            case self::ATTR_CLIENT_VERSION:
                return mysql_get_client_info();
            case self::ATTR_SERVER_VERSION:
                return mysql_get_server_info($this->link);
            case self::ATTR_CONNECTION_STATUS:
                return mysql_ping($this->link);
            default:
                return isset($this->attributes[$attribute]) ? $this->attributes[$attribute] : NULL;
        }
    }

    public function setAttribute($attribute, $value) {
        if (!isset($this->attributes[$attribute])) {
            return FALSE;
        } else {
            $this->attributes[$attribute] = $value;
            return TRUE;
        }
    }

    public function lastInsertId() {
        return mysql_insert_id($this->link);
    }

    public function exec($statement) {
        $ret = mysql_query($statement);
        $this->checkError($ret);
        if (FALSE === $ret) {
            return $ret;
        } else {
            return mysql_affected_rows($this->link);
        }
    }

    public function query($statement) {
        $ret = mysql_query($statement);
        $this->checkError($ret);
        if (FALSE === $ret) {
            return $ret;
        } else {
            return new EPDOStatement($ret, $this);
        }
    }

    public function quote($data) {
        return "'" . mysql_real_escape_string($data, $this->link) . "'";
    }

    public function prepare($statement, $driver_options = array()) {
        $statement_id = uniqid();
        if (preg_match_all('/:\w+/', $statement, $matches, PREG_SET_ORDER)) {
            $placeholders = array();
            foreach ($matches as $m) {
                $placeholders[] = $m[0];
            }
            $statement = preg_replace('/:\w+/', '?', $statement);
        } else {
            $placeholders = FALSE;
        }
        $statement = mysql_real_escape_string($statement, $this->link);
        $ret = mysql_query('PREPARE `' . $statement_id . '` FROM "' . $statement . '"');
        $this->checkError($ret);
        if (FALSE === $ret) {
            return $ret;
        } else {
            return new EPDOStatement($statement_id, $this, $placeholders);
        }
    }

    public function __sleep() {
        throw new EPDOException('You cannot serialize or unserialize ' . version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : __CLASS__ . ' instances');
    }

    public function __wakeup() {
        throw new EPDOException('You cannot serialize or unserialize ' . version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : __CLASS__ . ' instances');
    }

    public function __destruct() {
        mysql_close($this->link);
    }
}

class EPDOStatement implements Iterator {
    private $dbh;
    private $placeholders;
    private $statement_id;
    private $result = FALSE;
    private $current = FALSE;

    private $in = array();       // bindParam, bindValue
    private $intypes = array();  // bindParam
    private $out = array();      // bindColumn
    private $outtypes = array(); // bindColumn
    private $fetch_args = array();

    public $queryString = '';

    const FETCH_MASK = 0;
    const FETCH_ALL_MASK = 0;

    //public static final function createForStatement

    public final function __construct(/*$qs, */$arg, EPDO $dbh, $placeholders = NULL) {
        $this->dbh = $dbh;
        /*$this->queryString = $qs;*/
        if (is_resource($arg)) {
            $this->result = $arg;
        } else {
            $this->statement_id = $arg;
        }
        if ($placeholders) {
            $this->placeholders = $placeholders;
        }
    }

    public function __set($name, $value) {
        throw new EPDOException(version_compare(PHP_VERSION, '5.3.0', '>=') ? get_called_class() : __CLASS__ . "'s attributes are read only");
    }

    public function current() {
        return $this->current;
    }

    public function key() {
        return NULL;
    }

    public function next() {
        $this->current = $this->fetch();
    }

    public function rewind() {
        if ($this->result) {
            //mysql_data_seek($this->result, 0);
            $this->current = $this->fetch();
        } else {
            $this->current = FALSE;
        }
    }

    public function valid() {
        return FALSE !== $this->current;
    }

    public function rowCount() {
        return mysql_affected_rows($this->dbh->getLink());
    }

    public function columnCount() {
        if ($this->result) {
            return mysql_num_fields($this->result);
        } else {
            return 0;
        }
    }

    public function debugDumpParams() {
        return FALSE;
    }

    public function errorCode() {
        return call_user_func(array($this->dbh, __FUNCTION__));
    }

    public function errorInfo() {
        return call_user_func(array($this->dbh, __FUNCTION__));
    }

    public function getAttribute($attribute) {
        return call_user_func_array(array($this->dbh, __FUNCTION__), func_get_args());
    }

    public function setAttribute($attribute, $value) {
        return call_user_func_array(array($this->dbh, __FUNCTION__), func_get_args());
    }

    public function getColumnMeta($colno) {
        return (array) mysql_fetch_field($this->result, $colno);
    }

    public function nextRowset() {
        return FALSE;
    }

    private function _setVars(Array $input_parameters) {
        $parameters = array();
        foreach ($this->placeholders as $k => $v) {
            if (!array_key_exists($v, $input_parameters)) {
                throw new EPDOException(sprintf('parameter "%s" was not defined', $v));
                return FALSE;
            }
            $parameters[$k] = $input_parameters[$v];
        }
        if (count($parameters) < count($this->placeholders)) {
            throw new EPDOException('number of bound variables does not match number of tokens');
            return FALSE;
        }
        foreach ($parameters as $id => $parameter) {
            $key = sprintf('@`%s`', $id);

            if (is_numeric($parameter)) {
                $sf = '@`%s` = %s';
            } else {
                $sf = '@`%s` = \'%s\'';
            }
            //settype($parameter, 'string');
            $input_parameter = mysql_real_escape_string($parameter, $this->dbh->getLink());
            $sets[$key] = sprintf($sf, $id, $parameter);
        }
        if (FALSE === mysql_query('SET ' . implode(',', $sets), $this->dbh->getLink())) {
            $this->checkError();
            return FALSE;
        }
        return sprintf('EXECUTE `%s` USING %s', $this->statement_id, implode(',', array_keys($sets)));
    }

    public function execute($input_parameters = NULL) {
        if (is_array($input_parameters) && !empty($input_parameters)) {
            $safe_parameters = array();
            // TODO: assume first numeric key is 0 and add 1 to all numeric keys
            foreach ($input_parameters as $k => $v) {
                if (is_int($k)) {
                    continue;
                }
                if (!is_string($k)) {
                    // Exception
                }
                if ($k[0] === ':') {
                    $safe_parameters[$k] = $v;
                } else {
                    $safe_parameters[':' . $k] = $v;
                }
            }
            $ext = $this->_setVars($safe_parameters);
        } else if (!empty($this->in)) {
            foreach ($this->intypes as $k => $t) {
                $this->_applyType($this->in[$k], $t);
            }
            $ext = $this->_setVars($this->in);
        } else {
            $ext = sprintf('EXECUTE `%s`', $this->statement_id);
        }
        if (FALSE === $ext) {
            return FALSE;
        }
        $this->dbh->checkError($this->result = mysql_query($ext, $this->dbh->getLink()));

        return (bool) $this->result;
    }

    private function _applyType(&$value, $type) {
        switch ($type) {
            case EPDO::PARAM_NULL:
                $value = NULL;
                break;
            case EPDO::PARAM_BOOL:
                $value = !!$value;
                break;
            case EPDO::PARAM_INT:
                $value = intval($value);
                break;
            case EPDO::PARAM_STR:
                $value = strval($value);
                break;
            /*case EPDO::PARAM_LOB: // out only (bindColumn)
                $fp = fopen('php://memory', 'w+');
                fwrite($fp, $value);
                rewind($fp);
                $value = $fp;
                break;*/
        }
    }

    private function _bindCheck(&$parameter) {
        if (is_numeric($parameter)) {
            if ($parameter <= 1) {
                return FALSE;
            }
        } else {
            if ($parameter[0] !== ':') {
                $parameter = ':' . $parameter;
            }
            if (FALSE === array_search($parameter, $this->placeholders)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public function bindColumn($column, &$variable, $type = EPDO::PARAM_STR) {
        $this->fetch_args = array(EPDO::FETCH_BOUND);
        $this->outtypes[$column] = $type;
        $this->out[$column] = &$variable;

        return TRUE;
    }

    public function bindValue($parameter, $value, $type = EPDO::PARAM_STR) {
        if (!$this->_bindCheck($parameter)) {
            return FALSE;
        }
        $this->_applyType($value, $type);
        $this->in[$parameter] = $value;

        return TRUE;
    }

    public function bindParam($parameter, &$variable, $type = EPDO::PARAM_STR) {
        if (!$this->_bindCheck($parameter)) {
            return FALSE;
        }
        $this->intypes[$parameter] = $type;
        $this->in[$parameter] = &$variable;

        return TRUE;
    }

    public function setFetchMode($mode /*, ...*/) {
        // TODO: check arguments
        if ($mode === EPDO::FETCH_INTO) {
            $obj = (object) func_get_arg(1);
            $this->fetch_args = array(EPDO::FETCH_INTO, &$obj);
        } else {
            $args = func_get_args();
            $this->fetch_args = $args;
        }
    }

    private function _fetch(Array $args) {
        if (!$this->result) {
            return FALSE;
        }
        if (!$args) {
            if ($this->fetch_args) {
                $args = $this->fetch_args;
                $mode = array_shift($args);
            } else {
                $mode = $this->dbh->getAttribute(EPDO::ATTR_DEFAULT_FETCH_MODE);
            }
        } else {
            $mode = array_shift($args);
        }
        $nbArgs = count($args);
        switch ($mode & ~EPDO::FETCH_FLAGS) {
            case EPDO::FETCH_COLUMN: /* $no = 0 */
                $no = $nbArgs > 0 ? $args[0] : 0;
                // assume $no < mysql_num_fields($this->result) ?
                if (FALSE !== $row = mysql_fetch_row($this->result)) {
                    return $row[$no];
                } else {
                    return FALSE;
                }
            case EPDO::FETCH_BOTH:
                return mysql_fetch_array($this->result);
            case EPDO::FETCH_NUM:
                return mysql_fetch_row($this->result);
            case EPDO::FETCH_ASSOC:
                return mysql_fetch_assoc($this->result);
            case EPDO::FETCH_NAMED:
                if (FALSE === ($tmp = mysql_fetch_row($this->result))) {
                    return FALSE;
                }
                $row = array();
                for ($c = 0; $c < mysql_num_fields($this->result); $c++) {
                    $name = mysql_field_name($this->result, $c);
                    if (array_key_exists($name, $row)) {
                        if (!is_array($row[$name])) {
                            $row[$name] = (array) $row[$name];
                        }
                        $row[$name][] = $tmp[$c];
                    } else {
                        $row[$name] = $tmp[$c];
                    }
                }
                return $row;
            case EPDO::FETCH_FUNC: /* callback */
                // TODO: check valid callback
                if (FALSE === ($cbArgs = mysql_fetch_row($this->result))) {
                    return FALSE;
                } else {
                    return call_user_func_array($args[0], $cbArgs);
                }
            case EPDO::FETCH_CLASS: /* classname?, ctor_args? */
                // default (pdo as mysql_fetch_object): array_merge($this->fetch(), object properties) then __construct
                // late: __construct then array_merge($this->fetch(), object properties)
                $late = ($mode & EPDO::FETCH_PROPS_LATE) || (($mode & EPDO::FETCH_CLASSTYPE) && !method_exists('ReflectionClass', 'newInstanceWithoutConstructor'));
                if ($mode & EPDO::FETCH_CLASSTYPE) {
                    if (FALSE === ($row = mysql_fetch_assoc($this->result))) {
                        return FALSE;
                    }
                    $class_name = array_shift($row);
                    if (!$class_name || !class_exists($class_name)) { // call autoload
                        // error
                    }
                } else if ($nbArgs >= 1) {
                    $class_name = $args[0];
                    $ctor_args = $nbArgs == 2 ? $args[1] : array();
                } else {
                    $class_name = '';
                }
                if ($late) {
                    if (FALSE === ($row = mysql_fetch_assoc($this->result))) {
                        return FALSE;
                    }
                }
                if ($class_name) {
                    if ($late || ($mode & EPDO::FETCH_CLASSTYPE)) {
                        $rc = new ReflectionClass($class_name);
                        if (!$rc->isInstantiable()) {
                            // error
                        }
                        if (NULL !== ($ctor = $rc->getConstructor())) {
                            if ($ctor->getNumberOfRequiredParameters() < count($ctor_args)) {
                                // error
                            }
                        }
                        if (!$late) {
                            $obj = $rc->newInstanceWithoutConstructor();
                        } else {
                            if ($ctor_args) {
                                $obj = $rc->newInstanceArgs($ctor_args);
                            } else {
                                $obj = $rc->newInstance();
                            }
                        }
                        foreach ($row as $k => $v) {
                            if ($rc->hasProperty($k) && ($p = $rc->getProperty($k)) && ($p->isPrivate() || $p->isProtected())) {
                                $p->setAccessible(TRUE);
                                $p->setValue($obj, $v);
                                $p->setAccessible(FALSE);
                            } else {
                                $obj->$k = $v;
                            }
                        }
                        if (!$late) {
                            if ($ctor_args) {
                                $ctor->invokeArgs($obj, $ctor_args);
                            } else {
                                $ctor->invoke($obj);
                            }
                        }
                        return $obj;
                    } else if (class_exists($class_name)) { // call autoload
                        return mysql_fetch_object($this->result, $class_name, $ctor_args);
                    }
                }
                // no break here !
            case EPDO::FETCH_OBJ:
                return mysql_fetch_object($this->result);
            case EPDO::FETCH_INTO: /* &object */
                if (FALSE === ($row = mysql_fetch_assoc($this->result))) {
                    return FALSE;
                }
                // PDO::FETCH_INTO doesn't handle private properties (throw error or need to define __set)
                //$ro = new ReflectionObject($args[0]);
                foreach ($row as $k => $v) {
                    /*if ($ro->hasProperty($k) && ($p = $ro->getProperty($k)) && ($p->isPrivate() || $p->isProtected())) {
                        $p->setAccessible(TRUE);
                        $p->setValue($args[0], $v);
                        $p->setAccessible(FALSE);
                    } else {*/
                        $args[0]->$k = $v;
                    /*}*/
                }
                return $args[0];
            case EPDO::FETCH_BOUND:
                if (FALSE !== $row = mysql_fetch_row($this->result)) {
                    for ($c = 0; $c < mysql_num_fields($this->result); $c++) {
                        if (array_key_exists($c + 1, $this->out)) {
                            $this->out[$c + 1] = $row[$c];
                            $this->_applyType($this->out[$c + 1], $this->outtypes[$c + 1]);
                        } else {
                            $fieldname = mysql_field_name($this->result, $c);
                            if (array_key_exists($fieldname, $this->out)) {
                                $this->out[$fieldname] = $row[$c];
                                $this->_applyType($this->out[$fieldname], $this->outtypes[$fieldname]);
                            }
                        }
                    }
                }
                return FALSE !== $row;
            case EPDO::FETCH_KEY_PAIR:
                if (mysql_num_fields($this->result) != 2) {
                    // error
                }
                if (FALSE !== ($row = mysql_fetch_row($this->result))) {
                    return array($row[0] => $row[1]);
                } else {
                    return FALSE;
                }
        }
    }

    public function fetchColumn($no = 0) {
        return $this->_fetch(array(EPDO::FETCH_COLUMN, $no));
    }

    public function fetch(/*$mode, ... */) {
        // TODO: check arguments
        if (func_num_args() /* == 2 */ && func_get_arg(0) === EPDO::FETCH_INTO) {
            $obj = (object) func_get_arg(1);
            return $this->_fetch(array(EPDO::FETCH_INTO, &$obj));
        } else {
            return $this->_fetch(func_get_args());
        }
    }

    public function fetchObject($class_name = '', $ctor_args = array()) {
        return $this->_fetch(array(EPDO::FETCH_CLASS, $class_name, $ctor_args));
    }

    // temporary (UNIQUE, GROUP, KEY_PAIR // BOUND, INTO)
    public function fetchAll(/*$mode, ... */) {
        $rows = array();
        if (!func_num_args()) {
            if ($this->fetch_args) {
                $args = $this->fetch_args;
                $mode = array_shift($args);
            } else {
                $args = array();
                $mode = $this->dbh->getAttribute(EPDO::ATTR_DEFAULT_FETCH_MODE);
            }
        } else {
            $args = func_get_args();
            $mode = array_shift($args);
        }
        while ($row = $this->_fetch($args)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function closeCursor() {
        if ($this->statement_id) {
            mysql_query('DEALLOCATE PREPARE `' . $this->statement_id . '`', $this->dbh->getLink());
            $this->fetch_args = $this->in = $this->intypes = $this->out = $this->outtypes = array();
            $this->placeholders = $this->statement_id = $this->result = NULL;
            $this->current = FALSE;
        }
    }

    public function __destruct() {
        $this->closeCursor();
    }
}

if (!extension_loaded('pdo')) {
    class_alias('EPDO', 'PDO');
    class_alias('EPDOException', 'PDOException');
    class_alias('EPDOStatement', 'PDOStatement');
}
