<?php
require_once "vendor/autoload.php";
error_reporting(E_ALL ^ E_DEPRECATED);//for hQuery
use duzun\hQuery;
class CuckooNest{
    public $target_base_url = "https://nitter.net/";
    public $status = array();
    public $page;
    public $issues = [];
    public $basename_directory;
    private $hquery_cache_path = "../../cache/";
    private $hquery_cache_expires = 3600 * 2; //couple of hours
    private $status_path = "./status.json";
    private $images_path = "./images/";
    private $existing = array('posts'=>array(), 'images'=>array());
    function __construct($current_directory=__DIR__){
        chdir($current_directory);
        $this->basename_directory = basename($current_directory);
        if(!class_exists('hQuery')){ return $this->issue("missing hQuery class"); }
        hQuery::$cache_path = $this->hquery_cache_path;
        hQuery::$cache_expires = $this->hquery_cache_expires;
        $this->loadStatus();
        $this->checkExistingPosts();
        $this->checkExistingImages();
        $this->getPage();
        $this->getProfileFromPage();
        $this->getPostsFromPage();
        $this->saveStatus();
        $this->toJSON();
    }
    function loadStatus(){
        if(!file_exists($this->status_path)){ 
            $this->status = array(
                'username'=>$this->basename_directory,
                'fullname'=>"", 'avatar'=>"",
                'posts'=>[],
                'success'=>true
            );
            $this->saveStatus(); //to check if PHP can write to this folder
        }else{
            $status_json = file_get_contents($this->status_path);
            if(!$status_json){ return $this->issue("could not open ".$this->status_path); }
            $this->status = json_decode($status_json, true);
        }
        return $this->status;
    }
    function saveStatus(){
        $status_json = json_encode($this->status, true);
        $saved = file_put_contents($this->status_path, $status_json, LOCK_EX);
        if(!$saved){
            return $this->issue("could not save to ".$this->status_path);
        }
        return $saved;
    }
    function checkExistingPosts(){
        foreach($this->status['posts'] as $post){
            $this->existing['posts'][$post['identifier']] = true;
        }
    }
    function checkExistingImages(){
        if(!is_dir($this->images_path)){
            $created = mkdir($this->images_path);
            if(!$created){
                return $this->issue("could not create ".$this->images_path);
            }
            return true;
        }
        foreach(glob($this->images_path."*.*") as $path){
            $image = pathinfo($path);
            if(!$image['filename'] || !$image['extension']){ continue; }
            $this->existing['images'][$image['filename']] = $image['extension'];
        }
    }
    function issue($message){
        $this->issues[] = $message;
        return false;
    }
    function exitIfAnyIssuesFound(){
        if($this->issues){ $this->toJSON(); }
    }
    function toJSON(){
        header("Content-Type: application/json");
        if($this->issues){ 
            exit(json_encode(array('success'=>false, 'issues'=>$this->issues), true));
        }
        exit(json_encode($this->status, true));
    }
    function getPage(){
        if(!$this->status['username']){ return $this->issue("status has no username"); }
        $url = $this->target_base_url.$this->status['username'];
        $page = hQuery::fromUrl($url, [
            "Accept" => "text/html,application/xhtml+xml;q=0.9,*/*;q=0.8",
            "User-Agent" => "BlueBirb"
        ]);
        if(!$page){
            return $this->issue("No 200 response from ".$url);
        }
        $this->page = $page;
        return $this->page;
    }
    function findElements($selectors, $target=false, $optionals=[]){
        $target = $target ? $target : $this->page;
        $elements = array();
        foreach($selectors as $name=>$selector){
            $found = $target->find($selector);
            if(!$found && !in_array($name, $optionals)){
                $this->issue("found no {$name} element with {$selector} selector");
            }else{
                $elements[$name] = $found;
            }
        }
        $this->exitIfAnyIssuesFound();
        return $elements;
    }
    function getImageURL($url){
        if(!$url){ return false; }
        $filename = crc32($url); 
        $path = "images/{$filename}.jpg";
        if(isset($this->existing['images'][$filename])){
            return $path;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $fp = fopen($path, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_exec($ch);
        if(curl_errno($ch)){
            $error_msg = curl_error($ch);
            return $this->issue("could not download image from {$url}: {$error_msg}");
        }
        curl_close($ch);
        fclose($fp);
        return $path;
    }
    function getProfileFromPage(){
        $page = $this->findElements(array(
            'fullname'=>'.profile-card-fullname',
            'username'=>'.profile-card-username',
            'avatar'=>'.profile-card-avatar'
        ));
        $username = str_replace('@', '', $page['username']->text());
        if(strtolower($username) != strtolower($this->status['username'])){
            return $this->issue("requested {$this->status['username']} and response is for {$username}");
        }
        $this->status['fullname'] = $page['fullname']->text();
        $this->status['avatar'] = $this->getImageURL($page['avatar']->attr('href'));
        return $this->status;
    } 
    function getPostsFromPage(){
        $page = $this->findElements(array('items'=>'.timeline-item'));
        foreach($page['items'] as $item){
            $elements = $this->findElements(array(
                'link'=>'.tweet-link',
                'retweet'=>'.retweet-header',
                'date'=>'.tweet-date',
                'content'=>'.tweet-content',
                'still_images'=>'.still-image img',
                'fullname'=>'.tweet-header .fullname',
                'avatar'=>'.tweet-header .avatar.round',
            ), $item, ['retweet', 'still_images']);
            $identifier = crc32($elements['link']->attr('href'));
            if(isset($this->existing['posts'][$identifier])){ 
                continue; 
            }
            $post = array(
                'identifier'=>$identifier,
                'repost'=>!!$elements['retweet'],
                'html'=>strip_tags($elements['content']->html(), '<a>'),
                'date'=>$elements['date']->attr('title'),
                'images'=>[]
            );
            if($elements['still_images']){
                foreach($elements['still_images'] as $image){
                    array_push($post['images'], $this->getImageURL($image->attr('src')));
                }
            }
            if($post['repost']){
                $post['fullname'] = $elements['fullname']->text();
                $post['avatar'] = $this->getImageURL($elements['avatar']->attr('src'));
            }
            array_push($this->status['posts'], $post);
        }
        return $this->status;
    }
    static function generateEndpoints($usernames=array()){
        if(!in_array(__FILE__, get_included_files())){
            return false; 
        }
        $index_php = "
        <?php
        require_once '../../CuckooNest.php';
        \$cn = new CuckooNest(__DIR__);
        ";
        foreach(['nests', 'cache'] as $foldername){
            $folder = __DIR__."/{$foldername}";
            if(is_dir($folder)){ continue; }
            mkdir($folder);
        }
        foreach($usernames as $username){
            $folder = __DIR__."/nests/{$username}";
            $path = $folder."/index.php";
            if(is_dir($folder)){ 
                echo "\nalready created folder for ".$username;
                continue; 
            }
            mkdir($folder);
            file_put_contents($path, $index_php);
            echo "\ncreated folder for ".$username;
        }
        echo PHP_EOL;
    }
}
if(basename(__FILE__) == "CuckooNest.php" && $argv && count($argv) > 1){
    CuckooNest::generateEndpoints(array_slice($argv, 1));
}
