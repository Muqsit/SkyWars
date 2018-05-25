<?php
namespace muqsit\skywars\utils;

use muqsit\skywars\GameHandler;
use muqsit\skywars\game\SkyWars;

use pocketmine\level\Level;
use pocketmine\math\Vector3;

class GameCreator {

    /** @var GameHandler */
    private $handler;

    /** @var string */
    private $name;

    /** @var string */
    private $level;

    /** @var Vector3[] */
    private $spawns = [];

    public function __construct(GameHandler $handler, Level $level, string $name)
    {
        $this->handler = $handler;
        $this->level = $level->getFolderName();
        $this->name = $name;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function addSpawn(Vector3 $pos) : int
    {
        $this->spawns[] = $pos->asVector3();
        return count($this->spawns);
    }

    public function valid() : bool
    {
        return count($this->spawns) > 1;
    }

    public function toGame() : SkyWars
    {
        return new SkyWars($this->level, $this->name, ...$this->spawns);
    }
}