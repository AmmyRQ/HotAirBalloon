<?php

namespace AmmyRQ\HotAirBalloon;

use pocketmine\plugin\PluginBase;

use AmmyRQ\HotAirBalloon\Manager\{BalloonEntityManager, FileManager};
use Exception;

class Main extends PluginBase
{

    /**
     * @var Main|null
     */
    private static ?Main $instance = null;

    /**
     * @return Main
     * @throws Exception
     */
    public static function getInstance() : Main
    {
        if(is_null(self::$instance))
            throw new Exception("[HotAirBalloon] Instance is null.");

        return self::$instance;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function onEnable(): void
    {
        self::$instance = $this;

        FileManager::verifyFiles();
        BalloonEntityManager::registerBalloonEntity();
        new EventListener();

        $this->getLogger()->debug("[HotAirBalloon] Event listener registered successfully.");
        $this->getServer()->getCommandMap()->register("balloon", new BalloonCommand());
        $this->getLogger()->info("[HotAirBalloon] Plugin enabled.");
    }
}