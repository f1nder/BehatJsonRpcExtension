<?php

class JsonRpcException extends \Exception implements JsonSerializable {

    protected $data;

    public function __construct($message = "", $code = 0, $data)
    {
        $this->data = $data;
        parent::__construct($message, $code, null);
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return $this->data;
    }
}