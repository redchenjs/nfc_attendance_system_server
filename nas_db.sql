CREATE DATABASE nas_db;
USE nas_db;

--
-- Table structure for table `device_tbl`
--

CREATE TABLE `device_tbl` (
  `device_mac` varchar(100) NOT NULL DEFAULT 'null',
  `device_location` varchar(100) NOT NULL DEFAULT 'null',
  `running_version` varchar(100) DEFAULT NULL,
  `required_version` varchar(100) DEFAULT NULL,
  `create_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`device_mac`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `log_tbl`
--

CREATE TABLE `log_tbl` (
  `user_id` varchar(100) NOT NULL DEFAULT 'null',
  `device_location` varchar(100) NOT NULL DEFAULT 'null',
  `comment` varchar(100) NOT NULL DEFAULT '',
  `create_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `secure_tbl`
--

CREATE TABLE `secure_tbl` (
  `app_id` varchar(100) NOT NULL DEFAULT 'null',
  `app_secret` varchar(100) NOT NULL DEFAULT 'null',
  PRIMARY KEY (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `user_tbl`
--

CREATE TABLE `user_tbl` (
  `user_id` varchar(10) NOT NULL DEFAULT 'null',
  `user_passwd` varchar(6) NOT NULL DEFAULT 'null',
  `comment` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `wechat_tbl`
--

CREATE TABLE `wechat_tbl` (
  `user_id` varchar(10) NOT NULL DEFAULT 'null',
  `wx_code` varchar(100) NOT NULL DEFAULT 'null',
  `wx_openid` varchar(100) NOT NULL DEFAULT 'null',
  `user_token` varchar(100) NOT NULL DEFAULT 'null',
  `create_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`wx_openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
