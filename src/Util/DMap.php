<?php
namespace Ice\Util;
class DMap implements \ArrayAccess {
    public function __construct($datas = array()) {
        foreach ($datas as $k => $v) {
            $this->$k = $v;
        }
    }
    public function offsetExists($offset) {
        return isset($this->$offset);
    }

    public function offsetGet($offset) {
        return $this->$offset;
    }

    public function offsetSet($offset, $value) {
        $this->$offset = $value;
    }

    public function offsetUnset($offset) {
        unset($this->$offset);
    }
}