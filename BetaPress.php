<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

/* Задание №1 */
?>
<div><b>Задание №1</b></div><?
for ($i = 1, $o = 1, $limit = 1; $i <= 100; $i++) {
    echo $i;
    if ($o >= $limit) {
        echo  "<br/>";
        $o = 0;
        $limit++;
    }
    $o++;
}

/* Задание №2 */
?>
<div><b>Задание №2</b></div><?
$ar = array();
for ($i = 0; $i < 7; $i++) {
    for ($i2 = 0; $i2 < 5; $i2++) {
        $rand = rand(0, 15);
        $randY[$i2] += $rand;
        $randX[$i] += $rand;
        $ar[$i][$i2] = $rand;

    }
}
?>
<table border=1>
    <tr>
        <?
        foreach ($ar as $val => $var) {
            foreach ($var as $val1 => $var2) {
                echo "<td>" . $var2 . "</td>";
            }
            echo "<td><b>" . $randX[$val] . "</b></td>";
            echo "</tr>";
        }?>
    </tr>
    <tr>
        <?
        foreach ($randY as $val => $var) {
            echo "<td><b>" . $var . "</b></td>";
        }

        ?>
    </tr>
</table>
<?


/* Задание №3 */

$arSelect = Array(
    "ID",
    "NAME",
    "PREVIEW_TEXT",
    "PREVIEW_PICTURE",
    "DATE_ACTIVE_FROM",
);
$arFilter = Array(
    "IBLOCK_ID" => IB_SUB_EVENT_ID,
    "ACTIVE" => "Y");


$FROM = $_GET["FROM"];
$TO = $_GET["TO"];

if (preg_match("#[0-9]{2}\.[0-9]{2}\.[0-9]{4}#", $FROM)) {
    $arFilter[">=DATE_ACTIVE_FROM"] = date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), strtotime($FROM));

}

if (preg_match("#[0-9]{2}\.[0-9]{2}\.[0-9]{4}#", $TO)) {
    $arFilter["<=DATE_ACTIVE_FROM"] = date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), strtotime($TO));
}

$res = CIBlockElement::GetList(Array("SORT" => "DESC"), $arFilter, false, false, $arSelect);
?>
<form method="get">
<!-- По хорошему, нужно прикрутить datepicker или масску ввода --!>
<!-- Я не стал этого делать, так как решил что для оценки нужен только PHP код --!>

    от <input type="text" value="<?=$FROM?>" name="FROM" placeholder="<?=date("d.m.Y",strtotime("-1 week"))?>">
    до <input type="text" value="<?=$TO?>" name="TO" placeholder="<?=date("d.m.Y")?>">
    <input type="submit" value="Показать">
    <?
    if(isset($_GET['FROM'])||isset($_GET['TO'])){
    while ($row = $res->Fetch()):?>
        <div><?=$row['DATE_ACTIVE_FROM'];?> | <?=$row['NAME'];?></div>
        <?
    endwhile;
    }
    ?>
</form>
<?



/* Задание №4
Сложно ответить не видя код.
Во первых нужно кэшировать.
Если речь именно про оптимизацию скрипта, то нужно переписать все запросы к БД сделанные через API битрикса на чиcтый SQL, по возможности объединив выборку через JOIN. Это ускорит работу, зато будет кушать немного больше памяти.
По сути логика такая: делать на этой странице как можно меньше запросов к базе данных, и правильно расставить индексы в таблицах.
*/

/* Задание №5 */
$db->Query("SELECT * FROM users WHERE email='" . $_REQUEST['email'] . "'");
/*
Логика здесь правильная. Логических неправильностей нет. Но запрос написан плохо. Если бы я писал такой запрос, то он выглядел бы так:
если  $_REQUEST тут необходим, то пусть будет.
*/

$email = trim($_REQUEST['email']);
if (preg_match("регулярка для проверки email", $email)) {
    $sql = "
            SELECT
                    `выбираем только нужные значения`
            FROM
                    `users`
            WHERE
                    `users`.`email`='{$email}'";

    $DB->Query($sql);
} else {
    return false; /* выводим ошибку */
}

/*
 * Если в базе у каждого пользователя уникальный email то нужно поставить еще LIMIT 1
 */


require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");?>
