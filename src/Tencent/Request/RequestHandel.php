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
        foreach ($args as $key => $arg) {
            $op = $this->operation['parameters'][$key];
            if ($op===null){
                continue;
            }
            $function = $this->defaultRequestLocations[$op['location']];
            if ($function===null){
                continue;
            }
            $this->$function($op['sentAs'],$arg);
        }


    }

    function body($name, $arg)
    {
    }

    function query($name, $arg)
    {
    }

    function header($name, $arg)
    {
        $this->request->setHeader($name,$arg,false);
    }

    function json($name, $arg)
    {
    }

    function xml($name, $arg)
    {
    }

    function formParam($name, $arg)
    {
    }

    function multipart($name, $arg)
    {
    }


}