--
-- Database `nas_db`
--
CREATE DATABASE nas_db;
USE nas_db;
--
-- Table structure for table `device_tbl`
--
CREATE TABLE `device_tbl` (
  `device_mac` varchar(100) NOT NULL DEFAULT '',
  `device_location` varchar(100) NOT NULL DEFAULT '',
  `running_version` varchar(100) DEFAULT NULL,
  `required_version` varchar(100) DEFAULT NULL,
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`device_mac`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
--
-- Table structure for table `log_tbl`
--
CREATE TABLE `log_tbl` (
  `user_id` varchar(100) NOT NULL DEFAULT '',
  `device_location` varchar(100) NOT NULL DEFAULT '',
  `comment` varchar(100) DEFAULT NULL,
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`create_time`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
--
-- Table structure for table `secure_tbl`
--
CREATE TABLE `secure_tbl` (
  `app_id` varchar(100) NOT NULL DEFAULT '',
  `app_secret` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`app_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
--
-- Table structure for table `user_tbl`
--
CREATE TABLE `user_tbl` (
  `user_id` varchar(10) NOT NULL DEFAULT '',
  `user_passwd` varchar(6) NOT NULL DEFAULT '',
  `comment` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
--
-- Table structure for table `wechat_tbl`
--
CREATE TABLE `wechat_tbl` (
  `user_id` varchar(10) NOT NULL DEFAULT '',
  `wx_code` varchar(100) NOT NULL DEFAULT '',
  `wx_openid` varchar(100) NOT NULL DEFAULT '',
  `user_token` varchar(100) NOT NULL DEFAULT '',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `wx_openid`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;