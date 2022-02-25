// Creare's 'Implied Consent' EU Cookie Law Banner v:2.4
// Conceived by Robert Kent, James Bavington & Tom Foyster
 
var dropCookie = true;					  // false disables the Cookie, allowing you to style the banner
var cookieDuration = 14;					// Number of days before the cookie expires, and the banner reappears
var cookieName = 'complianceCookie';		// Name of our cookie
var cookieValue = 'on';					 // Value of cookie
 
function showCookies(div){
	div.innerHTML = '<p class="cookie-content">Pro zlepšení a zpříjemnění práce s webem používáme soubory cookie. Pokračovaním v prohlížení vyjadřujete souhlas se <a href="./soubory-cookie" rel="nofollow" title="Soubory cookie">Zásadama týkajících se souborů cookie</a> a <a href="./ochrana-udaju" rel="nofollow" title="Privacy Policy">Zásadama ochrany osobních údajů</a><a class="close-cookie-banner btn btn-primary" style="margin-left:20px;vertical-align:middle;padding-bottom:5px !important" href="javascript:void(0);" onclick="removeMe();"><span>Rozumím</span></a></p>';	
}

function removeMe(){
	var element = document.getElementById('cookie-law');
	element.parentNode.removeChild(element);
	setCookie(window.cookieName,window.cookieValue);
}

function getCookie(name) {
	var b = document.cookie.match("(^|;)\\s*" + name + "\\s*=\\s*([^;]+)");
	return b ? b.pop() : "";
}

function setCookie(name, value) {
  document.cookie = name +'='+ value +'; path=/;';
}

function deleteCookie(name) {
  document.cookie = name +'=; path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}