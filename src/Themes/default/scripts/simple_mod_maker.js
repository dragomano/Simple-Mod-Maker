const smm = new class {
	constructor() {
		window.addEventListener("DOMContentLoaded", () => {
			this.initHookSelect();
			this.restoreSettingPlacement();
		});
	}

	initHookSelect() {
		if (typeof TomSelect === "undefined" || typeof smmGenerator === "undefined") {
			return;
		}

		new TomSelect("#hooks", {
			plugins: {
				remove_button: {
					title: smmGenerator.texts.remove
				}
			},
			searchField: "value",
			allowEmptyOption: true,
			closeAfterSelect: false,
			placeholder: "integrate_load_theme",
			options: smmGenerator.hookList.data,
			items: smmGenerator.hookList.items,
			shouldLoad: (search) => search.length >= smmGenerator.minSearchLength,
			load: (search, callback) => {
				fetch(smmGenerator.hookUrl, {
					method: "POST",
					headers: {
						"Content-Type": "application/json; charset=utf-8"
					},
					body: JSON.stringify({
						search
					})
				})
				.then(response => response.json())
				.then(function (json) {
					let data = [];
					for (let i = 0; i < json.length; i++) {
						data.push({text: json[i].value, value: json[i].value})
					}

					callback(data)
				})
				.catch(function (error) {
					callback(false)
				})
			},
			render: {
				option: function (item, escape) {
					return `<div>${escape(item.value)}</div>`;
				},
				item: function (item, escape) {
					return `<div>${escape(item.value)}</div>`;
				},
				option_create: function (data, escape) {
					return `<div class="create">${smmGenerator.texts.addHook} <strong>${escape(data.input)}</strong>&hellip;</div>`;
				},
				no_results: function (data, escape) {
					return `<div class="no-results">${smmGenerator.texts.noMatches}</div>`;
				},
				not_loading: function (data, escape) {
					return `<div class="optgroup-header">${smmGenerator.texts.minSearchLength}</div>`;
				}
			},
			create: function (input) {
				return {value: input.toLowerCase(), text: input.toLowerCase()}
			}
		});
	}

	restoreSettingPlacement() {
		const placement = document.getElementById("settings_area");

		if (placement) {
			this.changeSettingPlacement(placement.value);
		}
	}

	post(target) {
		const formElements = target.elements;

		for (let i = 0; i < formElements.length; i++) {
			if ((formElements[i].required && formElements[i].value === "") || ! formElements[i].checkValidity()) {
				let elem = formElements[i].closest("section").id;

				document.getElementsByName("tabs").checked = false;
				document.getElementById(elem.replace("content_", "")).checked = true;

				let focusElement = document.getElementById(formElements[i].id);

				if (focusElement) focusElement.focus();

				return false
			}
		}
	}

	changeSettingPlacement(target) {
		const settingTabFields = document.querySelectorAll(".pf_title")
		const tabSettingTables = document.querySelector(".tab_setting_tables")

		if (target > 0) {
			settingTabFields.forEach(setting => setting.style.display = "block")
			tabSettingTables.style.display = "block"
		} else {
			settingTabFields.forEach(setting => setting.style.display = "none")
			tabSettingTables.style.display = "none"
		}
	}

	handleOptions() {
		return {
			options: smmGenerator.skeleton.options,
			addOption() {
				this.options.push({
					name: "",
					type: "check",
					default: "",
					variants: "",
					translations: {},
				});
			},
			removeOption(index) {
				this.options.splice(index, 1);
			}
		}
	}

	handleTables() {
		return {
			tables: smmGenerator.skeleton.tables,
			init() {
				this.tables.forEach(table => table.columns.forEach(column => this.watchColumn(column)));
			},
			addTable() {
				this.tables.push({
					name: "",
					columns: [],
				});
			},
			removeTable(index) {
				this.tables.splice(index, 1);
			},
			addColumn(table_index) {
				const column = {
					name: "",
					type: "int",
					null: false,
					size: 10,
					auto: false,
					default: ""
				};

				this.tables[table_index].columns.push(column);
				this.watchColumn(column);
			},
			removeColumn(table_index, column_index) {
				this.tables[table_index].columns.splice(column_index, 1);
			},
			watchColumn(column) {
				this.$watch(() => column.type, type => this.normalizeColumn(column));
				this.$watch(() => column.auto, auto => {
					if (auto) column.default = "";
				});
				this.normalizeColumn(column);
			},
			normalizeColumn(column) {
				const intTypes = ["tinyint", "int", "mediumint"];
				const textTypes = ["text", "mediumtext"];

				column.auto = intTypes.includes(column.type) && column.auto;
				column.null = textTypes.includes(column.type) && column.null;

				if (textTypes.includes(column.type)) {
					column.size = 0;
					column.default = "";
				} else {
					if (! column.size) {
						column.size = intTypes.includes(column.type) ? 10 : 255;
					}

					if (column.auto) {
						column.default = "";
					}
				}
			}
		}
	}

	handleTasks() {
		return {
			scheduledTasks: smmGenerator.skeleton.scheduledTasks,
			backgroundTasks: smmGenerator.skeleton.backgroundTasks,
			legacyTasks: smmGenerator.skeleton.legacyTasks,
			addTask(type = "scheduled") {
				switch (type) {
					case "scheduled":
						this.scheduledTasks.push({
							slug: "",
							names: {},
							descriptions: {},
							regularity: 0,
						});
						break;
					case "background":
						this.backgroundTasks.push({
							classname: "",
							regularity: 0,
						});
						break;
					default:
						this.legacyTasks.push({
							method: "",
							regularity: 0,
						});
				}
			},
			removeTask(index, type = "scheduled") {
				switch (type) {
					case "scheduled":
						this.scheduledTasks.splice(index, 1);
						break;
					case "background":
						this.backgroundTasks.splice(index, 1);
						break;
					default:
						this.legacyTasks.splice(index, 1);
				}
			}
		}
	}
}
