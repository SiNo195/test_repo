<?php

namespace ojy\warn;

use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;

class SWarn extends PluginBase implements Listener
{

    /** @var string */
    public const PREFIX = "§l§b[알림] §r§7";

    /** @var Config */
    public static $data;
    /** @var array */
    public static $db;

    /** @var Config */
    public static $setting;

    public function onEnable()
    {

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        self::$setting = new Config($this->getDataFolder() . "Setting.yml", Config::YAML, [
            "ban-warn-count" => 5,
            "ip-ban-warn-count" => 5
        ]);
        self::$data = new Config($this->getDataFolder() . "WarnData.yml", Config::YAML, ["warn" => [], "players" => [], "ip-bans" => []]);
        self::$db = self::$data->getAll();

        \o\c\c::command("경고", "경고 관련 명령어 입니다.", "/경고", [],
            function (CommandSender $sender, string $commandLabel, array $args) {
                if (!isset($args[0]))
                    $args[0] = 'x';
                switch ($args[0]) {
                    case "정보":
                    case "확인":
                        if (!isset($args[1]))
                            $args[1] = $sender->getName();
                        if (Server::getInstance()->getPlayer($args[1]) !== null)
                            $args[1] = Server::getInstance()->getPlayer($args[1])->getName();
                        $args[1] = strtolower($args[1]);
                        if (isset(self::$db["warn"][$args[1]]) && count(self::$db["warn"][$args[1]]) > 0) {
                            $sender->sendMessage(self::PREFIX . "경고 목록을 출력합니다.");
                            foreach (self::$db["warn"][$args[1]] as $warnId => $data) {
                                $amount = $data[0];
                                $why = $data[1];
                                $sender->sendMessage("§l§b[{$warnId}] §r§7경고 수: {$amount}, 사유: {$why}");
                            }
                        } else {
                            $sender->sendMessage(self::PREFIX . "받은 경고가 없습니다.");
                        }
                        break;
                    case "제거":
                        if ($sender->isOp()) {
                            if (isset($args[1]) && isset($args[2])) {
                                $v = array_shift($args);
                                $name = array_shift($args);
                                $warnId = array_shift($args);
                                if (Server::getInstance()->getPlayer($name) !== null)
                                    $name = Server::getInstance()->getPlayer($name)->getName();
                                if (isset(self::$db["warn"][strtolower($name)][$warnId])) {
                                    $sender->sendMessage(self::PREFIX . "{$name} 님의 {$warnId}번 경고를 {$v}했습니다.");
                                    unset(self::$db["warn"][strtolower($name)][$warnId]);
                                } else {
                                    $sender->sendMessage(self::PREFIX . "경고 정보를 찾을 수 없습니다.");
                                }
                            } else {
                                $sender->sendMessage(self::PREFIX . "/경고 제거 [닉네임] [번호]");
                            }
                        } else {
                            $sender->sendMessage(self::PREFIX . "이 명령어를 사용할 권한이 없습니다.");
                        }
                        break;
                    case "추가":
                        if ($sender->isOp()) {
                            $v = array_shift($args);
                            $name = array_shift($args);
                            $amount = array_shift($args);
                            if (isset($name) && isset($amount) && is_numeric($amount)) {
                                if (Server::getInstance()->getPlayer($name) !== null)
                                    $name = Server::getInstance()->getPlayer($name)->getName();
                                if (count($args) <= 0)
                                    $why = "서버 관리자의 경고";
                                else
                                    $why = implode(" ", $args);
                                self::addWarn($name, $amount, $why);
                            } else {
                                $sender->sendMessage(self::PREFIX . "/경고 추가 [닉네임] [횟수] [사유]");
                            }
                        } else {
                            $sender->sendMessage(self::PREFIX . "이 명령어를 사용할 권한이 없습니다.");
                        }
                        break;
                    default:
                        if ($sender->isOp()) {
                            $sender->sendMessage(self::PREFIX . "/경고 추가 [닉네임] [횟수] [사유]");
                            $sender->sendMessage(self::PREFIX . "/경고 제거 [닉네임] [번호]");
                        }
                        $sender->sendMessage(self::PREFIX . "/경고 확인 [닉네임] | 플레이어의 경고 정보를 확인합니다.");
                        break;
                }
            }
        );
    }

    public function onDisable()
    {
        self::$data->setAll(self::$db);
        self::$data->save();
    }

