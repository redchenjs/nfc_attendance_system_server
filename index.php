<?php
/**
 * File Name: index.php
 * PHP Version 7
 *
 * @category None
 * @package  None
 * @author   Jack Chen <redchenjs@live.com>
 * @license  https://server.zyiot.top/nas public
 * @version  GIT: <v2.5>
 * @link     https://server.zyiot.top/nas
 */

require "utils.php";

const HTTP_REQ_CODE_DEV_VERIFY_TOKEN = 100; // 设备端请求口令验证
const HTTP_REQ_CODE_DEV_UPDATE_FW    = 101; // 设备端请求固件更新
const HTTP_REQ_CODE_APP_GET_INFO     = 110; // 微信端获取用户信息
const HTTP_REQ_CODE_APP_GET_TOKEN    = 111; // 微信端获取验证口令
const HTTP_REQ_CODE_APP_BIND_USER    = 112; // 微信端请求绑定用户
const HTTP_REQ_CODE_APP_UNBIND_USER  = 113; // 微信端请求解绑用户
const HTTP_REQ_CODE_APP_UPDATE_PSWD  = 114; // 微信端请求修改密码

$data = file_get_contents("php://input");   // 获取POST数据
$data = json_decode($data, true);           // 解析JSON
$code = $data['request'];                   // 客户端请求码

switch ($code) {
case HTTP_REQ_CODE_DEV_VERIFY_TOKEN:
    $device_mac = $data['device_mac'];
    $user_token = $data['user_token'];
    $arr = array(
        'result' => verifyUserToken($device_mac, $user_token)
    );
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case HTTP_REQ_CODE_DEV_UPDATE_FW:
    $device_mac = $data['device_mac'];
    $fw_version = $data['fw_version'];
    getFirmwareUpdate($device_mac, $fw_version);
    break;
case HTTP_REQ_CODE_APP_GET_INFO:
    $wx_code = $data['wx_code'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        if (($user_id = getUserID($wx_openid)) !== null) {
            $last_info = getLastInfo($user_id);
            $arr = array(
                'result' => true,
                'user_id' => $user_id,
                'last_time' => $last_info['last_time'],
                'last_location' => $last_info['last_location']
            );
        } else {
            $arr = array(
                'result' => false
            );
        }
    } else {
        $arr = array(
            'result' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case HTTP_REQ_CODE_APP_GET_TOKEN:
    $wx_code = $data['wx_code'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        $user_token = getUserToken($wx_openid);
        $arr = array(
            'result' => $user_token !== null ? true : false,
            'user_token' => $user_token
        );
    } else {
        $arr = array(
            'result' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case HTTP_REQ_CODE_APP_BIND_USER:
    $wx_code = $data['wx_code'];
    $user_id = $data['user_id'];
    $user_passwd = $data['user_passwd'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        $errmsg = bindUser($wx_openid, $user_id, $user_passwd);
        $arr = array(
            'result' => $errmsg === null ? true : false,
            'errmsg' => $errmsg
        );
    } else {
        $arr = array(
            'result' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case HTTP_REQ_CODE_APP_UNBIND_USER:
    $wx_code = $data['wx_code'];
    $user_id = $data['user_id'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        $errmsg = unbindUser($wx_openid, $user_id);
        $arr = array(
            'result' => $errmsg === null ? true : false,
            'errmsg' => $errmsg
        );
    } else {
        $arr = array(
            'result' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
case HTTP_REQ_CODE_APP_UPDATE_PSWD:
    $wx_code = $data['wx_code'];
    $user_id = $data['user_id'];
    $old_passwd = $data['old_passwd'];
    $new_passwd = $data['new_passwd'];
    if (($wx_openid = getOpenID($wx_code)) !== null) {
        $errmsg = updatePassword($wx_openid, $user_id, $old_passwd, $new_passwd);
        $arr = array(
            'result' => $errmsg === null ? true : false,
            'errmsg' => $errmsg
        );
    } else {
        $arr = array(
            'result' => null
        );
    }
    header('content-type:application/json');
    echo json_encode($arr);
    break;
default:
    listLog();
    break;
}
?>
