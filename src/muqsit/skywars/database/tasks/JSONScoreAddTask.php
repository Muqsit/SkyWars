<?php

declare(strict_types=1);
namespace muqsit\skywars\database\tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class JSONScoreAddTask extends AsyncTask {

    /** @var string */
    private $file;

    /** @var int */
    private $score;

    /** @var int */
    private $total_score;

    public function __construct(string $player, string $folder_path, int $score)
    {
        $this->player = $player;
        $this->folder_path = $folder_path;
        $this->score = $score;
    }

    public function onRun() : void
    {
        $contents = [];

        $file = $this->folder_path . strtolower($this->player) . ".json";

        if (is_file($file)) {
            $contents = json_decode(file_get_contents($file), true);
        }

        $contents["formatted_name"] = $this->player;

        if (!isset($contents["score"])) {
            $contents["score"] = $this->score;
        } else {
            $contents["score"] += $this->score;
        }

        if ($contents["score"] < 0) {
            $contents["score"] = 0;
        }

        file_put_contents($file, json_encode($contents));
        $this->total_score = $contents["score"];
    }

    public function onCompletion(Server $server) : void
    {
        $server->getPluginManager()->getPlugin("SkyWars")->getDatabase()->onScoreChange($this->player, $this->total_score);
    }
}