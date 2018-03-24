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

/**  
 * 使用$wx_code换取$wx_openid，请求结果将被缓存
 * 
 * @param string $wx_code WeChat session code
 * 
 * @return string $wx_openid WeChat OpenID
 */
function getOpenID($wx_code)
{
    $dbhost = 'localhost:3306'; // mysql服务器主机地址
    $dbuser = 'nasadmin';       // mysql用户名
    $dbpass = 'naspasswd';      // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (! $conn ) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    $sql = 'SELECT wx_code, wx_openid FROM wechat_tbl '.
                    'WHERE BINARY wx_code=\''.$wx_code.'\'';
    $retval = mysqli_query($conn, $sql);
    if (! $retval ) {
        die('query err: '.mysqli_error($conn));
    }

    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        return $row['wx_openid'];
    } else {
        $app_id = "wx8d7f06fb7ba10c2d";
        $sql = 'SELECT app_id, app_secret FROM secure_tbl '.
                        'WHERE BINARY app_id=\''.$app_id.'\'';
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
    
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
            $app_secret = $row['app_secret'];
            $weixin = file_get_contents(
                "https://api.weixin.qq.com/sns/oauth2/access_token".
                "?appid=".$app_id."&secret=".$app_secret."&code=".$wx_code.
                "&grant_type=authorization_code"
            );
        
            $weixin = json_decode($weixin, true);
            $wx_openid = $weixin['openid'];
        } else {
            return null;
        }

        if ($wx_openid != null) {
            $sql = 'SELECT wx_openid FROM wechat_tbl '.
                            'WHERE BINARY wx_openid=\''.$wx_openid.'\'';
            $retval = mysqli_query($conn, $sql);
            if (! $retval ) {
                die('query err: '.mysqli_error($conn));
            }
            if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
                $sql = 'UPDATE wechat_tbl SET wx_code=\''.$wx_code.'\' '.
                                'WHERE BINARY wx_openid=\''.$wx_openid.'\'';
                $retval = mysqli_query($conn, $sql);
                if (! $retval ) {
                    die('query err: '.mysqli_error($conn));
                }       
            } else {
                $sql = 'INSERT INTO wechat_tbl (wx_code, wx_openid) '.
                                'VALUES (\''.$wx_code.'\', \''.$wx_openid.'\')';
                $retval = mysqli_query($conn, $sql);
                if (! $retval ) {
                    die('query err: '.mysqli_error($conn));
                }
            }
        }
        return $wx_openid;
    }
}

/**  
 * 使用$user_token验证，将记入日志
 * 
 * @param string $device_mac Device MAC Address
 * @param string $user_token User Token
 * 
 * @return bool true/false
 */
function matchToken($device_mac, $user_token)
{
    $dbhost = 'localhost:3306'; // mysql服务器主机地址
    $dbuser = 'nasadmin';       // mysql用户名
    $dbpass = 'naspasswd';      // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (! $conn ) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    $sql = 'SELECT user_id, user_token FROM wechat_tbl '.
                    'WHERE BINARY user_token=\''.$user_token.'\'';
    $retval = mysqli_query($conn, $sql);
    if (! $retval ) {
        die('query err: '.mysqli_error($conn));
    }

    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        $user_id = $row['user_id'];

        $sql = 'SELECT device_mac, device_location FROM device_tbl '.
                        'WHERE BINARY device_mac=\''.$device_mac.'\'';
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
            $device_location = $row['device_location'];
        } else {
            $device_location = $device_mac;
        }
        // Insert Log
        $sql = 'INSERT INTO log_tbl '.
                    '(user_id, device_location, submit_time, comment) VALUES '.
                    '(\''.$user_id.'\', \''.$device_location.'\', NOW(), \'签到成功\')';
        $retval = mysqli_query($conn, $sql);
        if (! $retval ) {
            die('query err: '.mysqli_error($conn));
        }
        return true;
    } else {
        // Insert Log
        $sql = 'INSERT INTO log_tbl '.
                    '(user_id, device_location, submit_time, comment) VALUES '.
                    '(\''.$user_id.'\', \''.$device_location.'\', NOW(), '.
                    '\'失败：验证失败\')';
        $retval = mysqli_query($conn, $sql);
        if (! $retval ) {
            die('query err: '.mysqli_error($conn));
        }
        return false;
    }
}

/**  
 * 使用$wx_openid查找并返回$user_id
 * 
 * @param string $wx_openid WeChat Open ID
 * 
 * @return string $user_id User ID
 */
