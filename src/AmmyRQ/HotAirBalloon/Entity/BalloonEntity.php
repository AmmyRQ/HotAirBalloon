<?php
/**
 * TODO:
 * - Fix diagonal movement (right now this type of action is inverted)
 */
namespace AmmyRQ\HotAirBalloon\Entity;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\{
    MovePlayerPacket,
    types\entity\EntityLink, types\entity\EntityMetadataFlags,
    types\entity\EntityMetadataProperties};
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\player\Player;

use AmmyRQ\HotAirBalloon\Main;
use AmmyRQ\HotAirBalloon\Manager\BalloonEntityManager;
use Exception;

class BalloonEntity extends BalloonEntityBase
{

    /** @var Player|null */
    private ?Player $owner = null;

    /** @var bool */
    private bool $isRidden = false;

    /** @var int */
    private int $riderOffset = 0;

    /**
     * Adds the basic attributes to entity
     * @return void
     */
    public function init() : void
    {
        $this->setScale(2.5);
        $this->setNameTag("");
        $this->setNameTagVisible(false);
    }

    /**
     * @param int $currentTick
     * @return bool
     * @throws Exception
     */
    public function onUpdate(int $currentTick): bool
    {
        if($this->owner == null)
        {
            $this->despawnBalloon();
            return parent::onUpdate($currentTick);
        }

        if(Main::getInstance()->getServer()->getPlayerExact($this->owner->getName()) == null)
        {
            BalloonEntityManager::despawnBalloon($this->owner);
            return parent::onUpdate($currentTick);
        }

        //If the player is driving his hot air balloon, their position will be updated every tick of entity update
        if($this->isRidden)
        {
            $this->owner->handleMovement($this->getPosition()->asVector3());
            $this->owner->setRotation($this->getLocation()->yaw, $this->getLocation()->pitch);
            $this->owner->resetFallDistance();

            //Adds the soft effect in free fall (slow fall)
            if($this->fallDistance > 0)
                $this->motion->y += 0.065;
        }

        return parent::onUpdate($currentTick);
    }

    /**
     * @param float $vX
     * @param float $vZ
     * @param float $yaw
     * @return void
     */
    public function updateBalloonMovement(float $vX, float $vZ, float $yaw) :  void
    {
        $speedFactor = 1.35;
        $x = $this->getDirectionPlane()->x / ($this->speed*$speedFactor);
        $z = $this->getDirectionPlane()->y / ($this->speed*$speedFactor);
        $finalX = 0;    $finalZ = 0;

        if($vX !== 0.0 || $vZ !== 0.0)
        {
            if($this->getPosition()->getY() < 188)
                $this->motion->y += 0.09;
        }

        //TODO: Movement is inverted using vZ & vX axis
        //Case 0 for no movement ($vZ and $vX)
        switch($vZ)
        {
            case 1:
                $finalX = $x;
                $finalZ = $z;
            break;

            case -1:
                $finalX = -$x;
                $finalZ = -$z;
            break;

            default:
                $avg = $x+$z/2;
                $finalX = $avg/1.414*$vZ;
                $finalZ = $avg/1.414*$vX;
            break;

            case 0:
            break;
        }

        switch($vX)
        {
            case 1:
                $finalX = $z;
                $finalZ = -$x;
            break;

            case -1:
                $finalX = -$z;
                $finalZ = $x;
            break;

            case 0:
            break;
        }

        $this->setRotation($yaw, 0.0);
        $this->move($finalX, $this->motion->y*0.25, $finalZ);

        $particleV3 = new Vector3($this->getPosition()->getX(), $this->getPosition()->getY()+2.7, $this->getPosition()->getZ());
        $this->getWorld()->addParticle($particleV3, new ExplodeParticle());
    }

    /**
     * @param bool $teleport
     * @return void
     */
    protected function broadcastMovement(bool $teleport = false): void
    {
        $pk = new MovePlayerPacket();
        $pk->actorRuntimeId = $this->getId();
        $pk->position = $this->getOffsetPosition($this->getPosition());
        $pk->pitch = $this->getLocation()->getPitch();
        $pk->headYaw = $this->getLocation()->getYaw();
        $pk->yaw = $this->getLocation()->getYaw();
        $pk->mode = MovePlayerPacket::MODE_NORMAL;
        //Sends the package to all viewers in world
        $this->getWorld()->broadcastPacketToViewers($this->getPosition(), $pk);
    }

    /**
     * @param Player $player
     * @return void
     */
    public function startRide(Player $player) : void
    {
        if(!$this->isRidden)
        {
            if(!$player instanceof $this->owner)
            {
                $player->sendTip("§c> This balloon is not yours, you can't get on it!");
                return;
            }

            $this->isRidden = true;
            $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);
            $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
            $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, true);
            $player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $this->getRiderSeatPosition());
            $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, true);
            $this->sendLinkToViewers($this->owner);

            $player->sendTip("§a> Now you are riding your balloon!\nJump or sneak to leave your balloon.");
        }
    }

    /**
     * @return void
     */
    public function stopRide() : void
    {
        if(!$this->isRidden)
            return;

        if($this->owner instanceof Player && $this->owner->isOnline())
        {
            $this->isRidden = false;

            $this->owner->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, false);
            $this->owner->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
            $this->owner->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, false);

            $this->sendLinkToViewers($this->owner, EntityLink::TYPE_REMOVE);

            $this->owner->sendTip("§c> You leaved your balloon.");
        }

        $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, true);
        $this->despawnBalloon();
    }

    public function isBeingRidden() : bool
    {
        return $this->isRidden;
    }

    /**
     * Disappears of the balloon with a particle effect
     * @return void
     */
    public function despawnBalloon(): void
    {
        $center = $this->getPosition();
        $radius = 4;
        $radiusSquare = $radius*$radius;

        for($x = $center->x - $radius; $x <= $center->x + $radius; $x++)
        {
            $xSquare = ($center->x - $x)*($center->x - $x);
            for($y = $center->y - $radius; $y <= $center->y + $radius; $y++)
            {
                $ySquare = ($center->y - $y) * ($center->y - $y);
                for ($z = $center->z - $radius; $z <= $center->z + $radius; $z++)
                {
                    $zSquare = ($center->z - $z) * ($center->z - $z);
                    if($xSquare + $ySquare + $zSquare < $radiusSquare)
                        $center->getWorld()->addParticle(new Vector3($x, $y, $z), new ExplodeParticle());
                }
            }
        }

        if($this->isBeingRidden())
            $this->stopRide();

        $this->isRidden = false;
        $this->setOwner(null);
        $this->close();
    }


    /**
     * @param Player|null $player
     * @return void
     */
    public function setOwner(?Player $player): void
    {
        $this->owner = $player;
    }

    /**
     * @return Player|null
     */
    public function getOwner() : ?Player
    {
        return $this->owner;
    }

    /**
     * Calculates the rider's seating position
     * @return Vector3
     */
    private function getRiderSeatPosition(): Vector3
    {
        return new Vector3(0, $this->height * 0.1 + $this->riderOffset, 0);
    }
}