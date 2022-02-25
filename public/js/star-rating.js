let starsColorSet = "darkorange";
let starsColorUnset = "grey";

let starsTouchSupport = 'ontouchstart' in window || navigator.msMaxTouchPoints;

//window.addEventListener("DOMContentLoaded", () => {
	let star_inputs = document.querySelectorAll(".stars");
	for (var i = star_inputs.length - 1; i >= 0; i--) {
		createStars(star_inputs[i]);
		setStars(star_inputs[i], star_inputs[i].getAttribute("data-value"));
	}
//});

function createStars(stars) {
	let star_count = 5;
	let repeat_char = "★";
	if (stars.querySelector("span") != undefined) {
		star_count = 4;
		repeat_char = stars.querySelector("span").innerHTML;
	}

	for (let i = 0; i < star_count; ++i)
		stars.innerHTML += "<span>" + repeat_char + "</span>";
}

function updateStars(stars, breakpoint) {

	let children = stars.querySelectorAll("span");

	if (event != undefined ) {
		if (event.type == "mousemove" && !Array.from(children).includes(event.target)) {
			return;
		}
	}

	for (var i = children.length - 1; i >= 0 ; --i) {

		if (event != undefined && event.target == children[i]) {
			breakpoint = i + 1;
		}

		if (i < breakpoint) {
			children[i].style.color = starsColorSet;
		} else {
			children[i].style.color = starsColorUnset;
		}
	}

	if (starsTouchSupport) {
		let field = stars.querySelector(".stars-field");

		//field.value = breakpoint;
	}
}

function rememberStars(stars) {
	let field = stars.querySelector(".stars-field");

  	setTimeout(function() {
		updateStars(stars, field.value);
  	}, 50);
}

function setStars(stars, value) {

	let children = stars.querySelectorAll("span");

	let field = stars.querySelector(".stars-field");
	let breakpoint = value;
	
	if (value == -1) {
		for (var i = children.length - 1; i >= 0; --i) {
			if (children[i].style.color == starsColorSet) {
				value = i + 1;
				break;
			}
		}
	}

	if (value > 0 && field != undefined)
		field.value = value;

	updateStars(stars, value);
}