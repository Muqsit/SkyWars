<?php
namespace muqsit\skywars\utils;

use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\SetEntityDataPacket;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\UUID;

class FloatingScoreboard extends Position {

    /** @var Player[] */
    private $hasSpawned = [];

    /** @var int */
    private $entityId;

    /** @var UUID */
    private $uuid;

    /** @var AddPlayerPacket */
    private $spawn_packet;

    /** @var PlayerSkinPacket */
    private $skin_packet;

    /** @var SetEntityDataPacket */
    private $metadata_packet;

    /** @var RemoveEntityPacket */
    private $despawn_packet;

    public function __construct(Position $pos)
    {
        parent::__construct($pos->x, $pos->y, $pos->z, $pos->level);

        $pk = new AddPlayerPacket();
        $pk->position = $pos->asVector3();
        $pk->entityRuntimeId = $this->entityId = Entity::$entityCount++;
        $pk->item = Item::get(Item::AIR, 0, 0);
        $pk->username = "Loading scoreboard...";
        $pk->uuid = $this->uuid = UUID::fromRandom();
        $pk->metadata[Entity::DATA_BOUNDING_BOX_WIDTH] = [Entity::DATA_TYPE_FLOAT, 0.00];
        $pk->metadata[Entity::DATA_BOUNDING_BOX_HEIGHT] = [Entity::DATA_TYPE_FLOAT, 0.00];
        $pk->metadata[Entity::DATA_SCALE] = [Entity::DATA_TYPE_FLOAT, 0.01];
        $this->spawn_packet = $pk;

        $pk = new PlayerSkinPacket();
        $pk->uuid = $this->uuid;
        $pk->skin = new Skin("Standard_Custom", str_repeat("\x00", 8192));
        $this->skin_packet = $pk;

        $pk = new RemoveEntityPacket();
        $pk->entityUniqueId = $this->entityId;
        $this->despawn_packet = $pk;

        $this->scheduleUpdate();
    }

    public function scheduleUpdate(int $tick_interval = 100) : void
    {
        $this->getLevel()->getServer()->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {

            /** @var FloatingScoreboard */
            private $scoreboard;

            public function __construct(FloatingScoreboard $scoreboard)
            {
                $this->scoreboard = $scoreboard;
            }

            public function onRun(int $tick) : void
            {
                if (!$this->scoreboard->onUpdate($tick)) {
                    $this->scoreboard->despawn();
                    Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                }
            }
        }, $tick_interval);
    }

    private function handleSpawnings() : void
    {
        $players = [];

        foreach ($this->level->getChunkPlayers($this->x >> 4, $this->z >> 4) as $player) {
            if (!isset($this->hasSpawned[$uuid = $player->getRawUniqueId()])) {
                $this->hasSpawned[$uuid] = $player;
                $player->dataPacket($this->spawn_packet);
                $player->dataPacket($this->skin_packet);

                if (isset($this->metadata_packet)) {
                    $player->dataPacket($this->metadata_packet);
                }
            }
            $players[$uuid] = null;
        }

        foreach (array_diff_key($this->hasSpawned, $players) as $key => $player) {
            unset($this->hasSpawned[$key]);
            $player->dataPacket($this->despawn_packet);
        }
    }

    private function sendUpdates() : void
    {
        $update = uniqid().PHP_EOL.uniqid().PHP_EOL.uniqid();

        $pk = new SetEntityDataPacket();
        $pk->metadata[Entity::DATA_NAMETAG] = [Entity::DATA_TYPE_STRING, $update];
        $pk->entityRuntimeId = $this->entityId;

        $this->level->getServer()->broadcastPacket($this->hasSpawned, $this->metadata_packet = $pk);
    }

    public function onUpdate(int $tick) : bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $this->handleSpawnings();
        $this->sendUpdates();
        return true;
    }
}