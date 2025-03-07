<?php

namespace CLADevs\VanillaX\network\handler;

use CLADevs\VanillaX\event\inventory\itemstack\CraftItemStackEvent;
use CLADevs\VanillaX\event\inventory\itemstack\CreativeCreateItemStackEvent;
use CLADevs\VanillaX\event\inventory\itemstack\DestroyItemStackEvent;
use CLADevs\VanillaX\event\inventory\itemstack\DropItemStackEvent;
use CLADevs\VanillaX\event\inventory\itemstack\MoveItemStackEvent;
use CLADevs\VanillaX\event\inventory\itemstack\SwapItemStackEvent;
use CLADevs\VanillaX\event\inventory\TradeItemEvent;
use CLADevs\VanillaX\inventories\InventoryManager;
use CLADevs\VanillaX\inventories\types\AnvilInventory;
use CLADevs\VanillaX\inventories\types\BeaconInventory;
use CLADevs\VanillaX\inventories\types\RecipeInventory;
use CLADevs\VanillaX\inventories\types\TradeInventory;
use CLADevs\VanillaX\session\SessionManager;
use CLADevs\VanillaX\VanillaX;
use Exception;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\inventory\CreativeInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ItemStackRequestPacket;
use pocketmine\network\mcpe\protocol\ItemStackResponsePacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerUIIds;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\BeaconPaymentStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftingConsumeInputStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftingMarkSecondaryResultStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeAutoStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeOptionalStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CreativeCreateStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingNonImplementedStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingResultsStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DestroyStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DropStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\GrindstoneStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestSlotInfo;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\LabTableCombineStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\LoomStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\MineBlockStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\PlaceIntoBundleStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\PlaceStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\SwapStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\TakeFromBundleStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\TakeStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseContainerInfo;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseSlotInfo;
use pocketmine\network\mcpe\protocol\types\recipe\CraftingRecipeBlockName;
use pocketmine\network\mcpe\protocol\types\recipe\ShapedRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapelessRecipe;
use pocketmine\Server;

class ItemStackRequestHandler{
    
    private ?Item $createdOutput = null;

    /** @var ItemStackResponseContainerInfo[] */
    private array $containerInfo = [];

    public function __construct(private NetworkSession $session){
    }

    public function handleItemStackRequest(ItemStackRequestPacket $packet): bool{
        foreach($packet->getRequests() as $request){
            try {
                foreach($request->getActions() as $action){
//                    var_dump(get_class($action));
                    if($action instanceof TakeStackRequestAction){
                        $this->handleTake($action);
                    }else if($action instanceof PlaceStackRequestAction){
                        $this->handlePlace($action);
                    }else if($action instanceof SwapStackRequestAction){
                        $this->handleSwap($action);
                    }else if($action instanceof DropStackRequestAction){
                        $this->handleDrop($action);
                    }else if($action instanceof DestroyStackRequestAction){
                        $this->handleDestroy($action);
                    }else if($action instanceof CraftingConsumeInputStackRequestAction){
                        $this->handleCraftingConsumeInput($action);
                    }else if($action instanceof CraftingMarkSecondaryResultStackRequestAction){
                        $this->handleCraftingMarkSecondaryResult($action);
                    }else if($action instanceof PlaceIntoBundleStackRequestAction){
                        $this->handlePlaceIntoBundle($action);
                    }else if($action instanceof TakeFromBundleStackRequestAction){
                        $this->handleTakeFromBundle($action);
                    }else if($action instanceof LabTableCombineStackRequestAction){
                        $this->handleLabTableCombine($action);
                    }else if($action instanceof BeaconPaymentStackRequestAction){
                        $this->handleBeaconPayment($action);
                    }else if($action instanceof MineBlockStackRequestAction){
                        $this->handleMineBlock($action);
                    }else if($action instanceof CraftRecipeStackRequestAction){
                        $this->handleCraftRecipe($action);
                    }else if($action instanceof CraftRecipeAutoStackRequestAction){
                        $this->handleCraftRecipeAuto($action);
                    }else if($action instanceof CreativeCreateStackRequestAction){
                        $this->handleCreativeCreate($action);
                    }else if($action instanceof CraftRecipeOptionalStackRequestAction){
                        $this->handleCraftRecipeOptional($action, $request->getFilterStrings());
                    }else if($action instanceof GrindstoneStackRequestAction){
                        $this->handleGrindstone($action);
                    }else if($action instanceof LoomStackRequestAction){
                        $this->handleLoom($action);
                    }else if($action instanceof DeprecatedCraftingNonImplementedStackRequestAction){
                        $this->handleDeprecatedCraftingNonImplemented($action);
                    }else if($action instanceof DeprecatedCraftingResultsStackRequestAction){
                        $this->handleDeprecatedCraftingResults($action);
                    }
                }
                $this->acceptRequest($request->getRequestId());
            }catch (Exception $e){
                Server::getInstance()->getLogger()->logException($e);
                $this->rejectRequest($request->getRequestId());
                VanillaX::getInstance()->getLogger()->debug("Failed to handle ItemStackRequest for player '" . $this->session->getPlayer()->getName() . "': " . $e->getMessage());
            }
        }
        return true;
    }

