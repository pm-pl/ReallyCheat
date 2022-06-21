<?php

namespace hachkingtohach1\reallycheat\checks;

use hachkingtohach1\reallycheat\player\RCPlayerAPI;
use hachkingtohach1\reallycheat\config\ConfigManager;
use hachkingtohach1\reallycheat\logging\LogManager;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\console\ConsoleCommandSender;

abstract class Check extends ConfigManager{

    public abstract function getName() :string;

    public abstract function enable() :bool;

    public abstract function ban() :bool;

    public abstract function maxViolations() :int;

    public abstract function check(DataPacket $packet, RCPlayerAPI $player) :void;

    private function replaceText(RCPlayerAPI $player, string $text) :string{
        $keys = [
            "{prefix}",
            "{player}",
            "{module}",
            "{time}",
            "{violation}"
        ];
        $replace = [
            self::getData(self::PREFIX), 
            $player->getName(), 
            $this->getName(), 
            date("F d, Y h:i:sA", time()), 
            $player->getViolation($this->getName())
        ];
        return str_replace($keys, $replace, $text);
    }

    public function failed(RCPlayerAPI $player) :bool{
        $randomNumber = rand(0, 100);
        $server = $player->getServer();
        $randomizeBan = self::getData(self::BAN_RANDOMIZE) === true ? ($randomNumber > 75 ? true : false) : true;
        $randomizeTransfer = self::getData(self::TRANSFER_RANDOMIZE) === true ? ($randomNumber > 75 ? true : false) : true;
        $notify = self::getData(self::ALERTS_ENABLE) === true ? (self::getData(self::ALERTS_ADMIN) === true ? ($player->hasPermission(self::getData(self::PERMISSION_BYPASS_PERMISSION)) ? true : false) : true) : false;
        $byPass = self::getData(self::PERMISSION_BYPASS_ENABLE) === true ? ($player->hasPermission(self::getData(self::PERMISSION_BYPASS_PERMISSION)) ? true : false) : false;
        $reachedMaxViolations = $player->getViolation($this->getName()) > $this->maxViolations() ? true : false;
        if($byPass){
            return false;
        }
        $player->addViolation($this->getName());
        if($notify){
            $player->sendMessage($this->replaceText($player, self::getData(self::ALERTS_MESSAGE)));
        }
        //If function ban is true => transfer not active
        if($reachedMaxViolations and $this->ban() and $randomizeBan and self::getData(self::BAN_ENABLE) === true){           
            foreach(self::getData(self::BAN_COMMANDS) as $command){
                $server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), $this->replaceText($player, $command));
                $server->broadcastMessage($this->replaceText($player, self::getData(self::BAN_MESSAGE)));           
            }
            LogManager::sendLogger($this->replaceText($player, self::getData(self::BAN_RECENT_LOGS_MESSAGE)));
        }
        if($reachedMaxViolations and !$this->ban() and $randomizeTransfer){         
            if(self::getData(self::TRANSFER_USECOMMAND_ENABLE) === true){
                foreach(self::getData(self::TRANSFER_USECOMMAND_COMMANDS) as $command){
                    $server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), $this->replaceText($player, $command));        
                }
            }else{
                $ip = explode(":", self::TRANSFER_IP);
                $port = isset($ip[1]) ? (is_numeric($ip[1]) ? $ip[1] : 19132) : 19123;
                $player->transfer($ip[0], $port);
            }
            $server->broadcastMessage($this->replaceText($player, self::getData(self::TRANSFER_MESSAGE)));
            LogManager::sendLogger($this->replaceText($player, self::getData(self::TRANSFER_RECENT_LOGS_MESSAGE)));
        }
        return true;      
    }

}