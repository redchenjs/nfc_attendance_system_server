<?php
/** 
 * File Name: utils.php
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
 * 使用$user_token验证，将记入日志
 * 
 * @param string $device_mac Device MAC Address
 * @param string $user_token User Token
 * 
 * @return bool true/false
 */
function verifyUserToken($device_mac, $user_token)
{
    $dbhost = 'localhost:3306'; // mysql服务器主机地址
    $dbuser = 'nasadmin';       // mysql用户名
    $dbpass = 'naspasswd';      // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    // 使用$user_token查找$user_id
    $sql = "SELECT `user_id` FROM `wechat_tbl` ".
            "WHERE BINARY `user_token`='".$user_token."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        // 找到$user_token
        $user_id = $row['user_id'];
        // 使用$device_mac查找$device_location
        $sql = "SELECT `device_location` FROM `device_tbl` ".
                "WHERE BINARY `device_mac`='".$device_mac."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 整理查询结果
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
            // 找到$device_location记录
            $device_location = $row['device_location'];
        } else {
            // 没有找到$device_location记录，用$device_mac暂替
            $device_location = $device_mac;
        }
        // 记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `submit_time`, `comment`) ".
                "VALUES ('".$user_id."', '".$device_location."', NOW(), '签到')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 返回结果
        return true;
    } else {
        // 没有查到$user_token，记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `submit_time`, `comment`) ".
                "VALUES ('".$user_id."', '".$device_location."', NOW(), '失败：验证错误')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 返回结果
        return false;
    }
}

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
    if (!$conn) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    // 通过$wx_code查找$wx_openid
    $sql = "SELECT `wx_openid` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_code`='".$wx_code."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        // 查到$wx_openid，直接返回结果
        return $row['wx_openid'];
    } else { // 没有查到$wx_openid
        // 通过$app_id获取$app_secret
        $app_id = "wx8d7f06fb7ba10c2d";
        $sql = "SELECT `app_secret` FROM `secure_tbl` ".
                "WHERE BINARY `app_id`='".$app_id."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 整理查询结果
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
            // 查到$app_secret
            $app_secret = $row['app_secret'];
            // 向微信服务器提交查询openid请求
            $weixin = file_get_contents(
                "https://api.weixin.qq.com/sns/oauth2/access_token".
                "?appid=".$app_id."&secret=".$app_secret."&code=".$wx_code.
                "&grant_type=authorization_code"
            );
            // 解析查询结果
            $weixin = json_decode($weixin, true);
            $wx_openid = $weixin['openid'];
            // 判断$wx_openid是否为空
            if ($wx_openid === null) {
                // 服务器返回空数据，优雅退出
                return 'null';
            }
        } else {
            // 查不到$app_secret，优雅退出
            return null;
        }
        // $wx_openid不为空，使用$wx_openid重新查询，防止$wx_openid记录重复
        $sql = "SELECT `wx_openid` FROM `wechat_tbl` ".
                "WHERE BINARY `wx_openid`='".$wx_openid."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 整理查询结果
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
            // 查到存在$wx_openid，更新对应的$wx_code
            $sql = "UPDATE `wechat_tbl` SET `wx_code`='".$wx_code."' ".
                    "WHERE BINARY `wx_openid`='".$wx_openid."'";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('query err: '.mysqli_error($conn));
            }
        } else {
            // 不存在$wx_openid，则插入$wx_code和$wx_openid记录
            $sql = "INSERT INTO `wechat_tbl` (`wx_code`, `wx_openid`) ".
                    "VALUES ('".$wx_code."', '".$wx_openid."')";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('query err: '.mysqli_error($conn));
            }
        }
        // 返回$wx_openid
        return $wx_openid;
    }
}

/**  
 * 使用$wx_openid查找并返回$user_id
 * 
 * @param string $wx_openid WeChat Open ID
 * 
 * @return string $user_id User ID
 */
function getUserID($wx_openid)
{
    $dbhost = 'localhost:3306'; // mysql服务器主机地址
    $dbuser = 'nasadmin';       // mysql用户名
    $dbpass = 'naspasswd';      // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    // 使用$wx_openid查找$user_id
    $sql = "SELECT `user_id` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_openid`='".$wx_openid."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        // 记录存在
        if ($row['user_id'] != 'null') {
            // 返回$user_id
            return $row['user_id'];
        }
    }
    // $user_id不存在
    return null;
}

/**  
 * 使用$user_id查找并返回$last_info
 * 
 * @param string $user_id User ID
 * 
 * @return array $last_info Last Login Info
 */
