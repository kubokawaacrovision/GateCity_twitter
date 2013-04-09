<?php
error_reporting(0); 
header('Content-type: text/javascript');
?>
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
function twitterCallback2(twitters, mask, redraw) {
  var filter=new filObj(mask);
  for(i=0; i<twitters.length-1; i++){
  	for(j=i+1; j<twitters.length; j++){
  		if(u_time(twitters[i].created_at)<u_time(twitters[j].created_at)){
            tmptw=twitters[i];
            twitters[i]=twitters[j];
            twitters[j]=tmptw;
  		}
  	}
  }
  for(i=0;i<twitters.length;i++){
    if(filter.validate(twitters[i].text) && filter.validate(twitters[i].id_str)) break;
  }
    var username = twitters[i].name;
    var status = twitters[i].text;
    if(!twitters[i].id_str || !twitters[i].twname) return;
    timestamp=absolute_time(twitters[i].created_at);
    var tail="・・・（"+username+" − "+timestamp+"）";
    var elem=document.getElementById('<?= $_GET['id'] ?>');
    var nobr=document.createElement('nobr');
    var span=document.createElement('span');
    var atag=document.createElement('a');
    atag.href="http://twitter.com/#!/"+twitters[i].twname;
    atag.target="_blank";
    span.style.cursor="pointer";
    atag.appendChild(span);
    nobr.appendChild(atag);
    elem.appendChild(nobr);
    span.innerHTML=status+tail.substring(3);
    if(span.offsetHeight<=elem.offsetHeight && span.offsetWidth<=elem.offsetWidth) return;
    for(var i=1; i<status.length; i++){
        span.innerHTML=status.substr(0, i)+tail;
        if(span.offsetHeight>elem.offsetHeight || span.offsetWidth>elem.offsetWidth){
            span.innerHTML=status.substr(0, i-1)+tail;
            break;
        }
    }
}
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
  return mm+"/"+dd+" "+hh+":"+mn;
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
<?php
require './favtw.php';
?>
