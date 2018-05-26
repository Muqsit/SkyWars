<?php
namespace muqsit\skywars;

use muqsit\skywars\database\Database;
use muqsit\skywars\game\SkyWars;
use muqsit\skywars\integration\Integration;
use muqsit\skywars\utils\loot\LootTable;
use muqsit\skywars\utils\FloatingScoreboard;

use pocketmine\lang\BaseLang;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase {

    /** @var GameHandler */
    private $game_handler;

    /** @var SignHandler */
    private $sign_handler;

    /** @var BaseLang */
    private $lang;

    /** @var Database|null */
    private $database;

    /** @var FloatingScoreboard[] */
    private $scoreboards = [];

    public function onEnable() : void
    {
        $integrations = Integration::init($this->getServer()->getPluginManager());
        if ($integrations > 0) {
            $this->getLogger()->info("Integrated with " . $integrations . " plugin" . ($integrations === 1 ? "" : "s") . ".");
        }

        $this->saveResources();
        $this->createDatabase();
        $this->loadLanguage();

        $this->loadGames();
        $this->sign_handler = new SignHandler($this);

        $this->initGames();
        $this->loadScoreboards();

        $this->getServer()->getCommandMap()->register($this->getName(), new SkyWarsCommand($this));
    }

    private function saveResources() : void
    {
        $dir = $this->getDataFolder();
        foreach ([$dir, $dir . "lang/", $dir . "mysql/"] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        $this->saveResource("config.yml");
        $this->saveResource("scoreboards.yml");
        $this->saveResource("signs.yml");
        $this->saveResource("loottable.yml");

        $this->saveResource("lang/eng.ini");
        $this->saveResource("lang/" . $this->getServer()->getLanguage()->getName() . ".ini");

        $this->saveResource("mysql/scoring.sql");

        LootTable::load(yaml_parse_file($this->getDataFolder() . "loottable.yml"));
    }

    private function createDatabase() : void
    {
        $scoring = $this->getConfig()->get("scoring");
        if ($scoring["enable"]) {
            $this->database = Database::fromConfig($this, $scoring["database"]);
        }
    }

    private function loadLanguage() : void
    {
        $lang = $this->getServer()->getLanguage()->getLang();
        $this->lang = new BaseLang($lang, $this->getDataFolder() . "lang/");
    }

    private function loadScoreboards() : void
    {
        if ($this->getConfig()->get("scoring")["enable"]) {
            $server = $this->getServer();
            $database = $this->getDatabase();

            foreach (yaml_parse_file($this->getDataFolder() . "scoreboards.yml") as $pos) {
                $position = new Position($pos["x"], $pos["y"], $pos["z"], $server->getLevelByName($pos["level"]));
                $this->scoreboards[] = new FloatingScoreboard($position, $database);
            }
        }
    }

    public function addScoreboard(Position $pos) : bool
    {
        if ($this->getConfig()->get("scoring")["enable"]) {
            $this->scoreboards[] = new FloatingScoreboard($pos->asPosition(), $this->getDatabase());
            return true;
        }

        return false;
    }

    private function loadGames() : void
    {
        $this->game_handler = new GameHandler($this);
    }

    private function initGames() : void
    {
        $this->getGameHandler()->init($this);
        SkyWars::init($this);
    }

    public function getLanguage() : BaseLang
    {
        return $this->lang;
    }

    public function getDatabase() : ?Database
    {
        return $this->database;
    }

    public function getGameHandler() : GameHandler
    {
        return $this->game_handler;
    }

    public function getSignHandler() : SignHandler
    {
        return $this->sign_handler;
    }

    public function onDisable() : void
    {
        $this->saveGames();
        $this->getSignHandler()->save();
        $this->closeDatabase();
        $this->saveScoreboards();
    }

    public function saveGames() : void
    {
        $handler = $this->getGameHandler();

        foreach ($handler->getAllGames() as $game) {
            $game->stop();
        }

        $handler->save();
    }

    private function saveScoreboards() : void
    {
        if ($this->getConfig()->get("scoring")["enable"]) {
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

            yaml_emit_file($this->getDataFolder() . "scoreboards.yml", $scoreboards);
        }
    }

    public function closeDatabase() : void
    {
        if ($this->database !== null) {
            $this->database->close();
        }
    }
}