function matchOpenID($wx_openid)
{
    $dbhost = 'localhost:3306'; // mysql服务器主机地址
    $dbuser = 'nasadmin';       // mysql用户名
    $dbpass = 'naspasswd';      // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (! $conn ) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    $sql = 'SELECT user_id, wx_openid FROM wechat_tbl '.
                    'WHERE BINARY wx_openid=\''.$wx_openid.'\'';
    $retval = mysqli_query($conn, $sql);
    if (! $retval ) {
        die('query err: '.mysqli_error($conn));
    }

    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        if ($row['user_id'] != 'null') {
            return $row['user_id'];
        }
    }

    return null;
}

/**  
 * 使用$wx_openid获取$user_token
 * 
 * @param string $wx_openid WeChat OpenID
 * 
 * @return string $user_token User Token
 */
function getToken($wx_openid)
{
    $dbhost = 'localhost:3306';  // mysql服务器主机地址
    $dbuser = 'nasadmin';        // mysql用户名
    $dbpass = 'naspasswd';       // mysql用户名密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (! $conn ) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    $sql = 'SELECT wx_openid, user_token FROM wechat_tbl '.
                    'WHERE BINARY wx_openid=\''.$wx_openid.'\'';
    $retval = mysqli_query($conn, $sql);
    if (! $retval ) {
        die('query err: '.mysqli_error($conn));
    }

    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        return $row['user_token'];
    } else {
        return null;
    }
}

/**  
 * 使用$user_id查找并校验$user_passwd，绑定$wx_openid与$user_id，生成$user_token，将记入日志
 * 
 * @param string $wx_openid   WeChat Open ID
 * @param string $user_id     User ID
 * @param string $user_passwd User Password
 * 
 * @return bool/string true/$errMsg
 */
function bindUser($wx_openid, $user_id, $user_passwd)
{
    $dbhost = 'localhost:3306';  // mysql服务器主机地址
    $dbuser = 'nasadmin';        // mysql用户名
    $dbpass = 'naspasswd';       // mysql用户名密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (! $conn ) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    $sql = 'SELECT user_id, user_passwd FROM user_tbl '.
                    'WHERE BINARY user_id=\''.$user_id.'\'';
    $retval = mysqli_query($conn, $sql);
    if (! $retval ) {
        die('query err: '.mysqli_error($conn));
    }

    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        if ($user_passwd == $row['user_passwd']) {
            $sql = 'SELECT user_id, wx_openid FROM wechat_tbl '.
                            'WHERE BINARY wx_openid=\''.$wx_openid.'\'';
            $retval = mysqli_query($conn, $sql);
            if (! $retval ) {
                die('query err: '.mysqli_error($conn));
            }
            if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
                if ($row['user_id'] == 'null') {
                    $user_token = md5(
                        $wx_openid.$user_id.$user_passwd.date('Y-m-d H:i:s')
                    );

                    $sql = 'UPDATE wechat_tbl SET user_id=\''.$user_id.'\', '.
                                'user_token=\''.$user_token.'\' '.
                                'WHERE BINARY wx_openid=\''.$wx_openid.'\'';
                    $retval = mysqli_query($conn, $sql);
                    if (! $retval ) {
                        die('query err: '.mysqli_error($conn));
                    }

                    // Insert Log
                    $sql = 'INSERT INTO log_tbl '.
                        '(user_id, device_location, submit_time, comment) VALUES '.
                        '(\''.$user_id.'\', \'微信绑定\', NOW(), \'成功\')';
                    $retval = mysqli_query($conn, $sql);
                    if (!$retval) {
                        die('query err: '.mysqli_error($conn));
                    }

                    return true;
                } else {
                    // Insert Log
                    $sql = 'INSERT INTO log_tbl '.
                        '(user_id, device_location, submit_time, comment) VALUES '.
                        '(\''.$user_id.'\', \'微信绑定\', NOW(), \'失败：用户已被绑定\')';
                    $retval = mysqli_query($conn, $sql);
                    if (!$retval) {
                        die('query err: '.mysqli_error($conn));
                    }

                    return '用户已被绑定';
                }
            }
        } else {
            // Insert Log
            $sql = 'INSERT INTO log_tbl '.
                    '(user_id, device_location, submit_time, comment) VALUES '.
                    '(\''.$user_id.'\', \'微信绑定\', NOW(), \'失败：密码错误\')';
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('query err: '.mysqli_error($conn));
            }

            return '密码错误';
        }
    } else {
        // Insert Log
        $sql = 'INSERT INTO log_tbl '.
                '(user_id, device_location, submit_time, comment) VALUES '.
                '(\''.$user_id.'\', \'微信绑定\', NOW(), \'失败：用户不存在\')';
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }

        return '用户不存在';
    }
}

