<?php
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

    /** @var libasynql */
    private $database;

    public function __construct(Loader $plugin, array $options)
    {
        $this->database = libasynql::create($plugin, $options, [
            "mysql" => "mysql/scoring.sql"
        ]);

        $this->database->executeGeneric(MySQLDatabase::INIT_QUERY);
    }

    public function addScore(Player $player, int $score) : void
    {
        $this->database->executeChange(MySQLDatabase::ADD_SCORE_QUERY, [
            "player" => $player->getName(),
            "score" => $score
        ]);
    }

    public function close() : void
    {
        $this->database->close();
    }
}