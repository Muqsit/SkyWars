<?php
namespace muqsit\skywars\database;

use muqsit\skywars\database\tasks\JSONScoreAddTask;

use pocketmine\Player;
use pocketmine\Server;

class JSONDatabase extends Database {

    public const TYPE = Database::JSON;

    /** @var ServerScheduler */
    private $scheduler;

    public function __construct(string $folder_path)
    {
        if (substr($folder_path, -1) !== DIRECTORY_SEPARATOR) {
            $folder_path .= DIRECTORY_SEPARATOR;
        }

        if (!is_dir($folder_path)) {
            mkdir($folder_path);
        }

        $this->folder_path = $folder_path;
        $this->scheduler = Server::getInstance()->getScheduler();
    }

    public function addScore(Player $player, int $score) : void
    {
        $this->scheduler->scheduleAsyncTask(new JSONScoreAddTask($this->folder_path . $player->getLowerCaseName() . ".json", $score));
    }

    public function addScoreCallback(string $file, int $score) : void
    {
        $player = substr($file, strlen($this->folder_path), 5);//5 = strlen(".json")
        //TODO: Update scoreboard
    }
}