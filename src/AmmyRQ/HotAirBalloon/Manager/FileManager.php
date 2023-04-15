<?php

namespace AmmyRQ\HotAirBalloon\Manager;

use AmmyRQ\HotAirBalloon\Main;

class FileManager
{

    /**
     * @return void
     * @throws \Exception
     */
    public static function verifyFiles() : void
    {
        if(!is_file(Main::getInstance()->getDataFolder() . "balloonModel.json"))
        {
            Main::getInstance()->saveResource("balloonModel.json");
            Main::getInstance()->getLogger()->debug("[HotAirBalloon] Model resource saved successfully.");
        }

        if(!is_file(Main::getInstance()->getDataFolder() . "ballonSkin.png"))
        {
            Main::getInstance()->saveResource("balloonSkin.png");
            Main::getInstance()->getLogger()->debug("[HotAirBalloon] Skin resource saved successfully.");
        }

        Main::getInstance()->getLogger()->debug("[HotAirBalloon] All files have been successfully verified.");
    }
}
