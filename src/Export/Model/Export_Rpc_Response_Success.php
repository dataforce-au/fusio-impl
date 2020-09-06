<?php

declare(strict_types = 1);

namespace Fusio\Impl\Export\Model;


class Export_Rpc_Response_Success implements \JsonSerializable
{
    /**
     * @var string|null
     */
    protected $jsonrpc;
    /**
     * @var Export_Rpc_Response_Result|null
     */
    protected $result;
    /**
     * @var int|null
     */
    protected $id;
    /**
     * @param string|null $jsonrpc
     */
    public function setJsonrpc(?string $jsonrpc) : void
    {
        $this->jsonrpc = $jsonrpc;
    }
    /**
     * @return string|null
     */
    public function getJsonrpc() : ?string
    {
        return $this->jsonrpc;
    }
    /**
     * @param Export_Rpc_Response_Result|null $result
     */
    public function setResult(?Export_Rpc_Response_Result $result) : void
    {
        $this->result = $result;
    }
    /**
     * @return Export_Rpc_Response_Result|null
     */
    public function getResult() : ?Export_Rpc_Response_Result
    {
        return $this->result;
    }
    /**
     * @param int|null $id
     */
    public function setId(?int $id) : void
    {
        $this->id = $id;
    }
    /**
     * @return int|null
     */
    public function getId() : ?int
    {
        return $this->id;
    }
    public function jsonSerialize()
    {
        return (object) array_filter(array('jsonrpc' => $this->jsonrpc, 'result' => $this->result, 'id' => $this->id), static function ($value) : bool {
            return $value !== null;
        });
    }
}
