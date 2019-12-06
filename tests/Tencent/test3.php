<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/12/3 0003
 * Time: 9:40
 */
include "../../vendor/autoload.php";
include "../../phpunit2.php";

$arr = getOperation();
$params = [
    'Bucket' => 'a',
    'Grants' => array(
        array(
            'Grantee'    => array(
                'DisplayName' => 'qcs::cam::uin/2779643970:uin/2779643970',
                'ID'          => 'qcs::cam::uin/2779643970:uin/2779643970',
                'Type'        => 'CanonicalUser',
            ),
            'Permission' => 'FULL_CONTROL',
        ),
        array(
            'Grantee'    => array(
                'DisplayName' => 'qcs::cam::uin/2779643970:uin/123312',
                'ID'          => 'qcs::cam::uin/2779643970:uin/123312312',
                'Type'        => 'group',
            ),
            'Permission' => 'FULL_CONTROL',
        ),
    ),
    'Owner'  => array(
        'DisplayName' => 'qcs::cam::uin/2779643970:uin/2779643970',
        'ID'          => 'qcs::cam::uin/2779643970:uin/2779643970',
    )
];
$xml = new \XMLWriter();

$xml->openMemory();
$xml->startDocument('1.0', 'utf-8');

handelParam($xml, $params);
$xml->endElement();

echo  $xml->outputMemory();


function handelParam(XMLWriter $xml, $params)
{
    $opArr = getOperation();
    foreach ($params as $key => $param) {
        $op = $opArr['parameters'][$key];
        if ($op['location'] == 'xml') {
            handelXmlElement($xml, $op['type'], $key, $param, $op);
        }
    }
}

/**
 * 处理数组类型
 * handelArray
 * @param XMLWriter $xml
 * @param           $paramName
 * @param           $param
 * @param           $op
 * @author Tioncico
 * Time: 10:22
 */
function handelArray(XMLWriter $xml, $paramName, $param, $op)
{
    foreach ($param as $key => $value) {
        //如果是object
        handelXmlElement($xml, $op['type'], $key, $value, $op);
    }
}

/**
 * 处理对象类型
 * handelObject
 * @param XMLWriter $xml
 * @param           $paramName
 * @param           $params
 * @param           $op
 * @author Tioncico
 * Time: 10:22
 */
function handelObject(XMLWriter $xml, $paramName, $params, $op)
{
    //先遍历属性值
    foreach ($params as $key => $value) {
        $keyName = $op[$key]['sentAs'] ?? $key;
        if (empty($op[$key]['data'])){
            continue;
        }
        handelXmlElement($xml, $op[$key]['type'], $keyName, $value, $op[$key]);
    }
    //再遍历正常属性
    foreach ($params as $key => $value) {
        $keyName = $op[$key]['sentAs'] ?? $key;
        if (!empty($op[$key]['data'])){
            continue;
        }
        handelXmlElement($xml, $op[$key]['type'], $keyName, $value, $op[$key]);
    }
}

/**
 * 处理字符串类型
 * handelString
 * @param XMLWriter $xml
 * @param           $value
 * @author Tioncico
 * Time: 10:22
 */
function handelString(XMLWriter $xml, $keyName, $value, $op)
{
//    var_dump($keyName, $value, $op);
    if ($op['data']){
        if ($op['data']['xmlNamespace']){
            $xml->writeAttribute('xmlns:xsi',$op['data']['xmlNamespace']);
        }
        if ($op['data']['xmlAttribute']){
            $xml->writeAttribute($op['sentAs'] ?? $keyName,$value);
        }
    }else{
        $xml->startElement($op['sentAs'] ?? $keyName);
        $xml->text($value);
        $xml->endElement();
    }
}

/**
 * 调度处理类型
 * handelXmlElement
 * @param XMLWriter $xml
 * @param           $type
 * @param           $keyName
 * @param           $value
 * @param           $op
 * @author Tioncico
 * Time: 10:22
 */
function handelXmlElement(XMLWriter $xml, $type, $keyName, $value, $op)
{
    if ($type == 'object') {
        $xml->startElement($op['name'] ?? $keyName);
        handelObject($xml, $keyName, $value, $op['properties']);
        $xml->endElement();
    } elseif ($type == 'array') {
        $xml->startElement($op['sentAs'] ?? $keyName);
        handelArray($xml, $keyName, $value, $op['items']);
        $xml->endElement();
    } elseif ($type == 'string') {
        handelString($xml, $keyName, $value, $op);
    }
}

