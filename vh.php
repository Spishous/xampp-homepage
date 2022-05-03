<?php
/*$content=file_get_contents("..\apache\conf\extra\httpd-vhosts.conf");
var_dump($content);*/
host::init();
virtual::init();


abstract class host{
    static $posSection=-1,
        $endSection=-1,
        $TStartSection="# Xampp URL",
        $TEndSection="# Xampp End",
        $host,$host_a,        $prefix="127.0.0.1 ",
        $path='C:/Windows/System32/drivers/etc/hosts';

    static function init(){
        self::$host=file_get_contents(self::$path);
        self::$host_a=explode(PHP_EOL,self::$host);
        for($i=0;$i<count(self::$host_a);$i++){
            if(trim(self::$host_a[$i])==self::$TStartSection){
                self::$posSection=$i;
            }
            if(trim(self::$host_a[$i])==self::$TEndSection){
                self::$endSection=$i;
            }
        }
        if(self::$posSection===-1){
            self::$host_a[]=self::$TStartSection;
            self::$posSection=count(self::$host_a)-1;
            self::$host_a[]=self::$TEndSection;
            self::$endSection=count(self::$host_a)-1;
        }
    }

    static function addURL($name){
        if($name!="localhost"){
            $list=self::getListUrl();
            if(!in_array($name,$list)){
                $state=self::$prefix.$name;
                array_splice(self::$host_a,self::$posSection+1,0,$state);
                self::$endSection++;
            }
        }
    }
    static function removeURL($name){
        $list=self::getListUrl();
        if(in_array($name,$list)) {
            for ($i = self::$posSection; $i <= self::$endSection; $i++) {
                if (self::$host_a[$i + 1] === self::$prefix . $name) {
                    array_splice(self::$host_a, $i + 1, 1);
                }
            }
            self::$endSection--;
        }
    }
    static function removeAllURL(){
        array_splice(self::$host_a, self::$posSection+1, (self::$endSection-self::$posSection)-1);
        self::$endSection=self::$posSection+1;
    }
    static function getListUrl():array{
        $result=[];
        for($i=self::$posSection+1;$i<self::$endSection;$i++){
            $result[]=substr(self::$host_a[$i],strlen(self::$prefix));
        }
        return $result;
    }
    static function updateHost(){
        file_put_contents(self::$path,implode(PHP_EOL,self::$host_a));
    }
}

abstract class virtual{
    static $virtual,$virtual_a,
        $prefixStart="<VirtualHost *:80>",
        $prefixEnd="</VirtualHost>",$listObj=[];
    static function init()
    {
        self::$virtual = file_get_contents('../apache/conf/extra/httpd-vhosts.conf');
        self::$virtual_a = explode(PHP_EOL, self::$virtual);
    }
    static function getListObj(): array
    {
        $pStart=0;$pEnd=0;$i=0;$result=[];

        foreach(self::$virtual_a as $value){
            if(trim($value)===self::$prefixStart){
                $pStart=$i;
            }
            if(trim($value)===self::$prefixEnd&&$pStart!=0){
                $pEnd=$i;
                $value=[];
                for($i=$pStart+1;$i<$pEnd;$i++){
                    $trimVal=trim(self::$virtual_a[$i]);
                    $key=substr($trimVal,0,strpos($trimVal," "));
                    $v=substr($trimVal,strpos($trimVal," "));
                    $value[$key]=trim($v);
                }
                $value["posS"]=$pStart;
                $value["posE"]=$pEnd;
                $pStart=0;$pEnd=0;
                $result[]=$value;
            }
            $i++;
        }
        return $result;
    }
    static function removeVirtual($url,$e=""){
        $list=self::getListObj();
        $url=trim($url);
        foreach ($list as $values){
            if(trim($values['ServerName'])==$url){
                array_splice(self::$virtual_a, $values['posS'], ($values['posE']-$values['posS'])+1);
            }
        }
    }
    static function addVirtual($name,$path){
        if(preg_match('/^[C-Z|c-z]:\//',$path)==0){
            $path=dirname(__DIR__.'/..').'\\'.$path;
        }
        if(is_dir($path)) {
            $array = self::getListObj();
            foreach ($array as $value) {
                if (trim($value['ServerName']) == $name) {
                    return false;
                }
            }
            $path=str_replace('\\','/',$path);
            self::$virtual_a[] = self::$prefixStart;
            self::$virtual_a[] = '   DocumentRoot "' . $path . '"';
            self::$virtual_a[] = '   ServerName ' . $name;
            if(isset($_GET['FallbackResource'])){
                self::$virtual_a[] = '   DirectoryIndex index.php';
                self::$virtual_a[] = '   <Directory "'. $path .'">';
                self::$virtual_a[] = '      Require all granted';
                self::$virtual_a[] = '      FallbackResource /index.php';
                self::$virtual_a[] = '   </Directory>';
            }
            self::$virtual_a[] = self::$prefixEnd;
            return true;
        }
        return false;
    }
    static function updateVirtual(){
        file_put_contents('../apache/conf/extra/httpd-vhosts.conf',implode(PHP_EOL,self::$virtual_a));
    }
    static function updateHost(): bool
    {
        host::removeAllURL();
        $array=self::getListObj();
        foreach($array as $value){
            if(trim($value['ServerName'])!='localhost'){
                host::addURL(trim($value['ServerName']));
            }
        }
        host::updateHost();
        return true;
    }
    static function listHtml(){
        $html="<table><thead><th>Link</th><th>Path</th><th>Action</th></thead><tbody>";
        $list=self::getListObj();
        foreach ($list as $v){
            $html.="<tr>
                <td><a href='http://".$v['ServerName']."'>".$v['ServerName']."</a></td>
                <td>".substr($v['DocumentRoot'],1,-1)."</td>
                <td><a href='?remove=".$v['ServerName']."' class='remove-btn'>X</a></td>
                </tr>";
        }
        $html.="</tbody></table>";
        return $html;
    }
}



