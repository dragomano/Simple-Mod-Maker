<?php

function template_callback_smm_readme_editor()
{
	global $context;

	foreach ($context['smm_languages'] as $lang) {
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', $lang['name'], '</h3>
		</div>
		<div class="windowbg">
			', template_control_richedit($context['smm_readme_editor'][$lang['filename']], null, 'bbcBox_message'), '
		</div>';
	}
}

function template_modification_post()
{
	global $context, $language, $txt;

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
	<form
		action="', $context['canonical_url'], '"
		method="post"
		accept-charset="', $context['character_set'], '"
		onsubmit="submitonce(this);"
		x-data="{ tab: \'', $language, '\', className: \'', $context['smm_skeleton']['name'], '\' }"
	>
		<div class="windowbg">
			<div class="smm_tabs">
				<input id="tab_basic" type="radio" name="tabs" checked>
				<label for="tab_basic" class="bg odd">
					<i class="main_icons check"></i><span> ', $txt['smm_tab_basic'], '</span>
				</label>
				<input id="tab_settings" type="radio" name="tabs">
				<label for="tab_settings" class="bg odd">
					<i class="main_icons corefeatures"></i><span> ', $txt['smm_tab_settings'], '</span>
				</label>
				<input id="tab_database" type="radio" name="tabs">
				<label for="tab_database" class="bg odd">
					<i class="main_icons server"></i><span> ', $txt['smm_tab_database'], '</span>
				</label>
				<input id="tab_tasks" type="radio" name="tabs">
				<label for="tab_tasks" class="bg odd">
					<i class="main_icons scheduled"></i><span> ', $txt['smm_tab_tasks'], '</span>
				</label>
				<input id="tab_package" type="radio" name="tabs">
				<label for="tab_package" class="bg odd">
					<i class="main_icons packages"></i><span> ', $txt['smm_tab_package'], '</span>
				</label>
				<section id="content_tab_basic" class="bg even">
					', template_post_tab($fields), '
				</section>
				<section id="content_tab_settings" class="bg even">
					<div class="infobox">', $txt['smm_tab_settings_desc'], '</div>
					', template_post_tab($fields, 'settings'), '
					<div class="tab_setting_tables" style="display: none">
						<hr>
						<table class="add_option centertext" x-data="smm.handleOptions()">
							<tbody>
								<template x-for="(option, index) in options" :key="index">
									<tr class="windowbg">
										<td colspan="4">
											<table class="plugin_options table_grid">
												<thead>
													<tr class="title_bar">
														<th style="width: 20%"></th>
														<th colspan="3">
															<span>', $txt['smm_option'], '</span>
															<button type="button" class="button" @click="removeOption(index)">
																<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
															</button>
														</th>
													</tr>
												</thead>
												<tbody>
													<tr class="windowbg" x-data="{ option_name: $id(\'option-name\') }">
														<td>
															<label :for="option_name">
																<strong>', $txt['smm_option_name'], '</strong>
															</label>
														</td>
														<td colspan="3">
															<input
																type="text"
																x-model="option.name"
																name="option_names[]"
																:id="option_name"
																pattern="^[a-z][a-z_]+$"
																maxlength="30"
																placeholder="option_name"
																required
															>
														</td>
													</tr>
													<tr class="windowbg" x-data="{ type_id: $id(\'option-type\'), default_id: $id(\'option-default\') }">
														<td>
															<label :for="type_id">
																<strong>', $txt['smm_option_type'], '</strong>
															</label>
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
															<td>
																<strong>', $txt['smm_option_variants'], '</strong>
															</td>
															<td colspan="3">
																<input x-model="option.variants" name="option_variants[]" placeholder="', $txt['smm_option_variants_placeholder'], '">
															</td>
														</tr>
													</template>
													<tr class="windowbg">
														<td>
															<strong>', $txt['smm_option_translations'], '</strong>
														</td>
														<td colspan="3">
															<table class="table_grid">
																<tbody>';

	foreach ($context['smm_languages'] as $lang) {
		echo '
																	<tr class="windowbg">
																		<td>
																			<input type="text" x-model="option.translations[\'', $lang['filename'], '\']" name="option_translations[', $lang['filename'], '][]"', in_array($lang['filename'], [$context['user']['language'], 'english']) ? ' required' : '', ' placeholder="', $lang['name'], '">
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
										<button type="button" class="button" @click="addOption()">
											<span class="main_icons plus"></span> ', $txt['smm_option_new'], '
										</button>
									</td>
								</tr>
							</tfoot>
						</table>
					</div>
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
													<th style="width: 20%"></th>
													<th colspan="3">
														<span>', $txt['smm_table'], '</span>
														<button type="button" class="button" @click="removeTable(index)">
															<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
														</button>
													</th>
												</tr>
											</thead>
											<tbody>
												<tr class="windowbg" x-data="{ table_name: $id(\'table-name\') }">
													<td>
														<label :for="table_name">
															<strong>', $txt['smm_table_name'], '</strong>
														</label>
													</td>
													<td colspan="2">
														<input
															type="text"
															x-model="table.name"
															name="table_names[]"
															:id="table_name"
															pattern="^[a-z][a-z_]+$"
															maxlength="64"
															placeholder="table_name"
															required
														>
													</td>
												</tr>
												<template x-for="(column, column_index) in table.columns" :key="column_index">
													<tr class="windowbg">
														<td colspan="6">
															<table class="plugin_options table_grid">
																<thead>
																	<tr class="title_bar">
																		<th style="width: 20%"></th>
																		<th colspan="5">
																			<span>', $txt['smm_column'], '</span>
																			<button type="button" class="button" @click="removeColumn(index, column_index)">
																				<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
																			</button>
																		</th>
																	</tr>
																</thead>
																<tbody>
																	<tr>
																		<td colspan="6">
																			<div class="noticebox">', $txt['smm_column_hint'], '</div>
																		</td>
																	</tr>
																	<tr class="windowbg" x-data="{ column_name: $id(\'column-name\') }">
																		<td>
																			<label :for="column_name">
																				<strong>', $txt['smm_column_name'], '</strong>
																			</label>
																		</td>
																		<td colspan="4">
																			<input
																				type="text"
																				x-model="column.name"
																				:name="`column_names[${index}][]`"
																				:id="column_name"
																				pattern="^[a-z][a-z_]+$"
																				maxlength="64"
																				placeholder="column_name"
																				required
																			>
																		</td>
																		<template x-if="[\'tinyint\', \'int\', \'mediumint\'].includes(column.type)">
																			<td>
																				<label>
																					<input type="checkbox" x-model="column.auto" :name="`column_auto[${index}][]`">
																						', $txt['smm_column_auto'], '
																					</input>
																				</label>
																			</td>
																		</template>
																		<template x-if="[\'text\', \'mediumtext\'].includes(column.type)">
																			<td>
																				<label>
																					<input type="checkbox" x-model="column.null" :name="`column_null[${index}][]`">
																						', $txt['smm_column_null'], '
																					</input>
																				</label>
																				<input type="hidden" x-model="column.default" :name="`column_defaults[${index}][]`">
																			</td>
																		</template>
																	</tr>
																	<tr class="windowbg" x-data="{ type_id: $id(\'column-type\'), size_id: $id(\'column-size\'), default_id: $id(\'column-default\') }">
																		<td>
																			<label :for="type_id"><strong>', $txt['smm_column_type'], '</strong></label>
																			<template x-if="column.auto">
																				<input type="hidden" x-model="column.default" :name="`column_defaults[${index}][]`">
																			</template>
																		</td>
																		<td :colspan="column.auto ? 2 : 1">
																			<select x-model="column.type" :name="`column_types[${index}][]`" :id="type_id">';

	foreach ($context['smm_column_types'] as $type) {
		echo '
																				<option value="', $type, '">', $type, '</option>';
	}

	echo '
																			</select>
																		</td>
																		<template x-if="! [\'text\', \'mediumtext\'].includes(column.type)">
																			<td>
																				<label :for="size_id"><strong>', $txt['smm_column_size'], '</strong></label>
																			</td>
																		</template>
																		<template x-if="! [\'text\', \'mediumtext\'].includes(column.type)">
																			<td :colspan="column.auto ? 2 : 1">
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
																		<template x-if="! [\'text\', \'mediumtext\'].includes(column.type) && ! column.auto">
																			<td>
																				<label :for="default_id">
																					<strong>', $txt['smm_option_default_value'], '</strong>
																				</label>
																			</td>
																		</template>
																		<template x-if="! [\'text\', \'mediumtext\'].includes(column.type) && ! column.auto">
																			<td colspan="2">
																				<template x-if="column.type === \'varchar\'">
																					<input x-model="column.default" :name="`column_defaults[${index}][]`" :id="default_id">
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
														<button type="button" class="button" @click="addColumn(index)">
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
									<button type="button" class="button" @click="addTable()">
										<span class="main_icons plus"></span> ', $txt['smm_table_new'], '
									</button>
								</td>
							</tr>
						</tfoot>
					</table>
				</section>
				<section id="content_tab_tasks" class="bg even">
					<div class="infobox">', $txt['smm_scheduled_tasks_info'], '</div>
					<table class="add_option centertext" x-data="smm.handleTasks()">
						<tbody>
							<template x-for="(task, index) in scheduledTasks" :key="index">
								<tr class="windowbg">
									<td colspan="4">
										<table class="plugin_options table_grid">
											<thead>
												<tr class="title_bar">
													<th style="width: 20%"></th>
													<th>
														<span>', $txt['smm_scheduled_task'], '</span>
														<button type="button" class="button" @click="removeTask(index)">
															<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
														</button>
													</th>
												</tr>
											</thead>
											<tbody>
												<tr class="windowbg">
													<td>
														<label>
															<strong>', $txt['smm_scheduled_task_slug'], '</strong>
														</label>
													</td>
													<td>
														<input
															type="text"
															x-model="task.slug"
															name="task_slugs[]"
															pattern="^[a-z][a-z_]+$"
															maxlength="24"
															placeholder="slug_name"
															required
														>
													</td>
												</tr>
												<tr class="windowbg">
													<td>
														<label>
															<strong>', $txt['smm_scheduled_task_name'], '</strong>
														</label>
													</td>
													<td>
														<table class="table_grid">
															<tbody>';

	foreach ($context['smm_languages'] as $lang) {
		echo '
																<tr class="windowbg">
																	<td>
																		<input
																			type="text"
																			x-model="task.names[\'', $lang['filename'], '\']"
																			name="task_names[', $lang['filename'], '][]"', in_array($lang['filename'], [$context['user']['language'], 'english']) ? ' required' : '', ' placeholder="', $lang['name'], '"
																		>
																	</td>
																</tr>';
	}

	echo '
															</tbody>
														</table>
													</td>
												</tr>
												<tr class="windowbg">
													<td>
														<label>
															<strong>', $txt['smm_scheduled_task_description'], '</strong>
														</label>
													</td>
													<td>
														<table class="table_grid">
															<tbody>';

	foreach ($context['smm_languages'] as $lang) {
		echo '
																<tr class="windowbg">
																	<td>
																		<input
																			type="text"
																			x-model="task.descriptions[\'', $lang['filename'], '\']"
																			name="task_descriptions[', $lang['filename'], '][]"', in_array($lang['filename'], [$context['user']['language'], 'english']) ? ' required' : '', ' placeholder="', $lang['name'], '"
																		>
																	</td>
																</tr>';
	}

	echo '
															</tbody>
														</table>
													</td>
												</tr>
												<tr class="windowbg" x-data="{ type_id: $id(\'run-type\') }">
													<td>
														<label :for="type_id">
															<strong>', $txt['smm_task_run'], '</strong>
														</label>
													</td>
													<td>
														<select
															x-model="task.regularity"
															name="task_regularities[]"
															:id="type_id"
														>';

	foreach ($txt['smm_scheduled_task_run_set'] as $type => $name) {
		echo '
															<option value="', $type, '">', $name, '</option>';
	}

	echo '
														</select>
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
									<button type="button" class="button" @click="addTask()">
										<span class="main_icons plus"></span> ', $txt['smm_scheduled_task_new'], '
									</button>
								</td>
							</tr>
						</tfoot>
					</table>

					<div class="infobox">', $txt['smm_background_tasks_info'], '</div>
					<table class="add_option centertext" x-data="smm.handleTasks()">
						<tbody>
							<template x-for="(task, index) in backgroundTasks" :key="index">
								<tr class="windowbg">
									<td colspan="4">
										<table class="plugin_options table_grid">
											<thead>
												<tr class="title_bar">
													<th style="width: 20%"></th>
													<th>
														<span>', $txt['smm_background_task'], '</span>
														<button type="button" class="button" @click="removeTask(index, \'background\')">
															<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
														</button>
													</th>
												</tr>
											</thead>
											<tbody>
												<tr class="windowbg">
													<td>
														<label>
															<strong>', $txt['smm_background_task_classname'], '</strong>
														</label>
													</td>
													<td>
														<input
															type="text"
															x-model="task.classname"
															name="background_task_classnames[]"
															pattern="^[A-Z][a-zA-Z_]+$"
															placeholder="BackgroundTask"
															required
														>
													</td>
												</tr>
												<tr class="windowbg" x-data="{ type_id: $id(\'run-type\') }">
													<td>
														<label :for="type_id">
															<strong>', $txt['smm_task_run'], '</strong>
														</label>
													</td>
													<td>
														<select
															x-model="task.regularity"
															name="background_task_regularities[]"
															:id="type_id"
														>';

	foreach ($txt['smm_background_task_run_set'] as $type => $name) {
		echo '
															<option value="', $type, '">', $name, '</option>';
	}

	echo '
														</select>
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
									<button type="button" class="button" @click="addTask(\'background\')">
										<span class="main_icons plus"></span> ', $txt['smm_background_task_new'], '
									</button>
								</td>
							</tr>
						</tfoot>
					</table>

					<div class="infobox">', $txt['smm_legacy_tasks_info'], '</div>
					<table class="add_option centertext" x-data="smm.handleTasks()">
						<tbody>
							<template x-for="(task, index) in legacyTasks" :key="index">
								<tr class="windowbg">
									<td colspan="4">
										<table class="plugin_options table_grid">
											<thead>
												<tr class="title_bar">
													<th style="width: 20%"></th>
													<th>
														<span>', $txt['smm_legacy_task'], '</span>
														<button type="button" class="button" @click="removeTask(index, \'legacy\')">
															<span class="main_icons delete"></span> <span class="remove_label">', $txt['remove'], '</span>
														</button>
													</th>
												</tr>
											</thead>
											<tbody>
												<tr class="windowbg">
													<td>
														<label>
															<strong>', $txt['smm_legacy_task_method'], '</strong>
														</label>
													</td>
													<td>
														<input
															type="text"
															x-model="task.method"
															name="legacy_task_methods[]"
															pattern="^[a-z][a-zA-Z]+$"
															placeholder="runTask"
															required
														>
													</td>
												</tr>
												<tr class="windowbg" x-data="{ type_id: $id(\'run-type\') }">
													<td>
														<label :for="type_id">
															<strong>', $txt['smm_task_run'], '</strong>
														</label>
													</td>
													<td>
														<select
															x-model="task.regularity"
															name="legacy_task_regularities[]"
															:id="type_id"
														>';

	foreach ($txt['smm_legacy_task_run_set'] as $type => $name) {
		echo '
															<option value="', $type, '">', $name, '</option>';
	}

	echo '
														</select>
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
									<button type="button" class="button" @click="addTask(\'legacy\')">
										<span class="main_icons plus"></span> ', $txt['smm_legacy_task_new'], '
									</button>
								</td>
							</tr>
						</tfoot>
					</table>
				</section>
				<section id="content_tab_package" class="bg even">
					', template_post_tab($fields, 'package'), '
				</section>
			</div>
			<br class="clear">
			<div class="centertext">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
				<button type="submit" class="button" name="save" @click="smm.post($root)">
					<i class="main_icons valid"></i> ', $txt['smm_build'], '
				</button>
			</div>
		</div>
	</form>

	<script type="module">
		if (window.Alpine === undefined) {
			const script = document.createElement("script");
			script.defer = true;
			script.src = "https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js";
			document.body.appendChild(script);
		}
	</script>
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
					options: ', json_encode($context['smm_skeleton']['options']), ',
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
					tables: ', json_encode($context['smm_skeleton']['tables']), ',
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

			handleTasks() {
				return {
					scheduledTasks: ', json_encode($context['smm_skeleton']['scheduled_tasks'] ?? []), ',
					backgroundTasks: ', json_encode($context['smm_skeleton']['background_tasks'] ?? []), ',
					legacyTasks: ', json_encode($context['smm_skeleton']['legacy_tasks'] ?? []), ',
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
	</script>';
}

function template_post_tab(array $fields, string $tab = 'basic'): bool
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

	return false;
}
