<?php namespace r;

require_once("misc.php");
require_once("util.php");
require_once("function.php");

function nativeToDatum($v) {
    if (is_array($v)) {
        $datumArray = array();
        $fullyAssociative = true;
        $mustUseMakeTerm = false;
        foreach($v as $key => $val) {
            if (!is_numeric($key) && !is_string($key)) throw new RqlDriverError("Key must be a string.");
            if ((is_object($val) && is_subclass_of($val, "\\r\\Query")) && !(is_object($val) && is_subclass_of($val, "\\r\\Datum"))) {
                $subDatum = $val;
                $mustUseMakeTerm = true;
            } else {
                $subDatum = nativeToDatum($val);
                if (!is_subclass_of($subDatum, "\\r\\Datum"))
                    $mustUseMakeTerm = true;
            }
            if (is_string($key)) {   
                $datumArray[$key] = $subDatum;
            } else {
                $fullyAssociative = false;
                $datumArray[] = $subDatum;
            }
        }
    
        // TODO: In the case of $fullyAssociative === true, we cannot
        //   know if we should convert to an array or an object. We
        //   currently assume object, but this is not overly clean.
        //   Of course the user always has the option to wrap data
        //   into a Datum manually.
        if ($fullyAssociative) {
            if ($mustUseMakeTerm) {
                return new MakeObject($datumArray);
            } else {
                return new ObjectDatum($datumArray);
            }
        } else {
            if ($mustUseMakeTerm) {
                return new MakeArray($datumArray);
            } else {
                return new ArrayDatum($datumArray);
            }
        }
    }
    else if (is_null($v)) {
        return new NullDatum();
    }
    else if (is_bool($v)) {
        return new BoolDatum($v);
    }
    else if (is_numeric($v)) {
        return new NumberDatum($v);
    }
    else if (is_string($v)) {
        return new StringDatum($v);
    }
    else {
        throw new RqlDriverError("Unhandled type " . get_class($v));
    }
}

// ------------- Helpers -------------
function protobufToDatum(pb\Datum $datum) {
    switch ($datum->type()) {
        case pb\Datum_DatumType::PB_R_NULL: return NullDatum::_fromProtobuffer($datum);
        case pb\Datum_DatumType::PB_R_BOOL: return BoolDatum::_fromProtobuffer($datum);
        case pb\Datum_DatumType::PB_R_NUM: return NumberDatum::_fromProtobuffer($datum);
        case pb\Datum_DatumType::PB_R_STR: return StringDatum::_fromProtobuffer($datum);
        case pb\Datum_DatumType::PB_R_ARRAY: return ArrayDatum::_fromProtobuffer($datum);
        case pb\Datum_DatumType::PB_R_OBJECT: return ObjectDatum::_fromProtobuffer($datum);
        default: throw new RqlDriverError("Unhandled datum type " . $datum->type());
    }
}

// ------------- RethinkDB make queries -------------
class MakeArray extends ValuedQuery
{
    public function __construct($value) {
        if (!is_array($value)) throw new RqlDriverError("Value must be an array.");
        $i = 0;
        foreach($value as $val) {
            $this->setPositionalArg($i++, $val);
        }
    }
    
    protected function getTermType() {
        return pb\Term_TermType::PB_MAKE_ARRAY;
    }
}

class MakeObject extends ValuedQuery
{
    public function __construct($value) {
        if (!is_array($value)) throw new RqlDriverError("Value must be an array.");
        foreach($value as $key => $val) {
            $this->setOptionalArg($key, $val);
        }
    }
    
    protected function getTermType() {
        return pb\Term_TermType::PB_MAKE_OBJ;
    }
}

// ------------- RethinkDB datum types -------------
abstract class Datum extends ValuedQuery
{
    public function __construct($value = null) {
        if (isset($value)) {
            $this->setValue($value);
        }
    }

    public function _getPBTerm() {
        $term = new pb\Term();
        $term->set_type(pb\Term_TermType::PB_DATUM);
        $term->set_datum($this->_getPBDatum());
        return $term;
    }
    
    protected function getTermType() {
        return pb\Term_TermType::PB_DATUM;
    }
    
    abstract public function _getPBDatum();
    
    public function toNative() {
        return $this->getValue();
    }
    
    public function __toString() {
        return "" . $this->getValue();
    }
    
    public function _toString(&$backtrace) {
        $result = $this->__toString();
        if (is_null($backtrace)) return $result;
        else {
            if ($backtrace === false) return str_repeat(" ", strlen($result));
            $backtraceFrame = $backtrace->_consumeFrame();
            if ($backtraceFrame !== false) throw new RqlDriverError("Internal Error: The backtrace says that we should have an argument in a Datum. This is not possible.");
            return str_repeat("~", strlen($result));
        }
    }
        
    public function getValue() {
        return $this->value;
    }
    public function setValue($val) {
        $this->value = $val;
    }
    private $value;
}

class NullDatum extends Datum
{
    public function _getPBDatum() {
        $datum = new pb\Datum();
        $datum->set_type(pb\Datum_DatumType::PB_R_NULL);
        return $datum;
    }
    
    static public function _fromProtobuffer(pb\Datum $datum) {
        $result = new NullDatum();
        $result->setValue(null);
        return $result;
    }
    
    public function setValue($val) {
        if (!is_null($val)) throw new RqlDriverError("Not null: " . $val);
        parent::setValue($val);
    }
    
