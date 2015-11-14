DROP TABLE IF EXISTS `torrents`;

CREATE TABLE IF NOT EXISTS `torrents` (
  `id` bigint(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` int(2) NOT NULL,
  `files` int(4) NOT NULL,
  `size` float(10,2) NOT NULL,
  `added` datetime NOT NULL,
  `sccId` bigint(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `torrents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `torrents`
  MODIFY `id` bigint(10) NOT NULL AUTO_INCREMENT;