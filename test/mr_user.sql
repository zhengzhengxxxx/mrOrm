
/*mrOrm测试数据库*/
SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for mr_user
-- ----------------------------
DROP TABLE IF EXISTS `mr_user`;
CREATE TABLE `mr_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_age` int(11) NOT NULL,
  `add_time` int(11) NOT NULL,
  `del_status` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
