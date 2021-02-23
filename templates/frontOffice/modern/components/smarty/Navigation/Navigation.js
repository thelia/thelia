let listeners = [];

const getNavItems = () => {
	return [...document.querySelectorAll('.Navigation .Navigation-item')];
};

const closeAllSubMenu = (exclude) => {
	for (const item of getNavItems()) {
		if (exclude !== item) {
			item.classList.remove('Navigation-item--active');
		}
	}
};

const toggleSubMenu = function () {
	closeAllSubMenu(this);
	this.classList.toggle('Navigation-item--active');
};

const applyListeners = () => {
	for (const navItem of getNavItems()) {
		const hasSubItems = navItem.querySelector('.Navigation-item-submenu');
		if (hasSubItems) {
			const mobileArrow = navItem.querySelector('.Navigation-arrow');

			if (mobileArrow) {
				mobileArrow.addEventListener('click', toggleSubMenu.bind(navItem));
			}
		}
		listeners.push(navItem);
	}
};

export default function Listener() {
	document.addEventListener(
		'click',
		(e) => {
			if (e.target.matches('[data-toggle-navigation]')) {
				document.querySelector('body').classList.toggle('Navigation--active');
			}
		},
		false
	);

	window.addEventListener('resize', () => {
		closeAllSubMenu();
	});

	// add listener to show/hide navigation submenu on hover (pure css solution is breaking when there is an y overflow...)
	applyListeners();
}
