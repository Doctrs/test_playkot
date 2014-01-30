<?



class my{
    private $mem,$db;
    private $count=50000; // Количество выборки записей из базы за один раз.
    private $max_send=100; // максимальное число передаваемых ИД в api
    private $iteration_second=3; // максимальное количество операций в секунду
    private $user_group=false;
    private $log=false;
    private $vk=false;
    private $text='%name%'; // шаблон сообщения
    
    function __construct(array $configure) {
        $this->text=file_get_contents('template.html');
        $this->mem=new mem($configure);
        $this->db=new db($configure);
    }
    
    // функция передачи сообщений и логирования
    private function sendNote(array $id,$name){
        $arr=$this->vk->sendNotification(join(',',$id),str_replace('%name%',$name,$this->text));
        // находим массив с ошибочными данными
        $error=array_diff($id,$arr);
        foreach($error as $item){
            $this->log->set($item,'no');
        }
        foreach($arr as $item){
            $this->log->set($item,'yes');
        }
        return $arr;
    }
    
    // добавляем класс логов
    function set_log(log $log){
        $this->log=$log;
    }
    
    // добавляем класс вконтакте
    function set_vk(vk $vk){
        $this->vk=$vk;
    }
    
    function start(){
        // если не указан класс вконтакте и логов то не выполняем
        if(!$this->log||!$this->vk)die('must have');
        
        // добавление в базу обьектов котоыре еще туда не записались.
        $this->upd_no_sql();
        // Общее хранилище.
        // Данные о количестве людей с такимто именем
        $this->mem->set('user_group',false);
        $user_group=$this->mem->get('user_group');
        
        if(!$user_group){
            // для того чтобы при запуске первого скрипта другой не попортил его статистику
            if(!$this->mem->get('start')){
                $this->mem->set('start',true);
                $user_group=$this->db->getall('select count(*) count,first_name FROM players where send=0 group by first_name');
                $this->mem->set('user_group',$user_group);
                $this->mem->set('start',false);
            }
            else{ // если какойто скрипт запущен а данных нет, ждем появления данных
                while(!$user_group=$this->mem->get('user_group')){
                    echo "wait memcache\n";
                    sleep(1);
                }
            }
        }
        die();
        $this->user_group=$user_group;
        // запускаем функцию с циклом для обхода всх записей
        $this->recursy();
    }
    
    private function recursy(){
        // выполняем пока в мемкэше не остентся данных
        while($this->user_group&&sizeof($this->user_group)){
            
            // обновялем данные в мескэше
            $values=$this->get_user_group();
            
            // выборка значений из базы с учетом итерации
            $sql=$this->db->getall('select id from players 
                              where first_name="'.$values['name'].'"
                              order by id limit '.
                              ($this->count*$values['iteration'])
                              .','.$this->count);

            $ids=[];
            $time_start=microtime(1);
            $iteration=0;
            
            foreach($sql as $key=>$item){
                $ids[]=$item['id']; // массив с Ид котоырм будем отправлять одинаковые сообщения
                
                // проверяем максимальное количество ИД для передачи в функцию
                if($key&&$key%$this->max_send==0){
                    $iteration++;
                    // Добавляем в кэш ИД которым успешно отправлено сообщение
                    $this->mem->set('no_sql',array_merge($this->mem->get('no_sql'),$this->sendNote($ids,$values['name'])));
                    $ids=[];
                    
                    if($iteration>=$this->iteration_second){
                        // обнуляем все параметры
                        $this->upd_no_sql();
                        $iteration=0;
                        // проверяем прошла ли секунда после максимально возможного количества операций в секунду
                        if(microtime(1)-$time_start<1)sleep(1);
                        $time_start=microtime(1);
                    }
                }
            }
        }
    }
    
    // Мемкэш собирает данные о пользвоателях котоыре уже получили сообщение, но еще не попали в базу.
    // Он обновялет базу и обнуляет свой массив
    private function upd_no_sql(){
        $array=$this->mem->get('no_sql');
        $this->mem->set('no_sql',[]);
        $this->db->query('update players set send=1 where id in ('.join(',',$array).')');
    }
    
    // считаем количество оставшихся записей с одинаковым именем, присваиваем номер итерации ну и всякие мелочи с общим хранилищем
    private function get_user_group(){
        $user_group=$this->mem->get('user_group');
        $name=$user_group[0]['first_name'];
        if(!$user_group[0]['iteration'])$user_group[0]['iteration']=0;
        $iter=$user_group[0]['iteration'];
        if($user_group[0]['count']<=$this->count){
            array_shift($user_group);
        }
        else{
            $user_group[0]['count']-=$this->count;
            $user_group[0]['iteration']++;
        }
        if(!sizeof($user_group))$user_group=false;
        $this->mem->set('user_group',$user_group);
        $this->user_group=$user_group;
        return ['iteration'=>$iter,'name'=>$name];
    }
}





// обертка мемкэша
class mem extends Memcache{
    private $time=3600;
    
    function __construct($configure) {
        $this->time=$configure['mem_time'];
        $this->connect($configure['mem_host'], $configure['mem_port']);
    }
    
    function set($key,$val){
        parent::set($key,$val,false,$this->time);
    }
    
    
}
