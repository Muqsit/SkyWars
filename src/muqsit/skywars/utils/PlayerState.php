<?php
namespace muqsit\skywars\utils;

use pocketmine\Player;

class PlayerState {

    /** @var EffectInstance[] */
    private $effects = [];

    /** @var float */
    private $health;

    /** @var int */
    private $maxHealth;

    /** @var float */
    private $food;

    /** @var float */
    private $saturation;

    /** @var Item[] */
    private $armor = [];

    /** @var Item[] */
    private $inventory = [];

    /** @var int */
    private $gamemode;

    /** @var Location */
    private $location;

    public function __construct(Player $player, bool $clear_inv = true, bool $clear_effects = true)
    {
        foreach ($player->getEffects() as $effect) {
            $this->effects[] = clone $effect;
        }

        $this->health = $player->getHealth();
        $this->maxHealth = $player->getMaxHealth();
        $player->setHealth($player->getMaxHealth());

        $this->food = $player->getFood();
        $player->setFood($player->getMaxFood());

        $this->saturation = $player->getSaturation();
        $player->setSaturation(20.00);

        $this->inventory = $player->getInventory()->getContents();
        $this->armor = $player->getArmorInventory()->getContents();

        if ($clear_inv) {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getCursorInventory()->clearAll();
            $player->getCraftingGrid()->clearAll();
        }

        if ($clear_effects) {
            $player->removeAllEffects();
        }

        $this->gamemode = $player->getGamemode();
        $this->location = $player->asLocation();
    }

    public function reset(Player $player, bool $override = true) : void
    {
        if ($override) {
            $player->removeAllEffects();
            $player->getCursorInventory()->clearAll();
            $player->getCraftingGrid()->clearAll();
        }

        foreach ($this->effects as $effect) {
            $player->addEffect($effect);
        }

        $player->getArmorInventory()->setContents($this->armor);
        $player->getInventory()->setContents($this->inventory);
        $player->setMaxHealth($this->maxHealth);
        $player->setHealth($this->health);
        $player->setFood($this->food);
        $player->setSaturation($this->saturation);

        $player->setGamemode($this->gamemode);
        $player->teleport($this->location);
    }
}