/**  
 * 使用$wx_openid查找并删除对应的$user_id和$user_token，将记入日志
 * 
 * @param string $wx_openid WeChat Open ID
 * @param string $user_id   User ID
 * 
 * @return bool/string true/$errMsg
 */
function unbindUser($wx_openid, $user_id)
{
    $dbhost = 'localhost:3306';  // mysql服务器主机地址
    $dbuser = 'nasadmin';        // mysql用户名
    $dbpass = 'naspasswd';       // mysql用户名密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    $sql = 'SELECT user_id, wx_openid FROM wechat_tbl '.
                    'WHERE BINARY wx_openid=\''.$wx_openid.'\'';
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }

    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        $sql = 'UPDATE wechat_tbl SET user_id=\'null\', user_token=\'null\' '.
                        'WHERE BINARY wx_openid=\''.$wx_openid.'\'';
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }

        // Insert Log
        $sql = 'INSERT INTO log_tbl '.
                '(user_id, device_location, submit_time, comment) VALUES '.
                '(\''.$user_id.'\', \'微信解绑\', NOW(), \'成功\')';
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }

        return true;
    } else {
        // Insert Log
        $sql = 'INSERT INTO log_tbl '.
                '(user_id, device_location, submit_time, comment) VALUES '.
                '(\''.$user_id.'\', \'微信解绑\', NOW(), \'失败：用户不存在\')';
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }

        return '用户不存在';
    }
}

/**  
 * 显示系统日志
 * 
 * @return none
 */
function listLog()
{
    $dbhost = 'localhost:3306';  // mysql服务器主机地址
    $dbuser = 'nasadmin';        // mysql用户名
    $dbpass = 'naspasswd';       // mysql用户名密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    $sql = 'SELECT user_id, device_location, submit_time, comment FROM log_tbl';
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    echo '<h2>NFC考勤系统日志<h2>';
    echo '<table border="1"><tr>'.
            '<td>用户</td>'.
            '<td>位置</td>'.
            '<td>操作时间</td>'.
            '<td>备注</td>'.
            '</tr>';
    while ($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) {
        echo '<tr>'.
                '<td> '.$row['user_id'].' </td> '.
                '<td> '.$row['device_location'].' </td> '.
                '<td> '.$row['submit_time'].' </td> '.
                '<td> '.$row['comment'].' </td> '.
             '</tr>';
    }
    echo '</table>';
}

$data = file_get_contents("php://input");
$data = json_decode($data, true);
$request = $data['request'];

switch ($request) {
case 100:   // 设备端请求验证token
    header('content-type:application/json');
    $device_mac = $data['mac'];
    $user_token = $data['token'];
    $arr = array(
        'status' => matchToken($device_mac, $user_token),    
    );
    echo json_encode($arr);
    break;
case 101:   // 微信端获取绑定状态
    header('content-type:application/json');
    $wx_code = $data['code'];
    $wx_openid = getOpenID($wx_code);
    if (($user_id = matchOpenID($wx_openid)) != null) {
        $arr = array(
            'status' => true,
            'stuNum' => $user_id
        );
        echo json_encode($arr);
    } else {
        $arr = array(
            'status' => false   
        );
        echo json_encode($arr);
    }
    break;
case 102:   // 微信端获取token
    header('content-type:application/json');
    $wx_code = $data['code'];
    $wx_openid = getOpenID($wx_code);
    if (($wx_token = getToken($wx_openid)) != null) {
        $arr = array(
            'status' => true,    
            'token'  => $wx_token
        );
        echo json_encode($arr);
    } else {
        $arr = array(
            'status' => false   
        );
        echo json_encode($arr);
    }
    break;
case 103:   // 微信端绑定学号
    header('content-type:application/json');
    $wx_code = $data['code'];
    $user_id = $data['stuNum'];
    $user_passwd = $data['stuPwd'];
    $wx_openid = getOpenID($wx_code);
    if (($err = bindUser($wx_openid, $user_id, $user_passwd)) === true) {
        $arr = array(
            'status' => true
        );
        echo json_encode($arr);
    } else {
        $arr = array(
            'status' => false,
            'errMsg' => $err
        );
        echo json_encode($arr);
    }
    break;
case 104:   // 微信端解绑学号
    header('content-type:application/json');
    $wx_code = $data['code'];
    $user_id = $data['stuNum'];
    $wx_openid = getOpenID($wx_code);
    if (($err = unbindUser($wx_openid, $user_id)) === true) {
        $arr = array(
            'status' => true
        );
        echo json_encode($arr);
    } else {
        $arr = array(
            'status' => false,
            'errMsg' => $err
        );
        echo json_encode($arr);
    }
    break;
default:    // 其他请求
    listLog();
    break;
}
?>