    /**
     * @param TakeStackRequestAction $action
     * @throws Exception
     * Carries item on cursor
     */
    private function handleTake(TakeStackRequestAction $action): void{
        $this->move(MoveItemStackEvent::TYPE_TAKE, $action->getSource(), $action->getDestination(), $action->getCount());
    }

    /**
     * @param PlaceStackRequestAction $action
     * @throws Exception
     * Once its placed onto inventory slot from a cursor
     */
    private function handlePlace(PlaceStackRequestAction $action): void{
        $this->move(MoveItemStackEvent::TYPE_PLACE, $action->getSource(), $action->getDestination(), $action->getCount());
    }

    public function move(int $type, ItemStackRequestSlotInfo $source, ItemStackRequestSlotInfo $destination, int $count): void{
        if($this->session->getPlayer()->isCreative() && $source->getContainerId() === ContainerUIIds::ARMOR && $this->getItemFromStack($source)->hasEnchantment(EnchantmentIdMap::getInstance()->fromId(EnchantmentIds::BINDING))){
            return;
        }
        $ev = new MoveItemStackEvent($this->session->getPlayer(), $type, $count, $source, $destination);
        $ev->call();

        if($ev->isCancelled()){
            VanillaX::getInstance()->getLogger()->debug("Failed to execute MoveItemStack Type " . $type . ": Event Cancelled");
            return;
        }
        $source = $ev->getSource();
        $destination = $ev->getDestination();
        $count = $ev->getCount();
        $dest = $this->getItemFromStack($destination);

        if($source->getContainerId() === ContainerUIIds::CREATED_OUTPUT){
            if($this->createdOutput === null){
                throw new Exception("Expected created_output_request to be set.");
            }
            $item = $this->createdOutput;
        }else{
            $item = $this->getItemFromStack($source);
            $this->setItemInStack($source, $item->setCount($item->getCount() - $count));
        }
        if($dest->isNull()){
            $dest = (clone $item)->setCount(0);
        }
        $this->setItemInStack($destination, $dest->setCount($dest->getCount() + $count));
    }

    /**
     * @param SwapStackRequestAction $action
     * Switching slot with items
     */
    private function handleSwap(SwapStackRequestAction $action): void{
        $ev = new SwapItemStackEvent($this->session->getPlayer(), $action->getSlot1(), $action->getSlot2());
        $ev->call();

        if($ev->isCancelled()){
            VanillaX::getInstance()->getLogger()->debug("Failed to execute SwapItemStack: Event Cancelled");
            return;
        }
        $source = $ev->getSource();
        $dest = $ev->getDestination();
        $sourceItem = $this->getItemFromStack($source);
        $destItem = $this->getItemFromStack($dest);

        $this->setItemInStack($source, $destItem);
        $this->setItemInStack($dest, $sourceItem);
    }

    /**
     * @param DropStackRequestAction $action
     * Dropping item while inside the inventory
     */
    private function handleDrop(DropStackRequestAction $action): void{
        $player = $this->session->getPlayer();
        $ev = new DropItemStackEvent($player, $action->getSource(), $action->getCount(), $action->isRandomly());
        $ev->call();

        if($ev->isCancelled()){
            VanillaX::getInstance()->getLogger()->debug("Failed to execute DropItemStack: Event Cancelled");
            return;
        }
        $source = $ev->getSource();
        if($source->getContainerId() !== ContainerUIIds::CREATED_OUTPUT){
            $item = $this->getItemFromStack($source);
            $this->setItemInStack($source, VanillaItems::AIR());
        }else{
            $item = $this->createdOutput;
        }
        $this->session->getPlayer()->dropItem($item);
    }

    /**
     * @param DestroyStackRequestAction $action
     * @throws Exception
     * Deleting items in creative mode by throwing it into creative inventory
     */
    private function handleDestroy(DestroyStackRequestAction $action): void{
        $source = $action->getSource();
        $player = $this->session->getPlayer();

        if(!$player->isCreative()){
            $handled = false;
            $inventory = $this->getInventory($source->getContainerId());

            if($inventory instanceof BeaconInventory){
                $handled = true;
            }
            if(!$handled){
                throw new Exception("received DestroyStackRequestAction while not being in creative");
            }
        }
        $ev = new DestroyItemStackEvent($player, $source);
        $ev->call();

        if($ev->isCancelled()){
            VanillaX::getInstance()->getLogger()->debug("Failed to execute DestroyItemStack: Event Cancelled");
            return;
        }
        $this->setItemInStack($ev->getSource(), VanillaItems::AIR());
    }

