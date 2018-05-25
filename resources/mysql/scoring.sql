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
ON DUPLICATE KEY UPDATE score=score+VALUES(score);
-- #  }

-- #  { fetch_score
-- #    :player string
SELECT score FROM scores WHERE player=:player;
-- #  }

-- # { fetch_top_scores
-- #    :limit int
SELECT player, score FROM scores ORDER by score DESC LIMIT :limit;
-- # }

-- #}