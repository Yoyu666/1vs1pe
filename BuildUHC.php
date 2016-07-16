<?php
namespace JMD\BuildUHC;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\Listener;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat as TE;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\tile\Chest;
use pocketmine\inventory\ChestInventory;
use onebone\economyapi\EconomyAPI;
use pocketmine\event\player\PlayerQuitEvent;
use JMD\BuildUHC\ResetMap;
use pocketmine\entity\Effect;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\block\Air;

class BuildUHC extends PluginBase implements Listener {

    public $prefix = TE::GRAY . "[" . TE::GREEN . TE::BOLD . "Build" . TE::RED . "UHC" . TE::RESET . TE::GRAY . "]";
	public $mode = 0;
	public $arenas = array();
	public $currentLevel = "";
	
	public function onEnable()
	{
		  $this->getLogger()->info(TE::DARK_AQUA . "BuildUHC by JMD");
                  
                $this->getServer()->getPluginManager()->registerEvents($this ,$this);
                $this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
                if(!empty($this->economy))
                {
                $this->api = EconomyAPI::getInstance();
                }
		@mkdir($this->getDataFolder());
                $config2 = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
		$config2->save();
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                if($config->get("money")==null)
                {
                   $config->set("money",500);
                }
		if($config->get("arenas")!=null)
		{
			$this->arenas = $config->get("arenas");
		}
		foreach($this->arenas as $lev)
		{
			$this->getServer()->loadLevel($lev);
		}
		$items = array(array(1,0,30),array(1,0,20),array(3,0,15),array(3,0,25),array(4,0,35),array(4,0,15),array(260,0,5),array(261,0,1),array(262,0,6),array(267,0,1),array(268,0,1),array(272,0,1),array(276,0,1),array(283,0,1),array(297,0,3),array(298,0,1),array(299,0,1),array(300,0,1),array(301,0,1),array(303,0,1),array(304,0,1),array(310,0,1),array(313,0,1),array(314,0,1),array(315,0,1),array(316,0,1),array(317,0,1),array(320,0,4),array(354,0,1),array(364,0,4),array(366,0,5),array(391,0,5),array(322,0,2));
		if($config->get("chestitems")==null)
		{
			$config->set("chestitems",$items);
		}
		$config->save();
		
		$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
		$playerlang->save();
		
		$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
		if($lang->get("en")==null)
		{
			$messages = array();
			$messages["kill"] = "was killed by";
			$messages["cannotjoin"] = "You can't join.";
			$messages["seconds"] = "seconds to start";
			$messages["deathmatchminutes"] = "minutes to DeathMatch!";
			$messages["deathmatchseconds"] = "seconds to DeathMatch!";
			$messages["chestrefill"] = "The chest have been refilled!";
			$messages["remainingminutes"] = "minutes remaining!";
			$messages["remainingseconds"] = "seconds remaining!";
			$messages["nowinner"] = "§fNo winner in arena: §b";
			$messages["moreplayers"] = "Need more players!";
			$lang->set("en",$messages);
		}
		$lang->save();
                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                $slots->save();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 10);
	}
	
	public function onDeath(PlayerDeathEvent $event){
        $jugador = $event->getEntity();
        $mapa = $jugador->getLevel()->getFolderName();
        if(in_array($mapa,$this->arenas))
		{
                if($event->getEntity()->getLastDamageCause() instanceof EntityDamageByEntityEvent)
                {
                $asassin = $event->getEntity()->getLastDamageCause()->getDamager();
                if($asassin instanceof Player){
                $event->setDeathMessage("");
                foreach($jugador->getLevel()->getPlayers() as $pl){
				$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
				$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
				$toUse = $lang->get($playerlang->get($pl->getName()));
                                $muerto = $jugador->getNameTag();
                                $asesino = $asassin->getNameTag();
				$pl->sendMessage(TE::RED . $muerto . TE::YELLOW . " " . $toUse["kill"] . " " . TE::GREEN . $asesino . TE::YELLOW . ".");
			}
                }
                }
                $jugador->setNameTag($jugador->getName());
                }
        }
	
	public function onMove(PlayerMoveEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$sofar = $config->get($level . "StartTime");
			if($sofar > 0)
			{
				$to = clone $event->getFrom();
				$to->yaw = $event->getTo()->yaw;
				$to->pitch = $event->getTo()->pitch;
				$event->setTo($to);
			}
		}
	}
	
	public function onLogin(PlayerLoginEvent $event)
	{
		$player = $event->getPlayer();
		$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
		if($playerlang->get($player->getName())==null)
		{
			$playerlang->set($player->getName(),"en");
			$playerlang->save();
		}
		$statistic = new Config($this->getDataFolder() . "/statistic.yml", Config::YAML);
		if($statistic->get($player->getName())==null)
		{
			$statistic->set($player->getName(),array(0,0));
			$statistic->save();
		}
		$player->getInventory()->clearAll();
		$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
		$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
		$player->teleport($spawn,0,0);
	}
        
        public function onQuit(PlayerQuitEvent $event)
        {
            $pl = $event->getPlayer();
            $level = $pl->getLevel()->getFolderName();
            if(in_array($level,$this->arenas))
            {
                $pl->removeAllEffects();
                $pl->getInventory()->clearAll();
                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                $pl->setNameTag($pl->getName());
                if($slots->get("slot1".$level)==$pl->getName())
                {
                    $slots->set("slot1".$level, 0);
                }
                if($slots->get("slot2".$level)==$pl->getName())
                {
                    $slots->set("slot2".$level, 0);
                }
                $slots->save();
            }
        }
	
	public function onBlockBreak(BlockBreakEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
                        $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                        if($config->get($level . "PlayTime") != null)
                        {
                                if($config->get($level . "PlayTime") > 299)
                                {
                                        $event->setCancelled(true);
                                }
                        }
		}
	}
	
	public function onBlockPlace(BlockPlaceEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
			$event->setCancelled(false);
		}
	}
	
	public function onDamage(EntityDamageEvent $event)
	{
		if($event instanceof EntityDamageByEntityEvent)
		{
			$player = $event->getEntity();
			$damager = $event->getDamager();
			if($player instanceof Player)
			{
				if($damager instanceof Player)
				{
					$level = $player->getLevel()->getFolderName();
					$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($level . "PlayTime") != null)
					{
						if($config->get($level . "PlayTime") > 300)
						{
							$event->setCancelled(true);
						}
					}
				}
			}
		}
	}
	
	public function onCommand(CommandSender $player, Command $cmd, $label, array $args) {
		$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
        switch($cmd->getName()){
			case "buhc":
				if($player->isOp())
				{
					if(!empty($args[0]))
					{
						if($args[0]=="make")
						{
							if(!empty($args[1]))
							{
								if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1]))
								{
									$this->getServer()->loadLevel($args[1]);
									$this->getServer()->getLevelByName($args[1])->loadChunk($this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
									array_push($this->arenas,$args[1]);
									$this->currentLevel = $args[1];
									$this->mode = 1;
									$player->sendMessage($this->prefix . "Touch the spawn points!");
									$player->setGamemode(1);
									$player->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(),0,0);
                                                                        $name = $args[1];
                                                                        $this->zipper($player, $name);
								}
								else
								{
									$player->sendMessage($this->prefix . "ERROR missing world.");
								}
							}
							else
							{
								$player->sendMessage($this->prefix . "ERROR missing parameters.");
							}
						}
						else
						{
							$player->sendMessage($this->prefix . "Invalid Command.");
						}
					}
					else
					{
					 $player->sendMessage($this->prefix . "BuildUHC Commands!");
                                         $player->sendMessage($this->prefix . "/buhc make [world]: Create a buhc game!");
                                         $player->sendMessage($this->prefix . "/buhcstart: start the game");
                                         $player->sendMessage($this->prefix . "/lang: Select language");
					}
				}
				else
				{
				}
			return true;
			
			case "lang":
				if(!empty($args[0]))
				{
					if($lang->get($args[0])!=null)
					{
						$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
						$playerlang->set($player->getName(),$args[0]);
						$playerlang->save();
						$player->sendMessage(TE::GREEN . "Lang: " . $args[0]);
					}
					else
					{
						$player->sendMessage(TE::RED . "Language not found!");
					}
				}
			return true;
                        
                        case "buhcstart":
                            if($player->isOp())
				{
                                $player->sendMessage("§aStarting in 10 sec...");
                                $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                                $config->set("arenas",$this->arenas);
                                foreach($this->arenas as $arena)
                                {
                                        $config->set($arena . "PlayTime", 300);
                                        $config->set($arena . "StartTime", 10);
                                }
                                $config->save();
                                }
                                return true;
								

                                
                        case "rankbuhc":
				if($player->isOp())
				{
				if(!empty($args[0]))
				{
					if(!empty($args[1]))
					{
					$rank = "";
					if($args[0]=="VIP")
					{
						$rank = "§b[§5VIP§b]";
					}
					else if($args[0]=="VIP+")
					{
						$rank = "§b[§6VIP§4+§b]";
					}
                                        else
                                        {
                                            goto end;
                                        }
                                        $config = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
					$config->set($args[1],$rank);
					$config->save();
					$player->sendMessage(TE::AQUA.$args[1].TE::GREEN." got rank: ".TE::YELLOW.$rank);
                                        end:
                                        }
                                }
                                }
                                return true;
                                
                        case "money":
                            if($player->isOp())
				{
				if(!empty($args[0]))
				{
                                    $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                                    $config->set("money",$args[0]);
                                    $config->save();
                                    $player->sendMessage(TE::GREEN."Prize of BuildUHC is: ".TE::AQUA.$args[0]);
                                }
                                }
                                return true;
	}
        }
        
        public function setkit($p)
        {
            $config = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
			
		}
            
	
	public function onInteract(PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$tile = $player->getLevel()->getTile($block);
		
		if($tile instanceof Sign) 
		{
			if($this->mode==26)
			{
				$tile->setText(TE::AQUA . "[Join]",TE::YELLOW  . "0 / 2","§f" . $this->currentLevel,$this->prefix);
				$this->refreshArenas();
				$this->currentLevel = "";
				$this->mode = 0;
				$player->sendMessage($this->prefix . "Arena Registered!");
			}
			else
			{
				$text = $tile->getText();
				if($text[3] == $this->prefix)
				{
					if($text[0]==TE::AQUA . "[Join]")
					{
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                                                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                                                $namemap = str_replace("§f", "", $text[2]);
						$level = $this->getServer()->getLevelByName($namemap);
                                                if($slots->get("slot1".$namemap)==null)
                                                {
                                                        $thespawn = $config->get($namemap . "Spawn1");
                                                        $slots->set("slot1".$namemap, $player->getName());
                                                        $slots->save();
                                                }
                                                else if($slots->get("slot2".$namemap)==null)
                                                {
                                                        $thespawn = $config->get($namemap . "Spawn2");
                                                        $slots->set("slot2".$namemap, $player->getName());
                                                        $slots->save();
                                                }
                                                foreach($level->getPlayers() as $playersinarena)
                                                        {
                                                        $playersinarena->sendMessage("§l§8»§r§5" . $player->getName() . " §bhas joined the BuildUHC");
                                                        }
						$spawn = new Position($thespawn[0]+0.5,$thespawn[1],$thespawn[2]+0.5,$level);
						$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
						$player->teleport($spawn,0,0);
						$player->getInventory()->clearAll();
                                                $player->removeAllEffects();
                                                $player->setHealth(20);
                                                $this->setkit($player);
                                                $buhc = Effect::getEffect(14);
                                                $buhc->setVisible(true);
                                                $buhc->setDuration(30);
                                                $player->addEffect($buhc);
					}
					else
					{
						$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
						$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
						$toUse = $lang->get($playerlang->get($player->getName()));
						$player->sendMessage($this->prefix . $toUse["cannotjoin"]);
					}
				}
			}
		}
		else if($this->mode>=1&&$this->mode<=1)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+1,$block->getZ()));
			$player->sendMessage($this->prefix . "Spawn " . $this->mode . " has been registered!");
			$this->mode++;
			$config->save();
		}
		else if($this->mode==2)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+1,$block->getZ()));
			$player->sendMessage($this->prefix . "Spawn " . $this->mode . " has been registered!");
			$config->set("arenas",$this->arenas);
			$player->sendMessage($this->prefix . "Touch Sign to register Arena!");
			$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
			$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn,0,0);
			$config->save();
			$this->mode=26;
		}
	}
	
	public function refreshArenas()
	{
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$config->set("arenas",$this->arenas);
		foreach($this->arenas as $arena)
		{
			$config->set($arena . "PlayTime", 300);
			$config->set($arena . "StartTime", 10);
		}
		$config->save();
	}
        
        public function zipper($player, $name)
        {
        $path = realpath($player->getServer()->getDataPath() . 'worlds/' . $name);
				$zip = new \ZipArchive;
				@mkdir($this->getDataFolder() . 'arenas/', 0755);
				$zip->open($this->getDataFolder() . 'arenas/' . $name . '.zip', $zip::CREATE | $zip::OVERWRITE);
				$files = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($path),
					\RecursiveIteratorIterator::LEAVES_ONLY
				);
                                foreach ($files as $datos) {
					if (!$datos->isDir()) {
						$relativePath = $name . '/' . substr($datos, strlen($path) + 1);
						$zip->addFile($datos, $relativePath);
					}
				}
				$zip->close();
				$player->getServer()->loadLevel($name);
				unset($zip, $path, $files);
        }
}

