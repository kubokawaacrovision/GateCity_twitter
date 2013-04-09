//globals
var statusHTML=new Array();
var menuHTML=new Array();
var CATEGORY={"root":[4,""],"レストラン":[0,"restaurant_s"],"ショップ":[1,"shops_s"],"サービス":[2,"services_s"],"クリニック":[3,"clinic_s"]};
//debug用
function dump(arr,level) {
	var dumped_text = "";
	if(!level) level = 0;
	
	//The padding given at the beginning of the line.
	var level_padding = "";
	for(var j=0;j<level+1;j++) level_padding += "    ";
	
	if(typeof(arr) == 'object') { //Array/Hashes/Objects 
		for(var item in arr) {
			var value = arr[item];
			
			if(typeof(value) == 'object') { //If it is an array,
				dumped_text += level_padding + "'" + item + "' ...\n";
				dumped_text += dump(value,level+1);
			} else {
				dumped_text += level_padding + "'" + item + "' => \"" + value + "\"\n";
			}
		}
	} else { //Stings/Chars/Numbers etc.
		dumped_text = "===>"+arr+"<===("+typeof(arr)+")";
	}
	return dumped_text;
}
//カテゴリーオブジェクト
function catObj(cat){
	this.data=cat;
}
catObj.prototype.attr=function(c){
	for(i in this.data){
		if(this.data[i].name==c){
			return this.data[i].attribute;
		}
	}
	return false;
}
catObj.prototype.symbol=function(c){
	for(i in this.data){
		if(this.data[i].name==c){
			return this.data[i].symbol;
		}
	}
	return false;
}
//フィルターオブジェクト
function filObj(fil){
	this.data=fil;
}
filObj.prototype.validate=function(word){
	for(i in this.data){
        if(word.indexOf(this.data[i].bad_word)>-1){
            return false;
        }
	}
	return true;
}
function postSend(kv){
    var f=document.createElement('form');
    f.method="POST";
    document.body.appendChild(f);
    for(i in kv){
        var p=document.createElement('input');
        p.type="hidden";
        p.name=i;
        p.value=kv[i];
        f.appendChild(p);
    }
    f.submit();
}
function alterFilter(id, flag){
    if(!confirm(((flag=="1")?"非表示":"表示")+'にしますか？')) return;
    postSend({'action':'toggleDisp', 'id':escape(id), 'show':flag});
}
function deleteCategory(name){
    if(!confirm('カテゴリーを削除しますか？')) return;
    postSend({'action':'deleteCategory', 'name':encodeURIComponent(name)});
}
function deleteID(id){
    if(!confirm('ID"'+id+'"を削除しますか？')) return;
    postSend({'action':'deleteID', 'id':escape(id)});
}
//エントリー関数
function twitterCallback2(twitters, mask) {
  var filter=new filObj(mask);
  //つぶやき 新しい順にソート
  for(i=0; i<twitters.length-1; i++){
  	for(j=i+1; j<twitters.length; j++){								  	  	//カテゴリーrootは一番上
  		if(u_time(twitters[i].created_at)<u_time(twitters[j].created_at) || twitters[j].category=='root'){
            if(twitters[i].category!='root'){
                tmptw=twitters[i];
                twitters[i]=twitters[j];
                twitters[j]=tmptw;
            }
  		}
  	}
  }
  //twitterごとに出力
  for (var i=0; i<twitters.length; i++){
    var username = twitters[i].twname;
    var visible=true;
    var root=twitters[i].category.match(/^root$/i);
    var status = twitters[i].text.replace(/((https?|s?ftp|ssh)\:\/\/[^"\s\<\>]*[^.,;'">\:\s\<\>\)\]\!])/g, function(url) {
      return '<a href="'+url+'">'+url+'</a>';
    }).replace(/\B@([_a-z0-9]+)/ig, function(reply) {
      return  reply.charAt(0)+'<a href="http://twitter.com/'+reply.substring(1)+'">'+reply.substring(1)+'</a>';
    });
    if(!twitters[i].twname) continue;
    //表示・非表示
    if(!filter.validate(status) || !filter.validate(twitters[i].id_str)) visible=false;
    //イメージ
    var twimage='<div id="img_container"><img src="'+twitters[i].avatar+'" width="48" height="48" onclick="selectID(\''+twitters[i].twname+'\')"><div class="small">'+((visible)?"表示":"非表示")+'</div></div>';
    //名前・ID
    var nickname='<div id="twname"><a href='+twitters[i].link+' title="HPを開く">'+'<span  class="nickname">'+twitters[i].name+'</span></a> <a href="http://twitter.com/#!/'+username+'" title="ツイッターホームを開く">(@'+username+')</a><span class="smallRight" onclick="deleteID(\''+username+'\')">ID削除</span></div>';
    if(root) nickname=nickname='<div id="twname"><a href='+twitters[i].link+' title="HPを開く">'+'<span  class="nickname">'+twitters[i].name+'</span></a> <a href="http://twitter.com/#!/'+username+'" title="ツイッターホームを開く">(@'+username+')</a></div>';
    //タイムスタンプ
    var timestamp=((format)? relative_time(twitters[i].created_at):absolute_time(twitters[i].created_at));
	var dval=new Date();
    if(u_time(twitters[i].created_at)<dval.getTime()+dval.getTimezoneOffset()*60*1000-period*24*3600*1000) timestamp="<font color='crimzson'>"+timestamp+"</font>";
    timestamp='<a style="font-size:85%" href="http://twitter.com/'+username+'/statuses/'+twitters[i].id_str+'" title="つぶやきを開く">'+timestamp+'</a>';
    //カテゴリー属性
    var catStr='<div id="twcat" class="'+CATEGORY[twitters[i].category][1]+'">'+twitters[i].category+'</div>';
    if(root) catStr="";
    
    //エレメントオブジェクト作成
    var elem=document.createElement('div');
    if(root){
        elem.setAttribute('id', 'root');
    } else {
        elem.setAttribute('id', 'user');
    }
    elem.innerHTML=twimage+nickname+'<div id="tweet-content">'+status+'</div>'+'<div id="tmwrapper">'+timestamp+catStr+'</div>';
    if(twitters[i].category.match(/^root$/i)) elem.innerHTML+='<div id="message">'+'</div>';
    statusHTML.push([elem, twitters[i].category]);
  }
  
  var twlistelem=document.getElementById('twitter_update_list');
  twlistelem.innerHTML="";
  for(i in statusHTML){
    twlistelem.appendChild(statusHTML[i][0]);
  }
}


