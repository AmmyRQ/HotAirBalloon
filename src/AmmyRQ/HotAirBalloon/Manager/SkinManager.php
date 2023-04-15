<?php
namespace AmmyRQ\HotAirBalloon\Manager;

use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\types\skin\{SkinData, SkinImage};

use AmmyRQ\HotAirBalloon\Main;
use JsonException;

class SkinManager
{

    /**
     * Converts an image to a string using PHP GD lib
     * @return string
     * @throws \Exception
     */
    public static function createSkin() : string
    {
        $skinPath = Main::getInstance()->getDataFolder() . "balloonSkin.png";
        $img = @imagecreatefrompng($skinPath);
        $bytes = '';

        for($y = 0; $y < @imagesy($img); $y++)
        {
            for($x = 0; $x < @imagesx($img); $x++)
            {
                $rgba = @imagecolorat($img, $x, $y);
                $a = ((~((int)($rgba >> 24))) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }

        @imagedestroy($img);

        return $bytes;
    }

    /**
     * Creates a new skin
     * @return Skin
     * @throws JsonException
     * @throws \Exception
     */
    public static function getSkinObject() : Skin
    {
        $skinObject = null;

        try
        {
            $skinObject = new Skin(
                "HotAirBalloon", self::createSkin(), "",
                "geometry.balloonModel",
                file_get_contents(Main::getInstance()->getDataFolder() . "balloonModel.json")
            );
        } catch(\Exception $exception)
        {
            Main::getInstance()->getLogger()->error("[HotAirBalloon] An error occurred (getSkinObject): " . $exception->getMessage());
        }

        if(is_null($skinObject))
            Main::getInstance()->getLogger()->warning("[HotAirBalloon] Skin object is null.");

        return $skinObject;
    }

    /**
     * Creates a new SkinData object
     * @return SkinData
     * @throws JsonException
     */
    public static function getSkinData() : SkinData
    {
        $skin = self::getSkinObject();
        $capeImage = new SkinImage(0, 0, "");

        return new SkinData(
            "HotAirBalloon", "", json_encode(["geometry" => ["default" => $skin->getGeometryName()]]),
            SkinImage::fromLegacy($skin->getSkinData()), [], $capeImage, $skin->getGeometryData()
        );
    }
}
