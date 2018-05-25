<?php
namespace muqsit\skywars\integration;

use muqsit\skywars\integration\economy\EconomyIntegration;
use muqsit\skywars\integration\economy\EconomyAPIIntegration;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginManager;

class Integration {

    /** @var EconomyIntegration|null */
    private static $economy;

    public static function init(PluginManager $manager) : int
    {
        $integrations = 0;

        if (Integration::integrateEconomy($manager)) {
            ++$integrations;
        }

        return $integrations;
    }

    public static function integrateEconomy(PluginManager $manager) : bool
    {
        $economy_api = $manager->getPlugin("EconomyAPI");
        if ($economy_api !== null) {
            Integration::$economy = new EconomyAPIIntegration($economy_api);
            return true;
        }

        return false;
    }

    public static function getEconomy() : ?EconomyIntegration
    {
        return Integration::$economy;
    }

    /** @var Plugin */
    protected $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }
}