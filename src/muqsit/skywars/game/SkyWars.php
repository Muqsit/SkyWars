<?php
namespace muqsit\skywars\game;

use muqsit\skywars\GameHandler;
use muqsit\skywars\game\tasks\CountdownTask;
use muqsit\skywars\game\tasks\GameTask;
use muqsit\skywars\game\tasks\RuntimeTask;
use muqsit\skywars\integration\Integration;
use muqsit\skywars\Loader;
use muqsit\skywars\utils\ChunkBackup;
use muqsit\skywars\utils\PlayerState;
use muqsit\skywars\utils\TextUtils;

use pocketmine\lang\BaseLang;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;

class SkyWars {

    //Game states in ascending order
    public const STATE_AWAITING_PLAYERS = 0;
    public const STATE_COUNTDOWN = 1;
    public const STATE_NO_PVP = 2;
    public const STATE_ONGOING = 3;

    //Types of player groups
    public const TYPE_ALL_PLAYERS = 0;
    public const TYPE_QUALIFIED_PLAYERS = 1;
    public const TYPE_DISQUALIFIED_PLAYERS = 2;

    /** @var string[] */
    protected static $center_aligned = [];

    /** @var BaseLang */
    protected static $lang;

    /** @var GameHandler */
    protected static $handler;

    /** @var SignHandler */
    protected static $sign_handler;

    /** @var int[]|null */
    protected static $scoring;

    /** @var Database */
    protected static $database;

    public static function init(Loader $plugin) : void
    {
        SkyWars::$center_aligned = array_flip($plugin->getConfig()->get("center-aligned-messages"));
        SkyWars::$handler = $plugin->getGameHandler();
        SkyWars::$lang = $plugin->getLanguage();
        SkyWars::$sign_handler = $plugin->getSignHandler();

        $scoring = $plugin->getConfig()->get("scoring");
        if ($scoring["enable"]) {
            SkyWars::$scoring = [
                "win-score" => $scoring["win-score"],
                "kill-score" => $scoring["kill-score"],
                "death-score" => $scoring["death-score"]
            ];

            SkyWars::$database = $plugin->getDatabase();
        }

        $plugin->getServer()->getPluginManager()->registerEvents(new SkyWarsListener($plugin), $plugin);
    }

    public static function translate(string $message, ...$args) : string
    {
        $result = SkyWars::$lang->translateString($message, $args);
        if (isset(SkyWars::$center_aligned[$message])) {
            $result = TextUtils::centerLine($result);
        }

        return $result;
    }

    public static function fromConfig(array $args) : SkyWars
    {
        [
            "name" => $name,
            "level" => $level,
            "spawns" => $spawns,
            "vertex1" => $vertex1,
            "vertex2" => $vertex2
        ] = $args;

        foreach ($spawns as &$spawn) {
            $spawn = new Vector3($spawn["x"], $spawn["y"], $spawn["z"]);
        }

        $instance = new SkyWars(
            $level,
            $name,
            new Vector3($vertex1["x"], $vertex1["y"], $vertex1["z"]),
            new Vector3($vertex2["x"], $vertex2["y"], $vertex2["z"]),
            ...$spawns
        );

        if (isset($args["min_players"])) {
            $instance->setMinPlayers($args["min_players"]);
        }

        if (isset($args["countdown"])) {
            $instance->setCountdown($args["countdown"]);
        }

        if (isset($args["pvp_disable_duration"])) {
            $instance->setPvPDisableDuration($args["pvp_disable_duration"]);
        }

        if (isset($args["monetary_reward"])) {
            $instance->setMonetaryReward($args["monetary_reward"]);
        }

        return $instance;
    }

    /** @var string */
    protected $level_name;

    /** @var Level */
    protected $level;

    /** @var string */
    protected $name;

    /** @var Vector3 */
    protected $vertex1;

    /** @var Vector3 */
    protected $vertex2;

    /** @var Player[] */
    protected $players = [];

    /** @var int[] */
    protected $disqualified = [];

    /** @var Vector3[] */
    protected $spawns = [];

    /** @var Vector3[] */
    protected $vacant_spawns;

    /** @var int[] */
    protected $player_spawn_index = [];

    /** @var int */
    protected $state;

    /** @var int */
    protected $min_players = 2;

    /** @var int */
    protected $countdown = 30;

    /** @var int */
    protected $no_pvp_timer = 30;

