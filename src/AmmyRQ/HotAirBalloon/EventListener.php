<?php
namespace AmmyRQ\HotAirBalloon;

use pocketmine\event\entity\{
    EntityDamageByEntityEvent, ProjectileLaunchEvent,
    EntityTeleportEvent, EntityDamageEvent
};
use pocketmine\event\player\{
    PlayerInteractEvent, PlayerItemConsumeEvent, PlayerItemUseEvent,
    PlayerQuitEvent, PlayerDeathEvent
};
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\{
    InteractPacket, InventoryTransactionPacket,
    PlayerAuthInputPacket, types\inventory\UseItemOnEntityTransactionData
};
use pocketmine\player\Player;
use pocketmine\event\Listener;

use AmmyRQ\HotAirBalloon\Entity\BalloonEntity;
use AmmyRQ\HotAirBalloon\Manager\BalloonEntityManager;
use Exception;

class EventListener implements Listener
{

    /**
     * @throws Exception from Main::getInstance()
     */
    public function __construct()
    {
        Main::getInstance()->getServer()->getPluginManager()->registerEvents($this, Main::getInstance());
    }

    /**
     * @param PlayerQuitEvent $event
     * @return void
     * @throws Exception
     */
    public function onQuit(PlayerQuitEvent $event) : void
    {
        $player = $event->getPlayer();

        if(BalloonEntityManager::balloonAlreadySpawned($player))
        {
            $balloon = BalloonEntityManager::getCurrentBalloon($player);

            if($balloon instanceof BalloonEntity)
                BalloonEntityManager::despawnBalloon($player);
        }
    }

    /**
     * @param PlayerDeathEvent $event
     * @return void
     * @throws Exception
     */
    public function onDeath(PlayerDeathEvent $event) : void
    {
        $player = $event->getPlayer();

        if(BalloonEntityManager::balloonAlreadySpawned($player))
        {
            $balloon = BalloonEntityManager::getCurrentBalloon($player);

            if($balloon instanceof BalloonEntity)
                BalloonEntityManager::despawnBalloon($player);
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @return void
     */
    public function onInteractPlayer(PlayerInteractEvent $event) : void
    {
        $player = $event->getPlayer();

        if(BalloonEntityManager::balloonAlreadySpawned($player))
        {
            $balloon = BalloonEntityManager::getCurrentBalloon($player);

            if($balloon instanceof BalloonEntity)
            {
                if($balloon->isBeingRidden())
                    $event->cancel();
            }
        }
    }

    /**
     * @param PlayerItemUseEvent $event
     * @return void
     */
    public function onItemUse(PlayerItemUseEvent $event) : void
    {
        $player = $event->getPlayer();

        if(BalloonEntityManager::balloonAlreadySpawned($player))
        {
            $balloon = BalloonEntityManager::getCurrentBalloon($player);

            if($balloon instanceof BalloonEntity)
            {
                if($balloon->isBeingRidden())
                    $event->cancel();
            }
        }
    }

    /**
     * @param PlayerItemConsumeEvent $event
     * @return void
     */
    public function onItemConsume(PlayerItemConsumeEvent $event) : void
    {
        $player = $event->getPlayer();

        if(BalloonEntityManager::balloonAlreadySpawned($player))
        {
            $balloon = BalloonEntityManager::getCurrentBalloon($player);

            if($balloon instanceof BalloonEntity)
            {
                if($balloon->isBeingRidden())
                    $event->cancel();
            }
        }
    }

    /**
     * @param ProjectileLaunchEvent $event
     * @return void
     */
    public function onProjectileLaunched(ProjectileLaunchEvent $event) : void
    {
        $player = $event->getEntity();

        if($player instanceof Player)
        {
            if(BalloonEntityManager::balloonAlreadySpawned($player))
            {
                $balloon = BalloonEntityManager::getCurrentBalloon($player);

                if($balloon instanceof BalloonEntity)
                {
                    if($balloon->isBeingRidden())
                        $event->cancel();
                }
            }
        }
    }

    /**
     * @param EntityDamageEvent $event
     * @return void
     */
    public function onEntityDamage(EntityDamageEvent $event) : void
    {
        $entity = $event->getEntity();

        if($entity instanceof BalloonEntity)
        {
            if($event instanceof EntityDamageByEntityEvent)
            {
                $player = $event->getDamager();

                if($player instanceof Player)
                    if(!$entity->isBeingRidden()) //Maybe this is unnecessary
                        $entity->startRide($player);
            }

            $event->cancel();
        }
    }

    /**
     * @param EntityTeleportEvent $event
     * @return void
     * @throws Exception
     */
    public function onTeleport(EntityTeleportEvent $event) : void
    {
        $player = $event->getEntity();
        if($player instanceof Player)
        {
            if(BalloonEntityManager::balloonAlreadySpawned($player))
            {
                $balloon = BalloonEntityManager::getCurrentBalloon($player);

                if($balloon instanceof BalloonEntity)
                {
                    if($balloon->isBeingRidden())
                        BalloonEntityManager::despawnBalloon($player);
                }
            }
        }
    }

    /**
     * @param DataPacketReceiveEvent $event
     * @return void
     * @throws Exception
     */
    public function onData(DataPacketReceiveEvent $event) : void
    {
        $pk = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();

        switch($pk->pid())
        {
            //Using WASD
            case PlayerAuthInputPacket::NETWORK_ID:
                if(BalloonEntityManager::balloonAlreadySpawned($player))
                {
                    $event->cancel();

                    if($pk->getMoveVecX() == 0 && $pk->getMoveVecZ() == 0) //No movement
                        return;

                    $balloon = BalloonEntityManager::getCurrentBalloon($player);
                    if($balloon->getOwner()->getName() == $player->getName())
                    {
                        if($balloon->isBeingRidden())
                            $balloon->updateBalloonMovement($pk->getMoveVecX(), $pk->getMoveVecZ(), $pk->getHeadYaw());
                    }
                }
            break;

            //Leaving balloon
            case InteractPacket::NETWORK_ID:
                if($pk->action === InteractPacket::ACTION_LEAVE_VEHICLE)
                {
                    $balloon = $player->getWorld()->getEntity($pk->targetActorRuntimeId);
                    if($balloon instanceof BalloonEntity) {
                        $balloon->stopRide();
                        BalloonEntityManager::despawnBalloon($player);
                        $event->cancel();
                    }
                }
            break;

            //Opening inventory while driving balloon
            case InventoryTransactionPacket::NETWORK_ID:
                if($pk->trData instanceof UseItemOnEntityTransactionData)
                {
                    $balloon = $player->getWorld()->getEntity($pk->trData->getActorRuntimeId());
                    if($balloon instanceof BalloonEntity)
                    {
                        if($balloon->getOwner()->getName() == $player->getName())
                            $balloon->startRide($player);
                    }
                }
            break;
        }
    }

}
