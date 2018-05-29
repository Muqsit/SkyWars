<?php

declare(strict_types=1);
namespace muqsit\skywars;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;

class SkyWarsCommand extends PluginCommand implements CommandExecutor {

    public function __construct(Loader $plugin)
    {
        parent::__construct("skywars", $plugin);
        $this->setAliases(["sw"]);
        $this->setExecutor($this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool
    {
        if (!isset($args[0])) {
            $sender->sendMessage(TextFormat::GOLD . "/" . $label . TextFormat::GRAY . " <" . TextFormat::RED . ($sender->isOp() ?
                "join, quit, create, decreate, remove, list, save, stop" :
                "join, quit"
            ) . TextFormat::GRAY . ">");
            return true;
        }

        switch ($args[0]) {
            case "join":
                if (!isset($args[1])) {
                    $sender->sendMessage(TextFormat::RED . "Usage /" . $label . " join <game>");
                    return true;
                }

                $handler = $this->getPlugin()->getGameHandler();
                $game = $handler->get($args[1]);
                if ($game === null) {
                    $sender->sendMessage(TextFormat::RED . "No game with the name '" . $args[1] . "' found.");
                    return false;
                }

                if (!$game->add($sender)) {
                    $sender->sendMessage(TextFormat::RED . "This game has already started.");
                    return false;
                }
                return true;
            case "quit":
                $handler = $this->getPlugin()->getGameHandler();
                $game = $handler->getGameByPlayer($sender);
                if ($game === null) {
                    $sender->sendMessage(TextFormat::RED . "You are currently not in any game.");
                    return false;
                }

                $game->remove($sender);
                return true;
            case "list":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                $list = $this->getPlugin()->getGameHandler()->getAllGames();
                if (empty($list)) {
                    $sender->sendMessage(TextFormat::RED . "There are no configured SkyWars games.");
                    return true;
                }

                $list = array_keys($list);
                $results_per_page = 10;

                $page = (int) ($args[1] ?? 1);
                $offset = $page * $results_per_page;
                if (!isset($list[$offset - 1])) {
                    $page = 1;
                    $offset = 0;
                }

                $result = TextFormat::YELLOW . "Skywars Games " . $page . "/" . ceil(count($list) / $results_per_page) . TextFormat::EOL;

                foreach (array_slice($list, $offset, 10) as $game) {
                    $result .= TextFormat::GREEN . ++$offset . ". " . $game . TextFormat::EOL;
                }

                $sender->sendMessage($result);
                return true;
            case "create":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                if (!isset($args[1])) {
                    $sender->sendMessage(TextFormat::RED . "Usage /" . $label . " create <game>");
                    return true;
                }

                if ($this->getPlugin()->getGameHandler()->create($args[1], $sender->getLevel(), $error) === null) {
                    $sender->sendMessage(TextFormat::RED . $error);
                    return false;
                }

                $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Game creation for '" . $args[1] . "' started!" . TextFormat::EOL . "Use " . TextFormat::ITALIC . "/" . $label . " setvertex " . $args[1] . TextFormat::RESET . TextFormat::LIGHT_PURPLE . " to mark the two borders of your game.");
                return true;
            case "setvertex":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                if (!isset($args[1]) || !isset($args[2]) || (($args[2] = (int) $args[2]) !== 1 && $args[2] !== 2)) {
                    $sender->sendMessage(TextFormat::RED . "Usage /" . $label . " setvertex <game> <1 or 2>");
                    return true;
                }

                $creator = $this->getPlugin()->getGameHandler()->getCreating($args[1]);
                if ($creator === null) {
                    $sender->sendMessage(TextFormat::RED . "No skywars game with the name '" . $args[1] . "' is being configured, use /" . $label . " create " . $args[1] . " to configure a skywars game.");
                    return false;
                }

                switch ($args[2]) {
                    case 1:
                        $creator->setVertex1($sender);
                        break;
                    case 2:
                        $creator->setVertex2($sender);
                        break;
                }

                $sender->sendMessage(TextFormat::GREEN . "Set Vertex #" . $args[2] . " for " . $creator->getName() . "!");

                if ($creator->getVertex1() !== null && $creator->getVertex2() !== null) {
                    $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Use " . TextFormat::ITALIC . "/" . $label . " addspawn " . $args[1] . TextFormat::RESET . TextFormat::LIGHT_PURPLE . " to add a spawn point.");
                }
                return true;
            case "addspawn":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                if (!isset($args[1])) {
                    $sender->sendMessage(TextFormat::RED . "Usage /" . $label . " addspawn <game>");
                    return true;
                }

                $creator = $this->getPlugin()->getGameHandler()->getCreating($args[1]);
                if ($creator === null) {
                    $sender->sendMessage(TextFormat::RED . "No skywars game with the name '" . $args[1] . "' is being configured, use /" . $label . " create " . $args[1] . " to configure a skywars game.");
                    return false;
                }

                $sender->sendMessage(TextFormat::GREEN . "Added #" . $creator->addSpawn($sender) . " spawn for " . $creator->getName() . " successfully!");
                if ($creator->valid()) {
                    $sender->sendMessage(TextFormat::YELLOW . "Use " . TextFormat::ITALIC . "/" . $label . " finalize " . $creator->getName() . TextFormat::RESET . TextFormat::YELLOW . " to publish this game.");
                }
                return true;
            case "finalize":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                if (!isset($args[1])) {
                    $sender->sendMessage(TextFormat::RED . "Usage /" . $label . " finalize <game>");
                    return true;
                }

                $creator = $this->getPlugin()->getGameHandler()->getCreating($args[1]);
                if ($creator === null) {
                    $sender->sendMessage(TextFormat::RED . "No skywars game with the name '" . $args[1] . "' is being configured, use /" . $label . " create " . $args[1] . " to configure a skywars game.");
                    return false;
                }

                if (!$this->getPlugin()->getGameHandler()->publishCreating($creator, $error)) {
                    $sender->sendMessage(TextFormat::RED . $error);
                    return false;
                }

                $sender->sendMessage(TextFormat::GREEN . "Successfully published game '" . $creator->getName() . "', you can join it using /" . $label . " join " . $creator->getName() . ".");
                return true;
            case "rebackup":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                if (!isset($args[1])) {
                    $sender->sendMessage(TextFormat::RED . "Usage /" . $label . " rebackup <game>");
                    return true;
                }

                $game = $this->getPlugin()->getGameHandler()->get($args[1]);
                if ($game === null) {
                    $sender->sendMessage(TextFormat::RED . "No skywars game with the name '" . $args[1] . "' is being configured, use /" . $label . " create " . $args[1] . " to configure a skywars game.");
                    return false;
                }

                $sender->sendMessage(TextFormat::YELLOW . "Recreating restore-chunks array for '" . $game->getName() . "'...");

                $t = microtime(true);
                $game->createChunkBackup();
                $t = microtime(true) - $t;

                $sender->sendMessage(TextFormat::GREEN . "Recreated restore-chunks array for '" . $game->getName() . "' (" . number_format($t, 10) . "secs).");
                return true;
            case "save":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                $handler = $this->getPlugin()->getGameHandler();

                $t = microtime(true);
                $handler->save($count);
                $t = microtime(true) - $t;

                $sender->sendMessage(TextFormat::GREEN . "Saved " . $count . " games (" . number_format($t, 10) . "secs).");
                return true;
            case "addscore":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                if (!isset($args[1]) || ($args[1] = (int) $args[1]) === 0) {
                    $sender->sendMessage(TextFormat::RED . "Usage /" . $label . " addscore <score>");
                    return true;
                }

                $db = $this->getPlugin()->getDatabase();
                if ($db === null) {
                    $sender->sendMessage(TextFormat::RED . "Could not fetch scoring database, make sure scoring is enabled in config.");
                    return false;
                }

                $db->addScore($sender, $args[1]);
                $sender->sendMessage(TextFormat::GREEN . "Added " . $args[1]. " score to yourself!");
                return true;
            case "addscoreboard":
                if (!$sender->isOp()) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
                    return false;
                }

                if (!$this->getPlugin()->getScoreboardHandler()->add($sender)) {
                    $sender->sendMessage(TextFormat::RED . "Could not fetch scoring database, make sure scoring is enabled in config.");
                    return false;
                }

                $sender->sendMessage(TextFormat::GREEN . "Installed scoreboard at your position!");
                return true;
        }

        $sender->sendMessage(TextFormat::RED . "Invalid command argument '" . $args[0] . "'.");
        return false;
    }
}