    /**
     * @param CraftingConsumeInputStackRequestAction $action
     * @throws Exception
     * Crafting input being reduced
     */
    private function handleCraftingConsumeInput(CraftingConsumeInputStackRequestAction $action): void{
        if($this->createdOutput === null || $this->createdOutput->isNull()){
            return;
        }
        $source = $action->getSource();
        $inventory = $this->getInventory($source->getContainerId());
        $index = ItemStackTranslator::netSlot($source->getSlotId(), $inventory);

        if($inventory instanceof PlayerCraftingInventory){
            $crafting = $this->session->getPlayer()->getCraftingGrid();
        }else{
            $crafting = $inventory;
        }
        $item = $crafting->getItem($index);
        $this->setItemInStack($action->getSource(), $item->setCount($item->getCount() - $action->getCount()));
    }

    private function handleCraftingMarkSecondaryResult(CraftingMarkSecondaryResultStackRequestAction $action): void{
    }

    private function handlePlaceIntoBundle(PlaceIntoBundleStackRequestAction $action): void{
    }

    private function handleTakeFromBundle(TakeFromBundleStackRequestAction $action): void{
    }

    private function handleLabTableCombine(LabTableCombineStackRequestAction $action): void{
    }

    private function handleBeaconPayment(BeaconPaymentStackRequestAction $action): void{
        $player = $this->session->getPlayer();
        $currentInventory = $player->getCurrentWindow();

        if($currentInventory instanceof BeaconInventory){
            $currentInventory->onBeaconPayment($player, $action->getPrimaryEffectId(), $action->getSecondaryEffectId());
        }
    }

    private function handleMineBlock(MineBlockStackRequestAction $action): void{
    }

    /**
     * @param CraftRecipeStackRequestAction $action
     * @throws Exception
     * Crafting normally without using auto
     */
    private function handleCraftRecipe(CraftRecipeStackRequestAction $action): void{
        $netId = $action->getRecipeId();
        $player = $this->session->getPlayer();
        $currentInventory = $player->getCurrentWindow();

        if($currentInventory instanceof RecipeInventory){
            $this->createdOutput = $currentInventory->getResultItem($player, $netId);
            return;
        }
        $this->craft($netId);
    }

    /**
     * @param CraftRecipeAutoStackRequestAction $action
     * @throws Exception
     * Whenever you auto craft
     */
    private function handleCraftRecipeAuto(CraftRecipeAutoStackRequestAction $action): void{
        $this->craft($action->getRecipeId(), $action->getRepetitions(), true);
    }

    private function craft(int $netId, int $repetitions = 0, bool $auto = false): void{
        $recipe = InventoryManager::getInstance()->getRecipeByNetId($netId);
        if($recipe === null){
            throw new Exception("Failed to find recipe for id: " . $netId);
        }
        if(!$recipe instanceof ShapedRecipe && !$recipe instanceof ShapelessRecipe){
            throw new Exception("Recipe is not Shaped or Shapeless");
        }
        if($recipe->getBlockName() !== CraftingRecipeBlockName::CRAFTING_TABLE){
            throw new Exception("This recipe is not for crafting table");
        }
        if($recipe instanceof ShapedRecipe){
            $outputs = $recipe->getOutput();
        }else{
            $outputs = $recipe->getOutputs();
        }
        $ev = new CraftItemStackEvent($this->session->getPlayer(), $recipe, TypeConverter::getInstance()->netItemStackToCore($outputs[0]), $repetitions, $auto);
        $ev->call();
        if($ev->isCancelled()){
            $ev->setResult(VanillaItems::AIR());
            VanillaX::getInstance()->getLogger()->debug("Failed to execute CraftItemStack: Event Cancelled");
        }

        $this->createdOutput = $ev->getResult();
    }

    /**
     * @param CreativeCreateStackRequestAction $action
     * @throws Exception
     * Taking item from creative inventory into cursor
     */
    private function handleCreativeCreate(CreativeCreateStackRequestAction $action): void{
        $player = $this->session->getPlayer();

        if(!$player->isCreative()){
            throw new Exception("received CreativeCreateStackRequestAction while not being in creative");
        }
        $inventory = CreativeInventory::getInstance();
        $humanIndex = $action->getCreativeItemId();

        if($humanIndex > ($maxIndex = count($inventory->getAll()))){
            throw new Exception("received CreativeCreateStackRequestAction, expected index below $maxIndex, received $humanIndex.");
        }
        $ev = new CreativeCreateItemStackEvent($player, $inventory->getItem($humanIndex), $humanIndex);
        $ev->call();

        if($ev->isCancelled()){
            VanillaX::getInstance()->getLogger()->debug("Failed to execute CreativeCreateItemStack: Event Cancelled");
            return;
        }
        $this->createdOutput = $ev->getItem();
    }

