<?php

declare(strict_types=1);
namespace muqsit\skywars\game;

use muqsit\skywars\Loader;

use pocketmine\block\SignPost;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class SkyWarsListener implements Listener {

    /** @var Loader */
    private $plugin;

    /** @var GameHandler */
    private $game_handler;

    /** @var SignHandler */
    private $sign_handler;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
        $this->game_handler = $plugin->getGameHandler();
        $this->sign_handler = $plugin->getSignHandler();
    }

    /**
     * @param PlayerQuitEvent
     */
    public function onPlayerQuit(PlayerQuitEvent $event) : void
    {
        $player = $event->getPlayer();
        $game = $this->game_handler->getGameByPlayer($player);
        if ($game !== null) {
            $game->remove($player);
        }
    }

    /**
     * @param PlayerInteractEvent
     * @priority MONITOR
     * @ignoreCancelled true
     */
    public function onPlayerInteract(PlayerInteractEvent $event) : void
    {
        $block = $event->getBlock();
        if ($block instanceof SignPost) {
            $game = $this->sign_handler->getGameFromSign($block);
            if ($game !== null) {
                $player = $event->getPlayer();
                if ($this->game_handler->getGameByPlayer($player) !== $game && !$game->add($player)) {
                    $player->sendMessage($this->plugin->getLanguage()->translate("join.failed.ongoing"));
                }
            }
        }
    }

    /**
     * @param SignChangeEvent
     * @priority NORMAL
     * @ignoreCancelled true
     */
    public function onSignChange(SignChangeEvent $event) : void
    {
        $player = $event->getPlayer();
        if ($player->isOp()) {
            $lines = $event->getLines();
            if ($lines[0] === "sw" && isset($lines[1]) && ($game = $this->game_handler->get($lines[1])) !== null) {
                $event->setLines($this->sign_handler->addGameSign($event->getBlock()->asPosition(), $game));
                $player->sendMessage(TextFormat::GREEN . "Join sign created for '" . $game->getName() . "'.");
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     * @priority HIGH
     * @ignoreCancelled true
     */
    public function onBlockBreak(BlockBreakEvent $event) : void
    {
        $player = $event->getPlayer();
        $game = $this->game_handler->getGameByPlayer($player);
        if ($game !== null && !$game->canModifyTerrain($player)) {
            $event->setCancelled();
            return;
        }

        $block = $event->getBlock();
        if (($game = $this->sign_handler->getGameFromSign($block)) !== null) {
            if (!$player->isOp()) {
                $event->setCancelled();
                return;
            }

            $this->sign_handler->removeGameSign($block);
            $player->sendMessage(TextFormat::DARK_GREEN . "Join sign deleted for '" . $game->getName() . "'.");
        }
    }

    /**
     * @param BlockPlaceEvent $event
     * @priority HIGH
     * @ignoreCancelled true
     */
    public function onBlockPlace(BlockPlaceEvent $event) : void
    {
        $player = $event->getPlayer();
        $game = $this->game_handler->getGameByPlayer($player);
        if ($game !== null && !$game->canModifyTerrain($player)) {
            $event->setCancelled();
        }
    }

    /**
     * @param EntityDamageEvent $event
     * @priority HIGH
     * @ignoreCancelled true
     */
    public function onEntityDamage(EntityDamageEvent $event) : void
    {
        $player = $event->getEntity();
        if ($player instanceof Player && ($game = $this->game_handler->getGameByPlayer($player)) !== null) {
            if (!$game->isPvPEnabled()) {
                $event->setCancelled();
            } elseif ($event->getFinalDamage() >= $player->getHealth()) {
                $event->setCancelled();

                $disqualifier = null;

                if (
                    ($event instanceof EntityDamageByEntityEvent && ($damager = $event->getDamager()) instanceof Player) ||
                    (($last_event = $player->getLastDamageCause()) instanceof EntityDamageByEntityEvent && ($damager = $last_event->getDamager()) instanceof Player)
                ) {
                    $disqualifier = $damager;
                }

                $game->disqualify($player, $disqualifier);
            }
        }
    }
}
