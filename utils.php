<?php
/**
 * File Name: utils.php
 * PHP Version 7
 *
 * @category None
 * @package  None
 * @author   Jack Chen <redchenjs@live.com>
 * @license  https://server.zyiot.top/nas public
 * @version  GIT: <v2.4>
 * @link     https://server.zyiot.top/nas
 */

const DB_HOST = 'localhost:3306';
const DB_USER = 'nasadmin';
const DB_PASS = 'naspasswd';
const DB_NAME = 'nas_db';

const FTP_HOST = 'localhost';
const FTP_USER = 'anonymous';
const FTP_PASS = '';

const WX_APP_ID = 'wx8d7f06fb7ba10c2d';

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
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 使用$user_token查找$user_id
    $sql = "SELECT `user_id` FROM `wechat_tbl` ".
            "WHERE BINARY `user_token`='".$user_token."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 找到$user_token
        $user_id = $row['user_id'];
        // 使用$device_mac查找$device_location
        $sql = "SELECT `device_location` FROM `device_tbl` ".
                "WHERE BINARY `device_mac`='".$device_mac."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 整理查询结果
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
            // 找到$device_location记录
            $device_location = $row['device_location'];
        } else {
            // 没有找到$device_location记录，用$device_mac暂替
            $device_location = $device_mac;
        }
        // 记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `comment`) ".
                "VALUES ('".$user_id."', '".$device_location."', '签到')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 返回结果
        return true;
    } else {
        // 没有查到$user_token，记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `comment`) ".
                "VALUES ('".$user_id."', '".$device_location."', '失败：验证错误')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
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
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 通过$wx_code查找$wx_openid
    $sql = "SELECT `wx_openid` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_code`='".$wx_code."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 查到$wx_openid，直接返回结果
        return $row['wx_openid'];
    } else { // 没有查到$wx_openid
        // 通过$app_id获取$app_secret
        $app_id = WX_APP_ID;
        $sql = "SELECT `app_secret` FROM `secure_tbl` ".
                "WHERE BINARY `app_id`='".$app_id."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 整理查询结果
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
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
            die('数据库异常');
        }
        // 整理查询结果
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
            // 查到存在$wx_openid，更新对应的$wx_code
            $sql = "UPDATE `wechat_tbl` SET `wx_code`='".$wx_code."' ".
                    "WHERE BINARY `wx_openid`='".$wx_openid."'";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('数据库异常');
            }
        } else {
            // 不存在$wx_openid，则插入$wx_code和$wx_openid记录
            $sql = "INSERT IGNORE INTO `wechat_tbl` (`wx_code`, `wx_openid`) ".
                    "VALUES ('".$wx_code."', '".$wx_openid."')";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('数据库异常');
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
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 使用$wx_openid查找$user_id
    $sql = "SELECT `user_id` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_openid`='".$wx_openid."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 记录存在
        if ($row['user_id'] !== 'null') {
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
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 使用$user_id查找$device_location和$create_time
    $sql = "SELECT `device_location`, `create_time` FROM `log_tbl` ".
            "WHERE BINARY `user_id`='".$user_id."' AND `comment`='签到' ".
            "ORDER BY `create_time` DESC LIMIT 0,1";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 记录存在
        $last_info = array(
            'device_location' => $row['device_location'],
            'create_time' => $row['create_time']
        );
    } else {
        // 记录不存在
        $last_info = array(
            'device_location' => '无',
            'create_time' => '无'
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
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 使用$wx_openid查找$user_token
    $sql = "SELECT `user_token` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_openid`='".$wx_openid."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
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
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 使用$user_id查询$user_passwd
    $sql = "SELECT `user_passwd` FROM `user_tbl` ".
            "WHERE BINARY `user_id`='".$user_id."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // $user_id记录存在，校验密码
        if ($user_passwd === $row['user_passwd']) {
            // 密码校验通过，查找$user_id是否已被绑定
            $sql = "SELECT `user_id` FROM `wechat_tbl` ".
                    "WHERE BINARY `user_id`='".$user_id."'";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('数据库异常');
            }
            // 整理查询结果
            if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
                // $user_id已被绑定，记录日志
                $sql = "INSERT INTO `log_tbl` ".
                    "(`user_id`, `device_location`, `comment`) ".
                    "VALUES ('".$user_id."', '微信绑定', '失败：用户已被绑定')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('数据库异常');
                }
                // 返回错误信息
                return '用户已被绑定';
            } else {
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
                    die('数据库异常');
                }
                // 记录日志
                $sql = "INSERT INTO `log_tbl` ".
                    "(`user_id`, `device_location`, `comment`) ".
                    "VALUES ('".$user_id."', '微信绑定', '成功')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('数据库异常');
                }
                // 返回结果
                return true;
            }
        } else {
            // 密码校验不通过，记录日志
            $sql = "INSERT INTO `log_tbl` ".
                    "(`user_id`, `device_location`, `comment`) ".
                    "VALUES ('".$user_id."', '微信绑定', '失败：密码错误')";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('数据库异常');
            }
            // 返回错误信息
            return '密码错误';
        }
    } else {
        // $user_id记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `comment`) ".
                "VALUES ('".$user_id."', '微信绑定', '失败：用户不存在')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 返回错误信息
        return '用户不存在';
    }
}

