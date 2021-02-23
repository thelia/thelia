export function getCookie(name) {
	const match = document.cookie.match(new RegExp(name + '=([^;]+)'));
	if (match) return match[1];
}

export function setCookie(name, value, days) {
	var expires = '';
	if (days) {
		var date = new Date();
		date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
		expires = '; expires=' + date.toGMTString();
	}
	document.cookie = name + '=' + value + expires + '; path=/';
}

const utils = { getCookie, setCookie };

export default utils;