    private function handleCraftRecipeOptional(CraftRecipeOptionalStackRequestAction $action, array $filterStrings): void{
        $player = $this->session->getPlayer();
        $currentInventory = $player->getCurrentWindow();

        if($currentInventory instanceof AnvilInventory){
            $this->createdOutput = $currentInventory->getResultItem($player, $action->getFilterStringIndex(), $filterStrings);
        }
    }

    private function handleGrindstone(GrindstoneStackRequestAction $action): void{
    }

    private function handleLoom(LoomStackRequestAction $action): void{
    }

    private function handleDeprecatedCraftingNonImplemented(DeprecatedCraftingNonImplementedStackRequestAction $action): void{
    }

    private function handleDeprecatedCraftingResults(DeprecatedCraftingResultsStackRequestAction $action): void{
        $player = $this->session->getPlayer();
        $window = $player->getCurrentWindow();

        if($window instanceof TradeInventory){
            $createdOutput = VanillaItems::AIR();
            $buyA = $window->getItem(0);
            $buyB = $window->getItem(1);

            if(!$buyA->isNull()){
                $result = TypeConverter::getInstance()->netItemStackToCore($action->getResults()[0]);

                foreach($window->getVillager()->getOffers()->getOffers() as $offers){
                    foreach($offers as $offer){
                        if($buyA->equals($offer->getInput()) && $result->equals($offer->getResult())){
                            if(!$buyB->isNull() && ($offer->getInput2() === null || !$buyB->equals($offer->getInput2()))){
                                //if buyB is found but slot is null or not equal
                                continue;
                            }
                            if($offer->getUses() >= $offer->getMaxUses()){
                                //used all max uses
                                continue;
                            }
                            $experience = $offer->getTraderExp();

                            $offer->setUses($offer->getUses() + 1);
                            if($experience > 0){
                                $window->getVillager()->setExperience($window->getVillager()->getExperience() + $experience);
                            }
                            $createdOutput = $offer->getResult();

                            $ev = new TradeItemEvent($player, $window->getVillager(), $offer->getInput(), $offer->getInput2(), $createdOutput, $experience);
                            $ev->call();
                            break 2;
                        }
                    }
                }
            }
            $this->createdOutput = $createdOutput;
        }
    }

    private function acceptRequest(int $requestId): void{
        $this->session->sendDataPacket(ItemStackResponsePacket::create([
            new ItemStackResponse(ItemStackResponse::RESULT_OK, $requestId, $this->containerInfo)
        ]));
        $this->containerInfo = [];
        $this->createdOutput = null;
    }

    private function rejectRequest(int $requestId): void{
        $this->session->sendDataPacket(ItemStackResponsePacket::create([
            new ItemStackResponse(ItemStackResponse::RESULT_ERROR, $requestId, $this->containerInfo)
        ]));
        $this->containerInfo = [];
        $this->createdOutput = null;
    }

    private function getInventory(int $id): Inventory{
        $inventory = ItemStackTranslator::translateContainerId($this->session->getPlayer(), $id);

        if(!$inventory){
            throw new Exception("Failed to find container with id of $id");
        }
        return $inventory;
    }

    private function getItemFromStack(ItemStackRequestSlotInfo $slotInfo): Item{
        $inventory = $this->getInventory($slotInfo->getContainerId());
        return $inventory->getItem(ItemStackTranslator::netSlot($slotInfo->getSlotId(), $inventory));
    }

    private function setItemInStack(ItemStackRequestSlotInfo $slotInfo, Item $item): void{
        $session = SessionManager::getInstance()->get($this->session->getPlayer());
        $index = $slotInfo->getSlotId();
        $inventory = $this->getInventory($containerId = $slotInfo->getContainerId());
        $netSlot = ItemStackTranslator::netSlot($index, $inventory);
        $itemStack = $session->trackItemStack($inventory, $netSlot, $item, null);

//        var_dump("Index $index " . $item->getName() . " " . $item->getCount() . " " . get_class($inventory));
        $this->containerInfo[] = new ItemStackResponseContainerInfo($containerId, [
            new ItemStackResponseSlotInfo(
                $index,
                $index,
                $item->getCount(),
                $itemStack->getStackId(),
                "",
                $item instanceof Durable ? $item->getDamage() : 0
            )
        ]);
        $inventory->setItem($netSlot, $item);
    }
}
