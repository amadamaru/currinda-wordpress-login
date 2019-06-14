var loop = setInterval(test_2, 3000);

jQuery(document).ready(function(){

	jQuery('#logout').on('click',function(e){
		var new_win = window.open(jQuery(this).data('logouturl'));
		new_win.close();
		window.location.href = jQuery(this).attr('href');
		e.preventDefault();
		return false;
	});

});

function test_2(){ 
	console.log("checking...");
	try{
		
	var jsondata = jQuery.parseJSON(jQuery("#currinda_iframe").contents().find("body").html());
	if(jsondata.success == true){
		clearInterval(loop);
		if(jsondata.login_success_url){
			window.location.href = jsondata.login_success_url;
		}else{
			location.reload();
		}
	}

	}catch(err){
	}
}


function currinda_login() {
	var child = window.open(WPURLS.siteurl+'/?option=currinda_user_login'); 
}

function currinda_child() {
	window.opener.location.reload();
	window.close();
}


