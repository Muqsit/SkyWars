<?php
namespace muqsit\skywars\game\tasks;

class CountdownTask extends GameTask {

    /** @var int */
    protected $countdown;

    public function setCountdown(int $value) : void
    {
        $this->countdown = $value;
    }

    public function onRun(int $tick) : void
    {
        $this->game->onCountdown($this->countdown--);
        if ($this->countdown < 1) {
            $this->game->start();
        }
    }
}