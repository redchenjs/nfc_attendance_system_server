<?php
/**
 * File Name: index.php
 * PHP Version 7
 *
 * @category None
 * @package  None
 * @author   Jack Chen <redchenjs@live.com>
 * @license  https://server.zyiot.top/nas public
 * @version  GIT: <v2.1>
 * @link     https://server.zyiot.top/nas
 */

require "utils.php";

$data = file_get_contents("php://input");   // 获取POST数据
$data = json_decode($data, true);           // 解析JSON
$request = $data['request'];                // 获取$request代码

switch ($request) {
case 100:   // 设备端请求口令验证
    $device_mac = $data['mac'];
    $user_token = $data['token'];
    $arr = array(
        'status' => verifyUserToken($device_mac, $user_token)
    );
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 101:   // 微信端获取用户信息
    $wx_code = $data['code'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        if ($wx_openid === 'null') {
            $arr = array(
                'status' => 'null'
            );
        } else if (($user_id = getUserID($wx_openid)) !== null) {
            $last_info = getLastInfo($user_id);
            $arr = array(
                'status' => true,
                'stuNum' => $user_id,
                'lastTime' => $last_info['create_time'],
                'lastLocation' => $last_info['device_location']
            );
        } else {
            $arr = array(
                'status' => false
            );
        }
    } else {
        $arr = array(
            'status' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 102:   // 微信端获取验证口令
    $wx_code = $data['code'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        if ($wx_openid === 'null') {
            $arr = array(
                'status' => 'null'
            );
        } else if (($wx_token = getUserToken($wx_openid)) !== null) {
            $arr = array(
                'status' => true,
                'token'  => $wx_token
            );
        } else {
            $arr = array(
                'status' => false
            );
        }
    } else {
        $arr = array(
            'status' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 103:   // 微信端绑定用户
    $wx_code = $data['code'];
    $user_id = $data['stuNum'];
    $user_passwd = $data['stuPwd'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        if ($wx_openid === 'null') {
            $arr = array(
                'status' => 'null'
            );
        } else if (($err = bindUser($wx_openid, $user_id, $user_passwd)) === true) {
            $arr = array(
                'status' => true
            );
        } else {
            $arr = array(
                'status' => false,
                'errMsg' => $err
            );
        }
    } else {
        $arr = array(
            'status' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 104:   // 微信端解绑用户
    $wx_code = $data['code'];
    $user_id = $data['stuNum'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        if ($wx_openid === 'null') {
            $arr = array(
                'status' => 'null'
            );
        } else if (($err = unbindUser($wx_openid, $user_id)) === true) {
            $arr = array(
                'status' => true
            );
        } else {
            $arr = array(
                'status' => false,
                'errMsg' => $err
            );
        }
    } else {
        $arr = array(
            'status' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case 105:   // 设备端固件更新
    $device_mac = $data['mac'];
    $firmware_version = $data['version'];
    getFirmwareUpdate($device_mac, $firmware_version);
    break;
case 106:   // 微信端修改密码
    $wx_code = $data['code'];
    $user_id = $data['stuNum'];
    $old_passwd = $data['oldPwd'];
    $new_passwd = $data['newPwd'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        if ($wx_openid === 'null') {
            $arr = array(
                'status' => 'null'
            );
        } else if (($err = updatePassword($wx_openid, $user_id, $old_passwd, $new_passwd)) === true) {
            $arr = array(
                'status' => true
            );
        } else {
            $arr = array(
                'status' => false,
                'errMsg' => $err
            );
        }
    } else {
        $arr = array(
            'status' => null
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
