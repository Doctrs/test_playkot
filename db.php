<?

class db{
    private $db;
    function __construct($configure){
        try{$db=new PDO('mysql:host='.$configure['db_host'].';dbname='.$configure['db_table'],$configure['db_name'],$configure['db_pass']);}
        catch(PDOException $e){die("Error: ".$e->getMessage());}
        $db->query("set names '".$configure['db_code']."'");
        $this->db=$db;
    }
    function query($sql){
        return $this->db->query($sql);
    }
    function getall($sql){
        $sql=$this->db->query($sql);
        return ($sql?$sql->fetchAll():false);
    }
    function getone($sql){
        $sql=$this->db->query($sql);
        return ($sql?$sql->fetch():false);
    }
    function getsingle($sql){
        $sql=$this->db->query($sql);
        if(!$sql)return false;
        $sql=$sql->fetch();
        return $sql[0];
    }
}