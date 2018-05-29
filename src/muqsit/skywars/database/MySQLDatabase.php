<?php

declare(strict_types=1);
namespace muqsit\skywars\database;

use muqsit\skywars\database\tasks\JSONScoreAddTask;
use muqsit\skywars\Loader;

use pocketmine\Player;
use pocketmine\Server;

use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;

class MySQLDatabase extends Database {

    public const TYPE = Database::MYSQL;

    public const INIT_QUERY = "skywars.init";
    public const ADD_SCORE_QUERY = "skywars.add_score";
    public const FETCH_SCORE_QUERY = "skywars.fetch_score";
    public const FETCH_TOP_SCORES_QUERY = "skywars.fetch_top_scores";

    /** @var libasynql */
    private $database;

    public function __construct(Loader $plugin, array $options)
    {
        $this->database = libasynql::create($plugin, $options, [
            "mysql" => "mysql/scoring.sql"
        ]);

        $this->database->executeGeneric(MySQLDatabase::INIT_QUERY);
        parent::__construct();
    }

    public function initializeScoreboard() : void
    {
        $database = $this;

        $this->database->executeSelect(MySQLDatabase::FETCH_TOP_SCORES_QUERY, [
            "limit" => Database::$scoreboard_display_limit
        ], function(array $rows) use ($database) : void {
            foreach ($rows as ["player" => $player, "score" => $score]) {
                $database->onScoreChange($player, $score);
            }
        });
    }

    public function addScore(Player $player, int $score) : void
    {
        $player = $player->getName();

        $this->database->executeChange(MySQLDatabase::ADD_SCORE_QUERY, [
            "player" => $player,
            "score" => $score
        ]);

        $database = $this;

        $this->database->executeSelect(MySQLDatabase::FETCH_SCORE_QUERY, [
            "player" => $player
        ], function(array $rows) use ($player, $database) : void {
            foreach ($rows as ["score" => $score]) {
                $database->onScoreChange($player, $score);
            }
        });
    }

    public function close() : void
    {
        $this->database->close();
    }
}