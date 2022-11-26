<?php

#ShopUI-HiroTeam plugin by RomainSav | Plugin de ShopUI-HiroTeam par RomainSav
#██╗░░██╗██╗██████╗░░█████╗░████████╗███████╗░█████╗░███╗░░░███╗
#██║░░██║██║██╔══██╗██╔══██╗╚══██╔══╝██╔════╝██╔══██╗████╗░████║
#███████║██║██████╔╝██║░░██║░░░██║░░░█████╗░░███████║██╔████╔██║
#██╔══██║██║██╔══██╗██║░░██║░░░██║░░░██╔══╝░░██╔══██║██║╚██╔╝██║
#██║░░██║██║██║░░██║╚█████╔╝░░░██║░░░███████╗██║░░██║██║░╚═╝░██║
#╚═╝░░╚═╝╚═╝╚═╝░░╚═╝░╚════╝░░░░╚═╝░░░╚══════╝╚═╝░░╚═╝╚═╝░░░░░╚═╝
#description:
#FRA: Ce plugin vous permet d'ajouter une boutique personnalisable sur votre serveur !
#ENG: This plugin allows you to add a customizable store on your server !

namespace HiroTeam\RomainSav\ui;

use HiroTeam\RomainSav\forms\CustomForm;
use HiroTeam\RomainSav\forms\SimpleForm;
use HiroTeam\RomainSav\DiscordWebhookAPI\CortexPE\DiscordWebhookAPI\Embed;
use HiroTeam\RomainSav\DiscordWebhookAPI\CortexPE\DiscordWebhookAPI\Message;
use HiroTeam\RomainSav\DiscordWebhookAPI\CortexPE\DiscordWebhookAPI\Webhook;

use HiroTeam\RomainSav\ShopUI;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringItem;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\item\LegacyStringToItemParser;
class UiManager
{
    /** @var ShopUI */
    private ShopUI $main;

    /**
     * UiManager constructor.
     * @param ShopUI $main
     */
    public function __construct(ShopUI $main)
    {
        $this->main = $main;
    }

    /**
     * @param Player $player
     */
    public function mainPage(Player $player)
    {
        $form = new SimpleForm(function (Player $player, $data) {
            $target = $data;
            if (is_null($target)) return;
            $this->categoryItems($player, $target);
        });

        $form->setTitle($this->main->getConfig()->get('main-title'));
        foreach ($this->main->getConfig()->get('shop') as $category => $name) {
            if (isset($name['image'])) {
                if (filter_var($name['image'], FILTER_VALIDATE_URL)) {
                    $form->addButton($name['category_name'], SimpleForm::IMAGE_TYPE_URL, $name['image'], $category);
                } else {
                    $form->addButton($name['category_name'], SimpleForm::IMAGE_TYPE_PATH, $name['image'], $category);
                }
            } else {
                $form->addButton($name['category_name'], -1, "", $category);
            }
        }
        $form->sendToPlayer($player);
    }

    /**
     * @param Player $player
     * @param string $category
     */
    private function categoryItems(Player $player, string $category)
    {
        $form = new SimpleForm(function (Player $player, $data) use ($category) {
            $target = $data;
            if (is_null($target)) return;

            $itemConfig = $this->main->getConfig()->get('shop')[$category]['items'][$target];

            if (isset($itemConfig['sell']) && !isset($itemConfig['buy'])) {

                $this->sell($player, $category, $target);
            } elseif (!isset($itemConfig['sell']) && isset($itemConfig['buy'])) {

                $this->buy($player, $category, $target);
            } else {

                $this->buyAndSell($player, $category, $target);
            }
        });

        $form->setTitle($this->main->getConfig()->get('shop')[$category]['category_name']);
        foreach ($this->main->getConfig()->get('shop')[$category]['items'] as $index => $item) {
            if (isset($item['image'])) {
                if (filter_var($item['image'], FILTER_VALIDATE_URL)) {
                    $form->addButton($item['name'], SimpleForm::IMAGE_TYPE_URL, $item['image'], $index);
                } else {
                    $form->addButton($item['name'], SimpleForm::IMAGE_TYPE_PATH, $item['image'], $index);
                }
            } else {
                $form->addButton($item['name'], -1, "", $index);
            }
        }
        $form->sendToPlayer($player);
    }

    //////////////////////////////////////////// SHARED /////////////////////////////////////////////////////////

