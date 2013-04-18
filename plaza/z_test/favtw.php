<?php
error_reporting(0);
include('./assets.php');
set_include_path(get_include_path().PATH_SEPARATOR.$includePath);
require_once 'Services/Twitter.php';
require_once 'HTTP/OAuth/Consumer.php';
header('Content-type:text/javascript');

//ファイルロッククラス
class file_lock{
	private $lockfile;
	private $lockfp;
	//コンストラクタ
	public function __construct($lockfile = ".lock"){
		$this->lockfile = $lockfile;
	}
	//ロック用関数
	public function lock($filename){
		$this->lockfp=fopen($this->lockfile, 'w');
		return(flock($this->lockfp,LOCK_EX));
	}
	//ロック解除用
	public function unlock($filename){
		flock($this->lockfp, LOCK_UN);
		fclose($this->lockfp);
	}
}

function wbsRequest2($method, $url, $params = array())
{
    $data = http_build_query($params);
    if($method == 'GET') {
        $url = ($data != '')?$url.'?'.$data:$url;
    }
    $ch = curl_init($url);
    if($method == 'POST'){
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $res = curl_exec($ch);
    //ステータスをチェック
    $respons = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if(preg_match("/^(404|403|500)$/",$respons)){
        return false;
    }
    return $res;
}

function readcache($c){
	$fh=fopen($c, "r");
	if(!$fh) die("var errstr='002 could not open cachefile ".$c."';");
	$json="";
	while (!feof($fh)) {
		$json.=fgets($fh);
	}
	$dbpath = "../fire/forex";
	
	
	fclose($fh);
	return $json;
}

//連想配列の配列の要素を検索
function isMember($ar, $tkey, $tval){
	foreach($ar as $a){
		if(gettype($a)=='object'){
			if($a->$tkey===$tval){
				return TRUE;
			}
		} else if(gettype($a)=='array'){
			if($a[$tkey]===$tval){
				return TRUE;
			}
		}
	}
	return FALSE;
}

//いらないフィルターの除去
function updateFilter($arr, $fil){
	global $dbpath;
	$dellist=array();
	foreach($fil as $f){
		//数字以外はスキップ
		if(!preg_match("/^[0-9]+$/", $f['bad_word'])) continue;
		$flag=true;
		foreach($arr as $a){
			if($f['bad_word']==$a['id_str']){
				$flag=false;
				break;
			}
		}
		if($flag){
			array_push($dellist, $f['bad_word']);
		}
	}
	if(count($dellist)>0){
		//NGリストから削除
		try {
			$dbh=new PDO('sqlite:'.$dbpath);
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			$dbh->exec("DELETE FROM filter WHERE bad_word IN('".join($dellist, "','")."')");
			unset($dbh);
		} catch (PDOExcetion $e){
			die("var errstr='Error: ".$e->getMessage()."';");
		}
	}
}

function putExit($tw, $cat, $fil, $option){
		echo 'var favtw='.$tw.";\n";
		echo 'var filter='.json_encode($fil).";\n";
		echo "var format=".$option[0]['format'].";\n";
		echo "var period='".$option[0]['period']."';\n";
		echo "var redraw='".$option[0]['redraw']."';\n";
		echo 'twitterCallback2(favtw, filter, redraw);';
		exit(1);
}

//main
$flock=new file_lock($cachepath.'.lock');	//ロックオブジェト
if(!file_exists($cachepath)){
	//キャッシュファイルが存在しなければ作成。初回のみの処理。
	$fh=fopen($cachepath, "w");
	if(!$fh) die("var errstr='000 could not create cache file.';");
	fclose($fh);
}

try {
   // connect to database
   $dbh = new PDO('sqlite:'.$dbpath);
   // query and retrieve active accounts
   $sth = $dbh->query("SELECT * FROM accounts WHERE activity = '1'");
   $accounts = $sth->fetchAll();
   $sth = null;
   // カテゴリーを取得
   $sth=$dbh->query("SELECT * FROM category");
   $category=json_encode($sth->fetchAll());
   $sth=null;
   // フィルターを取得
   $sth=$dbh->query("SELECT * FROM filter");
   $filter=$sth->fetchAll();
   $sth=null;
   // 設定を取得
   $sth=$dbh->query("SELECT * FROM options");
   $option=$sth->fetchAll();
   $sth=null;
   unset($dbh);
} catch (PDOException $e) {
   die("var errstr='100 Error: " . $e->getMessage()."';");
}

//処理中キャッシュファイルをロックする
if($flock->lock($cachepath)){
	if(filemtime($cachepath)<time()-(3600/150)+1 || filesize($cachepath)<1 || $_GET['refresh']=="true"){
		//最新つぶやきidの計算
		$json=readcache($cachepath);
		$tw=json_decode($json, true);
		$sinceId="0";
		foreach($tw as $t){
			if(intval($t['id_str'])>$sinceId){
				$sinceId=intval($t['id_str']);
			}
		}
		//refreshパラメータがtrueの場合は200件全取得
		if($_GET['refresh']=="true") $sinceId=0;
		//つぶやきの新規取得
		try { 
			$twitter = new Services_Twitter();
			$oauth = new HTTP_OAuth_Consumer($consumerKey, 
					$consumerSecret,
					$accessToken, 
					$accessTokenSecret);
			$twitter->setOAuth($oauth);
			if(strval($sinceId)==0){
				$msg = $twitter->statuses->home_timeline(array('count'=>200, 'include_rts'=>"true")); 
			} else {
				$msg = $twitter->statuses->home_timeline(array('count'=>200, 'since_id'=>$sinceId, 'include_rts'=>"true")); 
			}
		} catch (Services_Twitter_Exception $e) { 
			die("var errstr='".$e->getMessage()."'");
		}
		$jsarr=array();
		foreach($accounts as $a){
			$user=array();
			foreach($a as $key => $v){
				//記号の'を置き換え
				$a[$key]=str_replace('\'','`',$v);
				//前後の空白改行タブを削除
				$a[$key]=preg_replace("/^[\\s]+/", "", $a[$key]);
				$a[$key]=preg_replace("/[\\s]+$/", "", $a[$key]);
			}
			$user["name"]=$a["name"];
			$user["twname"]=$a["twname"];
			$user["link"]=$a["link"];
			$user["category"]=$a["category"];
			$user["avatar"]=$a["avatar"];
			//もし$msgに新しいつぶやきがあれば最新のもの採用
			$res=NULL;
			$latest=$sinceId;
			foreach($msg as $m){
				//$i=intval($m->id_str);
				if($m->id_str>$latest && $m->user->screen_name==$a['twname']){
					$res=array('name'=>$m->user->name, 'twname'=>$m->user->screen_name, 'avatar'=>$m->user->profile_image_url,
							'link'=>$m->user->url, 'text'=>$m->text, 'id_str'=>$m->id_str, 'created_at'=>$m->created_at);
					$latest=$m->id_str;
				}
			}
			if(!$res){
				//でなければキャッシュのつぶやき$twを採用
				foreach($tw as $t){
					if($t['twname']==$a['twname']) $res=$t;
				}
			}
			if(isset($res['id_str'])){
				$user["text"]=$res['text'];
				$user["created_at"]=$res['created_at'];
				$user["id_str"]=$res['id_str'];
				if(strlen($user["name"])==0) $user["name"]=$res['name'];
				if(strlen($user["avatar"])==0) $user["avatar"]=$res['avatar'];
				if(strlen($user["link"])==0) $user["link"]=$res['link'];
			} else {
				//エラーの場合は次のID
				if($a['name']!="") array_push($jsarr, array('name' => $a['name'],'twname' => $a['twname'],'link' => $a['link'],'category' => $a['category'],'avatar' => $a['avatar'],'text' => '更新待ち','created_at' => '','id_str' => ''));
				continue;
			}
			array_push($jsarr, $user);
		}
		//キャッシュ更新
		$json_jsarr=json_encode($jsarr);
		$fh=fopen($cachepath, "w");
		fputs($fh, $json_jsarr);
		fclose($fh);
		$flock->unlock($cachepath);
		//NGリストの更新
		updateFilter($jsarr, $filter);
		//httpd出力・終了
		putExit($json_jsarr, $category, $filter, $option);
	}
	//useCache:	//キャッシュ使用
	$json=readcache($cachepath);
	$flock->unlock($cachepath);
	$tw=json_decode($json, true);
	//追加したばかりでキャッシュにないもの
	foreach($accounts as $a){
		if(!isMember($tw, 'twname', $a['twname'])){
			array_push($tw, array('name' => $a['name'],'twname' => $a['twname'],'link' => $a['link'],'category' => $a['category'],'avatar' => $a['avatar'],'text' => '更新待ち','created_at' => '','id_str' => ''));
		}
	}
	//削除したばかりでキャッシュに残っているもの
	for($i=0; $i<count($tw); $i++){
		if(!isMember($accounts, 'twname', $tw[$i]['twname']) && $tw[$i]['id_str']!=""){
			$tw[$i]['twname']="";
		}
	}
	$json=json_encode($tw);
	//httpd出力
	putExit($json, $category, $filter, $option);
} else {
	echo "var errstr='001 could not lock cachefile.';alert(errstr);";
}
?>
