window.addEventListener("DOMContentLoaded", () => {
//	showCookieBanner();
	setMenuActiveItem();
	initializeButtonIcons();
});

// back to top
window.addEventListener("scroll", () => {
	if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
		document.getElementById("topPage").style.display = "block";
	} else {
		document.getElementById("topPage").style.display = "none";
	}
});

function showCookieBanner() {
	if(getCookie(window.cookieName) != window.cookieValue)
		showCookies(document.getElementById("cookie-law"));
}

function setMenuActiveItem() {
	let els = document.querySelectorAll(".navigable");

	for (var i = 0; i < els.length; ++i) {
		let dropdownItems = els[i].querySelectorAll(".dropdown-item");
		let isDropdown = dropdownItems.length != 0 ? true : false;
		let isDropdownItem = els[i].classList.contains("dropdown-item");

		if (!isDropdown) {
			if (!isDropdownItem) {
				if (comparePath(els[i].children[0].href, i)) {
					els[i].classList.add("active"); // main set
				}
			} else if (isDropdownItem) {
				if (comparePath(els[i].href)) {
					els[i].classList.add("active");
					els[i].parentNode.parentNode.parentNode.classList.add("active");
				}
			}
		}
	}
}

function initializeButtonIcons() {
	let buttons = document.querySelectorAll(".btn");

	for (let i = 0; i < buttons.length; ++i) {
		let icon = buttons[i].querySelector(".fa");
		if (icon != null) {

			if (icon.classList.contains("fa-edit"))
				buttons[i].title = "Upravit";
			else if (icon.classList.contains("fa-trash"))
				buttons[i].title = "Odstranit";
			else if (icon.classList.contains("fa-eraser"))
				buttons[i].title = "Navždy odstranit";
			else if (icon.classList.contains("fa-reply"))
				buttons[i].title = "Odpovědet";
			else if (icon.classList.contains("fa-paper-plane"))
				buttons[i].title = "Odeslat";
			else if (icon.classList.contains("fa-floppy-o"))
				buttons[i].title = "Uložit";
			else if (icon.classList.contains("fa-check"))
				buttons[i].title = "Zveřejnit";
			else if (icon.classList.contains("fa-comments"))
				buttons[i].title = "Diskuze";
			else if (icon.classList.contains("fa-comment"))
				buttons[i].title = "Nový příspěvek";
		}
	}
}