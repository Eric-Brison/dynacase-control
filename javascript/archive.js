/**
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero
 *          General Public License
 */

archiveStore = {};

/**
 * Update archive list
 */
function updateArchiveList(select) {

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					getArchivedContextList : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {
					updateArchiveList_success(responseObject, select);
				},
				failure : function(responseObject) {
					updateArchiveList_failure(responseObject);
				}
			});

}

function reloadArchiveStore() {
	if (archiveStore[currentArchive]) {
		archiveStore[currentArchive].load();
	}
}

function updateArchiveList_success(responseObject, select) {
	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	} else {
		var data = response.data;

		archiveList = data;

		var panel = Ext.getCmp('archive-list');

		panel.items.each(function(item, index, len) {
					if (item.id != 'archive-list-title') {
						this.remove(item, true);
					}
				}, panel);

		for (var i = 0; i < data.length; i++) {

			panel.add({
				title : data[i].name
						+ (data[i].datetime ? ' ('
								+ data[i].datetime.substr(0, 10) + ')' : ''),
				iconCls : (!data[i].inProgress)
						? 'x-icon-archive'
						: 'x-icon-loading',
				tabTip : data[i].description ? data[i].description : '',
				style : 'padding:10px;',
				layout : 'fit',
				disabled : data[i].inProgress,
				listeners : {
					activate : function(panel) {
						currentArchive = panel.title;
						reloadArchiveStore();
					}
				},
				items : [{
					xtype : 'panel',
					title : data[i].name,
					archive : data[i],
					iconCls : (!data[i].inProgress)
							? 'x-icon-archive'
							: 'x-icon-loading',
					id : 'archive-' + data[i].name,
					bodyStyle : 'overflow-y:auto;',
					items : [{
						layout : 'anchor',
						title : 'Archive Information',
						style : 'padding:10px;font-size:small;',
						bodyStyle : 'padding:5px;',
						xtype : 'panel',
						archive : data[i],
						// html: contextInfoHtml,
						tbar : [{
							text : 'Create Context',
							tooltip : 'Create Context from Archive',
							iconCls : 'x-icon-create',
							archive : data[i],
							handler : function(button) {

								var win = new Ext.Window({
									title : 'Create Context from Archive',
									iconCls : 'x-icon-create',
									layout : 'fit',
									border : false,
									modal : true,
									width : 600,
									items : [{
										xtype : 'form',
										id : 'create-archive-form',
										columnWidth : 1,
										bodyStyle : 'padding:10px',
										frame : true,
										autoHeight : true,
										items : [{
													xtype : 'textfield',
													fieldLabel : 'Name',
													name : 'name',
													anchor : '-15',
													value : button.archive.name
												}, {
													xtype : 'textfield',
													fieldLabel : 'Root',
													name : 'root',
													anchor : '-15'
												}, {
													xtype : 'textarea',
													fieldLabel : 'Description',
													name : 'desc',
													anchor : '-15',
													value : button.archive.description
												}, {
													xtype : 'textfield',
													fieldLabel : 'Url',
													name : 'url',
													anchor : '-15'
												}, {
													xtype : 'textfield',
													fieldLabel : 'Core Database Service',
													name : 'core_pgservice',
													anchor : '-15'
												}, {
													xtype : 'textfield',
													fieldLabel : 'Vault Root',
													name : 'vault_root',
													anchor : '-15'
												}, {
													xtype : 'checkbox',
													fieldLabel : 'Remove Profiles',
													name : 'remove_profiles',
													listeners : {
														check : function(
																checkbox,
																checked) {
															if (checked == true) {

																Ext
																		.getCmp('create-archive-form')
																		.getForm()
																		.findField('user_login')
																		.show();
																Ext
																		.getCmp('create-archive-form')
																		.getForm()
																		.findField('user_login')
																		.enable();
																Ext
																		.getCmp('create-archive-form')
																		.getForm()
																		.findField('user_password')
																		.show();
																Ext
																		.getCmp('create-archive-form')
																		.getForm()
																		.findField('user_password')
																		.enable();

															} else {

																Ext
																		.getCmp('create-archive-form')
																		.getForm()
																		.findField('user_login')
																		.hide();
																Ext
																		.getCmp('create-archive-form')
																		.getForm()
																		.findField('user_login')
																		.disable();
																Ext
																		.getCmp('create-archive-form')
																		.getForm()
																		.findField('user_password')
																		.hide();
																Ext
																		.getCmp('create-archive-form')
																		.getForm()
																		.findField('user_password')
																		.disable();

															}
														}
													}
												}, {
													xtype : 'textfield',
													fieldLabel : 'User Login',
													name : 'user_login',
													anchor : '-15',
													hidden : true
												}, {
													xtype : 'textfield',
													fieldLabel : 'User Password',
													name : 'user_password',
													anchor : '-15',
													hidden : true
												}, {
													xtype : 'checkbox',
													fieldLabel : 'Clean tmp directory?',
													name : 'clean_tmp_directory',
													checked : true
												}],

										buttons : [{
											text : 'Save',
											handler : function() {

												if (!Ext
														.getCmp('create-archive-form')
														.getForm()
														.findField('name')
														.getValue()) {
													Ext.Msg
															.alert(
																	'Web Installer',
																	'A name must be provided.');
													return;
												};

												if (!Ext
														.getCmp('create-archive-form')
														.getForm()
														.findField('root')
														.getValue()) {
													Ext.Msg
															.alert(
																	'Web Installer',
																	'A root must be provided.');
													return;
												};

												if (!Ext
														.getCmp('create-archive-form')
														.getForm()
														.findField('vault_root')
														.getValue()) {
													Ext.Msg
															.alert(
																	'Web Installer',
																	'A vault root must be provided.');
													return;
												};

												if (!Ext
														.getCmp('create-archive-form')
														.getForm()
														.findField('core_pgservice')
														.getValue()) {
													Ext.Msg
															.alert(
																	'Web Installer',
																	'A database service must be provided.');
													return;
												};

												if (Ext
														.getCmp('create-archive-form')
														.getForm()
														.findField('remove_profiles')
														.getValue()) {
													if (!Ext
															.getCmp('create-archive-form')
															.getForm()
															.findField('user_login')
															.getValue()) {
														Ext.Msg
																.alert(
																		'Web Installer',
																		'If you remove profiles, you must specify a user login.');
														return;
													};
													if (!Ext
															.getCmp('create-archive-form')
															.getForm()
															.findField('user_password')
															.getValue()) {
														Ext.Msg
																.alert(
																		'Web Installer',
																		'If you remove profiles, you must specify a user password.');
														return;
													};
												};

												mask = new Ext.LoadMask(Ext
																.getBody(), {
															msg : 'Making Context From Archive...'
														});
												mask.show();

												Ext
														.getCmp('create-archive-form')
														.getForm().submit({
															url : 'wiff.php',
															timeout : 3600,
															success : function(
																	form,
																	action) {
																// updateContextList('select-last');
																form.reset();
																var panel = Ext
																		.getCmp('create-archive-form');
																panel
																		.fireEvent(
																				'render',
																				panel);
																win.close();
																win.destroy();
																mask.hide();
																Ext.Msg
																		.alert(
																				'Web Installer',
																				'Context '
																						+ action.result.data.name
																						+ ' successfully created');
														(function() {
																	updateContextList();
																}).defer(1000);
															},
															failure : function(
																	form,
																	action) {
																// updateContextList('select-last');
																mask.hide();
																console
																		.log("Context Not created");
																if (action
																		&& action.result) {
																	Ext.Msg
																			.alert(
																					'Failure',
																					action.result.error);
																} else if (action
																		&& action.failureType == Ext.form.Action.CONNECT_FAILURE) {
																	Ext.Msg
																			.alert(
																					'Warning',
																					'Timeout reach if context not created yet please reload page later',
																					function() {
																						(function() {
																							updateContextList();
																						})
																								.defer(1000);
																					});
																} else {
																	Ext.Msg
																			.alert(
																					'Warning',
																					'Unknow error');
																}
															},
															params : {
																createContextFromArchive : true,
																archiveId : button.archive.id
															}// ,
																// waitMsg :
																// 'Creating
																// Context from
																// Archive...'
														});

												win.hide();
										(function() {
													updateContextList();
												}).defer(1000);

											}
										}]
									}]
								});

								win.show();

							}
						},
								// {
								// text: 'Download',
								// tooltip: 'Download',
								// iconCls: 'x-icon-archive',
								// archive: data[i],
								// handler: function(button){
								//	                            
								// Ext.Ajax.request({
								// url: 'wiff.php',
								// params: {
								// downloadArchive: true,
								// archiveId: button.archive.id
								// },
								// success:
								// function(responseObject){
								// downloadArchive_success(responseObject);
								// },
								// failure:
								// function(responseObject){
								// downloadArchive_failure(responseObject);
								// }
								// });
								//	                        	
								// }
								// },
								{
									text : 'Delete',
									tooltip : 'Delete',
									iconCls : 'x-icon-delete-archive',
									archive : data[i],
									handler : function(button) {

										Ext.Ajax.request({
											url : 'wiff.php',
											params : {
												deleteArchive : true,
												archiveId : button.archive.id
											},
											success : function(responseObject) {
												deleteArchive_success(responseObject);
											},
											failure : function(responseObject) {
												deleteArchive_failure(responseObject);
											}
										});

									}
								}],
						refresh : function() {

							var contextInfoHtml = '<ul><li class="x-form-item"><b>Archive Datetime :</b> '
									+ Ext.util.Format
											.htmlEncode(this.archive.datetime)
									+ '</li><li class="x-form-item"><b>Description :</b> '
									+ Ext.util.Format
											.htmlEncode(this.archive.description)
									+ '</li><li class="x-form-item"><b>Archive id :</b> '
									+ Ext.util.Format
											.htmlEncode(this.archive.id)
									+ '<li></ul><p>'
									+ '</li><li class="x-form-item"><b>Vault saved :</b> '
									+ Ext.util.Format
											.htmlEncode(this.archive.vault)
									+ '<li></ul><p>';

							this.body.update(contextInfoHtml);

						},
						listeners : {
							render : function(panel) {
								panel.refresh();
							}
						}

					}, {
						id : 'archive-' + data[i].name + '-installed',
						title : 'Installed',
						columnWidth : .45,
						layout : 'fit',
						style : 'padding:10px;padding-top:0px;',
						archive : data[i],
						listeners : {
							afterrender : function(panel) {

								currentArchive = panel.archive.id;

								archiveStore[currentArchive] = new Ext.data.JsonStore(
										{
											data : panel.archive.moduleList,
											fields : ['name', 'versionrelease',
													'availableversionrelease',
													'description', 'infopath',
													'errorstatus'],
											sortInfo : {
												field : 'name',
												direction : "ASC"
											}
										});

								var selModel = new Ext.grid.RowSelectionModel();

								var grid = new Ext.grid.GridPanel({
											selModel : selModel,
											loadMask : true,
											border : false,
											store : archiveStore[currentArchive],
											stripeRows : true,
											columns : [{
														id : 'name',
														header : 'Module',
														dataIndex : 'name',
														width : 140
													}, {
														id : 'installed-version',
														header : 'Installed Version',
														dataIndex : 'versionrelease'
													}, {
														id : 'description',
														header : 'Description',
														dataIndex : 'description'
													}],
											autoExpandColumn : 'description',
											autoHeight : true
										});

								grid.getView().getRowClass = function(record,
										index) {
									return (record.data.errorstatus
											? 'red-row'
											: '');
								};

								grid.getView().emptyText = 'No installed modules';

								panel.add(grid);

							}
						}
					}]

				}]
			})
		}

		// Selection of context to display
		if (data.length != 0) {
			if (select == 'select-last') {
				Ext.getCmp('archive-list').setActiveTab(Ext
						.getCmp('archive-list').items.last());
			} else {
				if (window.currentContext) {

					var contextArray = Ext.getCmp('archive-list').items.items;

					for (var i = 0; i < contextArray.length; i++) {
						if (contextArray[i].title == currentContext) {
							Ext.getCmp('archive-list')
									.setActiveTab(contextArray[i]);
						}
					}

				}

			}
		}

	}

}

function archive_success(responseObject) {

	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	} else {
		Ext.Msg.alert('WIFF', 'Archive created.', function() {
					updateArchiveList();
				});
	}

}

function archive_failure(responseObject) {
	updateArchiveList();
	// console.log('Archive Failure');
}

function downloadArchive_success(responseObject) {

	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}

	console.log('Download Archive', response);

}

function downloadArchive_failure(responseObject) {
	console.log('Download Archive Failure');
}

function deleteArchive_success(responseObject) {

	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	} else {
		Ext.Msg.alert('WIFF', 'Archive deleted.', function() {
					updateArchiveList();
				});
	}

}

function deleteArchive_failure(responseObject) {
	console.log('Archive Failure');
}

function updateArchiveList_failure(responseObject) {
	Ext.Msg.alert('Error', 'Could not retrieve archive list');
}