    public static function setBanWarnCount(int $count)
    {
        self::$setting->set("ban-warn-count", $count);
        self::$setting->save();
    }

    public static function setIpBanWarnCount(int $count)
    {
        self::$setting->set("ip-ban-warn-count", $count);
        self::$setting->save();
    }

    public static function getBanWarnCount()
    {
        return self::$setting->get("ban-warn-count");
    }

    public static function getIpBanWarnCount()
    {
        return self::$setting->get("ip-ban-warn-count");
    }

    public static function getTotalWarn(string $playerName)
    {
        $playerName = strtolower($playerName);
        if (isset(self::$db["warn"][$playerName])) {
            $res = 0;
            foreach (self::$db["warn"][$playerName] as $warnId => $data) {
                $res += $data[0];
            }
            return $res;
        }
        return 0;
    }

    public static function addWarn(string $playerName, int $amount, string $why)
    {
        $playerName = strtolower($playerName);
        if (!isset(self::$db["warn"][$playerName]))
            self::$db["warn"][$playerName] = [];
        self::$db["warn"][$playerName][] = [$amount, $why];
        $total = self::getTotalWarn($playerName);
        Server::getInstance()->broadcastMessage(self::PREFIX . "{$playerName} 님에게 §a\"{$why}§r§a\"§7의 사유로 경고 {$amount}이(가) 부여되었습니다.");
        Server::getInstance()->broadcastMessage(self::PREFIX . "{$playerName} 님의 누적 경고 수는 {$total} 입니다.");
        if ($total >= self::getBanWarnCount()) {
            Server::getInstance()->broadcastMessage(self::PREFIX . "{$playerName} 님이 경고수 초과로 이용이 제한되었습니다.");
            if (Server::getInstance()->getPlayer($playerName) !== null)
                Server::getInstance()->getPlayer($playerName)->kick(self::PREFIX . "경고수 초과로 이용이 제한되었습니다.\n총 경고수: " . self::getTotalWarn($playerName), false);
        }
        if ($total >= self::getIpBanWarnCount()) {
            $ips = [];
            if (isset(self::$db["players"][$playerName]))
                $ips = self::$db["players"][$playerName]["ip"];
            $bans = [];
            foreach ($ips as $ip) {
                if (!in_array($ip, self::$db["ip-bans"])) {
                    self::$db["ip-bans"][] = $ip;
                    $bans += self::ipCheck($ip);
                }
            }
            $bans = array_unique($bans);
            foreach ($bans as $playerName) {
                Server::getInstance()->broadcastMessage(self::PREFIX . "{$playerName} 님이 경고수 초과로 아이피밴 되었습니다.");
                if (Server::getInstance()->getPlayer($playerName) !== null)
                    Server::getInstance()->getPlayer($playerName)->kick(self::PREFIX . "경고수 초과로 이용이 제한되었습니다.\n총 경고수: " . self::getTotalWarn($playerName), false);
            }
        }
    }

    public static function ipCheck(string $ip): array
    {
        $res = [];
        foreach (self::$db["players"] as $playerName => $data) {
            if ($data["ip"] === $ip)
                $res[] = $playerName;
        }
        return $res;
    }

    public function onLogin(PlayerLoginEvent $event)
    {
        $player = $event->getPlayer();
        if (!isset(self::$db["players"][strtolower($player->getName())])) {
            self::$db["players"][strtolower($player->getName())] = ["name" => $player->getName(), "ip" => [$player->getAddress()]];
        } else {
            if (!in_array($player->getAddress(), self::$db["players"][strtolower($player->getName())]["ip"])) {
                self::$db["players"][strtolower($player->getName())]["ip"][] = $player->getAddress();
            }
        }
        ////// BAN CHECK //////
        if (self::getBanWarnCount() <= ($c = self::getTotalWarn($player->getName()))) {
            $event->setKickMessage(self::PREFIX . "경고수 초과로 이용이 제한되었습니다.\n총 경고수: {$c}");
            $event->setCancelled();
            return;
        }
        ///// IP BAN CHECK /////
        if (self::getIpBanWarnCount() <= $c) {
            if (in_array($player->getAddress(), self::$db["ip-bans"])) {
                $event->setKickMessage(self::PREFIX . "경고수 초과로 이용이 제한되었습니다.\n총 경고수: {$c}");
                $event->setCancelled();
                return;
            }
        }
    }
}