class RefreshSigns extends PluginTask {
    public $prefix = TE::GRAY . "[" . TE::GREEN . TE::BOLD . "Build" . TE::RED . "UHC" . TE::RESET . TE::GRAY . "]";
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
  
	public function onRun($tick)
	{
		$allplayers = $this->plugin->getServer()->getOnlinePlayers();
		$level = $this->plugin->getServer()->getDefaultLevel();
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Sign) {	
				$text = $t->getText();
				if($text[3]==$this->prefix)
				{
					$aop = 0;
                                        $namemap = str_replace("§f", "", $text[2]);
					foreach($allplayers as $player){if($player->getLevel()->getFolderName()==$namemap){$aop=$aop+1;}}
					$ingame = TE::AQUA . "[Join]";
					$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($namemap . "PlayTime")!=300)
					{
						$ingame = TE::DARK_PURPLE . "[Playing]";
					}
					else if($aop>=2)
					{
						$ingame = TE::GOLD . "[Full]";
					}
					$t->setText($ingame,TE::YELLOW  . $aop . " / 2",$text[2],$this->prefix);
				}
			}
		}
	}
}

class GameSender extends PluginTask {
    public $prefix = TE::GRAY . "[" . TE::GREEN . TE::BOLD . "Build" . TE::RED . "UHC" . TE::RESET . TE::GRAY . "]";
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
        