    /**
     * @param Player $player
     * @param string $category
     * @param $index
     */
    private function buy(Player $player, string $category, $index)
    {
        $form = new CustomForm(function (Player $player, $data) use ($category, $index) {
            $target = $data;
            if (is_null($target)) {
                $this->categoryItems($player, $category);
                return;
            }

            $itemConfig = $this->main->getConfig()->get('shop')[$category]['items'][$index];

            $money = $this->main->getEconomyAPI()->myMoney($player);
			if (is_numeric((int)$target[3])) {
                
            if ($money < $itemConfig['buy'] * $target[2]) {
                $player->sendMessage($this->main->getConfig()->get('not-enought-money'));
                return;
            }

            $itemIdMeta = explode(":", $itemConfig['idMeta']);
            $item = LegacyStringToItemParser::getInstance()->parse($itemIdMeta[0], $itemIdMeta[1], $target[2]);
            if (!$player->getInventory()->canAddItem($item)) {
                $player->sendMessage($this->main->getConfig()->get('no-place-in-inventory'));
                return;
            }

            $player->getInventory()->addItem($item);
            $player->sendMessage($this->replace($this->main->getConfig()->get('buyMessage'), [
                'item' => $itemConfig['name'],
                'price' => $target[2] * $itemConfig['buy']
            ]));
            $this->main->getEconomyAPI()->reduceMoney($player, $target[2] * $itemConfig['buy']);
            }
            
        });

        $itemConfig = $this->main->getConfig()->get('shop')[$category]['items'][$index];
        $money = $this->main->getEconomyAPI()->myMoney($player);

        $form->setTitle($itemConfig['name']);
        $form->addLabel("§aAcheter : " . $itemConfig['buy'] . "\$");
        $form->addDropdown("Items", [$itemConfig['name']]);
        $form->addInput("§6Indiquez la quantitée");
        $form->sendToPlayer($player);
    }
                               

    /**
     * @param Player $player
     * @param string $category
     * @param $index
     */
    private function sell(Player $player, string $category, $index)
    {
        $form = new CustomForm(function (Player $player, $data) use ($category, $index) {
            $target = $data;
            if (is_null($target)) {
                $this->categoryItems($player, $category);
                return;
            }
			if(is_numeric($target[3])) {
            $itemConfig = $this->main->getConfig()->get('shop')[$category]['items'][$index];
            $itemIdMeta = explode(":", $itemConfig['idMeta']);
            $item = LegacyStringToItemParser::getInstance()->parse($itemIdMeta[0], $itemIdMeta[1], $target[2]);

            if (!$player->getInventory()->contains($item)) {
                $player->sendMessage($this->main->getConfig()->get('not-enought-items'));
                return;
            }

            $this->main->getEconomyAPI()->addMoney($player, $target[2] * $itemConfig['sell']);
            $player->getInventory()->removeItem($item);
            $player->sendMessage($this->replace($this->main->getConfig()->get('sellMessage'), [
                'item' => $itemConfig['name'],
                'price' => $target[2] * $itemConfig['sell']
            ]));
            }
            
        });

        $itemConfig = $this->main->getConfig()->get('shop')[$category]['items'][$index];
        $itemIdMeta = explode(":", $itemConfig['idMeta']);
        $item = LegacyStringToItemParser::getInstance()->parse($itemIdMeta[0], $itemIdMeta[1]);

        $form->setTitle($itemConfig['name']);
        $form->addLabel("§cVendre : " . $itemConfig['sell'] . "\$");
        $form->addDropdown("Items", [$itemConfig['name']]);
        $form->addInput("§6Indiquez la quantitée");
        $form->sendToPlayer($player);
    }