    /** @var int */
    protected $monetary_reward = 0;

    /** @var int[] */
    protected $taskIds = [];

    /** @var ChunkBackup */
    private $chunk_backup;

    public function __construct(string $level_name, string $name, Vector3 $vertex1, Vector3 $vertex2, Vector3 ...$spawns)
    {
        $this->level_name = $level_name;
        $this->name = $name;

        $this->vertex1 = $vertex1;
        $this->vertex2 = $vertex2;

        if (empty($spawns)) {
            throw new \Error("Could not find any spawn points for game $name");
        }

        foreach ($spawns as $spawn) {
            $this->spawns[] = $spawn->asVector3();
        }

        $this->reset();
    }

    public function createChunkBackup() : void
    {
        $chunk_backup = new ChunkBackup($this->level);

        $x = [$this->vertex1->x >> 4, $this->vertex2->x >> 4];
        $minX = min($x);
        $maxX = max($x);

        $z = [$this->vertex1->z >> 4, $this->vertex2->z >> 4];
        $minZ = min($z);
        $maxZ = max($z);

        for ($x = $minX; $x <= $maxX; ++$x) {
            for ($z = $minZ; $z <= $maxZ; ++$z) {
                $chunk_backup->addChunk($x, $z);
            }
        }

        $chunk_backup->store();
        $this->chunk_backup = $chunk_backup;
    }

    public function restoreChunks() : void
    {
        $this->chunk_backup->removeEntities();
        $this->chunk_backup->restore();
    }

    public function setMinPlayers(int $value) : void
    {
        if ($value < 2) {
            throw new \Error("Minimum number of players cannot be lesser than 2.");
        }

        if ($value > count($this->spawns)) {
            throw new \Error("Minimum number of players cannot be greater than the number of configured spawns.");
        }

        $this->min_players = $value;
    }

    public function setCountdown(int $value) : void
    {
        if ($value < 0) {
            throw new \Error("Countdown value cannot be negative.");
        }

        $this->countdown = $value;
    }

    public function setPvPDisableDuration(int $value) : void
    {
        if ($value < 0) {
            throw new \Error("PvP disable duration cannot be negative.");
        }

        $this->no_pvp_timer = $value;
    }