    public function __toString() {
        return "null";
    }
}

class BoolDatum extends Datum
{
    public function _getPBDatum() {
        $datum = new pb\Datum();
        $datum->set_type(pb\Datum_DatumType::PB_R_BOOL);
        $datum->set_r_bool($this->getValue());
        return $datum;
    }
    
    static public function _fromProtobuffer(pb\Datum $datum) {
        $val = $datum->r_bool();
        
        $result = new BoolDatum();
        $result->setValue($val);
        return $result;
    }
    
    public function __toString() {
        if ($this->getValue()) return "true";
        else return "false";
    }
    
    public function setValue($val) {
        if (is_numeric($val)) $val = (($val == 0) ? false : true);
        if (!is_bool($val)) throw new RqlDriverError("Not a boolean: " . $val);
        parent::setValue($val);
    }
}

class NumberDatum extends Datum
{
    public function _getPBDatum() {
        $datum = new pb\Datum();
        $datum->set_type(pb\Datum_DatumType::PB_R_NUM);
        $datum->set_r_num($this->getValue());
        return $datum;
    }
    
    static public function _fromProtobuffer(pb\Datum $datum) {
        $val = $datum->r_num();
        
        $result = new NumberDatum();
        $result->setValue($val);
        return $result;
    }
    
    public function setValue($val) {
        if (!is_numeric($val)) throw new RqlDriverError("Not a number: " . $val);
        parent::setValue($val);
    }
}

class StringDatum extends Datum
{
    public function _getPBDatum() {
        $datum = new pb\Datum();
        $datum->set_type(pb\Datum_DatumType::PB_R_STR);
        $datum->set_r_str($this->getValue());
        return $datum;
    }
    
    static public function _fromProtobuffer(pb\Datum $datum) {
        $val = $datum->r_str();
        
        $result = new StringDatum();
        $result->setValue($val);
        return $result;
    }
    
    public function setValue($val) {
        if (!is_string($val)) throw new RqlDriverError("Not a string: " . $val);
        parent::setValue($val);
    }
    
    public function __toString() {
        return "'" . $this->getValue() . "'";
    }
}

class ArrayDatum extends Datum
{
    public function _getPBDatum() {
        $datum = new pb\Datum();
        $datum->set_type(pb\Datum_DatumType::PB_R_ARRAY);
        $i = 0;
        foreach ($this->getValue() as $val) {
            $datum->set_r_array($i, $val->_getPBDatum());
            ++$i;
        }
        return $datum;
    }
    
    static public function _fromProtobuffer(pb\Datum $datum) {
        $size = $datum->r_array_size();
        $val = array();
        for ($i = 0; $i < $size; ++$i) {
            $v = protobufToDatum($datum->r_array($i));
            $val[$i] = $v;
        }
        
        $result = new ArrayDatum();
        $result->setValue($val);
        return $result;
    }
    
    public function setValue($val) {
        if (!is_array($val)) throw new RqlDriverError("Not an array: " . $val);
        foreach($val as $v) {
            if (!(is_object($v) && is_subclass_of($v, "\\r\\Query"))) throw new RqlDriverError("Not a Query: " . $v);
        }
        parent::setValue($val);
    }
    
    public function toNative() {
        $native = array();
        foreach ($this->getValue() as $val) {
            $native[] = $val->toNative();
        }
        return $native;
    }
    
    public function __toString() {
        $string = 'array(';
        $first = true;
        foreach ($this->getValue() as $val) {
            if (!$first) {
                $string .= ", ";
            }
            $first = false;
            $string .= $val;
        }
        $string .= ')';
        return $string;
    }
}

class ObjectDatum extends Datum
{
    public function _getPBDatum() {
        $datum = new pb\Datum();
        $datum->set_type(pb\Datum_DatumType::PB_R_OBJECT);
        $i = 0;
        foreach ($this->getValue() as $key => $val) {
            $pair = new pb\Datum_AssocPair();
            $pair->set_key($key);
            $pair->set_val($val->_getPBDatum());
            $datum->set_r_object($i, $pair);
            ++$i;
        }
        return $datum;
    }
    
    static public function _fromProtobuffer(pb\Datum $datum) {
        $size = $datum->r_object_size();
        $val = array();
        for ($i = 0; $i < $size; ++$i) {
            $pair = $datum->r_object($i);
            $v = protobufToDatum($pair->val());
            $val[$pair->key()] = $v;
        }
        
        $result = new ObjectDatum();
        $result->setValue($val);
        return $result;
    }
    
    public function setValue($val) {
        if (!is_array($val)) throw new RqlDriverError("Not an array: " . $val);
        foreach($val as $k => $v) {
            if (!is_string($k) && !is_numeric($k)) throw new RqlDriverError("Not a string or number: " . $k);
            if (!(is_object($v) && is_subclass_of($v, "\\r\\Query"))) throw new RqlDriverError("Not a Query: " . $v);
        }
        parent::setValue($val);
    }
    
    public function toNative() {
        $native = array();
        foreach ($this->getValue() as $key => $val) {
            $native[$key] = $val->toNative();
        }
        return $native;
    }
    
    public function __toString() {
        $string = 'array(';
        $first = true;
        foreach ($this->getValue() as $key => $val) {
            if (!$first) {
                $string .= ", ";
            }
            $first = false;
            $string .= "'" . $key . "' => " . $val;
        }
        $string .= ')';
        return $string;
    }
}


?>
