$(function() {$("#foo2").carouFredSel({auto : {items : 1,duration : 3000,easing : "linear",pauseDuration : 0,pauseOnHover : "immediate-resume"}});$("#foo2_prev").hover(function() {var prev = $("#foo2");prev.trigger("configuration", ["direction", "right"]).trigger("configuration", {auto: {duration : 500}});}, function() {$("#foo2").trigger("configuration", {auto: {duration : 3000}});}).click(function() {return false;});$("#foo2_next").hover(function() {var next = $("#foo2");next.trigger("configuration", ["direction", "left"]).trigger("configuration", {auto: {duration : 500}});}, function() {$("#foo2").trigger("configuration", {auto: {duration : 3000}});}).click(function() {return false;});});