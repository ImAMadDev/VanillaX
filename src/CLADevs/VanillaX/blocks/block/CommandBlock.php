<?php

namespace CLADevs\VanillaX\blocks\block;

use CLADevs\VanillaX\blocks\tile\CommandBlockTile;
use CLADevs\VanillaX\blocks\utils\facing\AnyFacingTrait;
use CLADevs\VanillaX\utils\item\NonAutomaticCallItemTrait;
use CLADevs\VanillaX\utils\item\NonCreativeItemTrait;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\BlockToolType;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;

class CommandBlock extends Block implements NonAutomaticCallItemTrait, NonCreativeItemTrait{
    use AnyFacingTrait;

    public function __construct(int $id, int $meta){
        parent::__construct(new BlockIdentifier($id, $meta, $id, CommandBlockTile::class), self::asCommandBlockName($id), new BlockBreakInfo(-1, BlockToolType::NONE, 0, 3600000));
    }

    public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null): bool{
        if($player !== null && $player->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
            $player->getNetworkSession()->sendDataPacket(ContainerOpenPacket::blockInv(ContainerIds::NONE, WindowTypes::COMMAND_BLOCK, BlockPosition::fromVector3($this->getPosition())));
        }
        return true;
    }

    public function onScheduledUpdate(): void{
        $tile = $this->position->getWorld()->getTile($this->position);

        if(!$tile instanceof CommandBlockTile || $tile->isClosed()){
            return;
        }
        if($tile->getTickDelay() > 0 && $tile->getCountDelayTick() > 0){
            $tile->decreaseCountDelayTick();
        }else{;
            $tile->runCommand();
            if($tile->getCommandBlockMode() === CommandBlockTile::TYPE_REPEAT){
                $tile->setCountDelayTick($tile->getTickDelay());
                $this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
            }
        }
    }

    public function getMode(): int{
        if($this->getId() == BlockLegacyIds::REPEATING_COMMAND_BLOCK){
            return CommandBlockTile::TYPE_REPEAT;
        }elseif($this->getId() == BlockLegacyIds::CHAIN_COMMAND_BLOCK){
            return CommandBlockTile::TYPE_CHAIN;
        }
        return CommandBlockTile::TYPE_IMPULSE;
    }

    public static function asCommandBlockName(int $id): string{
        if($id === BlockLegacyIds::REPEATING_COMMAND_BLOCK){
            return "Repeating Command Block";
        }elseif($id === BlockLegacyIds::CHAIN_COMMAND_BLOCK){
            return "Chain Command Block";
        }
        return "Command Block";
    }

    public static function asCommandBlockFromMode(int $mode): int{
        if($mode == CommandBlockTile::TYPE_REPEAT){
            return BlockLegacyIds::REPEATING_COMMAND_BLOCK;
        }elseif($mode == CommandBlockTile::TYPE_CHAIN){
            return BlockLegacyIds::CHAIN_COMMAND_BLOCK;
        }
        return BlockLegacyIds::COMMAND_BLOCK;
    }
}