<?php
/**
 * File Name: app.php
 * PHP Version 7
 *
 * @category None
 * @package  None
 * @author   Jack Chen <redchenjs@live.com>
 * @license  https://server.zyiot.top/nas public
 * @version  GIT: <v2.6>
 * @link     https://server.zyiot.top/nas
 */

const DB_HOST = 'localhost:3306';
const DB_USER = 'nasadmin';
const DB_PASS = 'naspasswd';
const DB_NAME = 'nas_db';

const TEST_USER = 'test';
const WX_APP_ID = 'wx8d7f06fb7ba10c2d';

/**
 * 使用$wx_code换取$wx_openid，请求结果将被缓存
 *
 * @param string $wx_code WeChat session code
 *
 * @return string $wx_openid WeChat OpenID
 */
function getOpenID($wx_code)
{
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if (!$conn) {
        die('Access denied.');
    }
    mysqli_select_db($conn, DB_NAME);
    mysqli_set_charset($conn, 'utf8');

    // 通过$wx_code查找$wx_openid
    $sql = "SELECT `wx_openid` FROM `token_tbl`
            WHERE BINARY `wx_code`='$wx_code'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('Query failed.');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 查到$wx_openid，直接返回结果
        return $row['wx_openid'];
    } else {
        // 通过$app_id获取$app_secret
        $app_id = WX_APP_ID;
        $sql = "SELECT `app_secret` FROM `app_tbl`
                WHERE BINARY `app_id`='$app_id'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('Query failed.');
        }
        // 整理查询结果
        if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
            // 查到$app_secret
            $app_secret = $row['app_secret'];
            // 向微信服务器提交查询openid请求
            $weixin = file_get_contents(
                "https://api.weixin.qq.com/sns/jscode2session?".
                "appid=$app_id&secret=$app_secret&js_code=$wx_code&grant_type=authorization_code"
            );
            // 解析查询结果
            $weixin = json_decode($weixin, true);
            $wx_openid = $weixin['openid'];
            // 判断$wx_openid是否为空
            if ($wx_openid === null) {
                // 服务器返回空数据，优雅退出
                return null;
            }
        } else {
            // 查不到$app_secret，优雅退出
            return null;
        }
        // $wx_openid不为空，使用$wx_openid重新查询，防止$wx_openid记录重复
        $sql = "SELECT `wx_openid` FROM `token_tbl`
                WHERE BINARY `wx_openid`='$wx_openid'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('Query failed.');
        }
        // 整理查询结果
        if (mysqli_fetch_array($retval, MYSQLI_ASSOC) !== null) {
            // 查到存在$wx_openid，更新对应的$wx_code
            $sql = "UPDATE `token_tbl` SET `wx_code`='$wx_code'
                    WHERE BINARY `wx_openid`='$wx_openid'";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('Query failed.');
            }
        } else {
            // 不存在$wx_openid，则插入$wx_openid和$wx_code记录
            $sql = "INSERT IGNORE INTO `token_tbl` (`wx_openid`, `wx_code`)
                    VALUES ('$wx_openid', '$wx_code')";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('Query failed.');
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
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if (!$conn) {
        die('Access denied.');
    }
    mysqli_select_db($conn, DB_NAME);
    mysqli_set_charset($conn, 'utf8');

    // 使用$wx_openid查找$user_id
    $sql = "SELECT `user_id` FROM `token_tbl`
            WHERE BINARY `wx_openid`='$wx_openid'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('Query failed.');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 记录存在
        if ($row['user_id'] !== '') {
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
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if (!$conn) {
        die('Access denied.');
    }
    mysqli_select_db($conn, DB_NAME);
    mysqli_set_charset($conn, 'utf8');

    // 使用$user_id查找$device_location和$create_time
    $sql = "SELECT `location`, `create_time` FROM `log_tbl`
            WHERE BINARY `user`='$user_id' AND `comment`='签到'
            ORDER BY `create_time` DESC LIMIT 0,1";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('Query failed.');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 记录存在
        $last_info = array(
            'last_time' => $row['create_time'],
            'last_location' => $row['location']
        );
    } else {
        // 记录不存在
        $last_info = array(
            'last_time' => '无',
            'last_location' => '无'
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
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if (!$conn) {
        die('Access denied.');
    }
    mysqli_select_db($conn, DB_NAME);
    mysqli_set_charset($conn, 'utf8');

    // 使用$wx_openid查找$user_token
    $sql = "SELECT `user_token` FROM `token_tbl`
            WHERE BINARY `wx_openid`='$wx_openid'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('Query failed.');
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
 * @return string $errmsg
 */
function bindUser($wx_openid, $user_id, $user_passwd)
{
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if (!$conn) {
        die('Access denied.');
    }
    mysqli_select_db($conn, DB_NAME);
    mysqli_set_charset($conn, 'utf8');

    // 使用$user_id查询$user_passwd
    $sql = "SELECT `user_passwd` FROM `user_tbl`
            WHERE BINARY `user_id`='$user_id'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('Query failed.');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // $user_id记录存在，校验密码
        if ($user_passwd === $row['user_passwd']) {
            // 密码校验通过，查找$user_id是否已被绑定
            $sql = "SELECT `user_id` FROM `token_tbl`
                    WHERE BINARY `user_id`='$user_id'";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('Query failed.');
            }
            // 整理查询结果
            if ($user_id === TEST_USER || mysqli_fetch_array($retval, MYSQLI_ASSOC) === null) {
                // $user_id未被绑定，生成$user_token
                $user_token = md5(
                    $wx_openid.$user_id.$user_passwd.date('Y-m-d H:i:s')
                );
                // 更新$wx_openid对应的$user_id和$user_token
                $sql = "UPDATE `token_tbl` SET `user_id`='$user_id', `user_token`='$user_token'
                        WHERE BINARY `wx_openid`='$wx_openid'";
                $retval = mysqli_query($conn, $sql);
                if (! $retval ) {
                    die('Query failed.');
                }
                // 记录日志
                $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                        VALUES ('$user_id', '微信绑定', '成功')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('Query failed.');
                }
                // 返回错误信息
                return null;
            } else {
                // $user_id已被绑定，记录日志
                $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                        VALUES ('$user_id', '微信绑定', '失败：用户已被绑定')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('Query failed.');
                }
                // 返回错误信息
                return '用户已被绑定';
            }
        } else {
            if ($user_id === TEST_USER) {
                // 提示测试用户正确密码，记录日志
                $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                        VALUES ('$user_id', '微信绑定', '失败：测试用户未输入正确密码')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('Query failed.');
                }
                // 返回错误信息
                return '密码：'.$row['user_passwd'];
            } else {
                // 密码校验不通过，记录日志
                $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                        VALUES ('$user_id', '微信绑定', '失败：密码错误')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('Query failed.');
                }
                // 返回错误信息
                return '密码错误';
            }
        }
    } else {
        // $user_id记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                VALUES ('$user_id', '微信绑定', '失败：用户不存在')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('Query failed.');
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
 * @return string $errmsg
 */
function unbindUser($wx_openid, $user_id)
{
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if (!$conn) {
        die('Access denied.');
    }
    mysqli_select_db($conn, DB_NAME);
    mysqli_set_charset($conn, 'utf8');

    // 使用$wx_openid查找$user_id
    $sql = "SELECT `user_id` FROM `token_tbl`
            WHERE BINARY `wx_openid`='$wx_openid'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('Query failed.');
    }
    // 整理查询结果
    if (mysqli_fetch_array($retval, MYSQLI_ASSOC) !== null) {
        // $user_id记录存在，清空$user_id和$user_token
        $sql = "UPDATE `token_tbl` SET `user_id`='', `user_token`=''
                WHERE BINARY `wx_openid`='$wx_openid'";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('Query failed.');
        }
        // 记录日志
        $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                VALUES ('$user_id', '微信解绑', '成功')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('Query failed.');
        }
        // 返回错误信息
        return null;
    } else {
        // $user_id记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                VALUES ('$user_id', '微信解绑', '失败：用户不存在')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('Query failed.');
        }
        // 返回错误信息
        return '用户不存在';
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
 * @return string $errmsg
 */
