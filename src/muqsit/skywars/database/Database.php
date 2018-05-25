<?php
namespace muqsit\skywars\database;

use muqsit\skywars\Loader;

use pocketmine\Player;

abstract class Database {

    public const JSON = 0;
    public const MYSQL = 1;

    public const TYPE = -1;

    /** @var int[] */
    private $scoreboard = [];

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

    public function __construct()
    {
        $this->initializeScoreboard();
    }

    protected function addToScoreboard(string $player, int $score) : void
    {
        if (isset($this->scoreboard[$player]) || count($this->scoreboard) < 10) {
            $this->scoreboard[$player] = $score;
        } elseif ($score > ($min = min($this->scoreboard))) {
            foreach ($this->scoreboard as $key => $score) {
                if ($score === $min) {
                    $this->scoreboard[$player] = $score;
                    unset($this->scoreboard[$key]);
                    break;
                }
            }
        }
    }

    /**
     * Gets the top 10 scores from the database.
     *
     * @return array
     */
    public function getScoreboard() : array
    {
        return $this->scoreboard;
    }

    /**
     * Called so databases can initialize themselves after
     * construction.
     */
    abstract public function initializeScoreboard() : void;

    /**
     * Adds score to player.
     *
     * @param Player $player
     * @param int $score
     */
    abstract public function addScore(Player $player, int $score) : void;

    /**
     * Called AFTER the score in the database
     * has been updated.
     *
     * @param string $player
     * @param int $score
     */
    public function onScoreChange(string $player, int $score) : void
    {
        $this->addToScoreboard($player, $score);
    }

    /**
     * Called when the plugin disables so
     * that databases like SQL and MYSQL can
     * close their instances.
     */
    public function close() : void
    {
    }
}