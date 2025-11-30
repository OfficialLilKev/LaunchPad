<?php

declare(strict_types=1);

namespace LaunchPad;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\world\sound\GhastShootSound;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\Config;

class LaunchPadPlugin extends PluginBase implements Listener {

    private array $cooldowns = [];
    private float $force;
    private float $verticalLift;
    private bool $enableSound;
    private array $targetBlocks = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->loadConfiguration();
        
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("§aLaunchPad 2.0 Enabled! Ready to launch.");
    }

    private function loadConfiguration(): void {
        $config = $this->getConfig();
        
        $this->force = (float) $config->get("force", 2.0);
        $this->verticalLift = (float) $config->get("vertical_lift", 0.8);
        $this->enableSound = (bool) $config->get("sound", true);
        
        // Convert config strings to a fast lookup array
        $blocks = $config->get("blocks", ["oak_pressure_plate"]);
        foreach ($blocks as $blockName) {
            $this->targetBlocks[$blockName] = true;
        }
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        // Check if player is in cooldown to prevent spam/glitches
        if (isset($this->cooldowns[$name])) {
            if (time() - $this->cooldowns[$name] < 1) {
                return;
            }
            unset($this->cooldowns[$name]);
        }

        // Get the block strictly under the player's feet
        $block = $player->getWorld()->getBlock($player->getPosition());
        
        // In PM5, we use names, not IDs. Check if this block is in our config.
        // We replace spaces with underscores to match standard item names if needed.
        $blockName = str_replace(" ", "_", strtolower($block->getName()));

        if (isset($this->targetBlocks[$blockName])) {
            
            // 1. Calculate Launch Vector
            $direction = $player->getDirectionVector();
            
            // Apply the force from config
            $direction->x *= $this->force;
            $direction->z *= $this->force;
            
            // Apply hardcoded lift so they go UP and forward
            $direction->y = $this->verticalLift;

            // 2. Apply Physics (Motion)
            $player->setMotion($direction);
            
            // 3. Play Sound
            if ($this->enableSound) {
                $player->getWorld()->addSound($player->getPosition(), new GhastShootSound());
            }

            // 4. Set Cooldown
            $this->cooldowns[$name] = time();
        }
    }
}
