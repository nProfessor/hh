<?php
/**
 * Класс для работы с купонными сервисами
 * User: professor
 * Date: 24.10.12 18:58
 * To change this template use File | Settings | File Templates.
 */
class Kupon
{
    public $xml;
    public $tags;
    public $refLink = NULL;

    function __constructor()
    {

    }

    function  getData()
    {
        $data = array();

        $svg = new SimpleXMLElement(file_get_contents($this->xml));

        foreach ($svg->offers->offer as $var) {
            $id = $var->id;
            $url = preg_replace("#http://#i", "", $var->supplier->url);
            $url = preg_replace("#^([^/]+)/.*#i", "\\1", $url);
            $url = str_replace("www.", "", $url);
            $arSelect = Array("ID", "NAME");


            if ($this->filterSite($url)) {

                $arFilter = Array(
                    "IBLOCK_ID" => IB_CLUB_ID,
                    "PROPERTY_SITE" => "%" . $url . "%");

                if ($res = CIBlockElement::GetList(Array("SORT" => "DESC"), $arFilter, FALSE, FALSE, $arSelect)->Fetch()) {

                    if (!$resStock = CIBlockElement::GetList(Array("SORT" => "DESC"), array(
                        "CODE" => $id,
                        "TAGS" => $this->tags
                    ), FALSE, FALSE, $arSelect)->Fetch()
                    ) {
                        $PROP = array();
                        $PROP["URL"] = trim($var->url); // свойству с кодом 12 присваиваем значение "Белый"
                        $PROP["CLUB_ID"] = intval($res["ID"]); // свойству с кодом 3 присваиваем значение 38
                        $PROP["PRICE"] = intval($var->price); // свойству с кодом 3 присваиваем значение 38
                        $PROP["DISCOUNT"] = intval($var->discount); // свойству с кодом 3 присваиваем значение 38
                        $PROP["DISCOUNTPRICE"] = intval($var->discountprice); // свойству с кодом 3 присваиваем значение 38
                        $PROP["PRICECOUPON"] = intval($var->pricecoupon); // свойству с кодом 3 присваиваем значение 38
                        $PROP["PUBLIC"] = PROP_STOCK_PUBLIC; // свойству с кодом 3 присваиваем значение 38


                        $arLoadProductArray = Array(
                            "IBLOCK_ID" => IB_SUB_STOCK_ID,
                            "PROPERTY_VALUES" => $PROP,
                            "NAME" => str_replace(array(' "', '" '), array(" «", "» "), trim($var->name)),
                            "ACTIVE_FROM" => date("d.m.Y H:m:s", strtotime($var->beginsell)),
                            "ACTIVE_TO" => date("d.m.Y H:m:s", strtotime($var->endsell)),
                            "CODE" => $id,
                            "TAGS" => trim($this->tags),
                            "ACTIVE" => "Y", // активен
                            "PREVIEW_TEXT" => trim(strip_tags($var->description)),
                            "DETAIL_PICTURE" => CFile::MakeFileArray(trim($var->picture))
                        );

                        $data[] = $arLoadProductArray;

                    }

                }
            }
        }

        return count($data) ? $data : false;

    }

    function setStoks()
    {
        $clubList = array();
        set_time_limit(0);
        $data = $this->getData();

        if (!$data)
            return false;
        // Добавляем акции
        foreach ($data as $var) {
            $el = new CIBlockElement;
            $stockID = $el->Add($var);
            $var['ID'] = $stockID;
            $clubList[$var['PROPERTY_VALUES']['CLUB_ID']][] = $var;
        }

        $this->sendNotice($clubList);

    }

    /**
     * Парсим новые заведения
     */
    function parse()
    {
        $i=0;
        $count=0;
        $content = file_get_contents($this->parsePage);
        preg_match_all($this->parseReg, $content, $arr);

        $clubListID=array();
        foreach($arr[1] as $var){
            $clubListID[$var]=$var;
        }

        $xml = file_get_contents($this->xml);
        $svg = new SimpleXMLElement($xml);

        foreach ($svg->offers->offer as $var) {

            $R = array();
            if (isset($clubListID[intval($var->id)])) {

                $R['clubName'] = (string)$var->supplier->name;

                foreach ((array)$var->supplier->addresses as $address) {
                    if (is_array($address)) {
                        foreach ((array)$address as $var1) {
                            $R['clubAdress'][] = (string)$var1->name;
                        }
                    } else {
                        $R['clubAdress'][] = $address->name;
                    }
                }

                foreach ((array)$var->supplier->tel as $tel) {
                    $R['clubPhone'][] = $tel;
                }


                $id = $var->id;
                $url="";
                $url = preg_replace("#http://#i", "", trim($var->supplier->url));
                $url = preg_replace("#^([^/]+)/.*#i", "\\1", $url);
                $url = str_replace("www.", "", $url);


                $R['url'] = trim($url);
                $arSelect = Array("ID", "NAME");

                $arFilter = Array(
                    "IBLOCK_ID" => IB_CLUB_ID,
                    "PROPERTY_SITE" => "%" . $url . "%");

                $res = CIBlockElement::GetList(Array("SORT" => "DESC"), $arFilter, FALSE, FALSE, $arSelect);

                if (!$res->Fetch()) {

                    $PROP = array();
                    $PROP["SITE"] = trim($R['url']);
                    $PROP["LIST"] = array(PROP_CLUB_MODERATOR);


                    $el = new CIBlockElement();

                    $arLoadProductArray = Array(
                        "IBLOCK_ID" => IB_CLUB_ID,
                        "PROPERTY_VALUES" => $PROP,
                        "NAME" => trim($R['clubName']),
                        "TAGS" => $this->tags,
                        "ACTIVE" => "N",
                        "SORT" => "0"
                    );

                    if ($PRODUCT_ID = $el->Add($arLoadProductArray)) {
                        foreach ($R['clubAdress'] as $addressItem) {
                            MyTbCore::Add(array(
                                "CLUB_ID" => $PRODUCT_ID,
                                "SITY_ID" => 1,
                                "ADDRESS" => trim($addressItem),
                                "PHONE" => serialize($R['clubPhone'])
                            ), "address");
                        }
                         $i++;
                    }

                }
            }
        }

    }