/**
 * 使用$wx_openid查找并删除对应用户的$user_id和$user_token，将记入日志
 *
 * @param string $wx_openid WeChat Open ID
 * @param string $user_id   User ID
 *
 * @return bool/string true/$errMsg
 */
function unbindUser($wx_openid, $user_id)
{
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 使用$wx_openid查找$user_id
    $sql = "SELECT `user_id` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_openid`='".$wx_openid."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // $user_id记录存在，清空$user_id和$user_token
        $sql = "UPDATE `wechat_tbl` ".
                "SET `user_id`='null', `user_token`='null' ".
                "WHERE BINARY `wx_openid`='".$wx_openid."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `comment`) ".
                "VALUES ('".$user_id."', '微信解绑', '成功')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 返回结果
        return true;
    } else {
        // $user_id记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `comment`) ".
                "VALUES ('".$user_id."', '微信解绑', '失败：用户不存在')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 返回错误信息
        return '用户不存在';
    }
}

/**
 * 使用$device_mac和$firmware_version检索数据库决定设备是否需要更新，是则返回更新数据，否则返回空数据
 *
 * @param string $device_mac       Device MAC Address
 * @param string $firmware_version Firmware Version
 *
 * @return null/file
 */
function getFirmwareUpdate($device_mac, $firmware_version)
{
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 查找$device_mac是否存在
    $sql = "SELECT `device_mac` FROM `device_tbl` ".
            "WHERE BINARY `device_mac`='".$device_mac."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // $device_mac记录存在，更新数据库记录
        $sql = "UPDATE `device_tbl` ".
                "SET `running_version`='".$firmware_version."'".
                "WHERE BINARY `device_mac`='".$device_mac."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 使用$device_mac查找$required_version
        $sql = "SELECT `required_version` FROM `device_tbl` ".
                "WHERE BINARY `device_mac`='".$device_mac."'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
        // 整理查询结果
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
            if ($row['required_version'] !== '') {
                if ($row['required_version'] !== $firmware_version) {
                    // 设备固件运行版本与目标版本不符
                    $required_version = $row['required_version'];
                    // 从FTP服务器获取固件
                    $ftphost = FTP_HOST;    // ftp主机地址
                    $ftpuser = FTP_USER;    // ftp用户名
                    $ftppass = FTP_PASS;    // ftp用户密码
                    $local_file = '/tmp/nas_'.$required_version.'.bin';
                    $server_file = 'pub/firmware/nas/nas_'.$required_version.'.bin';
                    // 登录FTP服务器
                    $conn_id = ftp_connect($ftphost);
                    ftp_login($conn_id, $ftpuser, $ftppass);
                    // 获取目标版本固件
                    if (ftp_get($conn_id, $local_file, $server_file, FTP_BINARY)) {
                        // 固件获取成功，发送数据到设备端，记录日志
                        $sql = "INSERT INTO `log_tbl` ".
                                "(`user_id`, `device_location`, `comment`) ".
                                "VALUES ('".$device_mac."', '固件更新', '正在更新，从".
                                $firmware_version."到".$required_version."')";
                        $retval = mysqli_query($conn, $sql);
                        if (!$retval) {
                            die('数据库异常');
                        }
                        $file = fopen($local_file, "rb");
                        header("Content-type: application/octet-stream");
                        header("Accept-Ranges: bytes");
                        header("Accept-Length: ".filesize($local_file));
                        header("Content-Disposition: attachment; filename=nas_".$required_version.".bin");
                        echo fread($file, filesize($local_file));
                        fclose($file);
                    } else {
                        // 目标版本固件不存在，记录日志
                        $sql = "INSERT INTO `log_tbl` ".
                                "(`user_id`, `device_location`, `comment`) ".
                                "VALUES ('".$device_mac."', '固件更新', '失败：目标版本".
                                $required_version."不存在')";
                        $retval = mysqli_query($conn, $sql);
                        if (!$retval) {
                            die('数据库异常');
                        }
                    }
                    // 断开FTP连接
                    ftp_close($conn_id);
                } else {
                    // 没有新固件，记录日志
                    $sql = "INSERT INTO `log_tbl` ".
                            "(`user_id`, `device_location`, `comment`) ".
                            "VALUES ('".$device_mac."', '固件更新', '已为最新，当前运行版本：".
                            $firmware_version."')";
                    $retval = mysqli_query($conn, $sql);
                    if (!$retval) {
                        die('数据库异常');
                    }
                }
            } else {
                // 更新已禁用，记录日志
                $sql = "INSERT INTO `log_tbl` ".
                        "(`user_id`, `device_location`, `comment`) ".
                        "VALUES ('".$device_mac."', '固件更新', '更新已禁用，当前运行版本：".
                        $firmware_version."')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('数据库异常');
                }
            }
        }
    } else {
        // $device_mac记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `comment`) ".
                "VALUES ('".$device_mac."', '固件更新', '失败：设备未经授权')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
        }
    }
}

