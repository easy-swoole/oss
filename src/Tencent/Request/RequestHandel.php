<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/12/5 0005
 * Time: 14:07
 */

namespace EasySwoole\Oss\Tencent\Request;


use EasySwoole\Oss\Tencent\Http\HttpClient;

class RequestHandel
{
    /**
     * @var $request HttpClient
     */
    protected $request;
    protected $operation;
    protected $args;
    protected $defaultRequestLocations = [
        'body'      => 'body',
        'query'     => 'query',
        'header'    => 'header',
        'json'      => 'json',
        'xml'       => 'xml',
        'formParam' => 'formParam',
        'multipart' => 'multipart',
    ];

    public function __construct($request, $operation, $args)
    {
        $this->request = $request;
        $this->operation = $operation;
        $this->args = $args;
    }

    function handel()
    {
        $args = $this->args;
        $data = [];
        foreach ($args as $key => $arg) {
            $op = $this->operation['parameters'][$key];
            if ($op === null) {
                continue;
            }
            $location = $this->defaultRequestLocations[$op['location']];
            if ($location === null) {
                continue;
            }

            $data[$location][$op['sentAs'] ?? $key] = $this->$location($op, $arg);
            break;
        }
    }

    function body($op, $value)
    {
    }

    function query($op, $value)
    {
    }

    function header($op, $value)
    {
        return $value;
    }

    function json($op, $value)
    {

    }

    function xml($op, $value)
    {
        var_dump($op, $value);
        $xml = new \DOMDocument();
//        $xml->

        $this->handelXMLParams($xml,$op,$value);

    }

    function formParam($op, $value)
    {
    }

    function multipart($op, $value)
    {
    }

    function handelXMLParams($xml, $arr,$data)
    {
        foreach ($arr as $key =>$property){
            $value = new \DOMDocument();
            if ($property['type']=='object'){
                $value->$key = $this->handelXMLParams($value,$property['properties'],$data);
            }




            $xml->$key = $value;

        }


    }

}