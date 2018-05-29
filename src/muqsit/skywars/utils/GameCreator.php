<?php

declare(strict_types=1);
namespace muqsit\skywars\utils;

use muqsit\skywars\GameHandler;
use muqsit\skywars\game\SkyWars;

use pocketmine\level\Level;
use pocketmine\math\Vector3;

class GameCreator {

    /** @var bool */
    private static $auto_centering = true;

    public static function setAutoCentering(bool $value) : void
    {
        GameCreator::$auto_centering = $value;
    }


    /** @var GameHandler */
    private $handler;

    /** @var string */
    private $name;

    /** @var string */
    private $level;

    /** @var Vector3[] */
    private $spawns = [];

    /** @var Vector3 */
    private $vertex1;

    /** @var Vector3 */
    private $vertex2;

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

    public function getVertex1() : ?Vector3
    {
        return $this->vertex1;
    }

    public function getVertex2() : ?Vector3
    {
        return $this->vertex2;
    }

    public function setVertex1(Vector3 $pos) : void
    {
        $this->vertex1 = $pos->floor();
    }

    public function setVertex2(Vector3 $pos) : void
    {
        $this->vertex2 = $pos->floor();
    }

    public function addSpawn(Vector3 $pos) : int
    {
        $this->spawns[] = GameCreator::$auto_centering ? $pos->floor()->add(0.5, 0, 0.5) : $pos->asVector3();
        return count($this->spawns);
    }

    public function valid() : bool
    {
        return count($this->spawns) > 1;
    }

    public function toGame() : SkyWars
    {
        return new SkyWars($this->level, $this->name, $this->vertex1, $this->vertex2, ...$this->spawns);
    }
}