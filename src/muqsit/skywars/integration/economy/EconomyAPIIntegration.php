<?php

declare(strict_types=1);
namespace muqsit\skywars\integration\economy;

use pocketmine\Player;

class EconomyAPIIntegration extends EconomyIntegration {

    public function addMoney(Player $player, int $money) : void
    {
        $this->plugin->addMoney($player, $money);
    }
}