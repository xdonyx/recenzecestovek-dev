function sortTable(column) {

	let table = document.querySelector("table").querySelector("tbody");
	let rows = table.querySelectorAll("tr");

	let header = document.querySelector("table").rows[0];

	if (!isNaN(column)) {
		column = header.children[column];
	}

	let col = Array.prototype.indexOf.call(header.children, column);

	let sort = column.getAttribute("data-sort");
	sort = (sort == 0 ? 1 : 0);
	column.setAttribute("data-sort", sort);


	for (let i = 0; i < header.children.length; ++i) {

		let icon = header.children[i].querySelector(".fa");

		if (icon == undefined)
			continue;

		icon.style.color = "transparent";

		if (header.children[i] == column) {
			icon.classList.remove("fa-arrow-up");
			icon.classList.remove("fa-arrow-down");
			if (sort == 0)
				icon.classList.add("fa-arrow-up");
			else
				icon.classList.add("fa-arrow-down");

			icon.style.color = "orangered";
		}
	}

	rows = Array.from(rows).sort((a, b) => {

		let x = a.children[col];
		let y = b.children[col];

		if (sort == 0) {

			if (!isNaN(x.innerHTML))
				return parseInt(x.innerHTML) > parseInt(y.innerHTML);

			let str_x = x.innerHTML.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
			let str_y = y.innerHTML.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

			return str_x > str_y;
		} else {

			if (!isNaN(x.innerHTML))
				return parseInt(x.innerHTML) <= parseInt(y.innerHTML);

			let str_x = x.innerHTML.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
			let str_y = y.innerHTML.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

			return str_x <= str_y;
		}
	});

	table.innerHTML = "";
	for (let i = 0; i < rows.length; ++i)
		table.appendChild(rows[i]);
}