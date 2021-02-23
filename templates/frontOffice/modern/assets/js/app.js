import '../css/tailwind-base.css';
import '../css/app.css';
import '../css/tailwind-utilities.css';

import MiniCart from '@components/React/MiniCart';
import MiniLogin from '@components/React/MiniLogin';
import SvgAjax from '@utils/SvgAjax';
import axios from 'axios';

function main() {
	axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
	SvgAjax();
	MiniLogin();
	MiniCart();

	import(
		/* webpackChunkName: "Navigation" */ /* webpackPrefetch: true */ '@components/smarty/Navigation/Navigation'
	).then(({ default: Navigation }) => Navigation());

	import(
		/* webpackChunkName: "SearchDropdown" */ /* webpackPrefetch: true */ '@components/smarty/SearchDropdown/SearchDropdown'
	).then(({ default: SearchDropdown }) => SearchDropdown());

	import(
		/* webpackChunkName: "CookieBar" */ '@components/smarty/CookieBar/CookieBar'
	).then(({ default: CookieBar }) => CookieBar());

	import(
		/* webpackChunkName: "Modal" */ '@components/smarty/Modal/Modal'
	).then(({ default: Modal }) => Modal());
}

document.addEventListener('DOMContentLoaded', () => {
	main();
});
