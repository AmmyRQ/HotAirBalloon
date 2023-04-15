<?php

namespace AmmyRQ\HotAirBalloon\Manager;

use pocketmine\entity\{EntityDataHelper, EntityFactory, Location};
use pocketmine\nbt\tag\{CompoundTag, DoubleTag, FloatTag, ListTag};
use pocketmine\player\Player;
use pocketmine\world\World;

use AmmyRQ\HotAirBalloon\Entity\BalloonEntity;
use AmmyRQ\HotAirBalloon\Main;
use Exception;

class BalloonEntityManager
{

    /**
     * @var array
     * PlayerName => BalloonEntity
     */
    private static array $registeredBalloons = [];

    /**
     * @var bool
     */
    private static bool $entityAlreadyRegistered = false;

    /**
     * @throws Exception
     */
    public static function registerBalloonEntity() : void
    {
        if(!self::$entityAlreadyRegistered)
        {
            $factory = new EntityFactory();
            $factory->register(BalloonEntity::class, function(World $world, CompoundTag $nbt) : BalloonEntity
            {
                return new BalloonEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            }, ["Hot Air Balloon"]);

            Main::getInstance()->getLogger()->debug("[HotAirBalloon] Entity registered successfully.");
        }
    }

    /**
     * @param Player $player
     * @param Location $location
     * @return BalloonEntity|null
     * @throws Exception
     */
    public static function spawnBalloon(Player $player, Location $location) : ?BalloonEntity
    {
        $balloonNBT = new CompoundTag();

        $balloonNBT
            ->setTag(
            "Pos", new ListTag([
                new DoubleTag($location->x),
				new DoubleTag($location->y),
				new DoubleTag($location->z)
			]))
			->setTag("Motion", new ListTag([
                new DoubleTag(0.0),
                new DoubleTag(0.0),
                new DoubleTag(0.0)
            ]))
            ->setTag("Rotation", new ListTag([
                new FloatTag(0.0),
                new FloatTag(0.0)
            ]));

        $entity = new BalloonEntity($location, $balloonNBT);
        $entity->init();
        $entity->setOwner($player);
        $entity->spawnToAll();

        self::$registeredBalloons[$player->getName()] = $entity;
        $entity->startRide($player);

        is_null($entity) ?
            Main::getInstance()->getLogger()->warning("[HotAirBalloon] Tried to spawn a null entity by ". $player->getName() . ".")
        :
            Main::getInstance()->getLogger()->debug("[HotAirBalloon] Entity created successfully, by ". $player->getName() . ".");

        return $entity;
    }

    /**
     * @param Player $player
     * @return void
     * @throws Exception
     */
    public static function despawnBalloon(Player $player) : void
    {
        $balloon = self::$registeredBalloons[$player->getName()];

        if($balloon instanceof BalloonEntity)
            $balloon->despawnBalloon();

        unset(self::$registeredBalloons[$player->getName()]);

        Main::getInstance()->getLogger()->debug("[HotAirBalloon] Entity deleted successfully, by ". $player->getName() . ".");
    }

    /**
     * @param Player $player
     * @return bool
     */
    public static function balloonAlreadySpawned(Player $player) : bool
    {
        return array_key_exists($player->getName(), self::$registeredBalloons);
    }

    /**
     * @return array
     */
    public static function getRegisteredBalloons() : array
    {
        return self::$registeredBalloons;
    }

    /**
     * @param Player $player
     * @return BalloonEntity|null
     */
    public static function getCurrentBalloon(Player $player) : ?BalloonEntity
    {
        if(self::balloonAlreadySpawned($player))
            return self::$registeredBalloons[$player->getName()];

        return null;
    }

}
