<?php

abstract class log{
    abstract function set($id,$value);
}

class system extends log{
    function set($id,$value){
        system('echo "'.$id.' :: '.$value.'" >> file.txt');
    }
}

