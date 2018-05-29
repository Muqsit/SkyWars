<?php

declare(strict_types=1);
namespace muqsit\skywars\game\tasks;

class RuntimeTask extends GameTask {

    public function onRun(int $tick) : void
    {
        $this->game->onRun($tick);
    }
}
