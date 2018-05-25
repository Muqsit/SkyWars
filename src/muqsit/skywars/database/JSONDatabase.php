<?php
namespace muqsit\skywars\database;

use muqsit\skywars\database\tasks\JSONScoreAddTask;

use pocketmine\Player;
use pocketmine\Server;

class JSONDatabase extends Database {

    public const TYPE = Database::JSON;

    /** @var ServerScheduler */
    private $scheduler;

    /** @var int[] */
    private $scoreboard = [];

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
        parent::__construct();
    }

    public function initializeScoreboard() : void
    {
        if (empty($this->scoreboard)) {
            echo "Initializing JSON scoreboard...";
            foreach (scandir($this->folder_path) as $file) {
                if (substr($file, -4) === ".json") {
                    echo "\r\033[K[Reading file " . $file;

                    ["player" => $player, "score" => $score] = json_decode(file_get_contents($this->folder_path . $file), true);
                    $this->addToScoreboard($player, $score);
                }
            }
            echo "\r\033[K";
        }
    }

    public function addScore(Player $player, int $score) : void
    {
        $this->scheduler->scheduleAsyncTask(new JSONScoreAddTask($player->getName(), $this->folder_path, $score));
    }
}