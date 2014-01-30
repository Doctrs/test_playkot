<?

require 'config.php';
require 'db.php';
require 'class.php';
require 'log.php';


$send=new my($configure);
$send->set_log(new system()); // добавляем обработчик логов
$send->set_vk(new vk()); // добавляем класс ВК
$send->start(); // запуск скрипта



class vk{
    // удаляем рандомный элемент массива
    function sendNotification($ids,$text){
        $ids=explode(',',$ids);
        unset($ids[rand(0,sizeof($ids)-1)]);
        return $ids;
    }
}