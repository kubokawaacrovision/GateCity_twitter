var statusHTML=new Array();
var twwapper, filtered="all";
var CATEGORY={"root":[4,""],"レストラン":[0,"restaurant_s"],"ショップ":[1,"shops_s"],"サービス":[2,"services_s"],"クリニック":[3,"clinic_s"]};
var spinner=new Spinner().spin();
var target=document.getElementById('loader');
target.appendChild(spinner.el);
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
var eObj=function(e){
	this.elem=e;
}
eObj.prototype.getTwTags=function(o){
	var n=o;
	while(n){
		if(n.id=="twavatar") this.avatar=n;
		else if(n.id=="twname") this.name=n;
		else if(n.id=="twtext") this.text=n;
		else if(n.id=="twtime") this.time=n;
		else if(n.id=="twlink") this.link=n;
		else if(n.id=="twcategory") this.category=n;
		if(n.hasChildNodes()){
			this.getTwTags(n.firstChild);
		}
		n=n.nextSibling;
	}
}
function twitterCallback2(twitters, mask, redraw) {
  var filter=new filObj(mask);
  statusHTML.length=0;
  for(i=0; i<twitters.length-1; i++){
  	for(j=i+1; j<twitters.length; j++){								  	    		if(u_time(twitters[i].created_at)<u_time(twitters[j].created_at) || twitters[j].category=='root'){
            if(twitters[i].category!='root'){
                tmptw=twitters[i];
                twitters[i]=twitters[j];
                twitters[j]=tmptw;
            }
  		}
  	}
  }
	twwrapper=document.getElementById("left_contents");
	  for(i=0, j=twwrapper.children.length; i<j; i++){
		twwrapper.removeChild(twwrapper.lastChild);
	  }
	  twwrapper.innerHTML="";
	var twheader=document.getElementById("twheader").cloneNode(true);
  	twheader.innerHTML='<h3 class="title_twitter">ゲートシティプラザのつぶやき</h3>';
  	twwrapper.appendChild(twheader);
  for (var i=0; i<twitters.length; i++){
    var username = twitters[i].twname;
    var status = twitters[i].text.replace(/((https?|s?ftp|ssh)\:\/\/[^"\s\<\>]*[^.,;'">\:\s\<\>\)\]\!])/g, function(url) {
      return '<a href="'+url+'">'+url+'</a>';
    }).replace(/\B@([_a-z0-9]+)/ig, function(reply) {
      return  reply.charAt(0)+'<a href="http://twitter.com/'+reply.substring(1)+'">'+reply.substring(1)+'</a>';
    });
    var dval=new Date();
    if(u_time(twitters[i].created_at)<dval.getTime()+dval.getTimezoneOffset()*60*1000-period*24*3600*1000) break;
    if(!twitters[i].id_str || !twitters[i].twname || !filter.validate(status) || !filter.validate(twitters[i].id_str)) continue;
	var twuser=new eObj(document.getElementById("twuser").cloneNode(true));
	twuser.getTwTags(twuser.elem);
    twuser.avatar.src=twitters[i].avatar;
	twuser.avatar.alt=username;
	twuser.name.innerHTML=twitters[i].name;
	twuser.name.href="http://twitter.com/#!/"+username;
	if(twitters[i].category.match(/^root$/i)) twuser.category.innerHTML="&nbsp";
	else {
		var atag=document.createElement('a');
		atag.href="#";
		var img=document.createElement('img');
		img.src="/plaza/twitter/img/"+CATEGORY[twitters[i].category][1]+".gif";
		img.alt=twitters[i].category;
		img.className="ro";
		img.setAttribute('onclick',"filtering('"+twitters[i].category+"')");
		atag.appendChild(img);
		twuser.category.appendChild(atag);
	}
	twuser.text.innerHTML=status;
    twuser.time.innerHTML=((format)? relative_time(twitters[i].created_at):absolute_time(twitters[i].created_at));
	var img=document.createElement('img');
	if(twitters[i].category.match(/^root$/i)) img.src="/plaza/twitter/img/gco.gif";
	else img.src="/plaza/twitter/img/date.gif";
	img.alt="店舗のページへ"; 
	img.className="ro";
	var atag=document.createElement('a');
	atag.href=twitters[i].link;
	atag.appendChild(img);
	twuser.link.appendChild(atag);
    statusHTML.push([twuser.elem, twitters[i].category]);
  }
  // ゲートシティ各店舗のつぶやき
  filtering(filtered);
  // ゲートシティ各店舗の親元つぶやき
  //OfficialTwitter(filtered);
  //-----
  if(redraw){
	  setTimeout(function(){
	  	var favphp=document.getElementById('favtwphp');
	  	var src=favphp.getAttribute('src');
	  	src.match(/^.*favtw\.php/);
	  	
		document.body.removeChild(favphp);
		var jsp=document.createElement('script');
		jsp.setAttribute('type', 'text/javascript');
		jsp.setAttribute('src', RegExp.lastMatch+'?'+(new Date()).getTime());
		jsp.setAttribute('charset', 'utf-8');
		jsp.setAttribute('id', 'favtwphp');
		document.body.appendChild(jsp);
	  }, redraw*1000);
  }
}