    public function setMonetaryReward(int $value) : void
    {
        if ($value < 0) {
            throw new \Error("Monetary reward cannot be negative.");
        }

        $this->monetary_reward = $value;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getSpawns() : array
    {
        return $this->spawns;
    }

    public function getVacantSpawn(&$index = null) : ?Vector3
    {
        return $this->vacant_spawns[$index = array_rand($this->vacant_spawns)];
    }

    public function getPlayers(int $targets = SkyWars::TYPE_ALL_PLAYERS) : array
    {
        switch ($targets) {
            case SkyWars::TYPE_ALL_PLAYERS:
                return $this->players;
            case SkyWars::TYPE_QUALIFIED_PLAYERS:
                return array_diff_key($this->players, $this->disqualified);
            case SkyWars::TYPE_DISQUALIFIED_PLAYERS:
                return array_intersect_key($this->players, $this->disqualified);
        }

        throw new \InvalidArgumentException("Invalid type $type given.");
    }

    public function isDisqualified(Player $player) : bool
    {
        return isset($this->disqualified[$player->getRawUniqueId()]);
    }

    public function isJoinable() : bool
    {
        return $this->state <= SkyWars::STATE_COUNTDOWN && !empty($this->vacant_spawns);
    }

    public function reset() : void
    {
        if ($this->level === null) {
            $server = Server::getInstance();
            $level = $server->getLevelByName($this->level_name);
            if ($level === null) {
                throw new \Error("No level with the name " . $this->level_name . " could be found.");
            }

            $this->level = $level;
            $this->createChunkBackup();
        } else {
            $this->restoreChunks();
        }

        $this->setState(SkyWars::STATE_AWAITING_PLAYERS);

        if (!empty($this->players)) {
            foreach ($this->players as $player) {
                $this->remove($player, true);
            }
        }

        $this->vacant_spawns = $this->spawns;
    }

    public function add(Player $player) : bool
    {
        if (isset($this->players[$rawUUID = $player->getRawUniqueId()])) {
            throw new \Error("Attempted to add a player to the same game twice.");
        }

        if (!$this->isJoinable()) {
            return false;
        }

        if (!$this->handleJoin($player)) {
            return false;
        }

        $this->players[$rawUUID] = $player;
        $this->storePlayerState($player);

        $player->teleport($this->getVacantSpawn($index));
        $this->player_spawn_index[$rawUUID] = $index;
        unset($this->vacant_spawns[$index]);

        $player->setImmobile(true);
        $player->setGamemode(Player::SURVIVAL);

        $this->updateGameState();
        static::$handler->setPlayerGame($player, $this);

        SkyWars::$sign_handler->updateSigns($this);
        return true;
    }

    public function disqualify(Player $player, ?Player $disqualifier = null) : bool
    {
        if (isset($this->disqualified[$rawUUID = $player->getRawUniqueId()])) {
            throw new \Error("Attempted to disqualify a player twice.");
        }

        $this->handleDisqualify($player);

        $death_score = null;
        $kill_score = null;

        if (SkyWars::$scoring !== null) {
            SkyWars::$database->addScore($player, $death_score = SkyWars::$scoring["death-score"]);
            if ($disqualifier !== null) {
                SkyWars::$database->addScore($disqualifier, $kill_score = SkyWars::$scoring["kill-score"]);
            }
        }

        if ($disqualifier !== null) {
            $player->sendMessage(SkyWars::translate("death.by.player.message", $disqualifier->getName(), $death_score));
            $disqualifier->sendMessage(SkyWars::translate("kill.message", $player->getName(), $kill_score));
        } else {
            $player->sendMessage(SkyWars::translate("death.by.other.message", $death_score));
        }

        $this->disqualified[$rawUUID] = 0;
        $player->setGamemode(Player::SPECTATOR);

        $this->updateGameState();
        return true;
    }

    public function remove(Player $player, bool $game_ended = false) : void
    {
        if (!isset($this->players[$rawUUID = $player->getRawUniqueId()])) {
            throw new \Error("Attempted to remove a player that didn't join the game.");
        }

        $this->handleLeave($player);

        $player->setImmobile(false);

        unset($this->players[$rawUUID], $this->disqualified[$rawUUID]);
        $this->retrievePlayerState($player);

        $index = $this->player_spawn_index[$rawUUID];
        $this->vacant_spawns[$index] = $this->spawns[$index];
        unset($this->player_spawn_index[$rawUUID]);

        $this->updateGameState();
        static::$handler->setPlayerGame($player, null, $game_ended);

        SkyWars::$sign_handler->updateSigns($this);
    }

    public function canModifyTerrain(Player $player) : bool
    {
        return $this->state > SkyWars::STATE_COUNTDOWN && !$this->isDisqualified($player);
    }

    public function broadcastMessage(string $message, int $targets = SkyWars::TYPE_ALL_PLAYERS) : void
    {
        $players = $this->getPlayers($targets);
        if (!empty($players)) {
            $this->level->getServer()->broadcastMessage($message, $players);
        }
    }

    public function isPvPEnabled() : bool
    {
        return $this->state === SkyWars::STATE_ONGOING;
    }

    public function broadcastTip(string $tip, int $targets = SkyWars::TYPE_ALL_PLAYERS) : void
    {
        $players = $this->getPlayers($targets);
        if (!empty($players)) {
            $this->level->getServer()->broadcastTip($tip, $players);
        }
    }

    protected function storePlayerState(Player $player) : void
    {
        $this->player_states[$player->getRawUniqueId()] = new PlayerState($player);
    }

    protected function retrievePlayerState(Player $player) : void
    {
        $state = $this->player_states[$rawUUID = $player->getRawUniqueId()];
        $state->reset($player);
        unset($this->player_states[$rawUUID]);
    }

    protected function updateGameState() : void
    {
        switch ($this->state) {
            case SkyWars::STATE_AWAITING_PLAYERS:
                if (count($this->players) >= $this->min_players) {
                    $this->setState(SkyWars::STATE_COUNTDOWN);
                }
                break;
            case SkyWars::STATE_COUNTDOWN:
                if (count($this->players) < $this->min_players) {
                    $this->setState(SkyWars::STATE_AWAITING_PLAYERS);
                }
                break;
            case SkyWars::STATE_ONGOING:
                $players = $this->getPlayers(SkyWars::TYPE_QUALIFIED_PLAYERS);
                if (empty($players)) {
                    $this->reset();
                } elseif (count($players) === 1) {
                    $winner = array_pop($players);
                    $this->handleWin($winner);
                    $this->broadcastMessage(SkyWars::translate("broadcast.winner", $winner->getName(), $this->getName()));
                    $this->reset();

                    if ($this->monetary_reward > 0) {
                        $economy = Integration::getEconomy();
                        if ($economy !== null) {
                            $economy->addMoney($winner, $this->monetary_reward);
                        }
                    }

                    $score = null;
                    if (SkyWars::$scoring !== null) {
                        SkyWars::$database->addScore($winner, $score = SkyWars::$scoring["win-score"]);
                    }

                    $winner->sendMessage(SkyWars::translate("win.message", $this->getName(), $score));
                }
                break;
        }
    }

    protected function setState(int $state) : void
    {
        if ($this->state === $state) {
            return;
        }

        switch ($this->state = $state) {
            case SkyWars::STATE_AWAITING_PLAYERS:
                $this->cancelAllTasks();
                break;
            case SkyWars::STATE_COUNTDOWN:
                $task = new CountdownTask($this);
                $task->setCountdown($this->countdown);
                $this->scheduleTask($task, SkyWarsTasks::TYPE_COUNTDOWN);
                break;
            case SkyWars::STATE_NO_PVP:
                $this->cancelTask(SkyWarsTasks::TYPE_COUNTDOWN);
                $task = new RuntimeTask($this);
                $this->scheduleTask($task, SkyWarsTasks::TYPE_RUNTIME);

                foreach ($this->getPlayers() as $player) {
                    $player->setImmobile(false);
                }
                break;
        }
    }

    protected function scheduleTask(GameTask $task, int $identifier, int $tickrate = 20) : void
    {
        $scheduler = $this->level->getServer()->getScheduler();
        if (isset($this->taskIds[$identifier])) {
            $scheduler->cancelTask($this->taskIds[$identifier]);
        }

        $scheduler->scheduleRepeatingTask($task, $tickrate);
        $this->taskIds[$identifier] = $task->getTaskId();
    }

    public function cancelTask(int $identifier) : void
    {
        if (isset($this->taskIds[$identifier])) {
            $this->level->getServer()->getScheduler()->cancelTask($this->taskIds[$identifier]);
            unset($this->taskIds[$identifier]);
        }
    }

    public function cancelAllTasks() : void
    {
        foreach (array_keys($this->taskIds) as $identifier) {
            $this->cancelTask($identifier);
        }

        $this->taskIds = [];
    }

    public function onCountdown(int $seconds) : void
    {
        if ($seconds <= 5 || $seconds % 10 === 0) {
            $this->broadcastMessage(SkyWars::translate("countdown.message", $seconds));
        }
    }

    public function onRun(int $tick) : void
    {
        if ($this->state === SkyWars::STATE_NO_PVP) {
            if (--$this->no_pvp_timer > 0) {
                $this->broadcastTip(SkyWars::translate("nopvp.countdown.message", $this->no_pvp_timer));
            } else {
                $this->setState(SkyWars::STATE_ONGOING);
            }
        }
    }

    public function start() : void
    {
        $this->setState(SkyWars::STATE_NO_PVP);
    }

    public function stop() : void
    {
        $this->reset();
    }

    public function handleJoin(Player $player) : bool
    {
        //TODO: eventing
        return true;
    }

    public function handleDisqualify(Player $player) : void
    {
        //TODO: eventing
    }

    public function handleLeave(Player $player) : void
    {
        //TODO: eventing
    }

    public function handleWin(Player $player) : void
    {
        //TODO: eventing
    }

    public function toConfig() : array
    {
        return [
            "name" => $this->name,
            "level" => $this->level_name,
            "vertex1" => [
                "x" => $this->vertex1->x,
                "y" => $this->vertex1->y,
                "z" => $this->vertex1->z
            ],
            "vertex2" => [
                "x" => $this->vertex2->x,
                "y" => $this->vertex2->y,
                "z" => $this->vertex2->z
            ],
            "spawns" => array_map(function(Vector3 $pos) : array {
                return [
                    "x" => $pos->x,
                    "y" => $pos->y,
                    "z" => $pos->z
                ];
            }, $this->spawns),
            "min_players" => $this->min_players,
            "countdown" => $this->countdown,
            "pvp_disable_duration" => $this->no_pvp_timer,
            "monetary_reward" => $this->monetary_reward
        ];
    }
}
