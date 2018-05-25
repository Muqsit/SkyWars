<?php
namespace muqsit\skywars\game\tasks;

use muqsit\skywars\game\SkyWars;

use pocketmine\scheduler\Task;

abstract class GameTask extends Task {

    /** @var SkyWars */
    protected $game;

    public function __construct(SkyWars $game)
    {
        $this->game = $game;
    }

    public function getGame() : SkyWars
    {
        return $this->game;
    }
}