/**** Time ****/
function u_time(time_value){
  var values = time_value.split(" ");
  time_value = values[1] + " " + values[2] + ", " + values[5] + " " + values[3];
  var parsed_date = Date.parse(time_value);
  return parsed_date;
}
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
function relative_time(time_value) {
  var values = time_value.split(" ");
  time_value = values[1] + " " + values[2] + ", " + values[5] + " " + values[3];
  var parsed_date = Date.parse(time_value);
  var relative_to = (arguments.length > 1) ? arguments[1] : new Date();
  var delta = parseInt((relative_to.getTime() - parsed_date) / 1000);
  delta = delta + (relative_to.getTimezoneOffset() * 60);
  if (delta < 60) {
    return "ちょっと前";
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

/**** GateCity each store ****/
function filtering(cat){
    nchild=twwrapper.children.length;
    for(i=0; i<nchild; i++){
        twwrapper.removeChild(twwrapper.lastChild);
    }
	var twheader=document.getElementById("twheader").cloneNode(true);
  	twheader.innerHTML='<h3 class="title_twitter">ゲートシティプラザのつぶやき</h3>';
  	twwrapper.appendChild(twheader);
	filtered=cat;
    for(i=0; i<statusHTML.length; i++){
        if(statusHTML[i][1]==cat || cat=='all' || i==0){
            twwrapper.appendChild(statusHTML[i][0]);
			if(i==0){
				var twheader=document.getElementById("twheader").cloneNode(true);
				twheader.innerHTML='<h3 class="title_twitter">ゲートシティプラザ　お店のつぶやき</h3>\n<span class="date_txt_small">※掲載情報に関するお問い合わせは、各店舗までお願いします。</span>\n';
				twwrapper.appendChild(twheader);
			} else {
				var twline=document.createElement('div');
				var twlineimg=document.createElement('img');
				twlineimg.src="/plaza/twitter/img/line.gif";
				twline.className="twitter_line";
				twline.appendChild(twlineimg);
				twwrapper.appendChild(twline);
				
				twheader.innerHTML='<h3 class="title_twitter">オフィシャルツィート</h3>\n<span class="date_txt_small">※掲載情報に関するお問い合わせは、各店舗までお願いします。</span>\n';
				twrapper.append(twheader);
			}
        }
    }
}

/**** Official Store Tweet ****/
function OfficialTwitter(cat){
	nchild=twwrapper.children.length;
    for(i=0; i<nchild; i++){
        twwrapper.removeChild(twwrapper.lastChild);
    }
	var twheader=document.getElementById("twheader").cloneNode(true);
  	twheader.innerHTML='<h3 class="title_twitter">ゲートシティプラザのつぶやき</h3>';
  	twwrapper.appendChild(twheader);
	filtered=cat;
    for(i=0; i<statusHTML.length; i++){
        if(statusHTML[i][1]==cat || cat=='all' || i==0){
            twwrapper.appendChild(statusHTML[i][0]);
			if(i==0){
				var twheader=document.getElementById("twheader").cloneNode(true);
				twheader.innerHTML='<h3 class="title_twitter">オフィシャルのつぶやき</h3>\n<span class="date_txt_small">※掲載情報に関するお問い合わせは、公式ホームページまでお願いします。</span>\n';
				twwrapper.appendChild(twheader);
			} else {
				var twline=document.createElement('div');
				var twlineimg=document.createElement('img');
				twlineimg.src="/plaza/twitter/img/line.gif";
				twline.className="twitter_line";
				twline.appendChild(twlineimg);
				twwrapper.appendChild(twline);
			}
        }
    }
}