/**
 * 使用$wx_openid查找并更新对应用户的密码，成功后解绑用户，将记入日志
 *
 * @param string $wx_openid  WeChat Open ID
 * @param string $user_id    User ID
 * @param string $old_passwd Old Password
 * @param string $new_passwd New Password
 *
 * @return bool/string true/$errMsg
 */
function updatePassword($wx_openid, $user_id, $old_passwd, $new_passwd)
{
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 使用$wx_openid查找$user_id
    $sql = "SELECT `user_id` FROM `wechat_tbl` ".
            "WHERE BINARY `wx_openid`='".$wx_openid."'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 校验$user_id记录
        if ($user_id === $row['user_id']) {
            // $user_id记录校验成功，开始校验密码
            $sql = "SELECT `user_passwd` FROM `user_tbl` ".
                    "WHERE BINARY `user_id`='".$user_id."'";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('数据库异常');
            }
            $row = mysqli_fetch_array($retval, MYSQLI_ASSOC);
            if ($old_passwd === $row['user_passwd']) {
                // 密码校验通过，更新密码
                $sql = "UPDATE `user_tbl` ".
                        "SET `user_passwd`='".$new_passwd."' ".
                        "WHERE BINARY `user_id`='".$user_id."'";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('数据库异常');
                }
                // 解绑用户，清空$user_id和$user_token
                $sql = "UPDATE `wechat_tbl` ".
                        "SET `user_id`='null', `user_token`='null' ".
                        "WHERE BINARY `wx_openid`='".$wx_openid."'";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('数据库异常');
                }
                // 记录日志
                $sql = "INSERT INTO `log_tbl` ".
                        "(`user_id`, `device_location`, `comment`) ".
                        "VALUES ('".$user_id."', '修改密码', '成功：用户已自动解绑')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('数据库异常');
                }
                // 返回结果
                return true;
            } else {
                // 密码校验错误，记录日志
                $sql = "INSERT INTO `log_tbl` ".
                        "(`user_id`, `device_location`, `comment`) ".
                        "VALUES ('".$user_id."', '修改密码', '失败：原密码错误')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('数据库异常');
                }
                // 返回错误信息
                return '原密码错误';
            }
        } else {
            // $user_id记录校验错误，记录日志
            $sql = "INSERT INTO `log_tbl` ".
                    "(`user_id`, `device_location`, `comment`) ".
                    "VALUES ('".$user_id."', '修改密码', '失败：用户状态异常')";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('数据库异常');
            }
            // 返回错误信息
            return '用户状态异常';
        }
    } else {
        // $user_id记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` ".
                "(`user_id`, `device_location`, `comment`) ".
                "VALUES ('".$user_id."', '修改密码', '失败：用户不存在')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('数据库异常');
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
    $dbhost = DB_HOST;  // mysql主机地址
    $dbuser = DB_USER;  // mysql用户名
    $dbpass = DB_PASS;  // mysql用户密码
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
    if (!$conn) {
        die('无法访问数据库');
    }
    // 设置编码，防止中文乱码
    mysqli_query($conn, "set names utf8");
    mysqli_select_db($conn, DB_NAME);

    // 查询log_tbl中的最后20列
    $sql = "SELECT * FROM ".
            "(SELECT * FROM `log_tbl` ORDER BY `create_time` DESC LIMIT 20) ".
            "AS `tbl` ORDER BY `create_time` ASC";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('数据库异常');
    }
    // 输出查询结果
    echo '<header>';
    echo '<meta http-equiv="refresh" content="3">';
    echo '</header>';
    echo '<h2> 智慧校园NFC考勤系统日志 <h2>';
    echo '<table border="1" width=75%><tr>'.
            '<td> 用户 </td>'.
            '<td> 位置 </td>'.
            '<td> 操作时间 </td>'.
            '<td> 备注 </td>'.
         '</tr>';
    while ($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) {
        echo '<tr>'.
                '<td> '.$row['user_id'].' </td> '.
                '<td> '.$row['device_location'].' </td> '.
                '<td> '.$row['create_time'].' </td> '.
                '<td> '.$row['comment'].' </td> '.
             '</tr>';
    }
    echo '</table>';
}
?>
