<?php
namespace muqsit\skywars\utils\loot;

use pocketmine\item\ItemFactory;

class LootTable {

    /** @var LootLevel[] */
    private static $levels = [];

    /** @var int[] */
    private static $level_chances = [];

    public static function load(array $config) : void
    {
        LootTable::createLevels($config["levels"]);
        LootTable::addLootToLevels($config["items"]);
    }

    private static function createLevels(array $levels) : void
    {
        foreach ($levels as $level => [
            "max-items" => $max_items,
            "chance" => $chance
        ]) {
            LootTable::$levels[$level] = new LootLevel($max_items);
            LootTable::$level_chances[$level] = $chance;
        }

        $sum = array_sum(LootTable::$level_chances);
        if ($sum !== 100) {
            throw new \Error("The sum of the level chances must be 100, got " . $sum . ".");
        }
    }

    private static function addLootToLevels(array $loot) : void
    {
        foreach ($loot as $level => $items) {
            if (!isset(LootTable::$levels[$level])) {
                //notify about this skip
                continue;
            }

            $level = LootTable::$levels[$level];
            foreach ($items as $item) {
                $data = explode(":", $item);
                $count = 1;
                if (count($data) > 2) {
                    $count = (int) $data[2];
                }

                $item = ItemFactory::fromString($item);
                $item->setCount($count);

                $level->add($item);
            }
        }
    }

    public static function getRandomLevel() : LootLevel
    {
        while (true) {
            if (mt_rand(1, 100) <= LootTable::$level_chances[$level = array_rand(LootTable::$level_chances)]) {
                return LootTable::$levels[$level];
            }
        }
    }
}
