<?php

namespace AmmyRQ\HotAirBalloon\Entity;

use pocketmine\network\mcpe\protocol\{
    SetActorLinkPacket,
    AddPlayerPacket,
    PlayerListPacket,
    types\AbilitiesData,
    types\AbilitiesLayer,
    UpdateAbilitiesPacket,
    types\GameMode,
    types\DeviceOS,
    types\entity\EntityIds,
    types\entity\EntityLink,
    types\entity\PropertySyncData,
    types\PlayerListEntry,
    types\PlayerPermissions,
    types\inventory\ItemStack,
    types\inventory\ItemStackWrapper,
    types\command\CommandPermissions
};
use pocketmine\math\Vector3;
use pocketmine\entity\{EntitySizeInfo, Entity, Location};
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

use AmmyRQ\HotAirBalloon\Manager\SkinManager;
use Ramsey\Uuid\{Uuid, UuidInterface};

class BalloonEntityBase extends Entity
{

    /** @const NETWORK_ID */
    public const NETWORK_ID = EntityIds::PLAYER;

    /** @var UuidInterface|null  */
    protected ?UuidInterface $uuid = null;

    /** @var float */
    public float $height = 1.0;

    /** @var float */
    public float $width = 1.0;

    /** @var float */
    protected float $speed = 2;

    protected $gravity = 0.08;

    protected $drag = 0.02;

    protected float $baseOffset = 1.62;

    public function __construct(Location $location, CompoundTag $nbt = null)
    {
        parent::__construct($location, $nbt);
        $this->uuid = Uuid::fromString("aced7120-37dc-48bc-9841-c898e8559b8d");
        $this->setCanSaveWithChunk(false);
    }

    /**
     * @return EntitySizeInfo
     */
    public function getInitialSizeInfo() : EntitySizeInfo
    {
        return new EntitySizeInfo($this->height, $this->width);
    }

    /**
    * @return string
    */
    public static function getNetworkTypeId(): string
    {
        return EntityIds::PLAYER;
    }

    /**
     * @param Vector3 $vector3
     * @return Vector3
     */
    public function getOffsetPosition(Vector3 $vector3): Vector3
    {
        return $vector3->add(0, $this->baseOffset, 0);
    }

    /**
     * @throws \JsonException
     */
    public function sendSpawnPacket(Player $player): void
    {
        $balloonSkin = SkinManager::getSkinData();

        //PlayerListPacket to add a skin to the balloon
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_ADD;
        $pk->entries[] = PlayerListEntry::createAdditionEntry($this->uuid, $this->id, "Balloon-".$this->id, $balloonSkin);
        $player->getNetworkSession()->sendDataPacket($pk);

        //Completes the formation of the entity
        $pk = AddPlayerPacket::create(
            $this->uuid,
            "HotAirBalloon-" . $this->id,
            $this->id,
            "",
            $this->getPosition()->asVector3(),
            $this->getMotion(),
            $this->getLocation()->getPitch(),
            $this->getLocation()->getYaw(),
            $this->getLocation()->getYaw(),
            ItemStackWrapper::legacy(ItemStack::null()),
            GameMode::SURVIVAL,
            $this->getAllNetworkData(),
            new PropertySyncData([], []),
            UpdateAbilitiesPacket::create(
                new AbilitiesData(CommandPermissions::NORMAL, PlayerPermissions::VISITOR, $this->id, [
                    new AbilitiesLayer(
                        AbilitiesLayer::LAYER_BASE, array_fill(0, AbilitiesLayer::NUMBER_OF_ABILITIES, false),
                        0.0, 0.0
                    )
                ])
            ),
            [],
            "",
            DeviceOS::UNKNOWN
        );

        $player->getNetworkSession()->sendDataPacket($pk);

        //Removes the fake player
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_REMOVE;
        $pk->entries = [PlayerListEntry::createRemovalEntry($this->uuid)];

        $player->getNetworkSession()->sendDataPacket($pk);
    }

    /**
     * Sends the packet to all current players
     * @param Player $player
     * @param int $type
     * @return void
     */
    protected function sendLinkToViewers(Player $player, int $type = EntityLink::TYPE_RIDER) : void
    {
        foreach($this->getViewers() as $viewer)
        {
            $player->spawnTo($viewer);
            $pk = new SetActorLinkPacket();
            $pk->link = new EntityLink($this->getId(), $player->getId(), $type, true, true);
            $viewer->getNetworkSession()->sendDataPacket($pk);
        }
    }
}
