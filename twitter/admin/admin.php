<?php
include('../assets.php');
$dbpath=pathConv($dbpath);
set_include_path(get_include_path().PATH_SEPARATOR.pathConv($includePath));
require_once 'Services/Twitter.php';
require_once 'HTTP/OAuth/Consumer.php';

//キャッシュ対策
header("Expires: Thu, 01 Dec 1994 16:00:00 GMT");
header('Pragma: no-cache');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

//path conversion
function pathConv($path){
	if(preg_match("/^\.[^\.]/",$path)){
		return ".".$path;
	} else if(preg_match("|^/|",$path)){
		return $path;
	} else {
		return "../".$path;
	}
}

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

function jspr($str){
	echo "alert('{$str}');\n";
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
    //curl_setopt($ch, CURLOPT_HEADER,true); //header情報も一緒に欲しい場合
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
	fclose($fh);
	return $json;
}
//連想配列の配列の要素を検索
function isMember($ar, $tkey, $tval){
	foreach($ar as $a){
		if(gettype($a)=='object'){
			if($a->$tkey===$tval){
				echo "###".$a->$tkey."vs.".$tval;
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

//main
//error_reporting(0); //エラー出力いらないとき
try{
   // connect to database
   $dbh = new PDO('sqlite:'.$dbpath);
   $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
   //停止中のアカウント取得
   $sth = $dbh->query("SELECT * FROM accounts WHERE activity = '0'");
   $suspended = $sth->fetchAll();
   $sth = null;
   // 設定を取得
   $sth=$dbh->query("SELECT * FROM options");
   $options=$sth->fetchAll();
   $sth=null;
   unset($dbh);
} catch (PDOException $e){
   die("Error: " . $e->getMessage());
}
//停止中アカウントのhtml作成
$sarr=array();
foreach($suspended as $s){
	array_push($sarr, '<a href="#"><span id="suspended" class="menuLetter" onclick="selectID(\''.$s['twname'].'\')">'.$s['name'].'(@'.$s['twname'].')</span></a>');
}
$suspendedHTML=join($sarr, '、');
$refresh="false";
if(count($_POST)>0){
	try {
	   // connect to database
	   $dbh = new PDO('sqlite:'.$dbpath);
	   $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
	   // query and retrieve active accounts
	   $sth = $dbh->query("SELECT * FROM accounts WHERE activity = '1'");
	   $accounts = $sth->fetchAll();
	   $sth = null;
	   // カテゴリーを取得
	   $sth=$dbh->query("SELECT * FROM category");
	   $category=$sth->fetchAll();
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
	   die("Error: " . $e->getMessage());
	}
	if($_POST['action']==='editID'){
		$twname=urldecode($_POST['twname']);
		//アカウントの更新
		if(isMember($accounts, 'twname', $twname)){
			//既存データの更新
			try {
				$dbh=new PDO('sqlite:'.$dbpath);
				$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
				$dbh->exec("UPDATE accounts SET avatar='{$_POST['avatar']}', activity='{$_POST['activity']}', name='{$_POST['name']}', link='{$_POST['link']}', category='{$_POST['category']}' WHERE twname='$twname'");
				unset($dbh);
			} catch (PDOExcetion $e){
				die("Error: ".$e->getMessage());
			}
			$msg="アカウント情報を更新しました。";
		} else {
			//新規データの追加
			$flag=true;
			try { 
				$twitter = new Services_Twitter();
				$oauth = new HTTP_OAuth_Consumer($consumerKey, 
						$consumerSecret,
						$accessToken, 
						$accessTokenSecret);
				$twitter->setOAuth($oauth);
				$msg = $twitter->friendships->create($twname); 
			} catch (Services_Twitter_Exception $e) { 
				$flag=false;
			}
			if($flag){
				try {
					$dbh=new PDO('sqlite:'.$dbpath);
					$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
					$dbh->exec("INSERT INTO accounts VALUES ('{$_POST['avatar']}','{$_POST['activity']}','{$_POST['name']}','$twname','{$_POST['link']}','{$_POST['category']}')");
					unset($dbh);
				} catch (PDOExcetion $e){
					die("Error: ".$e->getMessage());
				}
				$msg="アカウントを追加しました。";
				$refresh="true";
			} else {
				$msg="アカウントを追加できませんでした。";
			}
		}
	} else if($_POST['action']==='deleteID'){
		//アカウント削除
		$id=urldecode($_POST['id']);
		try {
			$dbh=new PDO('sqlite:'.$dbpath);
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			$dbh->exec("DELETE FROM accounts WHERE twname='$id'");
			unset($dbh);
		} catch (PDOExcetion $e){
			die("Error: ".$e->getMessage());
		}
		$msg="アカウントを削除しました。";
	} else if($_POST['action']==='toggleDisp'){
		//表示・非表示切り替え
		if($_POST['show']=="1"){
			//非表示=NGリストに追加
			try {
				$dbh=new PDO('sqlite:'.$dbpath);
				$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
				$dbh->exec("INSERT INTO filter VALUES ('{$_POST['id']}')");
				unset($dbh);
			} catch (PDOExcetion $e){
				die("Error: ".$e->getMessage());
			}
			$msg="非表示にしました。";
		} else {
			//表示=NGリストから削除
			try {
				$dbh=new PDO('sqlite:'.$dbpath);
				$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
				$dbh->exec("DELETE FROM filter WHERE bad_word='{$_POST['id']}'");
				unset($dbh);
			} catch (PDOExcetion $e){
				die("Error: ".$e->getMessage());
			}
			$msg="非表示を解除しました。";
		}
	} else if($_POST['action']==='deleteCategory'){
		$name=urldecode($_POST['name']);
		//カテゴリー削除
		var_dump($_POST);
		echo $name;
		try {
			$dbh=new PDO('sqlite:'.$dbpath);
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			$dbh->exec("DELETE FROM category WHERE name='$name'");
			unset($dbh);
		} catch (PDOExcetion $e){
			die("Error: ".$e->getMessage());
		}
		$msg="カテゴリーを削除しました。";
	} else if($_POST['action']==='editCategory'){
		if(isMember($category, 'name', $_POST['name'])){
			//カテゴリー編集
			try {
				$dbh=new PDO('sqlite:'.$dbpath);
				$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
				$dbh->exec("UPDATE category SET attribute='{$_POST['attribute']}', name='{$_POST['name']}', symbol='{$_POST['symbol']}' WHERE name='{$_POST['name']}'");
				unset($dbh);
			} catch (PDOExcetion $e){
				die("Error: ".$e->getMessage());
			}
			$msg="カテゴリーを更新しました";
		} else {
			//カテゴリー追加
			try {
				$dbh=new PDO('sqlite:'.$dbpath);
				$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
				$dbh->exec("INSERT INTO category VALUES ('{$_POST['attribute']}','{$_POST['name']}','{$_POST['symbol']}')");
				unset($dbh);
			} catch (PDOExcetion $e){
				die("Error: ".$e->getMessage());
			}
			$msg="カテゴリーを追加しました。";
		}
	} else if($_POST['action']==='editOption'){
			//オプション編集
			try {
				$dbh=new PDO('sqlite:'.$dbpath);
				$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
				$dbh->exec("UPDATE options SET name='option', period='{$_POST['period']}', format='{$_POST['format']}', redraw='{$_POST['redraw']}' WHERE name='option'");
				unset($dbh);
			} catch (PDOExcetion $e){
				die("Error: ".$e->getMessage());
			}
			$msg="オプションを更新しました";
	}
}
?>
<!--?xml version="1.0" encoding="UTF-8"?-->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="ja">
<head>
<title>Favorite Twitters Admin Page</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" href="./favtw.css" type="text/css">
<script type="text/javascript">
var suspended=<?php echo json_encode($suspended)?>;
var options=<?php echo json_encode($options)?>;
</script>
</head><body bgcolor="#00b4e1">
<div id="editor" style="margin:10px;position:static">
<h2>管理画面</h2>
<p>管理メニュー [<a href="#"><span id="idtab" class="menuLetter" onclick="selectMenu('editID')" style="cursor:pointer">IDの追加・編集</span></a>/
<!--span id="categorytab" class="menuLetter" onclick="selectMenu('editCategory')" style="cursor:pointer">カテゴリーの追加・編集</span-->
<a href="#"><span id="optiontab" class="menuLetter" onclick="selectMenu('editOption')" style="cursor:pointer">オプションの編集</span></a>] <span style="color:lightsalmon"><?php echo $msg ?></span></p>

<div id="editID" style="display:none">
IDの追加・編集(つぶやき欄の画像をクリックするとIDの属性を入力欄にコピーします)<br>
IDの追加にはツイッター管理アカウントでIDをフォローする必要があります。<br>
<form action="./admin.php" method="POST" accept-charset="utf-8" name="editID">
<input type="hidden" name="action" value="editID">
<p>ID:<input type="text" name="twname" size=50> (IDを変更すると追加になります)</p>
<p>名前:<input type="text" name="name" size=30> (省略するとツイッター登録名が使用されます)</p>
<p>リンク:<input type="text" name="link" size=120><br>(省略するとツイッター登録リンクが使用されます)</p>
<p>カテゴリー:<select name="category" id="categorySelection">
<option>レストラン</option>
<option>ショップ</option>
<option>サービス</option>
<option>クリニック</option>
<option>root</option>
</select> (rootを選択するとリストの先頭に表示されます)</p>
<p>画像URI:<input type="text" name="avatar" size=120><br>(省略するとツイッター登録画像が使用されます)</p>
<p>状態:<input type="radio" name="activity" value="1">有効
<input type="radio" name="activity" value="0">停止 (一時停止します。画像下の表示・非表示はそのつぶやきだけを表示・非表示にします)</p>
<p>停止中のアカウント：<?php echo $suspendedHTML ?></p>
<input type="submit" value="確定"> <input type="reset" value="クリア">
</form></div>

<div id="editOption" style="display:none">
オプションの編集<br>
<form action="./admin.php" method="POST" accept-charset="utf-8" name="editOption">
<input type="hidden" name="action" value="editOption">
<p>禁止語句:<textarea cols=20 rows=10 name="bad_words" style="vertical-align:top"></textarea>（禁止語句を含むつぶやきは表示されません。改行で区切って入力。）
<p>表示期限:<input type="text" name="period" size=10>日を超えたつぶやきは表示されません。</p>
<p>更新時間:<input type="text" name="redraw" size=10>秒間隔でつぶやきを自動更新します。（０の場合自動更新しない。管理画面は自動更新しません。）</p>
<p>日付形式:<input type="radio" name="format" value="1">「〜日時分前」形式
<input type="radio" name="format" value="0">「年月日時分」形式</p>
<input type="submit" value="確定"> <input type="reset" value="クリア">
</form></div>
</div>

<div id="top-stuff"><span id="title"><?php echo $options[0]['title']?></span><div id="twlogo"><a href="http://twitter.com/"></a></div></div>
<?php echo '<div id="doc" style="background:'.$options[0]["bkcolor"].' url(.'.$options[0]["bkimage"].') no-repeat fixed">'?>
<div id="page-container">
<div id="main-content">
<div id="root_twitter"></div>
<div id="twitter_update_list"><div class="loader"><div id="loader"></div><p>読み込み中</p></div></div>

</div>
<div id="dashboard"></div>
</div></div>
<div id="debug"></div>
</body>
<script type="text/javascript" src="./admin.js" charset="utf-8"></script>
<script type="text/javascript" src="../favtw.php?refresh=<?= $refresh ?>&t=<?= time(); ?>" charset="utf-8"></script>
</html>
