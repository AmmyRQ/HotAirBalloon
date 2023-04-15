<?php
namespace AmmyRQ\HotAirBalloon;

use pocketmine\command\{Command, CommandSender};
use pocketmine\player\Player;

use jojoe77777\FormAPI\SimpleForm;
use AmmyRQ\HotAirBalloon\Manager\BalloonEntityManager;

class BalloonCommand extends Command
{

    public function __construct()
    {
        parent::__construct("balloon", "Hot Air Balloon command", "/balloon");
        $this->setPermission("ahb.perm");
    }

    /**
     * @param CommandSender $player
     * @param string $commandLabel
     * @param array $args
     * @return void
     */
    public function execute(CommandSender $player, string $commandLabel, array $args) : void
    {
        if(!$player instanceof Player)
        {
            $player->sendMessage("[HotAirBalloon] Use this in-game!");
            return;
        }

        if(!$this->testPermission($player))
            return;

        $this->openUI($player);
    }

    /**
     * @param Player $player
     * @return void
     */
    private function openUI(Player $player) : void
    {
        $form = new SimpleForm( function(Player $player, ?int $data = null)
            {
                if(isset($data))
                {
                    switch($data)
                    {
                        case 0: //Close form
                        break;

                        case 1: //Spawn
                            if(BalloonEntityManager::balloonAlreadySpawned($player))
                            {
                                $player->sendMessage("§c> Your hot air balloon has already spawned.");
                                return;
                            }

                            BalloonEntityManager::spawnBalloon($player, $player->getLocation());
                        break;

                        case 2: //Despawn
                            if(BalloonEntityManager::balloonAlreadySpawned($player))
                            {
                                BalloonEntityManager::despawnBalloon($player);
                                $player->sendMessage("§a> Your hot air balloon has been removed successfully.");
                            }
                            else
                                $player->sendMessage("§c> Your hot air balloon has not been spawned.");

                        break;

                        case 3: //About
                        {
                            $f = new SimpleForm(function (Player $player, ?int $data = null) { $this->openUI($player); });
                            $f->setTitle("§cHot Air Balloon §7- §bAbout");
                            $f->setContent("Plugin created by §6AmmyRQ§r.\n\n§8- Github: §ahttps://github.com/AmmyRQ\n§3- Discord: §fMillie#5082");
                            $f->addButton("Back to main menu");
                            $player->sendForm($f);
                        }
                        break;
                    }
                }
            }
        );

        $form->setTitle("§cHot Air Balloon §6- §fMain menu");
        $form->setContent("Select an option to continue:");
        $form->addButton("Close");
        $form->addButton("Spawn hot air balloon");
        $form->addButton("Remove hot air balloon");
        $form->addButton("About this plugin");
        $player->sendForm($form);
    }
}