    /**
     * @param Player $player
     * @param string $category
     * @param $index
     */
    private function buyAndSell(Player $player, string $category, $index)
    {
        $form = new CustomForm(function (Player $player, $data) use ($category, $index) {
            $target = $data;
            if (is_null($target)) {
                $this->categoryItems($player, $category);
                return;
            }
      
			if (is_numeric($target[3])) {
            $itemConfig = $this->main->getConfig()->get('shop')[$category]['items'][$index];
            $itemIdMeta = explode(":", $itemConfig['idMeta']);
            $item = LegacyStringToItemParser::getInstance()->parse($itemIdMeta[0], $itemIdMeta[1], (int)$target[3]);

            if (!$target[2]) {

                $money = $this->main->getEconomyAPI()->myMoney($player);

                if ($money < $itemConfig['buy'] * (int)$target[3]) {
                    $player->sendMessage($this->main->getConfig()->get('not-enought-money'));
                    return;
                }
                if (!$player->getInventory()->canAddItem($item)) {
                    $player->sendMessage($this->main->getConfig()->get('no-place-in-inventory'));
                    return;
                }
                $player->getInventory()->addItem($item);
                $player->sendMessage($this->replace($this->main->getConfig()->get('buyMessage'), [
                    'item' => $itemConfig['name'],
                    'price' => (int)$target[3] * $itemConfig['buy']
                ]));
                $this->main->getEconomyAPI()->reduceMoney($player, (int)$target[3] * $itemConfig['buy']);
                
                ///LOGS DISCORD
                $pseudojoueur = $player->getName();
				$webhook = new Webhook("https://discord.com/api/webhooks/1024428200786407466/WYprbmxW04Ndw5CYB-B-rOwVolC6ZbA9rGn_j07ZxpiFztPW5qvTBRdpdtWT_FhypXGG");
                $msg = new Message();
                $msg->setUsername("Zélurium Achat | Shop");
                $msg->setContent("Achat de $pseudojoueur");
                $embed = new Embed();
                            $embed->setTitle("Achat");
                			$articlename = $itemConfig['name'];
                			$pricearticle = (int)$target[3];
							$pricetotal = (int)$target[3] * $itemConfig['buy'];
                            $embed->setDescription("**Pseudo:** $pseudojoueur\n**Article :** $articlename\n**Quantité :** $pricearticle\n**Prix :** $pricetotal $");
                            $msg->addEmbed($embed);

                            $webhook->send($msg);
                        
            } else {

                if (!$player->getInventory()->contains($item)) {
                    $player->sendMessage($this->main->getConfig()->get('not-enought-items'));
                    return;
                }

                $this->main->getEconomyAPI()->addMoney($player, (int)$target[3] * $itemConfig['sell']);
				$webhook1 = new Webhook("https://discord.com/api/webhooks/1024426902749986826/F9nHKX5gEE9ckdK_mt2tLmadWKx7rk-s3Z9moZa6ieMp1QujcChGUfefxrM9bYAh-i6M");
                                			$pseudojoueur = $player->getName();
                $msg1 = new Message();
                $msg1->setUsername("Zélurium Achat | Shop");
                $msg1->setContent("vente de $pseudojoueur");
                $embed1 = new Embed();
                            $embed1->setTitle("Vente");
                			$articlename1 = $itemConfig['name'];
                			$pricearticle1 = (int)$target[3];
							$pricetotal1 = (int)$target[3] * $itemConfig['sell'];
                            $embed1->setDescription("**Pseudo:** $pseudojoueur\n**Article :** $articlename1\n**Quantité :** $pricearticle1\n**Prix :** $pricetotal1$");
                            $msg1->addEmbed($embed1);

                            $webhook1->send($msg1);
                $player->getInventory()->removeItem($item);
                $player->sendMessage($this->replace($this->main->getConfig()->get('sellMessage'), [
                    'item' => $itemConfig['name'],
                    'price' => (int)$target[3] * $itemConfig['sell']
                ]));

            }}
                   });
        $itemConfig = $this->main->getConfig()->get('shop')[$category]['items'][$index];
        $itemIdMeta = explode(":", $itemConfig['idMeta']);
        $item1 = LegacyStringToItemParser::getInstance()->parse($itemIdMeta[0], $itemIdMeta[1]);
        $form->setTitle($itemConfig['name']);
        $form->addLabel("§6Vous avez §e" . self::getItemCount($player, $item1) . " " . $itemConfig['name'] . "\n§aAcheter : " . $itemConfig['buy'] . "\$\n§cVendre : " . $itemConfig['sell'] . "\$");
        $form->addDropdown("Items", [$itemConfig['name']]);
        $form->addToggle("Acheter/Vendre", false);
        $form->addInput("§6Indiquez la quantitée");
        $form->sendToPlayer($player);
    }


    private function replace(string $str, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $str = str_replace("{" . $key . "}", $value, $str);
        }
        return $str;
    }

    /**
     * @param Player $player
     * @param Item $item
     * @return int
     */
    private function getItemInInventory(Player $player, Item $item): int
    {
        $result = array_map(function (Item $invItem) use ($item) {
            if ($invItem->getTypeId() === $item->getTypeId() && $invItem->getStateMeta() === $item->getStateMeta()) {
                return $invItem->getCount();
            }
            return 0;
        }, $player->getInventory()->getContents());

        return array_sum($result);
    }
    
    public static function getItemCount(Player $player, $item1) : int 
    {
        $count = 0;
        $content = array_merge($player->getInventory()->getContents(), $player->getArmorInventory()->getContents());
        foreach ($content as $item) {
           if ($item->getTypeId() === $item1->getTypeId()) $count += $item->getCount();
        }
        return $count;

    }
}