    /**
     * Записываем в базу информацию о том, в каких заведениях появились акции.
     *
     * @param $clubList
     */
    public function sendNotice($clubList)
    {
        if (!is_array($clubList) || !count($clubList)) {
            return false;
        }

        $clubListID = array();


        foreach ($clubList as $key => $var) {
            $clubListID[] = intval($key);
        }

        if (!is_array($clubListID) || !count($clubListID)) {
            return false;
        }

        global $DB;
        $usrListID = array();
        $arUserList = array();
        $res = CIBlockElement::GetList(Array("SORT" => "DESC"), array("IBLOCK_ID" => IB_USER_PROPS, "PROPERTY_LINK_STOK" => $clubListID), false, false, array("ID", "PROPERTY_USER", "PROPERTY_LINK_STOK", "PROPERTY_LINK_NEWS", "PROPERTY_LINK_EVENT", "PROPERTY_NOTICE_VALUE"));


        /*
         * Заполняем пользователями массив
         * Всех этих людей нужно будет уведомить
         *
         */
        while ($obj = $res->Fetch()) {
            $arUserList[$obj['PROPERTY_USER_VALUE']]["CLUB_LIST"] = $obj['PROPERTY_LINK_STOK_VALUE']; // список клубов для рассылки
            $PROPERTY_NOTICE_VALUE = unserialize($obj['PROPERTY_NOTICE_VALUE']); // настройки рассылки
            $arUserList[$obj['PROPERTY_USER_VALUE']]["SETTINGS"] = $PROPERTY_NOTICE_VALUE['stock']; // настройки рассылки
            $usrListID[] = intval($obj['PROPERTY_USER_VALUE']);
        }


        $arUserInfo = User::getList($usrListID);

        foreach ($arUserList as $userID => $var) { // перебираем всех пользователей
            $user = $arUserInfo[$userID];
            foreach ($var["CLUB_LIST"] as $clubID) { // Перебираем все клубы на которые он подписан
                $stoks = $clubList[$clubID];
                if (count($stoks)) {
                    $arStokID = array();
                    foreach ($stoks as $stok) {
                        $arStokID[] = intval($stok['ID']);

                    }
                    $varStok = implode("|", $arStokID);
                }
            }

            $sql = "INSERT INTO a_send_notice (USER_ID,TYPE,EVENT_ID,EMAIL,PHONE,ACTIVE) VALUES ('{$userID}','stoks','{$varStok}','{$user['EMAIL']}','{$user['PERSONAL_PHONE']}','1')";
            $DB->Query($sql);
        }

    }


    public
    function filterSite($url)
    {
        if (!preg_match("#(vkontakte|facebook|vk\.com)#is", $url)) {
            return true;
        } else {
            return false;
        }

    }

    static function getDataServise($key){
        $array=array(
            "bigcoupon"=>array("site"=>"BigCoupon.ru","name"=>"BigCoupon"),
            "citycoupon"=>array("site"=>"SityCoupon.ru","name"=>"SityCoupon"),
            "darikupon"=>array("site"=>"DariKupon.ru","name"=>"DariKupon"),
            "kingcoupon"=>array("site"=>"KingCoupon.ru","name"=>"KingCoupon"),
            "kuponauktsion"=>array("site"=>"KuponAuktsion.ru","name"=>"KuponAuktsion"),
            "ladykupon"=>array("site"=>"LadyKupon.ru","name"=>"LadyKupon"),
            "megakupon"=>array("site"=>"MegaKupon.ru","name"=>"MegaKupon"),
            "myfant"=>array("site"=>"MyFant.ru","name"=>"MyFant"),
            "skidkabum"=>array("site"=>"SkidkaBum.ru","name"=>"SkidkaBum"),
            "skidkacoupon"=>array("site"=>"Skidka-Coupon.ru","name"=>"SkidkaCoupon"),
            "skuponom"=>array("site"=>"sKuponom.ru","name"=>"sKuponom"),
            "vigoda"=>array("site"=>"Vigoda.ru","name"=>"Vigoda"),
            "zoombonus"=>array("site"=>"ZoomBonus.ru","name"=>"ZoomBonus"),
        );

        return $array[$key];

    }
}
