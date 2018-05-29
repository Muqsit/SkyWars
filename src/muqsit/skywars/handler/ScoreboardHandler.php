<?php

declare(strict_types=1);
namespace muqsit\skywars\handler;

use muqsit\skywars\database\Database;
use muqsit\skywars\game\SkyWars;
use muqsit\skywars\Loader;
use muqsit\skywars\utils\FloatingScoreboard;

use pocketmine\level\Position;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ScoreboardHandler {

    /** @var Database */
    private $database;

    /** @var string */
    private $path;

    /** @var FloatingScoreboard[] */
    private $scoreboards = [];

    /** @var array */
    private $config;

    public function __construct(Loader $plugin)
    {
        $this->path = $plugin->getDataFolder() . "scoreboards.yml";
        $this->config = $plugin->getConfig()->get("scoring");
        $this->database = $plugin->getDatabase();

        $this->load($plugin->getServer());
    }

    private function load(Server $server) : void
    {
        if ($this->config["enable"]) {
            Database::setScoreboardDisplayLimit($this->config["scoreboard"]["display-limit"]);

            [
                "title" => $title,
                "line" => $line
            ] = $this->config["scoreboard"]["display-format"];

            FloatingScoreboard::setTitle($title);
            FloatingScoreboard::setLineFormat($line);

            foreach (yaml_parse_file($this->path) as $pos) {
                $position = new Position($pos["x"], $pos["y"], $pos["z"], $server->getLevelByName($pos["level"]));
                $this->scoreboards[] = new FloatingScoreboard($position, $this->database);
            }
        }
    }

    public function add(Position $pos) : bool
    {
        if ($this->config["enable"]) {
            $this->scoreboards[] = new FloatingScoreboard($pos->asPosition(), $this->database);
            return true;
        }

        return false;
    }

    public function save() : void
    {
        if ($this->config["enable"]) {
            $scoreboards = [];
            foreach ($this->scoreboards as $scoreboard) {
                $scoreboard->close();
                $scoreboards[] = [
                    "x" => $scoreboard->x,
                    "y" => $scoreboard->y,
                    "z" => $scoreboard->z,
                    "level" => $scoreboard->getLevel()->getFolderName()
                ];
            }

            yaml_emit_file($this->path, $scoreboards);
        }
    }
}
