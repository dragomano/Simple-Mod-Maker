<?php

function template_callback_smm_readme_editor()
{
	global $context;

	echo '
		<table class="table_grid">
			<tbody>';

	foreach ($context['languages'] as $lang) {
		echo '
				<tr class="windowbg">
					<td>
						<label for="smm_readme_', $lang['filename'], '">
							<strong>' . $lang['name'] . '</strong>
						</label>
					</td>
					<td>', template_control_richedit($context['smm_readme_editor'][$lang['filename']], null, 'bbcBox_message'), '</td>
				</tr>';
	}

	echo '
			</tbody>
		</table>';
}

function template_modification_post()
{
	global $context, $txt;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $context['page_area_title'], '</h3>
	</div>';

	if (! empty($context['post_errors'])) {
		echo '
	<div class="errorbox">
		<ul>';

		foreach ($context['post_errors'] as $error) {
			echo '
			<li>', $error, '</li>';
		}

		echo '
		</ul>
	</div>';
	}

	$fields = $context['posting_fields'];

	echo '
	<form action="', $context['canonical_url'], '" method="post" accept-charset="', $context['character_set'], '" onsubmit="submitonce(this);" x-data>
		<div class="roundframe noup">
			<div class="smm_tabs">
				<input id="tab_basic" type="radio" name="tabs" checked>
				<label for="tab_basic" class="bg odd"><i class="main_icons check"></i><span> ', $txt['smm_tab_basic'], '</span></label>
				<input id="tab_settings" type="radio" name="tabs">
				<label for="tab_settings" class="bg odd"><i class="main_icons corefeatures"></i><span> ', $txt['smm_tab_settings'], '</span></label>
				<input id="tab_database" type="radio" name="tabs">
				<label for="tab_database" class="bg odd"><i class="main_icons server"></i><span> ', $txt['smm_tab_database'], '</span></label>
				<input id="tab_package" type="radio" name="tabs">
				<label for="tab_package" class="bg odd"><i class="main_icons packages"></i><span> ', $txt['smm_tab_package'], '</span></label>
				<section id="content_tab_basic" class="bg even">';

	template_post_tab($fields);

	echo '
				</section>
				<section id="content_tab_settings" class="bg even">
					<div class="infobox">', $txt['smm_tab_settings_desc'], '</div>';

	template_post_tab($fields, 'settings');

	echo '
					<hr>
					<table class="add_option centertext" x-data="smm.handleOptions()">
						<tbody>
							<template x-for="(option, index) in options" :key="index">
								<tr class="windowbg">
									<td colspan="4">
										<table class="plugin_options table_grid">
											<thead>
												<tr class="title_bar">
													<th>#</th>
													<th colspan="3">', $txt['smm_option_name'], '</th>
												</tr>
											</thead>
											<tbody>
												<tr class="windowbg">
													<td x-text="index + 1"></td>
													<td colspan="2">
														<input
															type="text"
															x-model="option.name"
															name="option_names[]"
															pattern="^[a-z][a-z_]+$"
															maxlength="30"
															placeholder="option_name"
															required
														>
													</td>
													<td>
														<button type="button" class="button" @click="removeOption(index)" style="width: 100%">
															<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
														</button>
													</td>
												</tr>
												<tr class="windowbg" x-data="{ type_id: $id(\'option-type\'), default_id: $id(\'option-default\') }">
													<td>
														<label :for="type_id"><strong>', $txt['smm_option_type'], '</strong></label>
													</td>
													<td>
														<select x-model="option.type" name="option_types[]" :id="type_id">';

	foreach ($txt['smm_option_types'] as $type => $name) {
		echo '
															<option value="', $type, '">', $name, '</option>';
	}

	echo '
														</select>
													</td>
													<td>
														<template x-if="! [\'bbc\', \'boards\', \'permissions\', \'callback\'].includes(option.type)">
															<label :for="default_id"><strong>', $txt['smm_option_default_value'], '</strong></label>
														</template>
													</td>
													<td>
														<template x-if="option.type === \'check\'">
															<input type="checkbox" x-model="option.default" :name="`option_defaults[${index}]`" :id="default_id">
														</template>
														<template x-if="[\'text\', \'password\', \'color\'].includes(option.type)">
															<input x-model="option.default" :name="`option_defaults[${index}]`" :id="default_id">
														</template>
														<template x-if="option.type === \'large_text\'">
															<textarea x-model="option.default" :name="`option_defaults[${index}]`" :id="default_id"></textarea>
														</template>
														<template x-if="option.type === \'select\'">
															<input x-model="option.default" :name="`option_defaults[${index}]`" :id="default_id">
														</template>
														<template x-if="option.type === \'select-multiple\'">
															<input x-model="option.default" :name="`option_defaults[${index}]`" :id="default_id">
														</template>
														<template x-if="option.type === \'int\'">
															<input type="number" min="0" step="1" x-model="option.default" :name="`option_defaults[${index}]`" :id="default_id">
														</template>
														<template x-if="option.type === \'float\'">
															<input type="number" min="0" step="0.1" x-model="option.default" :name="`option_defaults[${index}]`" :id="default_id">
														</template>
														<template x-if="[\'url\', \'date\', \'datetime-local\', \'email\', \'time\'].includes(option.type)">
															<input :type="option.type" x-model="option.default" :name="`option_defaults[${index}]`" :id="default_id">
														</template>
													</td>
												</tr>
												<template x-if="! [\'select-multiple\', \'select\'].includes(option.type)">
													<tr class="windowbg" style="display: none">
														<td colspan="4">
															<input x-model="option.variants" name="option_variants[]">
														</td>
													</tr>
												</template>
												<template x-if="[\'select-multiple\', \'select\'].includes(option.type)">
													<tr class="windowbg">
														<td colspan="1"><strong>', $txt['smm_option_variants'], '</strong></td>
														<td colspan="3">
															<input x-model="option.variants" name="option_variants[]" placeholder="', $txt['smm_option_variants_placeholder'], '">
														</td>
													</tr>
												</template>
												<tr class="windowbg">
													<td colspan="1"><strong>', $txt['smm_option_translations'], '</strong></td>
													<td colspan="3">
														<table class="table_grid">
															<tbody>';

	foreach ($context['languages'] as $lang) {
		echo '
																<tr class="windowbg">
																	<td><strong>', $lang['name'], '</strong></td>
																	<td>
																		<input type="text" x-model="option.translations[\'', $lang['filename'], '\']" name="option_translations[', $lang['filename'], '][]"', in_array($lang['filename'], array($context['user']['language'], 'english')) ? ' required' : '', ' placeholder="', $lang['filename'], '">
																	</td>
																</tr>';
	}

	echo '
															</tbody>
														</table>
													</td>
												</tr>
											</tbody>
										</table>
									</td>
								</tr>
							</template>
						</tbody>
						<tfoot>
							<tr>
								<td colspan="4">
									<button type="button" class="button" @click="addNewOption()">
										<span class="main_icons plus"></span> ', $txt['smm_option_new'], '
									</button>
								</td>
							</tr>
						</tfoot>
					</table>
				</section>
				<section id="content_tab_database" class="bg even">
					<div class="infobox">', $txt['smm_tab_database_desc'], '</div>
					<table class="add_option centertext" x-data="smm.handleTables()">
						<tbody>
							<template x-for="(table, index) in tables" :key="index">
								<tr class="windowbg">
									<td colspan="4">
										<table class="plugin_options table_grid">
											<thead>
												<tr class="title_bar">
													<th>#</th>
													<th colspan="3">', $txt['smm_table_name'], '</th>
												</tr>
											</thead>
											<tbody>
												<tr class="windowbg">
													<td x-text="index + 1"></td>
													<td colspan="2">
														<input
															type="text"
															x-model="table.name"
															name="table_names[]"
															pattern="^[a-z][a-z_]+$"
															maxlength="64"
															placeholder="table_name"
															required
														>
													</td>
													<td>
														<button type="button" class="button" @click="removeTable(index)">
															<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
														</button>
													</td>
												</tr>
												<template x-for="(column, column_index) in table.columns" :key="column_index">
													<tr class="windowbg">
														<td colspan="7">
															<table class="plugin_options table_grid">
																<thead>
																	<tr class="descbox bg even">
																		<th>#</th>
																		<th colspan="6">', $txt['smm_column_name'], '</th>
																	</tr>
																</thead>
																<tbody>
																	<tr class="windowbg">
																		<td x-text="column_index + 1"></td>
																		<td colspan="5">
																			<input
																				type="text"
																				x-model="column.name"
																				:name="`column_names[${index}][]`"
																				pattern="^[a-z][a-z_]+$"
																				maxlength="64"
																				placeholder="column_name"
																				required
																			>
																		</td>
																		<td>
																			<button type="button" class="button" @click="removeColumn(index, column_index)" style="width: 100%">
																				<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
																			</button>
																		</td>
																	</tr>
																	<tr class="windowbg" x-data="{ type_id: $id(\'column-type\'), size_id: $id(\'column-size\'), default_id: $id(\'column-default\') }">
																		<td>
																			<label :for="type_id"><strong>', $txt['smm_column_type'], '</strong></label>
																		</td>
																		<td :colspan="column.auto ? 3 : 1">
																			<select x-model="column.type" :name="`column_types[${index}][]`" :id="type_id">';

	foreach ($context['smm_column_types'] as $type) {
		echo '
																				<option value="', $type, '">', $type, '</option>';
	}

	echo '
																			</select>
																		</td>
																		<template x-if="[\'text\', \'mediumtext\'].includes(column.type)">
																			<td colspan="2">
																				<label>
																					<input type="checkbox" x-model="column.null" :name="`column_null[${index}][]`">
																						', $txt['smm_column_null'], '
																					</input>
																				</label>
																			</td>
																		</template>
																		<template x-if="! [\'text\', \'mediumtext\'].includes(column.type)">
																			<td>
																				<label :for="size_id"><strong>', $txt['smm_column_size'], '</strong></label>

																			</td>
																		</template>
																		<template x-if="! [\'text\', \'mediumtext\'].includes(column.type)">
																			<td>
																				<input
																					type="number"
																					min="1"
																					step="1"
																					x-model="column.size"
																					:name="`column_sizes[${index}][]`"
																					:id="size_id"
																					:value="[\'tinyint\', \'int\', \'mediumint\'].includes(column.type) ? 10 : 255"
																				>
																			</td>
																		</template>
																		<td>
																			<template x-if="[\'tinyint\', \'int\', \'mediumint\'].includes(column.type)">
																				<label>
																					<input type="checkbox" x-model="column.auto" :name="`column_auto[${index}][]`">
																						', $txt['smm_column_auto'], '
																					</input>
																				</label>
																			</template>
																		</td>
																		<template x-if="! column.auto">
																			<td>
																				<label :for="default_id"><strong>', $txt['smm_option_default_value'], '</strong></label>
																			</td>
																		</template>
																		<template x-if="! column.auto">
																			<td>
																				<template x-if="column.type === \'varchar\'">
																					<input x-model="column.default" :name="`column_defaults[${index}][]`" :id="default_id">
																				</template>
																				<template x-if="[\'text\', \'mediumtext\'].includes(column.type)">
																					<textarea x-model="column.default" :name="`column_defaults[${index}][]`" :id="default_id"></textarea>
																				</template>
																				<template x-if="[\'tinyint\', \'int\', \'mediumint\'].includes(column.type)">
																					<input
																						type="number"
																						min="0"
																						step="1"
																						x-model="column.default"
																						:name="`column_defaults[${index}][]`"
																						:id="default_id"
																					>
																				</template>
																			</td>
																		</template>
																	</tr>
																</tbody>
															</table>
														</td>
													</tr>
												</template>
											</tbody>
											<tfoot>
												<tr>
													<td colspan="4">
														<button type="button" class="button" @click="addNewColumn(index)">
															<span class="main_icons plus"></span> ', $txt['smm_column_new'], '
														</button>
													</td>
												</tr>
											</tfoot>
										</table>
									</td>
								</tr>
							</template>
						</tbody>
						<tfoot>
							<tr>
								<td colspan="4">
									<button type="button" class="button" @click="addNewTable()">
										<span class="main_icons plus"></span> ', $txt['smm_table_new'], '
									</button>
								</td>
							</tr>
						</tfoot>
					</table>
				</section>
				<section id="content_tab_package" class="bg even">';

	template_post_tab($fields, 'package');

	echo '
				</section>
			</div>
			<br class="clear">
			<div class="centertext">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
				<button type="submit" class="button" name="save" @click="smm.post($root)"><i class="main_icons valid"></i> ', $txt['smm_build'], '</button>
			</div>
		</div>
	</form>

	<script>
		new TomSelect("#hooks", {
			plugins: {
				remove_button:{
					title: "', $txt['remove'], '",
				}
			},
			searchField: "value",
			allowEmptyOption: true,
			closeAfterSelect: false,
			placeholder: "integrate_load_theme",
			options: [', implode(',', $context['smm_hook_list']['data']), '],
			items: [', implode(',', $context['smm_hook_list']['items']), '],
			shouldLoad: function (search) {
				return search.length >= 3;
			},
			load: function (search, callback) {
				fetch("', $context['canonical_url'], ';hooks", {
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
						data.push({text: json[i].innerHTML, value: json[i].value})
					}

					callback(data)
				})
				.catch(function (error) {
					callback(false)
				})
			},
			render: {
				option: function (item, escape) {
					return `<div>${item.value}</div>`;
				},
				item: function (item, escape) {
					return `<div>${item.value}</div>`;
				},
				option_create: function (data, escape) {
					return `<div class="create">', $txt['ban_add'], ' <strong>` + escape(data.input) + `</strong>&hellip;</div>`;
				},
				no_results: function (data, escape) {
					return `<div class="no-results">', $txt['no_matches'], '</div>`;
				},
				not_loading: function (data, escape) {
					return `<div class="optgroup-header">', sprintf($txt['smm_min_search_length'], 3), '</div>`;
				}
			},
			create: function (input) {
				return {value: input.toLowerCase(), text: input.toLowerCase()}
			}
		});

		const smm = new class {
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

			handleOptions() {
				return {
					options: ', json_encode($context['smm_skeleton']['options']), ',
					addNewOption() {
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
					tables: ', json_encode($context['smm_skeleton']['tables']), ',
					addNewTable() {
						this.tables.push({
							name: "",
							columns: [],
						});
					},
					removeTable(index) {
						this.tables.splice(index, 1);
					},
					addNewColumn(table_index) {
						this.tables[table_index].columns.push({
							name: "",
							type: "int",
							null: false,
							size: 10,
							auto: false,
							default: ""
						});
					},
					removeColumn(table_index, column_index) {
						this.tables[table_index].columns.splice(column_index, 1);
					}
				}
			}
		}
	</script>';
}

function template_post_tab(array $fields, string $tab = 'basic')
{
	global $context;

	$fields['subject'] = ['no'];

	foreach ($fields as $pfid => $pf) {
		if (empty($pf['input']['tab']))
			$pf['input']['tab'] = 'basic';

		if ($pf['input']['tab'] !== $tab)
			$fields[$pfid] = ['no'];
	}

	$context['posting_fields'] = $fields;

	LoadTemplate('Post');

	template_post_header();
}
