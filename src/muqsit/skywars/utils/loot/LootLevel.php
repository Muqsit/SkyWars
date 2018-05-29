<?php

declare(strict_types=1);
namespace muqsit\skywars\utils\loot;

use pocketmine\item\Item;

class LootLevel {

    /** @var int */
    private $max_item_count;

    /** @var Item[] */
    private $items = [];

    public function __construct(int $max_item_count)
    {
        if ($max_item_count < 1) {
            throw new \Error("Max item count cannot be less than 1, got " . $max_item_count);
        }

        $this->max_item_count = $max_item_count;
    }

    public function add(Item $item) : void
    {
        $this->items[] = $item;
    }

    public function getRandom() : Item
    {
        return $this->items[array_rand($this->items)];
    }
}
