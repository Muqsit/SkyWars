<?php
namespace muqsit\skywars\integration\economy;

use muqsit\skywars\integration\Integration;

use pocketmine\Player;

abstract class EconomyIntegration extends Integration {

    /**
     * Adds money into player's account.
     *
     * @param Player $player
     * @param int $money
     */
    abstract public function addMoney(Player $player, int $money) : void;
}