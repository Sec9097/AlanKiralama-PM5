<?php

declare(strict_types=1);

namespace AlankiraPlugin;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\event\EventPriority;
use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener {

    /** @var Config */
    private $config;
    /** @var array */
    private $tempPositions = [];
    /** @var array */
    private $selectionMode = [];

    public function onEnable(): void {
        $this->getLogger()->info("AlanKira eklentisi aktif!");
        $this->saveDefaultConfig();

        $alankiralarFile = $this->getDataFolder() . "alankiralar.yml";
        if (!file_exists($alankiralarFile)) {
            $this->getLogger()->info("alankiralar.yml bulunamadı, oluşturuluyor...");
            $defaultData = [];
            $config = new Config($alankiralarFile, Config::YAML);
            $config->setAll($defaultData);
            $config->save();
        }
        $this->config = new Config($alankiralarFile, Config::YAML);

        $pluginManager = $this->getServer()->getPluginManager();
        $pluginManager->registerEvent(
            BlockBreakEvent::class,
            function(BlockBreakEvent $event): void {
                $this->onBlockBreak($event);
            },
            EventPriority::HIGHEST,
            $this,
            false
        );
        $pluginManager->registerEvent(
            BlockPlaceEvent::class,
            function(BlockPlaceEvent $event): void {
                $this->onBlockPlace($event);
            },
            EventPriority::HIGHEST,
            $this,
            false
        );
        $pluginManager->registerEvent(
            PlayerMoveEvent::class,
            function(PlayerMoveEvent $event): void {
                $this->onPlayerMove($event);
            },
            EventPriority::NORMAL,
            $this,
            false
        );
    }

    // İki pozisyon arasındaki alanı kontrol eder.
    private function isInsideRegion(Vector3 $pos, Vector3 $pos1, Vector3 $pos2): bool {
        $minX = min($pos1->getX(), $pos2->getX());
        $maxX = max($pos1->getX(), $pos2->getX());
        $minY = min($pos1->getY(), $pos2->getY());
        $maxY = max($pos1->getY(), $pos2->getY());
        $minZ = min($pos1->getZ(), $pos2->getZ());
        $maxZ = max($pos1->getZ(), $pos2->getZ());
        return ($pos->getX() >= $minX && $pos->getX() <= $maxX) &&
               ($pos->getY() >= $minY && $pos->getY() <= $maxY) &&
               ($pos->getZ() >= $minZ && $pos->getZ() <= $maxZ);
    }

    // Blok kırma eventi
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();

        // Seçim modunda alankira alanı seçimi
        if (isset($this->selectionMode[$player->getName()])) {
            $mode = $this->selectionMode[$player->getName()];
            $block = $event->getBlock();
            $pos = $block->getPosition();
            $this->tempPositions[$player->getName()][$mode] = $pos;
            if ($mode === "pos1") {
                $this->selectionMode[$player->getName()] = "pos2";
                $player->sendMessage("Pos1 seçildi: (x:{$pos->getX()}, y:{$pos->getY()}, z:{$pos->getZ()}). Şimdi pos2 seçmek için bir blok kırın.");
                $event->cancel();
                return;
            } elseif ($mode === "pos2") {
                unset($this->selectionMode[$player->getName()]);
                $player->sendMessage("Pos2 seçildi: (x:{$pos->getX()}, y:{$pos->getY()}, z:{$pos->getZ()}). AlanKira oluşturma formu açılıyor...");
                $event->cancel();
                $this->openCreateMarketForm($player);
                return;
            }
        }

        if ($this->getServer()->isOp($player->getName())) {
            return;
        }

        $block = $event->getBlock();
        $blockPos = $block->getPosition();
        foreach ($this->config->getAll() as $marketName => $data) {
            if (
                !is_array($data) ||
                !isset($data["pos1"], $data["pos2"]) ||
                !is_array($data["pos1"]) ||
                !is_array($data["pos2"]) ||
                !isset($data["pos1"]["x"], $data["pos1"]["y"], $data["pos1"]["z"]) ||
                !isset($data["pos2"]["x"], $data["pos2"]["y"], $data["pos2"]["z"])
            ) {
                continue;
            }
            $pos1 = new Vector3($data["pos1"]["x"], $data["pos1"]["y"], $data["pos1"]["z"]);
            $pos2 = new Vector3($data["pos2"]["x"], $data["pos2"]["y"], $data["pos2"]["z"]);
            if ($this->isInsideRegion($blockPos, $pos1, $pos2)) {
                if ($data["owner"] === $player->getName()) {
                    $event->cancel();
                    return;
                } else {
                    $player->sendMessage("Blok kırma yetkiniz yok! (AlanKira alanı korumalı)");
                    $event->cancel();
                    return;
                }
            }
        }
    }

    // Blok yerleştirme eventi
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();

        if ($this->getServer()->isOp($player->getName())) {
            return;
        }

        $block = $event->getBlock();
        $blockPos = $block->getPosition();
        foreach ($this->config->getAll() as $marketName => $data) {
            if (
                !is_array($data) ||
                !isset($data["pos1"], $data["pos2"]) ||
                !is_array($data["pos1"]) ||
                !is_array($data["pos2"]) ||
                !isset($data["pos1"]["x"], $data["pos1"]["y"], $data["pos1"]["z"]) ||
                !isset($data["pos2"]["x"], $data["pos2"]["y"], $data["pos2"]["z"])
            ) {
                continue;
            }
            $pos1 = new Vector3($data["pos1"]["x"], $data["pos1"]["y"], $data["pos1"]["z"]);
            $pos2 = new Vector3($data["pos2"]["x"], $data["pos2"]["y"], $data["pos2"]["z"]);
            if ($this->isInsideRegion($blockPos, $pos1, $pos2)) {
                if ($data["owner"] === $player->getName()) {
                    $event->cancel();
                    return;
                } else {
                    $player->sendMessage("Blok yerleştirme yetkiniz yok! (AlanKira alanı korumalı)");
                    $event->cancel();
                    return;
                }
            }
        }
    }

    // Oyuncu alankira alanına girerse popup mesaj gösterir.
    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $pos = $event->getTo();
        foreach ($this->config->getAll() as $marketName => $data) {
            if (
                !is_array($data) ||
                !isset($data["pos1"], $data["pos2"]) ||
                !is_array($data["pos1"]) ||
                !is_array($data["pos2"]) ||
                !isset($data["pos1"]["x"], $data["pos1"]["y"], $data["pos1"]["z"]) ||
                !isset($data["pos2"]["x"], $data["pos2"]["y"], $data["pos2"]["z"])
            ) {
                continue;
            }
            $pos1 = new Vector3($data["pos1"]["x"], $data["pos1"]["y"], $data["pos1"]["z"]);
            $pos2 = new Vector3($data["pos2"]["x"], $data["pos2"]["y"], $data["pos2"]["z"]);
            if ($this->isInsideRegion($pos, $pos1, $pos2)) {
                $message = ($data["owner"] === "none") ? "Alan boş!" : "Alanın sahibi: " . $data["owner"];
                $player->sendPopup($message);
                return;
            }
        }
    }

    // /alankira komutu ana menüyü açar.
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("Bu komut sadece oyun içinde kullanılabilir!");
            return false;
        }
        if ($command->getName() === "alankira") {
            if (count($args) === 0) {
                $this->openMainMenu($sender);
                return true;
            }
            return false;
        }
        return false;
    }

    // Ana menü: Genel seçenekler.
    public function openMainMenu(Player $player): void {
        $actions = [];
        $form = new SimpleForm(function(Player $player, ?int $data) use (&$actions) {
            if ($data === null || !isset($actions[$data])) return;
            switch ($actions[$data]) {
                case "rent":
                    $this->openRentForm($player);
                    break;
                case "pay":
                    $player->sendMessage("Kira ödeme işlemi yapıldı (placeholder)!");
                    break;
                case "teleport":
                    $this->teleportToOwnedMarket($player);
                    break;
                case "admin":
                    $this->openAdminOperations($player);
                    break;
                case "reset":
                    $this->openResetForm($player);
                    break;
            }
        });
        $form->setTitle("AlanKira Menüsü");
        $form->setContent("Lütfen bir seçenek seçin:");
        $actions[] = "rent";
        $form->addButton("AlanKira Kirala");
        $actions[] = "pay";
        $form->addButton("Kira Öde");
        $actions[] = "teleport";
        $form->addButton("Işınlan");
        if ($player->hasPermission("alankiralekenti.admin") || $this->getServer()->isOp($player->getName())) {
            $actions[] = "admin";
            $form->addButton("Admin İşlemleri");
        } else {
            $isOwner = false;
            foreach ($this->config->getAll() as $name => $data) {
                if (is_array($data) && isset($data["owner"]) && $data["owner"] === $player->getName()) {
                    $isOwner = true;
                    break;
                }
            }
            if ($isOwner) {
                $actions[] = "reset";
                $form->addButton("AlanKira Sıfırla");
            }
        }
        $form->sendToPlayer($player);
    }

    // Admin işlemleri menüsü.
    public function openAdminOperations(Player $player): void {
        $actions = [];
        $form = new SimpleForm(function(Player $player, ?int $data) use (&$actions) {
            if ($data === null || !isset($actions[$data])) return;
            switch ($actions[$data]) {
                case "createMarket":
                    $this->startAreaSelectionFlow($player);
                    break;
                case "markets":
                    $this->openMarketsForm($player);
                    break;
                case "reset":
                    $this->openResetForm($player);
                    break;
                case "back":
                    $this->openMainMenu($player);
                    break;
            }
        });
        $form->setTitle("Admin İşlemleri");
        $form->setContent("Lütfen bir işlem seçin:");
        $actions[] = "createMarket";
        $form->addButton("AlanKira Oluştur");
        $actions[] = "markets";
        $form->addButton("AlanKira'lar");
        $actions[] = "reset";
        $form->addButton("AlanKira Sıfırla");
        $actions[] = "back";
        $form->addButton("Geri");
        $form->sendToPlayer($player);
    }

    // Kiralama işlemi: Oyuncunun bulunduğu konumun tanımlı alankira alanında olup olmadığını kontrol eder.
    public function openRentForm(Player $player): void {
        $pos = $player->getPosition();
        $alankiraFound = null;
        $alankiraNameFound = "";
        foreach ($this->config->getAll() as $marketName => $data) {
            if (!is_array($data) || !isset($data["pos1"], $data["pos2"])) continue;
            $pos1 = new Vector3($data["pos1"]["x"], $data["pos1"]["y"], $data["pos1"]["z"]);
            $pos2 = new Vector3($data["pos2"]["x"], $data["pos2"]["y"], $data["pos2"]["z"]);
            if ($this->isInsideRegion($pos, $pos1, $pos2)) {
                $alankiraFound = $data;
                $alankiraNameFound = $marketName;
                break;
            }
        }
        if ($alankiraFound === null) {
            $player->sendMessage("Bir AlanKira alanı içinde olmalısın!");
            return;
        }
        if ($alankiraFound["owner"] === "none") {
            $this->openRentConfirmationForm($player, $alankiraNameFound, $alankiraFound);
        } else if ($alankiraFound["owner"] === $player->getName()) {
            $this->openRentExtensionForm($player, $alankiraNameFound, $alankiraFound);
        } else {
            $player->sendMessage("Bu AlanKira zaten kiralanmış!");
        }
    }

    // Kiralama onay formu - EconomyAPI entegrasyonlu.
    public function openRentConfirmationForm(Player $player, string $alankiraName, array $data): void {
        $price = $data["price"];
        $form = new SimpleForm(function(Player $player, ?int $dataChoice) use ($alankiraName, $price) {
            if ($dataChoice === null) return;
            if ($dataChoice === 0) {
                $money = EconomyAPI::getInstance()->myMoney($player);
                if ($money < $price) {
                    $player->sendMessage("Yeterli paranız yok! Kiralama için {$price} gerekiyor.");
                    return;
                }
                EconomyAPI::getInstance()->reduceMoney($player, $price);

                $alankiraData = $this->config->get($alankiraName);
                if ($alankiraData["owner"] !== "none") {
                    $player->sendMessage("Bu alan artık kiralanmış!");
                    return;
                }
                $alankiraData["owner"] = $player->getName();
                $alankiraData["rental_expires"] = time() + 604800; // 1 hafta
                $this->config->set($alankiraName, $alankiraData);
                $this->config->save();
                $player->sendMessage("AlanKira başarıyla kiralandı! Kira süresi 1 hafta. {$price} çekildi.");
            } else {
                $player->sendMessage("Kiralama işlemi iptal edildi.");
            }
        });
        $form->setTitle("Kiralama Onayı");
        $form->setContent("Bu alanı {$price} karşılığında 1 hafta kiralamak istediğinizden emin misiniz?");
        $form->addButton("Evet");
        $form->addButton("Hayır");
        $form->sendToPlayer($player);
    }

    // Kira uzatma formu - EconomyAPI entegrasyonlu.
    public function openRentExtensionForm(Player $player, string $alankiraName, array $data): void {
        $weeklyPrice = $data["price"];
        $dailyPrice = $weeklyPrice / 7;
        $currentExpiration = isset($data["rental_expires"]) ? $data["rental_expires"] : time();
        $remainingDays = ceil(($currentExpiration - time()) / 86400);
        $form = new CustomForm(function(Player $player, ?array $dataInput) use ($alankiraName, $dailyPrice) {
            if ($dataInput === null) return;
            $days = (int)($dataInput[0] ?? 0);
            if ($days <= 0) {
                $player->sendMessage("Geçerli bir gün sayısı girmelisiniz!");
                return;
            }
            $cost = $dailyPrice * $days;
            $money = EconomyAPI::getInstance()->myMoney($player);
            if ($money < $cost) {
                $player->sendMessage("Yeterli paranız yok! Uzatma için {$cost} gerekiyor.");
                return;
            }
            EconomyAPI::getInstance()->reduceMoney($player, $cost);

            $alankiraData = $this->config->get($alankiraName);
            $currentExpiration = isset($alankiraData["rental_expires"]) ? $alankiraData["rental_expires"] : time();
            $alankiraData["rental_expires"] = $currentExpiration + ($days * 86400);
            $this->config->set($alankiraName, $alankiraData);
            $this->config->save();
            $player->sendMessage("AlanKira'nız {$days} gün uzatıldı! Ücret: {$cost}");
        });
        $form->setTitle("Kira Uzatma");
        $form->addLabel("Mevcut kira süresi: {$remainingDays} gün. Kaç gün uzatmak istersiniz?");
        $form->addInput("Uzatma gün sayısı:", "Örnek: 7");
        $form->sendToPlayer($player);
    }

    // Kiralanan alankira alanına ışınlama.
    public function teleportToOwnedMarket(Player $player): void {
        foreach ($this->config->getAll() as $name => $data) {
            if (is_array($data) && isset($data["owner"]) && $data["owner"] === $player->getName()) {
                $pos = $data["pos1"];
                $player->teleport(new Vector3($pos["x"], $pos["y"], $pos["z"]));
                $player->sendMessage("AlanKira'nıza ışınlandınız!");
                return;
            }
        }
        $player->sendMessage("Kiralanmış AlanKira'nız bulunamadı!");
    }

    // Alankira oluşturma formu.
    public function openCreateMarketForm(Player $player): void {
        $form = new CustomForm(function(Player $player, ?array $data) {
            if ($data === null) return;
            $player->sendMessage("DEBUG: Form verileri: " . json_encode($data));
            $alankiraName = trim($data[1] ?? "");
            $price = (int)($data[2] ?? 0);
            $player->sendMessage("DEBUG: Girilen AlanKira adı: '{$alankiraName}'");
            if (empty($alankiraName)) {
                $player->sendMessage("AlanKira adı boş olamaz!");
                return;
            }
            if (!isset($this->tempPositions[$player->getName()]["pos1"]) || !isset($this->tempPositions[$player->getName()]["pos2"])) {
                $player->sendMessage("Alanlar seçilmedi, lütfen önce alanları seçin!");
                return;
            }
            $pos1 = [
                "x" => $this->tempPositions[$player->getName()]["pos1"]->getX(),
                "y" => $this->tempPositions[$player->getName()]["pos1"]->getY(),
                "z" => $this->tempPositions[$player->getName()]["pos1"]->getZ()
            ];
            $pos2 = [
                "x" => $this->tempPositions[$player->getName()]["pos2"]->getX(),
                "y" => $this->tempPositions[$player->getName()]["pos2"]->getY(),
                "z" => $this->tempPositions[$player->getName()]["pos2"]->getZ()
            ];
            $this->config->set($alankiraName, [
                "pos1" => $pos1,
                "pos2" => $pos2,
                "price" => $price,
                "owner" => "none"
            ]);
            $this->config->save();
            $player->sendMessage("AlanKira başarıyla oluşturuldu!");
        });
        $form->setTitle("AlanKira Oluştur");
        $form->addLabel("Alanlar seçildi. Lütfen AlanKira adını ve kira fiyatını girin:");
        $form->addInput("AlanKira Adı:", "Örnek: Market1");
        $form->addInput("Kira Fiyatı:", "Örnek: 100");
        $form->sendToPlayer($player);
    }

    // Alan seçim akışını başlatır.
    public function startAreaSelectionFlow(Player $player): void {
        $this->selectionMode[$player->getName()] = "pos1";
        $player->sendMessage("Lütfen pos1'i seçmek için bir blok kırın.");
    }

    // Alankira sıfırlama formu.
    public function openResetForm(Player $player): void {
        $form = new CustomForm(function(Player $player, ?array $data) {
            if ($data === null) return;
            $alankiraName = trim($data[0] ?? "");
            if (empty($alankiraName)) {
                $player->sendMessage("AlanKira adı boş olamaz!");
                return;
            }
            if (!$this->config->exists($alankiraName)) {
                $player->sendMessage("Böyle bir AlanKira bulunamadı!");
                return;
            }
            $dataConfig = $this->config->get($alankiraName);
            if (!$player->hasPermission("alankiralekenti.admin") && $dataConfig["owner"] !== $player->getName()) {
                $player->sendMessage("Bu alanı sıfırlama yetkiniz yok!");
                return;
            }
            $pos1 = new Vector3($dataConfig["pos1"]["x"], $dataConfig["pos1"]["y"], $dataConfig["pos1"]["z"]);
            $pos2 = new Vector3($dataConfig["pos2"]["x"], $dataConfig["pos2"]["y"], $dataConfig["pos2"]["z"]);
            $world = $player->getWorld();
            $minX = (int)min($pos1->getX(), $pos2->getX());
            $maxX = (int)max($pos1->getX(), $pos2->getX());
            $minY = (int)min($pos1->getY(), $pos2->getY());
            $maxY = (int)max($pos1->getY(), $pos2->getY());
            $minZ = (int)min($pos1->getZ(), $pos2->getZ());
            $maxZ = (int)max($pos1->getZ(), $pos2->getZ());
            for ($x = $minX; $x <= $maxX; $x++) {
                for ($y = $minY; $y <= $maxY; $y++) {
                    for ($z = $minZ; $z <= $maxZ; $z++) {
                        $world->setBlock(new Vector3($x, $y, $z), BlockFactory::get(BlockIds::AIR));
                    }
                }
            }
            $dataConfig["owner"] = "none";
            $this->config->set($alankiraName, $dataConfig);
            $this->config->save();
            $player->sendMessage("{$alankiraName} AlanKira sıfırlandı ve tekrar kiraya hazır hale getirildi!");
        });
        $form->setTitle("AlanKira Sıfırla");
        $form->addLabel("Sıfırlamak istediğiniz AlanKira'nın adını girin:");
        $form->addInput("AlanKira Adı:");
        $form->sendToPlayer($player);
    }

    // Tüm alankiraları listeleyen form.
    public function openMarketsForm(Player $player): void {
        $markets = $this->config->getAll();
        $actions = [];
        $form = new SimpleForm(function(Player $player, ?int $data) use ($markets, &$actions) {
            if ($data === null || !isset($actions[$data])) return;
            $selectedMarket = $actions[$data];
            $this->openMarketActionsForm($player, $selectedMarket);
        });
        $form->setTitle("AlanKira'lar");
        $form->setContent("Tüm AlanKira'lar listesi:");
        foreach ($markets as $marketName => $data) {
            if (!is_array($data)) continue;
            $status = ($data["owner"] === "none") ? "Boş" : "Kiralanmış: " . $data["owner"];
            $form->addButton("{$marketName}\nFiyat: {$data["price"]}\nDurum: {$status}");
            $actions[] = $marketName;
        }
        $form->sendToPlayer($player);
    }

    // Seçilen alankiranın işlemlerini yapan alt form.
    public function openMarketActionsForm(Player $player, string $marketName): void {
        $form = new SimpleForm(function(Player $player, ?int $dataChoice) use ($marketName) {
            if ($dataChoice === null) return;
            switch ($dataChoice) {
                case 0:
                    $marketData = $this->config->get($marketName);
                    $player->sendMessage("{$marketName} AlanKira'nın fiyatı: {$marketData['price']}");
                    break;
                case 1:
                    $this->config->remove($marketName);
                    $this->config->save();
                    $player->sendMessage("{$marketName} AlanKira silindi!");
                    break;
                case 2:
                    $this->resetMarket($player, $marketName);
                    break;
                case 3:
                    $this->openMarketsForm($player);
                    break;
            }
        });
        $form->setTitle("AlanKira: {$marketName}");
        $form->setContent("Lütfen bir işlem seçin:");
        $form->addButton("AlanKira'nın Fiyatı");
        $form->addButton("AlanKira'yı Sil");
        $form->addButton("AlanKira Sıfırla");
        $form->addButton("Geri");
        $form->sendToPlayer($player);
    }

    // Belirli bir alankirayı sıfırlayan metot.
    public function resetMarket(Player $player, string $marketName): void {
        if (!$this->config->exists($marketName)) {
            $player->sendMessage("Böyle bir AlanKira bulunamadı!");
            return;
        }
        $dataConfig = $this->config->get($marketName);
        $pos1 = new Vector3($dataConfig["pos1"]["x"], $dataConfig["pos1"]["y"], $dataConfig["pos1"]["z"]);
        $pos2 = new Vector3($dataConfig["pos2"]["x"], $dataConfig["pos2"]["y"], $dataConfig["pos2"]["z"]);
        $world = $player->getWorld();
        $minX = (int)min($pos1->getX(), $pos2->getX());
        $maxX = (int)max($pos1->getX(), $pos2->getX());
        $minY = (int)min($pos1->getY(), $pos2->getY());
        $maxY = (int)max($pos1->getY(), $pos2->getY());
        $minZ = (int)min($pos1->getZ(), $pos2->getZ());
        $maxZ = (int)max($pos1->getZ(), $pos2->getZ());
        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                for ($z = $minZ; $z <= $maxZ; $z++) {
                    $world->setBlock(new Vector3($x, $y, $z), BlockFactory::get(BlockIds::AIR));
                }
            }
        }
        $dataConfig["owner"] = "none";
        $this->config->set($marketName, $dataConfig);
        $this->config->save();
        $player->sendMessage("{$marketName} AlanKira sıfırlandı ve tekrar kiraya hazır hale getirildi!");
    }
}