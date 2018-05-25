<?php
namespace muqsit\skywars\database;

use muqsit\skywars\Loader;

use pocketmine\Player;

abstract class Database {

    public const JSON = 0;
    public const MYSQL = 1;

    public const TYPE = -1;

    public static function fromConfig(Loader $plugin, array $config) : ?Database
    {
        switch (strtolower($config["type"])) {
            case "json":
                return new JSONDatabase($plugin->getDataFolder() . $config["json"]);
            case "mysql":
                return new MySQLDatabase($plugin, $config);
        }

        return null;
    }

    /**
     * Adds score to player.
     *
     * @param Player $player
     * @param int $score
     */
    abstract public function addScore(Player $player, int $score) : void;

    /**
     * Called when the plugin disables so
     * that databases like SQL and MYSQL can
     * close their instances.
     */
    public function close() : void
    {
    }
}