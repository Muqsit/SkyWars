<?php

declare(strict_types=1);
namespace muqsit\skywars;

use muqsit\skywars\database\Database;
use muqsit\skywars\game\SkyWars;
use muqsit\skywars\handler\GameHandler;
use muqsit\skywars\handler\ScoreboardHandler;
use muqsit\skywars\handler\SignHandler;
use muqsit\skywars\integration\Integration;
use muqsit\skywars\utils\loot\LootTable;
use muqsit\skywars\utils\GameCreator;

use pocketmine\lang\BaseLang;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase {

    /** @var GameHandler */
    private $game_handler;

    /** @var SignHandler */
    private $sign_handler;

    /** @var ScoreboardHandler */
    private $scoreboard_handler;

    /** @var BaseLang */
    private $lang;

    /** @var Database|null */
    private $database;

    public function onEnable() : void
    {
        $integrations = Integration::init($this->getServer()->getPluginManager());
        if ($integrations > 0) {
            $this->getLogger()->info("Integrated with " . $integrations . " plugin" . ($integrations === 1 ? "" : "s") . ".");
        }

        $this->saveResources();
        $this->createDatabase();
        $this->loadLanguage();

        $this->game_handler = new GameHandler($this);
        $this->scoreboard_handler = new ScoreboardHandler($this);
        $this->loadGames();
        $this->sign_handler = new SignHandler($this);

        $this->initGames();

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
        GameCreator::setAutoCentering($this->getConfig()->get("auto-center-spawns"));
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

    public function getScoreboardHandler() : ScoreboardHandler
    {
        return $this->scoreboard_handler;
    }

    public function onDisable() : void
    {
        $this->saveGames();
        $this->getSignHandler()->save();
        $this->getScoreboardHandler()->save();

        $this->closeDatabase();
    }

    public function saveGames() : void
    {
        $handler = $this->getGameHandler();

        foreach ($handler->getAllGames() as $game) {
            $game->stop();
        }

        $handler->save();
    }

    public function closeDatabase() : void
    {
        if ($this->database !== null) {
            $this->database->close();
        }
    }
}
