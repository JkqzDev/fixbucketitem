<?php

declare(strict_types=1);

namespace jkqzdev\fixbucketitem;

use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Bucket;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\item\LiquidBucket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\sound\ItemBreakSound;

final class Main extends PluginBase
{

	protected function onEnable() : void {
		$this->getServer()->getPluginManager()->registerEvent(
			PlayerInteractEvent::class,
			function (PlayerInteractEvent $event) : void {
				$block = $event->getBlock();
				$item = $event->getItem();
				$player = $event->getPlayer();

				$newItem = clone $item;

				if (!$item instanceof Bucket && !$item instanceof LiquidBucket) return;
				if (!$player->isSneaking()) return;
				$returnedItems = [];
				$result = $newItem->onInteractBlock($player, $block->getSide($event->getFace()), $block, $event->getFace(), $event->getTouchVector(), $returnedItems);

				if ($result !== ItemUseResult::SUCCESS) return;
				$player->getInventory()->setItemInHand($newItem);
				$this->returnItemsFromAction($player, $item, $newItem, $returnedItems);
			},
			EventPriority::NORMAL,
			$this
		);
	}

	private function returnItemsFromAction(Player $player, Item $oldHeldItem, Item $newHeldItem, array $extraReturnedItems) : void {
		$heldItemChanged = false;

		if (!$newHeldItem->equalsExact($oldHeldItem) && $oldHeldItem->equalsExact($player->getInventory()->getItemInHand())) {
			$newReplica = clone $oldHeldItem;
			$newReplica->setCount($newHeldItem->getCount());

			if ($newReplica instanceof Durable && $newHeldItem instanceof Durable) {
				$newDamage = $newHeldItem->getDamage();

				if ($newDamage >= 0 && $newDamage <= $newReplica->getMaxDurability()) $newReplica->setDamage($newDamage);
			}
			$damagedOrDeducted = $newReplica->equalsExact($newHeldItem);

			if (!$damagedOrDeducted || $player->hasFiniteResources()) {
				if ($newHeldItem instanceof Durable && $newHeldItem->isBroken()) $player->broadcastSound(new ItemBreakSound());
				$player->getInventory()->setItemInHand($newHeldItem);
				$heldItemChanged = true;
			}
		}

		if (!$heldItemChanged) $newHeldItem = $oldHeldItem;

		if ($heldItemChanged && count($extraReturnedItems) > 0 && $newHeldItem->isNull()) $player->getInventory()->setItemInHand(array_shift($extraReturnedItems));

		foreach ($player->getInventory()->addItem(...$extraReturnedItems) as $drop) {
			$ev = new PlayerDropItemEvent($player, $drop);

			if ($player->isSpectator()) $ev->cancel();
			$ev->call();

			if (!$ev->isCancelled()) $player->dropItem($drop);
		}
	}
}