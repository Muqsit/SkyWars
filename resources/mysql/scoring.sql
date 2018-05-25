-- #!mysql
-- #{skywars

-- #  { init
CREATE TABLE IF NOT EXISTS scores (
    player VARCHAR(15) NOT NULL PRIMARY KEY,
    score INT UNSIGNED NOT NULL
);
-- #  }

-- #  { add_score
-- #    :player string
-- #    :score int
INSERT INTO scores (player, score) VALUES(:player, :score)
ON DUPLICATE KEY UPDATE score=VALUES(score);
-- #  }
-- #}