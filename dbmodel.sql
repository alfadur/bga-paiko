
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Paiko implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

CREATE TABLE `tiles`(
    `tile_id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` TINYINT UNSIGNED NOT NULL,
    `state` TINYINT UNSIGNED NOT NULL,
    `player_id` INTEGER UNSIGNED NOT NULL,
    PRIMARY KEY (`tile_id`),
    FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE `board`(
    `x` TINYINT UNSIGNED NOT NULL,
    `y` TINYINT UNSIGNED NOT NULL,
    `tile_id` TINYINT UNSIGNED NULL,
    PRIMARY KEY(`x`, `y`),
    FOREIGN KEY(`tile_id`) REFERENCES `tiles`(`tile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
