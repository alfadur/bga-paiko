
-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- Paiko implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

CREATE TABLE IF NOT EXISTS `piece` (
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` INTEGER UNSIGNED NOT NULL,
    `type` TINYINT UNSIGNED NOT NULL,
    `x` TINYINT NULL,
    `y` TINYINT NULL,
    `angle` TINYINT NOT NULL DEFAULT  0,
    `state` TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`x`, `y`),
    FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1;