        public function getResetmap() {
        Return new ResetMap($this);
        }
  
	public function onRun($tick)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$arenas = $config->get("arenas");
                $money = $config->get("money");
		if(!empty($arenas))
		{
			foreach($arenas as $arena)
			{
				$time = $config->get($arena . "PlayTime");
				$timeToStart = $config->get($arena . "StartTime");
				$levelArena = $this->plugin->getServer()->getLevelByName($arena);
				if($levelArena instanceof Level)
				{
					$playersArena = $levelArena->getPlayers();
					if(count($playersArena)==0)
					{
						$config->set($arena . "PlayTime", 300);
						$config->set($arena . "StartTime", 10);
					}
					else
					{
						if(count($playersArena)>=2)
						{
							if($timeToStart>0)
							{
								$timeToStart--;
								foreach($playersArena as $pl)
								{
									$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
									$toUse = $lang->get($playerlang->get($pl->getName()));
									$pl->sendPopup(TE::GREEN . $timeToStart . " " . $toUse["seconds"].TE::RESET);
								}
                                                                if($timeToStart==9)
                                                                {
                                                                    $levelArena->setTime(0);
                                                                    $levelArena->stopTime();
                                                                }
								if($timeToStart<=0)
								{
									$this->refillChests($levelArena);
                                                                        foreach($playersArena as $pl)
                                                                        {
                                                                            $pl->getInventory()->setContents(array(Item::get(0, 0, 0)));
							                                                $pl->getInventory()->setHelmet(Item::get(Item::DIAMOND_HELMET));
							                                                $pl->getInventory()->setChestplate(Item::get(Item::DIAMOND_CHESTPLATE));
							                                                $pl->getInventory()->setLeggings(Item::get(Item::DIAMOND_LEGGINGS));
							                                                $pl->getInventory()->setBoots(Item::get(Item::DIAMOND_BOOTS));
							                                                $pl->getInventory()->setItem(0, Item::get(Item::DIAMOND_SWORD, 0, 1));
							                                                $pl->getInventory()->addItem(Item::get(ITEM::COBBLESTONE, 0, 64));
		                                                                    $pl->getInventory()->addItem(Item::get(ITEM::GOLDEN_APPLE, 0, 20));
							                                                $pl->getInventory()->addItem(Item::get(ITEM::BOW, 0, 1));
							                                                $pl->getInventory()->addItem(Item::get(ITEM::ARROW, 0, 64));
		                                                                    $pl->getInventory()->setHotbarSlotIndex(0, 0);	
                                                                           
                                                                        }
								}
								$config->set($arena . "StartTime", $timeToStart);
								 
							}
							else
							{
								$aop = count($levelArena->getPlayers());
								if($aop==1)
								{
									foreach($playersArena as $pl)
									{
										$pl->getInventory()->clearAll();
										$pl->removeAllEffects();
										$pl->teleport($this->getOwner()->getServer()->getDefaultLevel()->getSafeSpawn(),0,0);
                                                                                $pl->setHealth(20);
                                                                                $pl->setFood(20);
                                                                                $pl->setNameTag($pl->getName());
                                                                                if(!empty($this->plugin->api))
                                                                                {
                                                                                $this->plugin->api->addMoney($pl,$money);
                                                                                }
                                                                                $this->getResetmap()->reload($levelArena);
                                                                                }
                                                                                $config->set($arena . "PlayTime", 300);
                                                                                $config->set($arena . "StartTime", 10);
								}
                                                                if(($aop>=2))
                                                                    {
                                                                    foreach($playersArena as $pl)
                                                                        {
                                                                        $pl->sendPopup(TE::GOLD." You are currently on BuildUHC".TE::RESET);
                                                                        }
                                                                }
								$time--;
								if($time == 299)
								{
                                                                        $slots = new Config($this->plugin->getDataFolder() . "/slots.yml", Config::YAML);
                                                                        $slots->set("slot1".$arena, 0);
                                                                        $slots->set("slot2".$arena, 0);
                                                                    
                                                                        $slots->save();
									foreach($playersArena as $pl)
									{
										$pl->sendMessage("§e>--------------------------------");
                                                                                $pl->sendMessage("§e>§cAttention: §6The game is starting!");
                                                                                $pl->sendMessage("§e>§fUsing the map: §b" . $arena);
                                                                                $pl->sendMessage("§e>--------------------------------");
									}
								}
                                                                if($time == 250)
								{
									foreach($playersArena as $pl)
									{
										$pl->sendMessage("§e>--------------------------");
                                                                                $pl->sendMessage("§e>§5Made by JMD");
                                                                                $pl->sendMessage("§e>--------------------------");
									}
								}
                                   
								if($time>=300)
								{
								$time2 = $time - 180;
								$minutes = $time2 / 60;
								}
								else
								{
									$minutes = $time / 60;
									if(is_int($minutes) && $minutes>0)
									{
										foreach($playersArena as $pl)
										{
											$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
                                                                                        $lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
											$toUse = $lang->get($playerlang->get($pl->getName()));
											$pl->sendMessage($this->prefix . $minutes . " " . $toUse["remainingminutes"]);
										}
									}
									else if($time == 30 || $time == 15 || $time == 10 || $time ==5 || $time ==4 || $time ==3 || $time ==2 || $time ==1)
									{
										foreach($playersArena as $pl)
										{
											$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
                                                                                        $lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
											$toUse = $lang->get($playerlang->get($pl->getName()));
											$pl->sendMessage($this->prefix . $time . " " . $toUse["remainingseconds"]);
										}
									}
									if($time <= 0)
									{
										foreach($playersArena as $pl)
										{
											$pl->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(),0,0);
											$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
                                                                                        $lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
											$toUse = $lang->get($playerlang->get($pl->getName()));
											$pl->sendMessage($this->prefix . $toUse["nowinner"].$arena);
											$pl->getInventory()->clearAll();
                                                                                        $pl->removeAllEffects();
                                                                                        $pl->setFood(20);
                                                                                        $pl->setHealth(20);
                                                                                        $pl->setNameTag($pl->getName());
                                                                                        $this->getResetmap()->reload($levelArena);
										}
										$time = 300;
									}
								}
								$config->set($arena . "PlayTime", $time);
							}
						}
						else
						{
							if($timeToStart<=0)
							{
								foreach($playersArena as $pl)
								{
									$pl->teleport($this->getOwner()->getServer()->getDefaultLevel()->getSafeSpawn(),0,0);
									$pl->getInventory()->clearAll();
										$pl->sendMessage($this->prefix . $pl->getName() . " §ehas won BuildUHC");
										$pl->sendMessage(" §aA new BuildUHC game has opened!§c Map:§e§l" . $arena);
                                                                        $pl->removeAllEffects();
                                                                        $pl->setHealth(20);
                                                                        $pl->setNameTag($pl->getName());
                                                                        if(!empty($this->plugin->api))
                                                                        {
                                                                        $this->plugin->api->addMoney($pl,$money);
                                                                        }
                                                                        $this->getResetmap()->reload($levelArena);
								}
								$config->set($arena . "PlayTime", 300);
								$config->set($arena . "StartTime", 10);
							}
							else
							{
								foreach($playersArena as $pl)
								{
									$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
									$toUse = $lang->get($playerlang->get($pl->getName()));
									$pl->sendPopup(TE::DARK_AQUA . $toUse["moreplayers"].TE::RESET);
								}
								$config->set($arena . "PlayTime", 300);
								$config->set($arena . "StartTime", 10);
							}
						}
					}
				}
			}
		}
		$config->save();
	}
	
	public function refillChests(Level $level)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Chest) 
			{
				$chest = $t;
				$chest->getInventory()->clearAll();
				if($chest->getInventory() instanceof ChestInventory)
				{
					for($i=0;$i<=26;$i++)
					{
						$rand = rand(1,3);
						if($rand==1)
						{
							$k = array_rand($config->get("chestitems"));
							$v = $config->get("chestitems")[$k];
							$chest->getInventory()->setItem($i, Item::get($v[0],$v[1],$v[2]));
						}
					}									
				}
			}
		}
	}
}