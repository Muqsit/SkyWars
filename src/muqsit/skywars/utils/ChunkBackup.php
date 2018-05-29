<?php

declare(strict_types=1);
namespace muqsit\skywars\utils;

use pocketmine\level\Level;
use pocketmine\level\format\Chunk;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\tile\Tile;

class ChunkBackup {

    const TYPE_CHUNKS = 0;
    const TYPE_TILES = 1;

    /** @var Level */
    private $level;

    /** @var int[] */
    private $chunk_hashes = [];

    /** @var array */
    private $snapshot;

    public function __construct(Level $level)
    {
        $this->level = $level;
    }

    public function addChunk(int $chunkX, int $chunkZ) : void
    {
        $hash = Level::chunkHash($chunkX, $chunkZ);
        $this->chunk_hashes[$hash] = $hash;
    }

    public function removeEntities() : void
    {
        foreach ($this->chunk_hashes as $hash) {
            Level::getXZ($hash, $chunkX, $chunkZ);
            $chunk = $this->level->getChunk($chunkX, $chunkZ, false);
            if ($chunk !== null) {
                foreach ($chunk->getSavableEntities() as $entity) {
                    $entity->close();
                }
            }
        }
    }

    public function store() : void
    {
        $chunks = [];
        $tiles = [];

        foreach ($this->chunk_hashes as $hash) {
            Level::getXZ($hash, $chunkX, $chunkZ);
            $chunk = $this->level->getChunk($chunkX, $chunkZ, false);
            if ($chunk !== null) {
                $chunks[$hash] = $chunk->fastSerialize();
                foreach ($chunk->getTiles() as $tile) {
                    $tile->saveNBT();
                    $tiles[] = $tile->namedtag;
                }
            }
        }

        $this->snapshot = [
            ChunkBackup::TYPE_CHUNKS => $chunks,
            ChunkBackup::TYPE_TILES => $tiles
        ];
    }

    public function restore() : void
    {
        [
            ChunkBackup::TYPE_CHUNKS => $chunks,
            ChunkBackup::TYPE_TILES => $tiles
        ] = $this->snapshot;

        foreach ($chunks as $chunk) {
            $chunk = Chunk::fastDeserialize($chunk);
            $this->level->setChunk($chunk->getX(), $chunk->getZ(), $chunk);
        }

        foreach ($tiles as $nbt) {
            Tile::createTile($nbt->getString("id"), $this->level, $nbt);
        }
    }
}