function getLastInfo($user_id)
{
    $dbhost = 'localhost:3306'; // mysql服务器主机地址
    $dbuser = 'nasadmin';       // mysql用户名
    $dbpass = 'naspasswd';      // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    // 使用$user_id查找$device_location和$submit_time
    $sql = "SELECT `device_location`, `submit_time` FROM `log_tbl` ".
            "WHERE BINARY `user_id`='".$user_id."' AND `comment`='签到' ".
            "ORDER BY `submit_time` DESC LIMIT 0,1";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        // 记录存在
        $last_info = array(
            'device_location' => $row['device_location'],
            'submit_time' => $row['submit_time']
        );
    } else {
        // 记录不存在
        $last_info = array(
            'device_location' => '无',
            'submit_time' => '无'
        );
    }
    // 返回结果
    return $last_info;
}

/**  
 * 使用$wx_openid获取$user_token
 * 
 * @param string $wx_openid WeChat OpenID
 * 
 * @return string $user_token User Token
 */
function getUserToken($wx_openid)
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

    // 使用$wx_openid查找$user_token
    $sql = "SELECT `user_token` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_openid`='".$wx_openid."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        // 记录存在
        return $row['user_token'];
    } else {
        // 记录不存在
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
    if (!$conn) {
        die('conn err: '.mysqli_error($conn));
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, 'nas_db');

    // 使用$user_id查询$user_passwd
    $sql = "SELECT `user_passwd` FROM `user_tbl` ".
            "WHERE BINARY `user_id`='".$user_id."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        // $user_id记录存在，校验密码
        if ($user_passwd == $row['user_passwd']) {
            // 密码校验通过，使用$wx_openid查询$user_id
            $sql = "SELECT `user_id` FROM `wechat_tbl` ".
                    "WHERE BINARY `wx_openid`='".$wx_openid."'";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('query err: '.mysqli_error($conn));
            }
            // 整理查询结果
            if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
                // 查到$wx_openid，判断$user_id是否已被绑定
                if ($row['user_id'] == 'null') {
                    // $user_id未被绑定，生成$user_token
                    $user_token = md5(
                        $wx_openid.$user_id.$user_passwd.date('Y-m-d H:i:s')
                    );
                    // 更新$wx_openid对应的$user_id和$user_token
                    $sql = "UPDATE `wechat_tbl` ".
                    "SET `user_id`='".$user_id."', `user_token`='".$user_token."'".
                    "WHERE BINARY `wx_openid`='".$wx_openid."'";
                    $retval = mysqli_query($conn, $sql);
                    if (! $retval ) {
                        die('query err: '.mysqli_error($conn));
                    }
                    // 记录日志
                    $sql = "INSERT INTO `log_tbl` ".
                        "(`user_id`, `device_location`, `submit_time`, `comment`) ".
                        "VALUES ('".$user_id."', '微信绑定', NOW(), '成功')";
                    $retval = mysqli_query($conn, $sql);
                    if (!$retval) {
                        die('query err: '.mysqli_error($conn));
                    }
                    // 返回结果
                    return true;
                } else {
                    // $user_id已被绑定，记录日志
                    $sql = "INSERT INTO `log_tbl` ".
                        "(`user_id`, `device_location`, `submit_time`, `comment`) ".
                        "VALUES ('".$user_id."', '微信绑定', NOW(), '失败：用户已被绑定')";
                    $retval = mysqli_query($conn, $sql);
                    if (!$retval) {
                        die('query err: '.mysqli_error($conn));
                    }
                    // 返回错误信息
                    return '用户已被绑定';
                }
            }
        } else {
            // 密码校验不通过，记录日志
            $sql = "INSERT INTO `log_tbl` ".
                    "(`user_id`, `device_location`, `submit_time`, `comment`) ".
                    "VALUES ('".$user_id."', '微信绑定', NOW(), '失败：密码错误')";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('query err: '.mysqli_error($conn));
            }
            // 返回错误信息
            return '密码错误';
        }
    } else {
        // $user_id记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `submit_time`, `comment`) ".
                "VALUES ('".$user_id."', '微信绑定', NOW(), '失败：用户不存在')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 返回错误信息
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

    // 使用$wx_openid查找$user_id
    $sql = "SELECT `user_id` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_openid`='".$wx_openid."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) != null) {
        // $user_id记录存在，清空$user_id和$user_token
        $sql = "UPDATE `wechat_tbl` ".
                "SET `user_id`='null', `user_token`='null' ".
                "WHERE BINARY `wx_openid`='".$wx_openid."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `submit_time`, `comment`) ".
                "VALUES ('".$user_id."', '微信解绑', NOW(), '成功')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 返回结果
        return true;
    } else {
        // $user_id记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `submit_time`, `comment`) ".
                "VALUES ('".$user_id."', '微信解绑', NOW(), '失败：用户不存在')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('query err: '.mysqli_error($conn));
        }
        // 返回错误信息
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

    // 查询log_tbl中的所有列
    $sql = "SELECT `user_id`, `device_location`, `submit_time`, `comment` ".
            "FROM `log_tbl`";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('query err: '.mysqli_error($conn));
    }
    // 输出查询结果
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
?>
