这是一套PHP的ORM框架

mr意思:mysql and redis

此框架作用：
1.mysql与redis同步读写
2.redis被动缓存拉起
3.使用分片拉起缓存，在加载大列表时无性能问题
4.支持多组redis+读写分离操作
5.使用多组redis时，内置平均分配算法（默认）和一致性哈希算法,也可以自己实现接口去完成自己特定的算法

示例在test/run.php,配置好redis和mysql即可运行看到效果
