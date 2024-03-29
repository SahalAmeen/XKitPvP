<?php

namespace KitPvP\arena\pvp;


use KitPvP\BaseEvent;
use onebone\economyapi\EconomyAPI;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class CustomKill extends BaseEvent {

    public $titles = [
        0 => [
            'title' => '&c&lYou died!',
            'subtitle' => '&eYou were killed by %player%'
        ]
    ];

    /**
     * @param EntityDamageEvent $event
     */
    public function catchDamage(EntityDamageEvent $event)
    {
        $player = $event->getEntity();
        $cl = $this->getPlugin()->getRegion(1);
        if ($event->getEntity() instanceof Player) {
            if ($event instanceof EntityDamageByEntityEvent) {
                $player = $event->getEntity();
                if ($this->getPlugin()->getRegion(1)->isInAnyArena($player)) {
                    $damager = $event->getDamager();
                    $damage = $event->getFinalDamage();
                    if (($player->getHealth() - $damage) < 0.5) { // Check if damage dealt would kill player.
                        $player->setHealth(0);
                        $event->setCancelled(true);
                        $this->customKill($player);
                        $this->reward($player, $damager, 50);
                    }
                }
            }
        }
    }

    /**
     * @param Player $player
     */
    private function customKill(Player $player)
    {
        $this->spawnDeadEntity($player);
        $ak = Server::getInstance()->getPluginManager()->getPlugin('AdvancedKits');
        if (isset($ak->hasKit[$player->getLowerCaseName()])) unset($ak->hasKit[$player->getLowerCaseName()]);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        if ($player->hasEffects()) $player->removeAllEffects();
        if ($player->isOnFire()) $player->extinguish();
        $player->setHealth($player->getMaxHealth());
        $player->setFood($player->getMaxFood());
        $player->teleport($player->getLevel()->getSafeSpawn());
    }

    /**
     * @param Player $loser
     * @param Player $winner
     * @param int $prize
     */
    private function reward(Player $loser, Player $winner, int $prize)
    {
        $this->title($loser, 0, $winner);
        $api = EconomyAPI::getInstance();
        $api->addMoney($winner, $prize);
        $winner->sendPopup(TextFormat::colorize('.eYou killed .c'.$loser->getName().PHP_EOL.'.a+ $'.$prize, '.'));
    }


    /**
     * @param Player $player
     * @param int $id
     * @param Player|null $killer
     */
    private function title(Player $player, int $id, Player $killer = null)
    {
        $title = null;
        $subtitle = '';
        for ($i = 0; $i < count($this->titles); ++$i) {
            switch ($id) {
                case $i:
                    $title = $this->titles[$i]['title'];
                    $subtitle = $killer !== null ? str_replace('%player%', $killer->getName(), $this->titles[0]['subtitle']) : $this->titles[0]['subtitle'];
                    break;
            }
        }
        if (!is_null($title)) {
            $player->addTitle(TextFormat::colorize($title), TextFormat::colorize($subtitle), 20, 20 * 3, 20);
        }
    }

    /**
     * @param Player $entity
     */
    private function spawnDeadEntity(Player $entity) {
        $this->getPlugin()->getScheduler()->scheduleRepeatingTask(new DeadEntityTask($entity), 20);
    }
}
