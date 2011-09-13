<?php
/**
 * CREDITS: based on initial work of stealth35 (https://github.com/stealth35/mysql_prepare)
 **/

/**
 * TODO:
 * - CRITICAL: "save" setFetchMode parameters for the next fetches
 **/

if (!extension_loaded('pdo')) {
    class_alias('EPDO', 'PDO');
    class_alias('EPDOException', 'PDOException');
    class_alias('EPDOStatement', 'PDOStatement');
}

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
    const PARAM_LOB          = __LINE__; // compatibility only
    const PARAM_STMT         = __LINE__; // compatibility only
    const PARAM_INPUT_OUTPUT = __LINE__; // compatibility only

    const ATTR_ERRMODE            = __LINE__;
    const ATTR_STATEMENT_CLASS    = __LINE__;
    const ATTR_DEFAULT_FETCH_MODE = __LINE__;
    const ATTR_DRIVER_NAME        = __LINE__;

    const ERRMODE_SILENT    = __LINE__;
    const ERRMODE_WARNING   = __LINE__;
    const ERRMODE_EXCEPTION = __LINE__;

    private $link;
    private $default_mode;
    private $attributes = array(
        self::ATTR_ERRMODE            => self::ERRMODE_SILENT,
        self::ATTR_DEFAULT_FETCH_MODE => self::FETCH_BOTH,
        self::ATTR_DRIVER_NAME        => 'mysql',
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
        return isset($this->attributes[$attribute]) ? $this->attributes[$attribute] : NULL;
    }

    public function setAttribute($attribute, $value) {
        // TODO: ATTR_DRIVER_NAME read only
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
    private $fetch_mode = 0;

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

            if(is_numeric($parameter)) {
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
            // TODO: apply types (PDO::PARAM_*) for bindParam
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

    public function setFetchMode($mode, $class_name = NULL) {
        $this->fetch_mode = $mode;
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
        $this->fetch_mode = EPDO::FETCH_BOUND;
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

    private function _fetch($mode = 0 /* , ...*/) {
        if (!$this->result) {
            return FALSE;
        }
        if (!$mode) {
            if ($this->fetch_mode) {
                $mode = $this->fetch_mode;
            } else {
                $mode = $this->dbh->getAttribute(EPDO::ATTR_DEFAULT_FETCH_MODE);
            }
        }
        switch ($mode & ~EPDO::FETCH_FLAGS) {
            case EPDO::FETCH_COLUMN: /* $no = 0 */
                $no = func_num_args() > 1 ? func_get_arg(1) : 0;
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
                // array_change_key_case
                return mysql_fetch_assoc($this->result);
            case EPDO::FETCH_NAMED:
                // TODO: play with mysql_*field* ?
            case EPDO::FETCH_FUNC: /* callback */
                if (FALSE === $args = mysql_fetch_row($this->result)) {
                    return FALSE;
                } else {
                    return call_user_func_array(func_get_arg(1), $args);
                }
            case EPDO::FETCH_OBJ:
                return mysql_fetch_object($this->result);
            case EPDO::FETCH_CLASS: /* classname?, ctor_args? */
                if ($mode & EPDO::FETCH_CLASSTYPE) {
                    // ???
                } else {
                    return mysql_fetch_object($this->result/* ... */);
                }
            case EPDO::FETCH_INTO:
                // ???
                return FALSE;
            case EPDO::FETCH_BOUND:
                // TODO: casts according to (E)PDO::PARAM_*
                if (FALSE !== $row = mysql_fetch_row($this->result)) {
                    for ($c = 0; $c < mysql_num_fields($this->result); $c++) {
                        if (array_key_exists($c + 1, $this->out)) {
                            $this->out[$c + 1] = $row[$c];
                        } else {
                            $fieldname = mysql_field_name($this->result, $c);
                            if (array_key_exists($fieldname, $this->out)) {
                                $this->out[$fieldname] = $row[$c];
                            }
                        }
                    }
                }
                return FALSE !== $row;
            case EPDO::FETCH_KEY_PAIR:
                if (mysql_num_fields($this->result) != 2) {
                    // error
                }
                if (FALSE !== $row = mysql_fetch_row($this->result)) {
                    return array($row[0] => $row[1]);
                } else {
                    return FALSE;
                }
        }
    }

    public function fetchColumn($no = 0) {
        return $this->_fetch(EPDO::FETCH_COLUMN, $no);
    }

    public function fetch($mode = 0) {
        return $this->_fetch($mode);
    }

    public function fetchObject($class_name = '', $ctor_args = array()) {
        if ($class_name && class_exists($class_name)) {
            return $this->_fetch(EPDO::FETCH_CLASS, $class_name, $ctor_args);
        } else {
            return $this->_fetch(EPDO::FETCH_OBJ);
        }
    }

    public function fetchAll($mode = 0) {
        $rows = array();
        if (!$mode) {
            if ($this->fetch_mode) {
                $mode = $this->fetch_mode;
            } else {
                $mode = $this->dbh->getAttribute(EPDO::ATTR_DEFAULT_FETCH_MODE);
            }
        }
        /*while ($row = $this->_fetch()) {
            $rows[] = $row;
        }*/
        return $rows;
    }

    public function closeCursor() {
        if ($this->statement_id) {
            mysql_query('DEALLOCATE PREPARE `' . $this->statement_id . '`', $this->dbh->getLink());
            $this->in = $this->intypes = $this->out = $this->outtypes = array();
            $this->placeholders = $this->statement_id = $this->result = NULL;
            $this->current = FALSE;
            $this->fetch_mode = 0;
        }
    }

    public function __destruct() {
        $this->closeCursor();
    }
}
