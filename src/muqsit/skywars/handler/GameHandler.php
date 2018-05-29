<?php

declare(strict_types=1);
namespace muqsit\skywars\handler;

use muqsit\skywars\game\SkyWars;
use muqsit\skywars\Loader;
use muqsit\skywars\utils\GameCreator;

use pocketmine\level\Level;
use pocketmine\Player;

class GameHandler {

    /** @var SkyWars[] */
    private $games = [];

    /** @var string[] */
    private $player_games = [];

    /** @var GameCreator[] */
    private $game_creators = [];

    /** @var array */
    private $cache = [];//this stores the games added/deleted during runtime

    /** @var string */
    private $config_path;

    /** @var SignHandler */
    private $sign_handler;

    /** @var bool */
    private $auto_rejoin;

    public function init(Loader $plugin) : void
    {
        $this->auto_rejoin = $plugin->getConfig()->get("auto-rejoin-games");
        $this->sign_handler = $plugin->getSignHandler();
        $this->config_path = $plugin->getDataFolder() . "games.yml";

        if (file_exists($this->config_path)) {
            foreach (yaml_parse_file($this->config_path) as $args) {
                $this->add(SkyWars::fromConfig($args), false);
            }
        }
    }

    public function create(string $name, Level $level, &$error = null) : ?GameCreator
    {
        if ($this->getCreating($name) !== null) {
            $error = "A game with the name '" . $name . "' is already being created.";
            return null;
        }

        if ($this->get($name) !== null) {
            $error = "A game with the name '" . $name . "' already exists.";
            return null;
        }

        return $this->game_creators[strtolower($name)] = new GameCreator($this, $level, $name);
    }

    public function getCreating(string $name) : ?GameCreator
    {
        return $this->game_creators[strtolower($name)] ?? null;
    }

    public function removeCreating(string $name) : void
    {
        unset($this->game_creators[strtolower($name)]);
    }

    public function publishCreating(GameCreator $creator, &$error) : bool
    {
        if (!$creator->valid()) {
            $error = "The configuration for this game is unfinished and cannot be published. Please add more spawnpoints.";
            return false;
        }

        $this->removeCreating($creator->getName());
        $this->add($creator->toGame());
        return true;
    }

    public function add(SkyWars $game, bool $is_new = true) : bool
    {
        $game_name = strtolower($game->getName());
        if (isset($this->games[$game_name]) || isset($this->game_creators[$game_name])) {
            return false;
        }

        $this->games[$game_name] = $game;
        if ($is_new) {
            $this->cache[$game_name] = $game->toConfig();
        }
        return true;
    }

    public function remove(string $game) : bool
    {
        if (isset($this->games[$game = strtolower($game)])) {
            $this->games[$game]->stop();
            unset($this->games[$game]);
            $this->cache[$game] = null;
            $this->sign_handler->removeGameSigns($game);
            return true;
        }

        return false;
    }

    public function save(&$count = null) : void
    {
        $count = count($this->cache);
        if ($count === 0) {
            return;
        }

        $config = file_exists($this->config_path) ? yaml_parse_file($this->config_path) : [];

        foreach ($this->cache as $name => $configuration) {
            unset($this->cache[$name]);
            if ($configuration !== null) {
                $config[$name] = $configuration;
            }
        }

        yaml_emit_file($this->config_path, $config);
    }

    public function get(string $game) : ?SkyWars
    {
        return $this->games[strtolower($game)] ?? null;
    }

    public function getAllGames() : array
    {
        return $this->games;
    }

    public function getGameByPlayer(Player $player) : ?SkyWars
    {
        return $this->get($this->player_games[$player->getId()] ?? "");
    }

    public function setPlayerGame(Player $player, ?SkyWars $game, bool $game_ended = false) : void
    {
        if ($game === null) {
            if (isset($this->player_games[$pid = $player->getId()])) {
                $game = $this->player_games[$pid];
                unset($this->player_games[$pid]);
                if ($game_ended && $this->auto_rejoin) {
                    $this->get($game)->add($player);
                }
            }
        } else {
            $previous_game = $this->getGameByPlayer($player);
            if ($previous_game !== null) {
                $previous_game->remove($player);
            }

            $this->player_games[$player->getId()] = strtolower($game->getName());
        }
    }
}
