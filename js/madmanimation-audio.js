/************************************************
 *
 *  1. Open up your Google Chrome
 *  2. Point it to http://animatable.com/demos/madmanimation/
 *  3. Wait for it to load
 *  4. Paste this into address bar:
 *     javascript:var d=document,s=d.createElement('script');s.src='http://code.dominykas.com/js/madmanimation-audio.js';d.body.appendChild(s);
 *  5. Click watch
 *
  ***********************************************/


$(document).ready(function() {

	$("h1 a").unbind();
	$("h1 a").click(function() {

		// original function, with delays shortened a little
		var startAnimation = function()
		{
			var li = $('#animation li');

			//var delays = [4500,2000,2400,2200,5000,3000,3000,3800,2800,3000,2500,1800,1800,1800,3000,14000];
			var delays = [4500,1000,1400,1200,3000,2000,2000,2800,1800,2000,1500,1300,1300,1300,2500,14000];

			function sumPrev(array, index){
				var sum = 0;
				for(var i = 0; i < index; i++){
					sum += array[i];
				}
				return sum;
			}

			li.each(function(i){
				setTimeout(function($ele){
					$ele.addClass("go").siblings().removeClass("go");
				}, sumPrev(delays, i), $(this));
			});
		};

		var getAudio = function()
		{
			var audio = document.getElementById('itCrowdTheme');
			if (audio) $(audio).remove();

			audio = document.createElement('audio');
			audio.id="itCrowdTheme";
			// http://www.last.fm/music/The+IT+Crowd
			audio.src='http://freedownloads.last.fm/download/46728796/The%2BIT%2BCrowd%2BTheme.mp3';
			$(document.body).append(audio);
			return audio;
		};

		if (Modernizr.audio) {
			$(document.body).append('<div id="loading" style="position: absolute;top:0;left:0;">Loading audio...</div>');

			var audio = getAudio();
	
			audio.addEventListener("canplaythrough", function(){
				$('#loading').remove();
				audio.play();
				startAnimation();
			}, false);

		} else {
	
			startAnimation();

		};
		return false;
	});
});
