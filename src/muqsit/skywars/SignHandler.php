<?php
namespace muqsit\skywars;

use muqsit\skywars\game\SkyWars;

use pocketmine\level\Position;
use pocketmine\utils\TextFormat;

class SignHandler {

    /** @var string */
    private $path;

    /** @var string[] */
    private $signs = [];

    /** @var Position[] */
    private $game_signs = [];

    /** @var string[] */
    private $text;

    /** @var GameHandler */
    private $game_handler;

    public function __construct(Loader $plugin)
    {
        $this->game_handler = $plugin->getGameHandler();
        $this->path = $plugin->getDataFolder() . "signs.yml";

        $this->text = array_map([TextFormat::class, "colorize"], $plugin->getConfig()->get("sign-format"));
        while (count($this->text) < 4) {
            $this->text[] = "";
        }

        $this->load($plugin);
    }

    private function load(Loader $plugin) : void
    {
        $this->signs = yaml_parse_file($this->path);
        foreach ($this->signs as $key => $game) {
            [$x, $y, $z, $level] = explode(";", $key);
            $this->game_signs[$game][$key] = new Position((int) $x, (int) $y, (int) $z, $plugin->getServer()->getLevelByName($level));
        }
    }

    public function save() : void
    {
        yaml_emit_file($this->path, $this->signs);
    }

    public function getSigns(?string $game = null) : array
    {
        if ($game !== null) {
            return $this->game_signs[strtolower($game)] ?? [];
        }
        return $this->game_signs;
    }

    public function addGameSign(Position $pos, SkyWars $game) : array
    {
        $game_name = strtolower($game->getName());

        $this->signs[$key = $pos->x . ";" . $pos->y . ";" . $pos->z . ";" . $pos->getLevel()->getFolderName()] = $game_name;
        $this->game_signs[$game_name][$key] = $pos;//a small price for O(1)?

        return $this->getSignLines($game);
    }

    public function getSignLines(SkyWars $game) : array
    {
        return str_replace([
            '{NAME}',
            '{PLAYERS}',
            '{MAX_PLAYERS}'
        ], [
            $game->getName(),
            count($game->getPlayers()),
            count($game->getSpawns())
        ], $this->text);
    }

    public function updateSigns(SkyWars $game) : void
    {
        $signs = $this->getSigns($game->getName());
        if (!empty($signs)) {
            [$text1, $text2, $text3, $text4] = $this->getSignLines($game);
            foreach ($signs as $pos) {
                $pos->level->getTileAt($pos->x, $pos->y, $pos->z)->setText($text1, $text2, $text3, $text4);
            }
        }
    }

    public function getGameFromSign(Position $pos) : ?SkyWars
    {
        $key = $pos->x . ";" . $pos->y . ";" . $pos->z . ";" . $pos->getLevel()->getFolderName();
        if (isset($this->signs[$key])) {
            return $this->game_handler->get($this->signs[$key]);
        }

        return null;
    }

    public function removeGameSign(Position $pos) : void
    {
        if (isset($this->signs[$key = $pos->x . ";" . $pos->y . ";" . $pos->z . ";" . $pos->getLevel()->getFolderName()])) {
            unset($this->game_signs[$this->signs[$key]][$key], $this->signs[$key]);
        }
    }

    public function removeGameSigns(?string $game_search = null) : int
    {
        $changed = 0;
        $block = Block::get(Block::AIR);

        foreach ($this->signs as $key => $game) {
            if ($game_search === null || $game !== $game_search) {
                $pos = $this->game_signs[$this->signs[$key]][$key];
                $level = $pos->getLevel();
                $level->setBlock($pos, $block, false, false);
                $tile = $level->getTileAt($pos);
                if ($tile !== null) {
                    $tile->close();
                }
                unset($this->game_signs[$this->signs[$key]][$key], $this->signs[$key]);
                ++$changed;
            }
        }

        return $changed;
    }
}
