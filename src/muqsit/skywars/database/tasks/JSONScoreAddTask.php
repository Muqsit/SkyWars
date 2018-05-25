<?php
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

    public function __construct(string $file, int $score)
    {
        $this->file = $file;
        $this->score = $score;
    }

    public function onRun() : void
    {
        $contents = [];
        if (is_file($this->file)) {
            $contents = json_decode(file_get_contents($this->file), true);
        }

        if (!isset($contents["score"])) {
            $contents["score"] = $this->score;
        } else {
            $contents["score"] += $this->score;
        }

        file_put_contents($this->file, json_encode($contents));
        $this->total_score = $contents["score"];
    }

    public function onCompletion(Server $server) : void
    {
        $server->getPluginManager()->getPlugin("SkyWars")->getDatabase()->addScoreCallback($this->file, $this->total_score);
    }
}