$errorPath=false;
$content="<h1>404 found</h1>";
if(isset($_GET['remove'])){
    virtual::removeVirtual($_GET['remove']);
    virtual::updateVirtual();
    virtual::updateHost();
}
if(isset($_GET['add'],$_GET['path'])&&trim($_GET['add'])&&trim($_GET['path'])){
    if(virtual::addVirtual(urlencode($_GET['add']),$_GET['path'])){
        virtual::updateVirtual();
        virtual::updateHost();
    }else{
        $errorPath=true;
    }

}
$content=virtual::listHtml();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Virtual-host</title>
    <style>
        body{background:#1a2029;color:#f5f5f5;font-family:sans-serif}
        .add{display:flex;flex-flow:column;margin:2em auto;border-top:1px solid grey;width:50%}
        .add form{display:flex;flex-flow:column;width:400px;align-self:center;margin-top:2em}
        form input{padding: 0.7em;border-radius:5px;border: none;}
        input[type="submit"]{margin-top:3em;background: #30363a;color: white;cursor: pointer;}
        label{font-size:.8em;margin:.4em .8em}
        th,td{padding:.2em 1em;text-align:center}
        td{border-top:1px solid #494949}
        table{margin:auto;border-collapse: collapse}
        td {line-height: 1.6em;}
        a#setting svg {height: 1.8em;fill: white;}
        a#setting {margin:1.2em 3em;display: block;padding:.5em}
				input[type="submit"]:hover {background: #3a4044;}
        .error-input {color: orangered;margin: 0.5em 0 0 1em;font-size: 12px}
        table a {color:#39adff}
        a.remove-btn {width: 2em;display: block;margin: auto;border-radius: 6px;background: #b13a28;color: #1a2029;text-decoration: none;font-weight: 700}
        a.remove-btn:hover {background: #c74d3a}
        tbody tr:hover {background: rgb(124 132 139 / 21%)}
    </style>
</head>
<body>
<a href="/" id="setting">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.1.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path d="M256 0C114.6 0 0 114.6 0 256c0 141.4 114.6 256 256 256s256-114.6 256-256C512 114.6 397.4 0 256 0zM384 288H205.3l49.38 49.38c12.5 12.5 12.5 32.75 0 45.25s-32.75 12.5-45.25 0L105.4 278.6C97.4 270.7 96 260.9 96 256c0-4.883 1.391-14.66 9.398-22.65l103.1-103.1c12.5-12.5 32.75-12.5 45.25 0s12.5 32.75 0 45.25L205.3 224H384c17.69 0 32 14.33 32 32S401.7 288 384 288z"/></svg>
</a>
    <?=$content ?>
<div class="add">
    <form method="get">
        <label>Link</label>
        <input type="text" name="add">
        <label>Path</label>
        <input type="text" name="path" value="<?= $_GET['val'] ?? 'folder' ?>">
        <?= ($errorPath)?"<span class='error-input'>Chemin de dossier introuvable</span>":""?>
        <label style="margin-top:2em"><input type="checkbox" name="FallbackResource">Rediriger les routes vers l'index</label>

        <input type="submit" value="Ajouter">
    </form>
</div>
</body>
</html>
