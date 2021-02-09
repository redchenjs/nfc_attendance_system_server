--
-- Database `nas_db`
--
CREATE DATABASE nas_db;
USE nas_db;
--
-- Table structure for table `app_tbl`
--
CREATE TABLE `app_tbl` (
  `app_id` varchar(18) NOT NULL DEFAULT '',
  `app_secret` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`app_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
--
-- Table structure for table `dev_tbl`
--
CREATE TABLE `dev_tbl` (
  `device_mac` varchar(17) NOT NULL DEFAULT '',
  `device_location` varchar(32) NOT NULL DEFAULT '',
  `firmware_running` varchar(32) NOT NULL DEFAULT '',
  `firmware_required` varchar(32) NOT NULL DEFAULT '',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`device_mac`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
--
-- Table structure for table `log_tbl`
--
CREATE TABLE `log_tbl` (
  `user` varchar(32) NOT NULL DEFAULT '',
  `location` varchar(32) NOT NULL DEFAULT '',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`create_time`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
--
-- Table structure for table `user_tbl`
--
CREATE TABLE `user_tbl` (
  `user_id` varchar(10) NOT NULL DEFAULT '',
  `user_passwd` varchar(6) NOT NULL DEFAULT '',
  `comment` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
--
-- Table structure for table `token_tbl`
--
CREATE TABLE `token_tbl` (
  `wx_openid` varchar(28) NOT NULL DEFAULT '',
  `wx_code` varchar(32) NOT NULL DEFAULT '',
  `user_id` varchar(10) NOT NULL DEFAULT '',
  `user_token` varchar(32) NOT NULL DEFAULT '',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`wx_openid`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;