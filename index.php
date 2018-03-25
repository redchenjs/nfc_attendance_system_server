<?php
/** 
 * File Name: index.php
 * PHP Version 7
 * 
 * @category None
 * @package  None
 * @author   Jack Chen <redchenjs@live.com>
 * @license  https://redchenjs.vicp.net/nas public
 * @version  GIT: <1.0>
 * @link     https://redchenjs.vicp.net/nas
 */ 

require "utils.php";

$data = file_get_contents("php://input");   // 获取POST数据
$data = json_decode($data, true);           // 解析JSON
$request = $data['request'];                // 获取$request代码

switch ($request) {
case 100:   // 设备端请求验证$user_token
    $device_mac = $data['mac'];
    $user_token = $data['token'];
    $arr = array(
        'status' => verifyUserToken($device_mac, $user_token)
    );
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 101:   // 微信端获取$user_id
    $wx_code = $data['code'];
    if (($wx_openid = getOpenID($wx_code)) !== null
        && ($user_id = getUserID($wx_openid)) !== null
    ) {
        $last_info = getLastInfo($user_id);
        $arr = array(
            'status' => true,
            'stuNum' => $user_id,
            'lastTime' => $last_info['submit_time'],
            'lastLocation' => $last_info['device_location']
        );
    } else {
        $arr = array(
            'status' => false
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 102:   // 微信端获取$user_token
    $wx_code = $data['code'];
    if (($wx_openid = getOpenID($wx_code)) !== null
        && ($wx_token = getUserToken($wx_openid)) !== null
    ) {
        $arr = array(
            'status' => true,
            'token'  => $wx_token
        );
    } else {
        $arr = array(
            'status' => false
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 103:   // 微信端绑定$user_id
    $wx_code = $data['code'];
    $user_id = $data['stuNum'];
    $user_passwd = $data['stuPwd'];
    if (($wx_openid = getOpenID($wx_code)) !== null
        && ($err = bindUser($wx_openid, $user_id, $user_passwd)) === true
    ) {
        $arr = array(
            'status' => true
        );
    } else {
        $arr = array(
            'status' => false,
            'errMsg' => $err
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 104:   // 微信端解绑$user_id
    $wx_code = $data['code'];
    $user_id = $data['stuNum'];
    if (($wx_openid = getOpenID($wx_code)) !== null
        && ($err = unbindUser($wx_openid, $user_id)) === true
    ) {
        $arr = array(
            'status' => true
        );
    } else {
        $arr = array(
            'status' => false,
            'errMsg' => $err
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
default:    // 其他请求
    listLog();
    break;
}
?>
