<?php

declare(strict_types=1);
namespace muqsit\skywars\utils;

use pocketmine\block\BlockIds;
use pocketmine\Player;

class BlockUtils {

    public static function trapPlayerInBox(Player $player, int $blockId = BlockIds::AIR, int $blockMeta = 0) : void
    {
        $level = $player->getLevel();
        $pos = $player->floor();
        $player->teleport($pos->add(0.5, 0, 0.5));

        $x = $pos->x;
        $y = $pos->y;
        $z = $pos->z;

        for ($i = -1; $i <= 1; ++$i) {
            for ($k = -1; $k <= 1; ++$k) {
                if ($i === $k || $i === -$k) {
                    continue;
                }

                for ($j = 0; $j <= 1; ++$j) {
                    $level->setBlockIdAt($x + $i, $y + $j, $z + $k, $blockId);
                    $level->setBlockDataAt($x + $i, $y + $j, $z + $k, $blockMeta);
                }
            }
        }

        $level->setBlockIdAt($x, $y + 2, $z, $blockId);
        $level->setBlockDataAt($x, $y + 2, $z, $blockMeta);
    }
}