function updatePassword($wx_openid, $user_id, $old_passwd, $new_passwd)
{
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if (!$conn) {
        die('Access denied.');
    }
    mysqli_select_db($conn, DB_NAME);
    mysqli_set_charset($conn, 'utf8');

    // 使用$wx_openid查找$user_id
    $sql = "SELECT `user_id` FROM `token_tbl`
            WHERE BINARY `wx_openid`='$wx_openid'";
    $retval = mysqli_query($conn, $sql);
    if (!$retval) {
        die('Query failed.');
    }
    // 整理查询结果
    if (($row = mysqli_fetch_array($retval, MYSQLI_ASSOC)) !== null) {
        // 校验$user_id记录
        if ($user_id === $row['user_id']) {
            // $user_id记录校验成功，开始校验密码
            $sql = "SELECT `user_passwd` FROM `user_tbl`
                    WHERE BINARY `user_id`='$user_id'";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('Query failed.');
            }
            $row = mysqli_fetch_array($retval, MYSQLI_ASSOC);
            if ($old_passwd === $row['user_passwd']) {
                // 密码校验通过，更新密码
                $sql = "UPDATE `user_tbl` SET `user_passwd`='$new_passwd'
                        WHERE BINARY `user_id`='$user_id'";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('Query failed.');
                }
                // 解绑用户，清空$user_id和$user_token
                $sql = "UPDATE `token_tbl` SET `user_id`='', `user_token`=''
                        WHERE BINARY `wx_openid`='$wx_openid'";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('Query failed.');
                }
                // 记录日志
                $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                        VALUES ('$user_id', '修改密码', '成功：用户已自动解绑')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('Query failed.');
                }
                // 返回错误信息
                return null;
            } else {
                // 密码校验错误，记录日志
                $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                        VALUES ('$user_id', '修改密码', '失败：原密码错误')";
                $retval = mysqli_query($conn, $sql);
                if (!$retval) {
                    die('Query failed.');
                }
                // 返回错误信息
                return '原密码错误';
            }
        } else {
            // $user_id记录校验错误，记录日志
            $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                    VALUES ('$user_id', '修改密码', '失败：用户状态异常')";
            $retval = mysqli_query($conn, $sql);
            if (!$retval) {
                die('Query failed.');
            }
            // 返回错误信息
            return '用户状态异常';
        }
    } else {
        // $user_id记录不存在，记录日志
        $sql = "INSERT INTO `log_tbl` (`user`, `location`, `comment`)
                VALUES ('$user_id', '修改密码', '失败：用户不存在')";
        $retval = mysqli_query($conn, $sql);
        if (!$retval) {
            die('Query failed.');
        }
        // 返回错误信息
        return '用户不存在';
    }
}