//UNIX時間を返す
function u_time(time_value){
  var values = time_value.split(" ");
  time_value = values[1] + " " + values[2] + ", " + values[5] + " " + values[3];
  var parsed_date = Date.parse(time_value);
  return parsed_date;
}

//yyyy-mm-dd形式で返す
function absolute_time(time_value){
  var values = time_value.split(" ");
  time_value = values[1] + " " + values[2] + ", " + values[5] + " " + values[3];
  dt=new Date(time_value);
  local=new Date();
  dt.setTime(dt.getTime()-local.getTimezoneOffset()*60*1000);
  yy = dt.getYear();
  if (yy < 2000) { yy += 1900; }
  mm = (dt.getMonth()+101).toString().slice(1);
  dd = (dt.getDate()+100).toString().slice(1);
  hh=dt.getHours();
  mn=(dt.getMinutes()+100).toString().slice(1);
  return yy+"-"+mm+"-"+dd+" "+hh+":"+mn;
}

//何分、何時間前形式で返す
function relative_time(time_value) {
  var values = time_value.split(" ");
  time_value = values[1] + " " + values[2] + ", " + values[5] + " " + values[3];
  var parsed_date = Date.parse(time_value);
  var relative_to = (arguments.length > 1) ? arguments[1] : new Date();
  var delta = parseInt((relative_to.getTime() - parsed_date) / 1000);
  delta = delta + (relative_to.getTimezoneOffset() * 60);
  if (delta < 60 *12) {
    return "ちょっと前";	//1分前までは「ちょっと前」
  } else if(delta < 120) {
    return "約一分前";
  } else if(delta < (60*60)) {
    return (parseInt(delta / 60)).toString() + " 分前";
  } else if(delta < (120*60)) {
    return "約一時間前";
  } else if(delta < (24*60*60)) {
    return "約 " + (parseInt(delta / 3600)).toString() + " 時間前";
  } else if(delta < (48*60*60)) {
    return "一日前";
  } else {
    return (parseInt(delta / 86400)).toString() + " 日前";
  }
}
//メニュー選択
function selectMenu(tab){
    var tabs=['editID','editOption'];
    for(i in tabs){
        if(tabs[i]==tab){
            var s=document.getElementById(tab).style;s.display='block';
            if(tab=='editID'){
                var ei=document.editID;
                if(!ei.activity[0].checked && !ei.activity[1].checked) ei.activity[0].click();
            } else if(tab=='editOption'){
                var eo=document.editOption;
                eo.period.value=period;
                eo.redraw.value=redraw;
                eo.bad_words.value=(function(){
                    var bw=new Array();
                    for(i in filter){
                        if(!filter[i].bad_word.match(/^\d+$/))
                            bw.push(filter[i].bad_word);
                    }
                    return bw.join('\n');
                })();
                if(format==1) eo.format[0].click();
                else eo.format[1].click();
            }
        } else {
            var s=document.getElementById(tabs[i]).style;s.display='none';
        }
    }
}
//ID選択
function selectID(id){
    var src=(function(){
        for(i in favtw){
            if(favtw[i].twname==id) return favtw[i];
        }
        for(i in suspended){
            if(suspended[i].twname==id) return suspended[i];
        }
    })();
    var target=document.editID;
    target.twname.value=src.twname;
    target.name.value=src.name;
    if(!src.link.match(/^http:\/\/..\.twimg\.com/)) target.link.value=src.link;
    if(!!window.attachEvent) document.getElementById('categorySelection').selectedIndex=CATEGORY[src.category][0];	//this is IE
    else target.category.value=src.category;
    target.avatar.value=src.avatar;
    if(!src.activity) target.activity[0].click();
    else target.activity[1].click();
}