function xml(XMLWriter $xml, $data, $parameters)
{
    if ($parameters['type'] == 'array') {
        foreach ($data as $key => $datum) {
//            var_dump($datum);die;
            foreach ($datum as $key => $va) {
                $op = $parameters[$key];
                if ($op['location'] != 'xml') {
                    continue;
                }
//                var_dump($op);
                $va = isset($va[0]) ?? $va;
                $elementName = $op['sentAs'] ?? $key;
                $xml->startElement($elementName);
//        var_dump($va,$op);
                //设置值
                if (is_array($va)) {
//            xml($xml, $va, $parameters['items'] ?? $parameters['properties']);
                } else {
                    //设置属性
                    if (!empty($op['data']['xmlAttribute'])) {
                        $xml->writeAttribute($elementName, $va);    // 属性
                        $xml->writeAttribute('xmlns:xsi', $op['data']['xmlNamespace']);
                    } else {
                        $xml->text($va);
                    }
                }

                $xml->endElement();
            }


        }
    }


//    var_dump($data,$parameters);


}


function getOperation()
{
    $arr = array(
        'httpMethod'    => 'PUT',
        'uri'           => '/{Bucket}{/Key*}?acl',
        'class'         => 'Qcloud\\Cos\\Command',
        'responseClass' => 'PutObjectAclOutput',
        'responseType'  => 'model',
        'data'          => array(
            'xmlRoot' => array(
                'name' => 'AccessControlPolicy',
            ),
        ),
        'parameters'    => array(
            'ACL'              => array(
                'type'     => 'string',
                'location' => 'header',
                'sentAs'   => 'x-cos-acl',
            ),
            'Grants'           => array(
                'type'     => 'array',
                'location' => 'xml',
                'sentAs'   => 'AccessControlList',
                'items'    => array(
                    'name'       => 'Grant',
                    'type'       => 'object',
                    'properties' => array(
                        'Grantee'    => array(
                            'type'       => 'object',
                            'properties' => array(
                                'DisplayName' => array(
                                    'type' => 'string'),
                                'ID'          => array(
                                    'type' => 'string'),
                                'Type'        => array(
                                    'type'   => 'string',
                                    'sentAs' => 'xsi:type',
                                    'data'   => array(
                                        'xmlAttribute' => true,
                                        'xmlNamespace' => 'http://www.w3.org/2001/XMLSchema-instance')),
                                'URI'         => array(
                                    'type' => 'string'))),
                        'Permission' => array(
                            'type' => 'string',
                        ),
                    ),
                ),
            ),
            'Owner'            => array(
                'type'       => 'object',
                'location'   => 'xml',
                'properties' => array(
                    'DisplayName' => array(
                        'type' => 'string',
                    ),
                    'ID'          => array(
                        'type' => 'string',
                    ),
                ),
            ),
            'Bucket'           => array(
                'required' => true,
                'type'     => 'string',
                'location' => 'uri',
            ),
            'GrantFullControl' => array(
                'type'     => 'string',
                'location' => 'header',
                'sentAs'   => 'x-cos-grant-full-control',
            ),
            'GrantRead'        => array(
                'type'     => 'string',
                'location' => 'header',
                'sentAs'   => 'x-cos-grant-read',
            ),
            'GrantReadACP'     => array(
                'type'     => 'string',
                'location' => 'header',
                'sentAs'   => 'x-cos-grant-read-acp',
            ),
            'GrantWrite'       => array(
                'type'     => 'string',
                'location' => 'header',
                'sentAs'   => 'x-cos-grant-write',
            ),
            'GrantWriteACP'    => array(
                'type'     => 'string',
                'location' => 'header',
                'sentAs'   => 'x-cos-grant-write-acp',
            ),
            'Key'              => array(
                'required'  => true,
                'type'      => 'string',
                'location'  => 'uri',
                'minLength' => 1,
                'filters'   => array(
                    'Qcloud\\Cos\\Client::explodeKey')
            ),
            'RequestPayer'     => array(
                'type'     => 'string',
                'location' => 'header',
                'sentAs'   => 'x-cos-request-payer',
            ),
            'ACP'              => array(
                'type'                 => 'object',
                'additionalProperties' => true,
            ),
        )
    );
    return $arr;
}




