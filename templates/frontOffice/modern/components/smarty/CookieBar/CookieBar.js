import { setCookie } from '@utils/cookies';

export default function CookieBar() {
	const $cookieBar = document.querySelector('.CookieBar');
	const $btn = document.querySelector('.CookieBar-btn');

	if (!$cookieBar || !$btn) return;

	$btn.addEventListener('click', (e) => {
		e.preventDefault();
		$cookieBar.classList.add('hidden');
		setCookie('cookie_consent', 3, 30);
	});
}
