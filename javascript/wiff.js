/**
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero
 *          General Public License
 */

Ext.override(Ext.layout.FormLayout, {
			renderItem : function(c, position, target) {
				if (c && !c.rendered && (c.isFormField || c.fieldLabel)
						&& c.inputType != 'hidden') {
					var args = this.getTemplateArgs(c);
					if (typeof position == 'number') {
						position = target.dom.childNodes[position] || null;
					}
					if (position) {
						c.itemCt = this.fieldTpl.insertBefore(position, args,
								true);
					} else {
						c.itemCt = this.fieldTpl.append(target, args, true);
					}
					c.actionMode = 'itemCt';
					c.render('x-form-el-' + c.id);
					c.container = c.itemCt;
					c.actionMode = 'container';
				} else {
					Ext.layout.FormLayout.superclass.renderItem.apply(this,
							arguments);
				}
			}
		});
Ext.override(Ext.form.Field, {
			getItemCt : function() {
				return this.itemCt;
			}
		});

// Global variables
installedStore = {};
availableStore = {};

// Memorize repository logins and passwords
authInfo = [];

// Wiff functions
// Password File Test
function checkPasswordFile() {
	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					hasPasswordFile : true
				},
				success : function(responseObject) {
					var response = eval('(' + responseObject.responseText + ')');
					if (response.error) {
						Ext.Msg.alert('Server Error', response.error);
					} else {
						if (response.data) {
							// Nothing to do.
						} else {
							displayPasswordWindow(false);
						}
					}

				},
				failure : function(responseObject) {

				}

			});
}

function updateWIFF() {

	mask = new Ext.LoadMask(Ext.getBody(), {
				msg : 'Updating...'
			});
	mask.show();

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					update : true
				},
				success : function(responseObject) {

					mask.hide();

					var response = eval('(' + responseObject.responseText + ')');
					if (response.error) {
						Ext.Msg.alert('Server Error', response.error);
					} else {
						Ext.Msg.alert('Freedom Web Installer',
								'Update successful. Click OK to restart.',
								function(btn) {
									window.location.reload();
								});
					}

				},
				failure : function(responseObject) {

				}

			});
}

function reloadModuleStore() {
	if (installedStore[currentContext]) {
		installedStore[currentContext].load();
	}
	if (availableStore[currentContext]) {
		availableStore[currentContext].load();
	}
}

function displayPasswordWindow(canCancel) {

	var fields = [];

	if (!canCancel) {
		var infoPanel = new Ext.Panel({
			border : false,
			html : '<i>Your Wiff is currently not protected by authentification.<br/>Please define a login and a password.</i>',
			bodyStyle : 'padding-bottom:10px;'
		});
		fields.push(infoPanel);
	}

	var loginField = new Ext.form.TextField({
				fieldLabel : 'Login',
				xtype : 'textfield',
				anchor : '-15'

			});

	var passwordField = new Ext.form.TextField({
				fieldLabel : 'Password',
				xtype : 'textfield',
				inputType : 'password',
				anchor : '-15'
			});

	var confirmPasswordField = new Ext.form.TextField({
				fieldLabel : 'Confirm Password',
				xtype : 'textfield',
				inputType : 'password',
				anchor : '-15'
			});

	fields.push(loginField);
	fields.push(passwordField);
	fields.push(confirmPasswordField);

	if (!canCancel) {
		var infoPanel = new Ext.Panel({
					border : false,
					html : '<i>You can change login and password later in Setup.</i>',
					bodyStyle : 'padding-top:10px;'
				});
		fields.push(infoPanel);
	}

	var win = new Ext.Window({
				title : 'Freedom Web Installer - Define Password',
				layout : 'fit',
				modal : true,
				height : 200,
				width : 300,
				items : [{
					xtype : 'form',
					labelWidth : 120,
					bodyStyle : 'padding:10px',
					border : false,
					items : fields,
					bbar : [{
						text : 'Save',
						iconCls : 'x-icon-ok',
						handler : function(b, e) {

							var newLogin = loginField.getValue();
							var newPassword = passwordField.getValue();

							var confirmNewPassword = confirmPasswordField
									.getValue();

							if (newPassword != confirmNewPassword) {
								Ext.Msg.alert('Freedom Web Installer',
										'Provided passwords are not the same.');
							} else {

								mask = new Ext.LoadMask(Ext.getBody(), {
											msg : 'Saving...'
										});
								mask.show();

								Ext.Ajax.request({
											url : 'wiff.php',
											params : {
												createPasswordFile : true,
												login : newLogin,
												password : newPassword
											},
											success : function(responseObject) {

												mask.hide();

												var response = eval('('
														+ responseObject.responseText
														+ ')');
												if (response.error) {
													Ext.Msg.alert(
															'Server Error',
															response.error);
												} else {
													Ext.Msg
															.alert(
																	'Freedom Web Installer',
																	'Save successful.',
																	function(
																			btn) {
																		win
																				.close();
																	});

												}

											},
											failure : function(responseObject) {

											}

										});

								win.close();

							}

						}
					}, {
						text : 'Cancel',
						iconCls : 'x-icon-undo',
						handler : function(b, e) {
							win.close();
						},
						disabled : !canCancel
					}]
				}],
				listeners : {
					afterrender : function() {

						Ext.Ajax.request({
									url : 'wiff.php',
									params : {
										getLogin : true
									},
									success : function(responseObject) {
										var response = eval('('
												+ responseObject.responseText
												+ ')');
										if (response.error) {
											Ext.Msg.alert('Server Error',
													response.error);
										} else {
											if (response.data) {
												loginField
														.setValue(response.data);
											}
										}

									},
									failure : function(responseObject) {

									}

								});

					},
					close : function() {
						checkPasswordFile();
					}
				}
			});
	win.show();
}

function displayChangelog(record) {

	var changelog = record.get('changelog');

	var html = '<p>';
	for (var i = 0; i < changelog.length; i++) {
		html += '<li style="font-size:medium;margin-top:5px;margin-bottom:5px;border-bottom:1px solid #99BBE8;"><img src=images/icons/tick.png style="position:relative;top:3px;" /><b>  version '
				+ changelog[i]['version']
				+ ' </b><i>('
				+ changelog[i]['date']
				+ ')</i></li>';
		for (var j = 0; j < changelog[i]['action'].length; j++) {
			var url = changelog[i]['action'][j]['url'];
			var urlLabel = '';
			var index = url.indexOf('issues/');
			if (index != -1) {
				urlLabel = url.substr(index, 6) + ' ' + url.substr(index + 7);
			} else {
				urlLabel = 'more';
			}
			html += '<li style="padding-left:20px;"><b>'
					+ changelog[i]['action'][j]['title']
					+ '</b><i>'
					+ (url ? ' <a href=' + url + ' target="_blank">' + urlLabel
							+ '</a>' : '') + '</i><br/><i>'
					+ changelog[i]['action'][j]['description'] + '</i></li>';
		}
	}
	html += '</p>';

	var win = new Ext.Window({
		title : record.get('name') + ' changelog',
		modal : true,
		layout : 'fit',
		height : 300,
		width : 600,
		bodyStyle : 'padding:15px;text-align:justify;overflow:auto;list-style-type:none;',
		html : html,
		iconCls : 'x-icon-log'
	});

	win.show();

}

function displayRepositoryWindow(grid, record) {

	if (!record) {
		var nameField = new Ext.form.TextField({
					fieldLabel : 'Name',
					anchor : '-15',
					allowBlank : false,
					vtype : 'alphanum'
				});
	} else {
		var nameField = new Ext.form.DisplayField({
					fieldLabel : 'Name',
					anchor : '-15'
				})
	}

	var descriptionField = new Ext.form.TextField({
				fieldLabel : 'Description',
				anchor : '-15'
			});

	var protocolField = new Ext.form.TextField({
				fieldLabel : 'Protocol',
				anchor : '-15'
			});

	var hostField = new Ext.form.TextField({
				fieldLabel : 'Host',
				anchor : '-15'
			});

	var pathField = new Ext.form.TextField({
				fieldLabel : 'Path',
				anchor : '-15'
			});

	var authentifiedBox = new Ext.form.Checkbox({
				fieldLabel : 'Authentified',
				listeners : {
					check : function(checkbox, checked) {
						if (checked == true) {
							loginField.show();
							passwordField.show();
							confirmPasswordField.show();

							loginField.enable();
							passwordField.enable();
							confirmPasswordField.enable();
						} else {
							loginField.hide();
							passwordField.hide();
							confirmPasswordField.hide();

							loginField.disable();
							passwordField.disable();
							confirmPasswordField.disable();
						}
					}
				}
			});

	var defaultBox = new Ext.form.Checkbox({
				fieldLabel : 'Default'
			});

	var loginField = new Ext.form.TextField({
				fieldLabel : 'Login',
				anchor : '-15',
				hidden : true
			});

	var passwordField = new Ext.form.TextField({
				fieldLabel : 'Password',
				inputType : 'password',
				anchor : '-15',
				hidden : true
			});

	var confirmPasswordField = new Ext.form.TextField({
				fieldLabel : 'Confirm Password',
				inputType : 'password',
				anchor : '-15',
				hidden : true
			});

	if (record) {
		nameField.setValue(record.get('name'));
		descriptionField.setValue(record.get('description'));
		protocolField.setValue(record.get('protocol'));
		hostField.setValue(record.get('host'));
		pathField.setValue(record.get('path'));
		if (record.get('authentified') == 'yes') {
			authentifiedBox.setValue(true);
		} else {
			authentifiedBox.setValue(false);
		}
		if (record.get('default') == 'yes') {
			defaultBox.setValue(true);
		} else {
			defaultBox.setValue(false);
		}
		loginField.setValue(record.get('login'));
		passwordField.setValue(record.get('password'));
		confirmPasswordField.setValue(record.get('password'));
	}

	var win = new Ext.Window({
		title : 'Freedom Web Installer - Add Repository',
		layout : 'fit',
		modal : true,
		width : 300,
		height : 350,
		items : [{
			xtype : 'form',
			labelWidth : 120,
			border : false,
			bodyStyle : 'padding:5px;',
			items : [nameField, descriptionField, protocolField, hostField,
					pathField, defaultBox, authentifiedBox, loginField,
					passwordField, confirmPasswordField],
			bbar : [{
				text : 'Save',
				iconCls : 'x-icon-ok',
				handler : function(b, e) {
					var newName = nameField.getValue();
					var newDescription = descriptionField.getValue();
					var newProtocol = protocolField.getValue();
					var newHost = hostField.getValue();
					var newPath = pathField.getValue();
					var newLogin = loginField.getValue();
					var newPassword = passwordField.getValue();
					var confirmNewPassword = confirmPasswordField.getValue();
					var newAuthentified = authentifiedBox.getValue() == true
							? 'yes'
							: 'no';
					var newDefault = defaultBox.getValue() == true
							? 'yes'
							: 'no';

					if (newName == '') {
						Ext.Msg.alert('Freedom Web Installer',
								'A repository name must be provided.');
					}

					if (newPassword != confirmNewPassword) {
						Ext.Msg.alert('Freedom Web Installer',
								'Provided passwords are not the same.');
					}

					if (nameField.isValid()) {

						mask = new Ext.LoadMask(Ext.getBody(), {
									msg : 'Saving...'
								});
						mask.show();

						Ext.Ajax.request({
							url : 'wiff.php',
							params : {
								createRepo : record ? false : true,
								modifyRepo : record ? true : false,
								name : newName,
								description : newDescription,
								protocol : newProtocol,
								host : newHost,
								path : newPath,
								'default' : newDefault,
								login : newLogin,
								password : newPassword,
								authentified : newAuthentified
							},
							success : function(responseObject) {

								mask.hide();

								var response = eval('('
										+ responseObject.responseText + ')');
								if (response.error) {
									Ext.Msg.alert('Server Error',
											response.error);
								} else {
									if (response.data) {
										Ext.Msg.alert('Freedom Web Installer',
												'Save successful.', function(
														btn) {
													win.close();
													grid.getStore().reload();
													Ext
															.getCmp('create-context-form')
															.fireEvent(
																	'render',
																	Ext
																			.getCmp('create-context-form'));
												});
									} else {
										Ext.Msg
												.alert(
														'Freedom Web Installer',
														'Save successful.<br/><img src="images/icons/error.png" style="margin-right:2px;vertical-align:bottom;"/><b>Warning.</b> Repository not valid.',
														function(btn) {
															win.close();
															grid.getStore()
																	.reload();
															Ext
																	.getCmp('create-context-form')
																	.fireEvent(
																			'render',
																			Ext
																					.getCmp('create-context-form'));
														});
									}

								}

							},
							failure : function(responseObject) {

							}

						});

					}

				}
			}, {
				text : 'Cancel',
				iconCls : 'x-icon-undo',
				handler : function(b, e) {
					win.close();
				}
			}]
		}],
		listeners : {
			close : function() {
				checkPasswordFile();
			}
		}
	});

	return win;

}

/**
 * Update context list
 */
function updateContextList(select) {

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					getContextList : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {
					updateContextList_success(responseObject, select);
				},
				failure : function(responseObject) {
					updateContextList_failure(responseObject);
				}

			});

}

// //////////

getCurrentContext = function() {

	for (var i = 0; i < contextList.length; i++) {
		if (contextList[i].name == currentContext) {
			return contextList[i];
		}
	}
	return false;

}

getCurrentRepo = function(repoName) {

	var context = getCurrentContext();
	if (context) {
		for (var i = 0; i < context.repo.length; i++) {
			if (context.repo[i].name == repoName) {
				return context.repo[i];
			}
		}
	}
	return false;

}

setRepoAuth = function(name, login, password) {

	var repo = getRepoAuth(name);
	if (!repo) {
		authInfo.push({
					name : name,
					login : login,
					password : password
				});
	} else {
		repo.login = login;
		repo.password = password;
	}

}

getRepoAuth = function(name) {

	for (var i = 0; i < authInfo.length; i++) {
		if (authInfo[i]['name'] == name) {
			return authInfo[i];
		}
	}
	return false;

}

askRepoAuth = function(repoName) {

	var repo = getCurrentRepo(repoName);

	var nameField = new Ext.form.DisplayField({
				fieldLabel : 'Name',
				anchor : '-15'
			});

	var descriptionField = new Ext.form.DisplayField({
				fieldLabel : 'Description',
				anchor : '-15'
			});

	if (repo.login) {
		var loginField = new Ext.form.DisplayField({
					fieldLabel : 'Login',
					anchor : '-15'
				});
	} else {
		var loginField = new Ext.form.TextField({
					fieldLabel : 'Login',
					anchor : '-15'
				});
	}

	var passwordField = new Ext.form.TextField({
				fieldLabel : 'Password',
				inputType : 'password',
				anchor : '-15'
			});

	var confirmPasswordField = new Ext.form.TextField({
				fieldLabel : 'Confirm Password',
				inputType : 'password',
				anchor : '-15'
			});

	nameField.setValue(repo.name);
	descriptionField.setValue(repo.description);
	loginField.setValue(repo.login);

	var win = new Ext.Window({
		title : 'Freedom Web Installer - Authentify Repository',
		layout : 'fit',
		modal : true,
		height : 300,
		width : 300,
		items : [{
			xtype : 'form',
			labelWidth : 120,
			border : false,
			bodyStyle : 'padding:5px;',
			items : [nameField, descriptionField, loginField, passwordField,
					confirmPasswordField],
			bbar : [{
				text : 'Authentify',
				iconCls : 'x-icon-ok',
				handler : function(b, e) {
					var name = nameField.getValue();
					var login = loginField.getValue();
					var password = passwordField.getValue();
					var confirmPassword = confirmPasswordField.getValue();

					if (name == '') {
						Ext.Msg.alert('Freedom Web Installer',
								'A repository name must be provided.');
					}

					if (password != confirmPassword) {
						Ext.Msg.alert('Freedom Web Installer',
								'Provided passwords are not the same.');
					}

					mask = new Ext.LoadMask(Ext.getBody(), {
								msg : 'Authentifying...'
							});
					mask.show();

					Ext.Ajax.request({
								url : 'wiff.php',
								params : {
									authRepo : true,
									name : name,
									login : login,
									password : password,
									authInfo : Ext.encode(authInfo)
								},
								success : function(responseObject) {

									mask.hide();

									var response = eval('('
											+ responseObject.responseText + ')');
									if (response.error) {
										Ext.Msg.alert('Server Error',
												response.error);
									} else {
										if (response.data) {
											Ext.Msg.alert(
													'Freedom Web Installer',
													'Authentify successful.',
													function(btn) {
														win.close();
														setRepoAuth(name,
																login, password);
														updateContextList();
													});
										} else {
											Ext.Msg.alert(
													'Freedom Web Installer',
													'Authentify failed.',
													function(btn) {

													});
										}

									}

								},
								failure : function(responseObject) {

								}

							});

				}
			}, {
				text : 'Cancel',
				iconCls : 'x-icon-undo',
				handler : function(b, e) {
					win.close();
				}
			}]
		}],
		listeners : {
			close : function() {
				checkPasswordFile();
			}
		}
	});

	win.show();

}

function updateContextList_success(responseObject, select) {
	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}
	var data = response.data;

	contextList = data;

	var panel = Ext.getCmp('context-list');

	panel.items.each(function(item, index, len) {
				if (item.id != 'context-list-title') {
					this.remove(item, true);
				}
			}, panel);

	var importButton = function() {
		var importButtonRes = new Ext.ux.form.FileUploadField({
					name : 'module',
					buttonOnly : true,
					buttonCfg : {
						text : 'Import Module',
						iconCls : 'x-icon-import',
						tooltip : 'Open a local file browser'
					},
					listeners : {
						fileselected : function(button, file) {

							if (!button.importForm) {
								var importFormEl = button.container
										.createChild({
													tag : 'form',
													style : 'display:none;'
												});
								button.container.importForm = new Ext.form.BasicForm(
										importFormEl, {
											url : 'wiff.php',
											fileUpload : true
										});
							}

							var inputFileEl = button.detachFileInput();
							inputFileEl.appendTo(button.container.importForm
									.getEl());

							button.container.importForm.submit({
										waitTitle : 'Module Import',
										waitMsg : 'Importing...',
										params : {
											importArchive : true,
											context : currentContext
										},
										success : button.onImportSuccess,
										failure : button.onImportFailure
									});

						}
					},
					onImportSuccess : function(form, action) {
						var inputFileEl = form.getEl().child('input');
						inputFileEl.remove();
						installLocal(action.result.data);
					},
					onImportFailure : function(form, action) {
						var response = eval('(' + action.response.responseText
								+ ')');
						var inputFileEl = form.getEl().child('input');
						inputFileEl.remove();
						Ext.Msg.alert('Import Failed', response.error);
					}
				});
		return (importButtonRes);
	};

	var onDeleteContextButton = function(button) {
		Ext.Msg.show({
					title : 'Warning',
					msg : "Deleting context will empty database but not delete it. Do you really want to delete this context?",
					buttons : Ext.Msg.YESNO,
					icon : Ext.Msg.WARNING,
					fn : function(btn, text, opt) {
						if (btn != 'yes') {
							return false;
						}
						mask = new Ext.LoadMask(Ext.getBody(), {
									msg : 'Deleting Context...'
								});
						mask.show();
						Ext.Ajax.request({
									url : 'wiff.php',
									timeout : 3600,
									params : {
										contextToDelete : button.context.name,
										deleteContext : true
									},
									success : function(response, options) {
										var responseDecode = Ext.util.JSON
												.decode(response.responseText);
										if (responseDecode.success == false) {
											Ext.Msg.alert('Warning',
													responseDecode.error);
											mask.hide();
						(function			() {
												updateContextList();
											}).defer(1000);
										} else {
											console.log("Context deleted :: ",
													response.responseText);
											mask.hide();
											if (responseDecode.error) {
												Ext.Msg.alert('Web Installer',
														responseDecode.error);
											} else {
												Ext.Msg
														.alert(
																'Web Installer',
																'Context '
																		+ responseDecode.data
																		+ ' deleted');
											}
						(function			() {
												updateContextList();
											}).defer(1000);
										}
									},
									failure : function(response, options) {
										mask.hide();
										if (options.failureType) {
											Ext.Msg.alert('Warning',
													options.failureType);
										} else {
											Ext.Msg.alert('Warning',
													'Unknow Error');
										}
									}
								});
					}
				});
	};

	for (var i = 0; i < data.length; i++) {

		panel.add({
			title : data[i].name,
			iconCls : (!data[i].inProgress)
					? 'x-icon-context'
					: 'x-icon-loading',
			tabTip : data[i].description,
			style : 'padding:10px;',
			layout : 'fit',
			disabled : data[i].inProgress,
			listeners : {
				activate : function(panel) {
					currentContext = panel.title;
					reloadModuleStore();
				}
			},
			items : [{
				xtype : 'panel',
				title : data[i].name,
				iconCls : (!data[i].inProgress)
						? 'x-icon-context'
						: 'x-icon-loading',
				id : data[i].name,
				bodyStyle : 'overflow-y:auto;',
				items : [{
					layout : 'anchor',
					title : 'Context Information',
					style : 'padding:10px;font-size:small;',
					bodyStyle : 'padding:5px;',
					xtype : 'panel',
					context : data[i],
					// html: contextInfoHtml,
					tbar : [{
						text : 'Modify Context',
						tooltip : 'Modify Context',
						iconCls : 'x-icon-setup',
						context : data[i],
						handler : function(button) {
							var win = new Ext.Window({
								title : 'Modify Context',
								iconCls : 'x-icon-setup',
								layout : 'fit',
								border : false,
								modal : true,
								width : 600,
								items : [{
									xtype : 'form',
									id : 'save-context-form',
									columnWidth : 1,
									bodyStyle : 'padding:10px',
									frame : true,
									autoHeight : true,
									items : [{
												xtype : 'textfield',
												fieldLabel : 'Name',
												name : 'name',
												anchor : '-15',
												value : button.context.name
											}, {
												xtype : 'displayfield',
												fieldLabel : 'Root',
												name : 'root',
												anchor : '-15',
												value : button.context.root
											}, {
												xtype : 'textarea',
												fieldLabel : 'Description',
												name : 'desc',
												anchor : '-15',
												value : button.context.description
											}, {
												xtype : 'textfield',
												fieldLabel : 'Url',
												name : 'url',
												anchor : '-15',
												value : button.context.url
											}],

									buttons : [{
										text : 'Save',
										handler : function() {
											Ext.getCmp('save-context-form')
													.getForm().submit({
														url : 'wiff.php',
														success : function(
																form, action) {
															updateContextList('select-last');
															form.reset();
															var panel = Ext
																	.getCmp('create-context-form');
															panel.fireEvent(
																	'render',
																	panel);
															win.close();
															win.destroy();
														},
														failure : function(
																form, action) {
															updateContextList('select-last');
															if (action
																	&& action.result) {
																Ext.Msg
																		.alert(
																				'Failure',
																				action.result.error);
															} else {
																Ext.Msg
																		.alert(
																				'Failure',
																				'Select at least one repository.');
															}
														},
														params : {
															saveContext : true,
															root : button.context.root
														},
														waitMsg : 'Saving Context...'
													})
										}
									}],
									listeners : {
										render : function(panel) {

											repoStore = new Ext.data.JsonStore(
													{
														url : 'wiff.php',
														baseParams : {
															getRepoList : true
														},
														root : 'data',
														fields : ['name',
																'baseurl',
																'description',
																'protocol',
																'host', 'path',
																'url',
																'authentified',
																'login',
																'password',
																'displayUrl'],
														autoLoad : true
													});

											repoBoxList = new Array();

											repoStore.on('load', function() {

												repoStore.each(
														function(record) {

															var checked = false;

															for (var j = 0; j < button.context.repo.length; j++) {
																if (button.context.repo[j].name == record
																		.get('name')) {
																	checked = true;
																}
															}

															repoBoxList.push({
																boxLabel : record
																		.get('description')
																		+ ' <i>('
																		+ record
																				.get('displayUrl')
																		+ ')</i>'
																		+ ')</i>',
																name : 'repo-'
																		+ record
																				.get('name'),
																checked : checked
															});

														});

												panel
														.remove(panel.checkBoxGroup);

												panel.checkBoxGroup = new Ext.form.CheckboxGroup(
														{
															fieldLabel : 'Repositories',
															allowBlank : false,
															blankText : "You must select at least one repository.",
															columns : 1,
															items : repoBoxList
														});

												panel.checkBoxGroup = panel
														.add(panel.checkBoxGroup);
												panel.doLayout();

											});

										}
									}
								}]
							});

							win.show();

						}
					}, importButton(), {
						text : 'Delete context',
						iconCls : 'x-icon-delete-context',
						context : data[i],
						handler : onDeleteContextButton,
						tooltip : 'Delete context'
					}, {
						text : 'Create Archive',
						tooltip : 'Create Archive',
						iconCls : 'x-icon-create-archive',
						context : data[i],
						handler : function(button) {

							var win = new Ext.Window({
								title : 'Create Archive',
								iconCls : 'x-icon-setup',
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
												name : 'archiveName',
												anchor : '-15',
												value : button.context.name
											}, {
												xtype : 'textarea',
												fieldLabel : 'Description',
												name : 'archiveDesc',
												anchor : '-15',
												value : button.context.description
											}, {
												xtype : 'checkbox',
												fieldLabel : 'Exclude Vault',
												name : 'vaultExclude'
											}],
									buttons : [{
										text : 'Create Archive',
										context : data[i],
										handler : function() {

											mask = new Ext.LoadMask(Ext
															.getBody(), {
														msg : 'Making Archive...'
													});
											mask.show();

											Ext.getCmp('create-archive-form')
													.getForm().submit({
														url : 'wiff.php',
														timeout : 3600,
														success : function(
																form, action) {
															// win.hide();
															Ext.Msg
																	.alert(
																			'Web Installer',
																			'Context successfully archived');
															mask.hide();
													(function() {
																updateArchiveList();
															}).defer(1000);

															// archive_success(action.response);
														},
														failure : function(
																form, action) {
															console
																	.log("Error archive not created");
															// win.hide();
															mask.hide();
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
																				'Timeout reach if archive not created yet please reload page later',
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
															// archive_failure(action.response);
														},
														params : {
															archiveContext : true,
															name : button.context.name
														}// ,
															// waitMsg : 'Making
															// Archive...'
													});

											win.hide();
									(function() {
												updateArchiveList();
											}).defer(1000);
										}
									}, {
										text : 'Cancel',
										handler : function() {
											win.close();
										}
									}]
								}]
							});

							win.show();

						}
					}],
					refresh : function() {
						var repositoryHtml = '<ul>';

						var needRepoValidationList = new Array();
						var lenghtRepo = 0;
						if (this.context.repo) {
							lenghtRepo = this.context.repo.length;
						}
						for (var j = 0; j < lenghtRepo; j++) {
							var repoName = this.context.repo[j].name;
							var repoIconId = 'repo-icon-' + repoName;
							var repoLabelId = 'repo-label-' + repoName;

							repositoryHtml += '<li class="x-form-item" style="margin-left:30px;">';
							if (getRepoAuth(this.context.repo[j].name)) {
								repositoryHtml += '<img id="'
										+ repoIconId
										+ '" src="images/icons/lock_open.png" style="position:relative;top:3px;margin-right:3px;" />';
							} else if (this.context.repo[j].needAuth) {
								repositoryHtml += '<a href=javascript:askRepoAuth("'
										+ this.context.repo[j].name
										+ '")><img id="'
										+ repoIconId
										+ '" src=images/icons/lock.png style="position:relative;top:3px;margin-right:3px;" /></a>';
							} else {
								repositoryHtml += '<img id="'
										+ repoIconId
										+ '" src="images/icons/loading.gif" style="position:relative;top:3px;margin-right:3px;" />';
								needRepoValidationList.push({
											'name' : repoName,
											'icon' : repoIconId,
											'label' : repoLabelId
										});
							}
							repositoryHtml += '<!-- <b>'
									+ this.context.repo[j].label + ' --></b>';
							if (this.context.repo[j].description) {
								repositoryHtml += '<i> ('
										+ this.context.repo[j].description
										+ ')</i>';
							}
							repositoryHtml += '&nbsp;<span id="' + repoLabelId
									+ '"i style="font-weight: bold;"></span>';
							repositoryHtml += '</li>';
						}
						repositoryHtml += '</ul>'
						var contextInfoHtml = '<ul><li class="x-form-item"><b>Root :</b> '
								+ this.context.root
								+ '</li><li class="x-form-item"><b>Description :</b> '
								+ this.context.description
								+ '</li><li class="x-form-item"><b>Url :</b>'
								+ (this.context.url
										? '<a href=' + this.context.url
												+ ' target="_blank" > '
												+ this.context.url + '</a>'
										: '<i> no url</i>')
								+ '</li><li class="x-form-item"><b>Repositories :</b> '
								+ repositoryHtml + '</li></ul><p>';

						this.body.update(contextInfoHtml);

						for (var j = 0; j < needRepoValidationList.length; j++) {

							var repoName = needRepoValidationList[j]['name'];
							var repoIconId = needRepoValidationList[j]['icon'];
							var repoLabelId = needRepoValidationList[j]['label'];

							setRepoValidityIconLabel(repoName, repoIconId,
									repoLabelId);
						}

					},
					listeners : {
						render : function(panel) {
							panel.refresh();
						}
					}

				}, {
					id : data[i].name + '-installed',
					title : 'Installed',
					columnWidth : .45,
					layout : 'fit',
					style : 'padding:10px;padding-top:0px;',
					context : data[i],
					listeners : {
						afterrender : function(panel) {

							// Unused for now
							function hasRepoToAuth(context) {

								var ret = false;

								for (var i = 0; i < context.repo.length; i++) {
									if (context.repo[i]['authentified'] == 'yes'
											&& !context.repo[i]['password']) {
										ret = true;
									}
								}
								return ret;
							}

							currentContext = panel.ownerCt.title;

							var status = new Ext.ux.grid.RowActions({
										header : 'Status',
										autoWidth : true,
										actions : [{
													iconCls : 'x-icon-ok',
													tooltip : "No Error",
													hideIndex : "(errorstatus!='')"
												}, {
													iconCls : 'x-icon-ko',
													tooltip : "See Error",
													hideIndex : "(errorstatus=='')"
												}]
									});

							status.on({
								action : function(grid, record, action, row,
										col) {

									switch (action) {
										case 'x-icon-ko' :
											Ext.Msg
													.alert(
															'Freedom Web Installer',
															'Error happened during <b>'
																	+ record
																			.get('errorstatus')
																	+ '</b>');
											break;
									}

								}
							});

							var actions = new Ext.ux.grid.RowActions({
										header : '',
										autoWidth : false,
										width : 90,
										actions : [{
													iconCls : 'x-icon-update',
													tooltip : 'Update',
													hideIndex : '!canUpdate'
												}, {
													iconCls : 'x-icon-param',
													tooltip : 'Parameters',
													hideIndex : '!hasParameter'
												}, {
													iconCls : 'x-icon-log',
													tooltip : 'Changelog',
													hideIndex : '!changelog.length'
												}, {
													iconCls : 'x-icon-help',
													tooltip : 'Help',
													hideIndex : '!infopath'
													// }, {
												// iconCls: 'x-icon-remove',
												// tooltip: 'Remove',
												// hideIndex:
												// "(name=='freedom-core')"
											}]
									});

							actions.on({
										action : function(grid, record, action,
												row, col) {

											currentModule = {
												name : record.get('name')
											};

											switch (action) {
												case 'x-icon-update' :
													var operation = 'upgrade';
													break;
												case 'x-icon-param' :
													var operation = 'parameter';
													break;
												case 'x-icon-help' :
													var operation = 'help';
													break;
												// case 'x-icon-remove':
												// var operation = 'uninstall';
												// break;
												case 'x-icon-log' :
													displayChangelog(record);
													break;
											}

											if (operation == 'parameter') {
												toInstall = [];
												toInstall[0] = currentModule;
												askParameter(currentModule,
														operation);
											}
											if (operation == 'upgrade') {
												upgrade([currentModule.name]);
											}
											if (operation == 'help') {
												window.open(
														record.get('infopath'),
														'_newtab');
											}
											// if (operation == 'remove') {
											// remove(currentModule);
											// }

										}
									});

							installedStore[currentContext] = new Ext.data.JsonStore(
									{
										url : 'wiff.php',
										baseParams : {
											context : this.ownerCt.id,
											getInstalledModuleList : true,
											authInfo : Ext.encode(authInfo)
										},
										root : 'data',
										fields : ['name', 'versionrelease',
												'availableversionrelease',
												'description', 'infopath',
												'errorstatus', {
													name : 'canUpdate',
													type : 'boolean'
												}, {
													name : 'hasParameter',
													type : 'boolean'
												}, 'changelog'],
										// autoLoad: true,
										sortInfo : {
											field : 'name',
											direction : "ASC"
										},
										listeners : { // beforeload:
										// function(store,
										// options){
										// //return false;
										// Ext.Msg.alert('Freedom Web
										// Installer',
										// 'Here I could ask for repository
										// login/password', function(){
										// return false;
										// });
										// },
										// load: function(){
										// console.log('LOAD');
										// },
										// exception: function(){
										// console.log('Exception on load');
										// }
										}
									});

							var selModel = new Ext.grid.CheckboxSelectionModel(
									{
										// header: '',
										listeners : {
											// prevent selection of records
											beforerowselect : function(
													selModel, rowIndex,
													keepExisting, record) {
												if ((record.get('canUpdate') != true)) {
													return false;
												}
											}
										}
									});

							var grid = new Ext.grid.GridPanel({
								selModel : selModel,
								loadMask : true,
								tbar : [{
									text : 'Upgrade Selection',
									tooltip : 'Upgrade selected module(s)',
									iconCls : 'x-icon-install',
									handler : function(button, eventObject) {
										var selections = grid
												.getSelectionModel()
												.getSelections();
										var modules = [];
										for (var i = 0; i < selections.length; i++) {
											modules.push(selections[i]
													.get('name'));
										}
										if (modules.length != 0) {
											upgrade(modules);
										} else {
										}
									}
								}, {
									text : 'Refresh',
									tooltip : 'Refresh installed module(s)',
									iconCls : 'x-icon-refresh',
									handler : function(button, eventObject) {
										if (installedStore[currentContext]) {
											installedStore[currentContext]
													.load();
										}
									}
								}],
								border : false,
								store : installedStore[currentContext],
								stripeRows : true,
								columns : [selModel, actions, {
											id : 'name',
											header : 'Module',
											dataIndex : 'name',
											width : 140
										}, {
											id : 'installed-version',
											header : 'Installed Version',
											dataIndex : 'versionrelease'
										}, {
											id : 'available-version',
											header : 'Available Version',
											dataIndex : 'availableversionrelease'
										}, status, {
											id : 'description',
											header : 'Description',
											dataIndex : 'description'
										}],
								autoExpandColumn : 'description',
								autoHeight : true,
								plugins : [actions, status]
							});

							grid.getView().getRowClass = function(record, index) {
								return (record.data.errorstatus
										? 'red-row'
										: '');
							};

							grid.getView().emptyText = 'No installed modules';

							panel.add(grid);

						}
					}
				}, {
					id : data[i].name + '-available',
					title : 'Available',
					columnWidth : .45,
					layout : 'fit',
					style : 'padding:10px;padding-top:0px;',
					listeners : {
						render : function(panel) {

							var actions = new Ext.ux.grid.RowActions({
										header : '',
										autoWidth : false,
										width : 44,
										actions : [{
													iconCls : 'x-icon-log',
													tooltip : 'Changelog',
													hideIndex : '!changelog.length'
												}, {
													iconCls : 'x-icon-help',
													tooltip : 'Help',
													hideIndex : '!infopath'
												}]
									});

							actions.on({
										action : function(grid, record, action,
												row, col) {

											var module = record.get('name');

											switch (action) {
												// case 'x-icon-install':
												// var operation = 'install';
												// break;
												case 'x-icon-help' :
													var operation = 'help';
													break;
												case 'x-icon-log' :
													displayChangelog(record);
													break;
											}

											// if (operation == 'install') {
											// install([module]);
											// }

											if (operation == 'help') {
												window.open(
														record.get('infopath'),
														'_newtab');
											}

										}
									});

							availableStore[currentContext] = new Ext.data.JsonStore(
									{
										url : 'wiff.php',
										baseParams : {
											context : this.ownerCt.id,
											getAvailableModuleList : true,
											authInfo : Ext.encode(authInfo)
										},
										root : 'data',
										fields : ['name', 'versionrelease',
												'description', 'infopath',
												'basecomponent', {
													name : 'repository',
													convert : function(v) {
														return v.description;
													}
												}, 'changelog'],
										// autoLoad: true,
										sortInfo : {
											field : 'name',
											direction : "ASC"
										},
										listeners : {
											// beforeload: function(){
											// console.log('BEFORELOAD');
											// },
											// load:
											// function(store,records,options){
											// console.log('LOAD');
											// var data = store.reader.jsonData;
											// if(!data.success){
											// Ext.Msg.alert('Freedom Web
											// Installer','Error : ' +
											// data.error);
											// }
											// },
											exception : function() {
												// Not sent ; Should be
												// corrected in
												// following ext releases
												// console.log('EXCEPTION');
											},
											loadexception : function(proxy,
													type, action, options,
													response, arg) {

												// console.log('LOADEXCEPTION',proxy,
												// type, action, options,
												// response);
												// Ext.Msg.alert('Freedom Web
												// Installer','Error when
												// connecting to
												// repositories.');

											}
										}
									});

							var selModel = new Ext.grid.CheckboxSelectionModel(
									{
										header : ''
									});

							var grid = new Ext.grid.GridPanel({
								border : false,
								store : availableStore[currentContext],
								stripeRows : true,
								selModel : selModel,
								loadMask : true,
								tbar : [{
									text : 'Install Selection',
									tooltip : 'Install selected module(s)',
									iconCls : 'x-icon-install',
									handler : function(button, eventObject) {
										var selections = grid
												.getSelectionModel()
												.getSelections();
										var modules = [];
										for (var i = 0; i < selections.length; i++) {
											modules.push(selections[i]
													.get('name'));
										}
										install(modules);
									}
								}, {
									text : 'Refresh',
									tooltip : 'Refresh available module(s)',
									iconCls : 'x-icon-refresh',
									handler : function(button, eventObject) {
										if (availableStore[currentContext]) {
											availableStore[currentContext]
													.load();
										}
									}
								}],
								columns : [selModel, actions, {
											id : 'name',
											header : 'Module',
											dataIndex : 'name',
											width : 140
										}, {
											id : 'available-version',
											header : 'Available Version',
											dataIndex : 'versionrelease'
										}, {
											id : 'description',
											header : 'Description',
											dataIndex : 'description'
										}, {
											id : 'repository',
											header : 'Repository',
											dataIndex : 'repository'
										}],
								autoExpandColumn : 'description',
								autoHeight : true,
								plugins : [actions]
							});

							grid.getStore().on('load',
									function(store, records, options) {

										var recs = [];
										grid.getStore().each(function(rec) {
											if (rec.get('basecomponent') == 'yes') {
												recs.push(rec);
											}
										});
										grid.getSelectionModel().selectRecords(
												recs, true);

										grid.getSelectionModel().on(
												'rowdeselect',
												function(selModel, rowIndex,
														record) {
													if ((record
															.get('basecomponent') == 'yes')) {
														grid
																.getSelectionModel()
																.selectRecords(
																		[record],
																		true);
													}
												});

									});

							grid.getView().emptyText = 'No available modules';

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
			Ext.getCmp('context-list')
					.setActiveTab(Ext.getCmp('context-list').items.last());
		} else {
			if (window.currentContext) {

				var contextArray = Ext.getCmp('context-list').items.items;

				for (var i = 0; i < contextArray.length; i++) {
					if (contextArray[i].title == currentContext) {
						Ext.getCmp('context-list')
								.setActiveTab(contextArray[i]);
					}
				}

			}

		}
	}

}

function updateContextList_failure(responseObject) {
	Ext.Msg.alert('Error', 'Could not retrieve context list');
}

/**
 * upgrade a module
 */
function upgrade(modulelist) {
	mask = new Ext.LoadMask(Ext.getBody(), {
				msg : 'Resolving dependencies...'
			});
	mask.show();

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					'modulelist[]' : modulelist,
					getModuleDependencies : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {
					upgrade_success(responseObject);
				},
				failure : function(responseObject) {
					upgrade_failure(responseObject);
				}
			});
};

function upgrade_success(responseObject) {

	mask.hide();

	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}

	var data = response.data;

	toDownload = data;
	toInstall = data.slice();

	htmlModuleList = '<ul>';
	for (var i = 0; i < toDownload.length; i++) {
		htmlModuleList = htmlModuleList + '<li><b>' + toDownload[i].name
				+ '</b> <i>(' + toDownload[i].versionrelease + ')</i> </li>';
	}
	htmlModuleList = htmlModuleList + '</ul>';

	Ext.Msg.show({
				title : 'Freedom Web Installer',
				msg : 'Installer will install following module(s) : <br/>'
						+ htmlModuleList,
				buttons : {
					ok : true,
					cancel : true
				},
				fn : function(btn) {
					switch (btn) {
						case 'ok' :
							if (toDownload.length > 0) {
								// for (var i = 0; i < toDownload.length; i++) {
								download(toDownload[0], 'upgrade');
								// }
							}
							break;
						case 'cancel' :
							// Do nothing. Will simply close message window.
							break;
					}
				}
			});
}

function upgrade_failure(module, reponseObject) {
	mask.hide();
}

/**
 * remove a module
 */
function remove(module, operation) {

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					module : module.name,
					operation : operation,
					getPhaseList : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {
					remove_success(module, operation, responseObject);
				},
				failure : function(responseObject) {
					remove_failure(module, operation, responseObject);
				}

			});

};

function remove_success(module, operation, responseObject) {
	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}

	var data = response.data;

	currentPhaseList = data;
	currentPhaseIndex = 0;

	executePhaseList(operation);
}

function remove_failure(module, operation, responseObject) {
	Ext.Msg.alert('Error', 'Could not retrieve phase list');
}

/**
 * import a local module
 */
function installLocal(file) {
	mask = new Ext.LoadMask(Ext.getBody(), {
				msg : 'Resolving dependencies...'
			});
	mask.show();

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					file : file,
					getLocalModuleDependencies : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {

					mask.hide();

					Ext.MessageBox.show({
								title : 'Freedom Web Installer',
								msg : 'Execute which scenario for imported module ?',
								buttons : {
									ok : 'Install',
									no : 'Upgrade',
									cancel : 'Cancel'
								},
								fn : function(btn) {

									if (btn == 'ok') {
										install_success(responseObject);
									}
									if (btn == 'no') {
										upgrade_success(responseObject);
									}
									if (btn == 'cancel') {
										Ext.MessageBox.hide();
									}
								},
								icon : Ext.MessageBox.QUESTION
							});

				},
				failure : function(responseObject) {
					install_failure(responseObject);
				}
			})
}

/**
 * install a module
 */
function install(modulelist) {
	mask = new Ext.LoadMask(Ext.getBody(), {
				msg : 'Resolving dependencies...'
			});
	mask.show();

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					'modulelist[]' : modulelist,
					getModuleDependencies : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {
					install_success(responseObject);
				},
				failure : function(responseObject) {
					install_failure(responseObject);
				}
			});

}

function install_success(responseObject) {

	mask.hide();

	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
		return;
	}

	var data = response.data;

	toDownload = data;
	toInstall = data.slice();

	removeList = new Array();
	installList = new Array();

	for (var i = 0; i < toDownload.length; i++) {
		if (toDownload[i].needphase == 'replaced') {
			removeList.push(toDownload[i]);
		} else {
			installList.push(toDownload[i]);
		}
	}

	htmlModuleList = '';
	if (removeList.length > 0) {
		htmlModuleList = htmlModuleList
				+ 'Installer will remove the following module(s):<br/><br/>';
		htmlModuleList = htmlModuleList + '<ul>';
		for (var i = 0; i < removeList.length; i++) {
			htmlModuleList = htmlModuleList + '<li><b>' + removeList[i].name
					+ '</b> <i>(' + removeList[i].versionrelease + ')</i></li>';
		}
		htmlModuleList = htmlModuleList + '</ul>';
		htmlModuleList = htmlModuleList + '<br/><br/>';
	}
	if (installList.length > 0) {
		htmlModuleList = htmlModuleList
				+ 'Installer will install the following module(s):<br/><br/>';
		htmlModuleList = htmlModuleList + '<ul>';
		for (var i = 0; i < installList.length; i++) {
			htmlModuleList = htmlModuleList + '<li><b>' + installList[i].name
					+ '</b> <i>(' + installList[i].versionrelease
					+ ')</i></li>';
		}
		htmlModuleList = htmlModuleList + '</ul>';
		htmlModuleList = htmlModuleList + '<br/><br/>';
	}

	Ext.Msg.show({
				title : 'Freedom Web Installer',
				msg : htmlModuleList,
				buttons : {
					ok : true,
					cancel : true
				},
				fn : function(btn) {
					switch (btn) {
						case 'ok' :
							if (toDownload.length > 0) {
								// for (var i = 0; i < toDownload.length; i++) {
								download(toDownload[0], 'install');
								// }
							}
							break;
						case 'cancel' :
							// Do nothing. Will simply close message window.
							break;
					}
				}
			});

}

function install_failure(responseObject) {
	mask.hide();
}

/**
 * wstop
 */
function wstop(operation) {
	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					wstop : 'yes',
					authInfo : Ext.encode(authInfo)
				},
				callback : function(option, success, responseObject) {

					getGlobalwin();

					if (toInstall[0].needphase == 'replaced') {
						/**
						 * Skip license/param and go directly to phases
						 */
						getPhaseList(toInstall[0], operation);
					} else {
						getLicenseAgreement(toInstall[0], operation);
					}

				}
			});
}

function getGlobalwin() {

	globalwin = new Ext.Window({
				title : 'Freedom Web Installer',
				id : 'module-window',
				layout : 'column',
				resizable : true,
				// height: 400,
				width : 700,
				modal : true
			});

	modulepanel = new Ext.Panel({
				title : 'Module List',
				columnWidth : 0.25,
				height : 422,
				setModuleIcon : function(name, icon) {
					var panel = this.getComponent('module-' + name);
					panel.setIconClass(icon);
				}

			});

	for (var i = 0; i < toInstall.length; i++) {
		var panel = new Ext.Panel({
					title : toInstall[i].name,
					iconCls : 'x-icon-none',
					id : 'module-' + toInstall[i].name,
					border : false,
					style : 'padding:0px;'
				});

		modulepanel.add(panel);
	}

	globalwin.add(modulepanel);

	processpanel = [];

	globalwin.show();

}

/**
 * wstart
 */
function wstart(module, operation) {
	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					wstart : 'yes',
					authInfo : Ext.encode(authInfo)
				},
				callback : function(option, success, responseObject) {

					// Ext.Msg.alert('Freedom Web Installer','Module <b>' +
					// module.name
					// + '</b> installed successfully', function(){
					// If applicable, start installing next module in list
					if (toInstall[0]) {
						if (toInstall[0].needphase == 'replaced') {
							/**
							 * Skip parameter prompt and perform the replacement
							 * processes
							 */
							getPhaseList(toInstall[0], operation);
						} else {
							askParameter(toInstall[0], operation);
						}
					} else {
						Ext.Msg.alert('Freedom Web Installer',
								'Install successful', function() {
									installedStore[currentContext].load();
									availableStore[currentContext].load();
									globalwin.close();
								});
					}
					// })

					// The end
				}
			});
}

/**
 * download a module
 */
function download(module, operation) {

	if (module.status != 'downloaded' && module.needphase != 'replaced') {
		mask = new Ext.LoadMask(Ext.getBody(), {
					msg : 'Downloading "' + module.name + '"...'
				});

		mask.show();

		Ext.Ajax.request({
					url : 'wiff.php',
					params : {
						context : currentContext,
						module : module.name,
						download : true,
						authInfo : Ext.encode(authInfo)
					},
					success : function(responseObject) {
						download_success(module, operation, responseObject);
					},
					failure : function(responseObject) {
						download_failure(module, operation, responseObject);
					}
				});
	} else {
		download_success(module, operation);
	}

}

function download_success(module, operation, responseObject) {
	toDownload.remove(module);
	if (toDownload.length > 0) {
		download(toDownload[0], operation);
	} else {
		mask.hide();
		wstop(operation);
	}
}

function download_failure(module, operation, responseObject) {
}

/**
 * ask parameter
 */
function askParameter(module, operation) {

	if (operation == 'parameter') {
		getGlobalwin();
	}

	modulepanel.setModuleIcon(module.name, 'x-icon-loading');

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					module : module.name,
					operation : operation,
					getParameterList : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {
					askParameter_success(module, operation, responseObject);
				},
				failure : function(responseObject) {
					askParameter_failure(module, operation, responseObject);
				}
			})

}

function askParameter_success(module, operation, responseObject) {

	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}

	var data = response.data;

	if (data.length > 0) {

		module.hasParameter = true;

		var form = new Ext.form.FormPanel({
					id : 'parameter-panel',
					labelWidth : 200,
					border : false,
					frame : true,
					bodyStyle : 'padding:15px;',
					monitorValid : true,
					autoHeight : true,
					buttons : [{
						text : 'Save Parameters',
						formBind : true,
						handler : function() {

							form = Ext.getCmp('parameter-panel').getForm();

							form.submit({
										url : 'wiff.php',
										success : function(form, action) {
											Ext.getCmp('parameter-window')
													.close();
											getPhaseList(module, operation);
										},
										failure : function(form, action) {
											Ext.Msg.alert('Failure',
													action.result.error);
										},
										params : {
											context : currentContext,
											module : module.name,
											operation : operation,
											storeParameter : true
										},
										waitMsg : 'Saving Parameters...'
									});

						}
					}, {
						text : 'Cancel',
						handler : function() {

							Ext.getCmp('parameter-window').close();

						}
					}]

				});

		for (var i = 0; i < data.length; i++) {

			if (data[i].type == 'text') {

				form.add({
							xtype : 'textfield',
							name : data[i].name,
							fieldLabel : data[i].label,
							value : data[i].value
									? data[i].value
									: data[i]['default'],
							allowBlank : data[i].needed != 'Y' ? true : false,
							anchor : '-15'
						});

			}

			if (data[i].type == 'enum') {

				var values = data[i].values.split('|');

				var valuesData = [];

				for (var i = 0; i < values.length; i++) {
					valuesData.push([values[i]]);
				}

				form.add({
							xtype : 'combo',
							name : data[i].name,
							fieldLabel : data[i].label,
							editable : false,
							disableKeyFilter : true,
							forceSelection : true,
							value : data[i]['default'],
							triggerAction : 'all',

							mode : 'local',

							store : new Ext.data.SimpleStore({
										fields : ['value'],
										data : valuesData
									}),

							valueField : 'value',
							displayField : 'value',

							anchor : '-15'

						});

			}

		}

		var parameterWindow = new Ext.Window({
					title : 'Parameters for ' + module.name,
					id : 'parameter-window',
					modal : true,
					layout : 'fit',
					width : 400,
					autoHeight : true
				});

		parameterWindow.add(form);

		parameterWindow.show();

	} else {
		if (operation == 'install' || operation == 'upgrade') {
			getPhaseList(module, operation);
		}

	}
}

function askParameter_failure(module, operation, responseObject) {
}

/**
 * License agreement
 */
function getLicenseAgreement(module, operation) {
	license = module.license;
	if (license != '') {
		Ext.Ajax.request({
					'url' : 'wiff.php',
					'params' : {
						'getLicenseAgreement' : true,
						'context' : currentContext,
						'module' : module.name,
						'operation' : operation,
						'license' : module.license
					},
					'success' : function(responseObject) {
						getLicenseAgreement_success(module, operation,
								responseObject);
					},
					'failure' : function(responseObject) {
						getLicenseAgreement_failure(module, operation,
								responseObject);
					}
				});
	} else {
		askParameter(module, operation);
	}
}

function getLicenseAgreement_success(module, operation, responseObject) {
	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}

	var data = response.data;

	if (data.agree == 'yes' || data.license == '') {
		askParameter(module, operation);
	} else {
		var licenseText = Ext.util.Format.htmlEncode(data.license);

		var headerPanel = new Ext.Panel({
					bodyStyle : 'padding-bottom:10px;text-align:center',
					html : '<p>License agreement for "'
							+ Ext.util.Format.htmlEncode(module.name)
							+ '"</p><p>License: "'
							+ Ext.util.Format.htmlEncode(module.license) + '"'
				});

		var licensePanel = new Ext.Panel({
			border : false,
			bodyStyle : 'padding-bottom:10px;',
			autoScroll : true,
			flex : 1,
			items : [new Ext.Panel({
						html : '<pre style="color: black; background-color: white;">'
								+ licenseText + '</pre>'
					})]
		});

		var licenseAsk = new Ext.form.FormPanel({
					id : 'license-formpanel',
					border : false,
					frame : true,
					bodyStyle : 'padding:15px;',
					monitorValid : true,
					layout : 'vbox',
					items : [headerPanel, licensePanel],
					buttons : [{
								text : 'Yes',
								handler : function() {
									storeLicenseAgreement(module, operation,
											'yes');
									// askParameter(module, operation);
								}
							}, {
								text : 'No',
								handler : function() {
									licenseWin.close();
									if (Ext.getCmp('module-window')) {
										Ext.getCmp('module-window').close();
									}
								}
							}]
				});

		var licenseWin = new Ext.Window({
					id : 'license-window',
					items : [licenseAsk],
					height : 400,
					width : 600,
					modal : true,
					closable : false,
					layout : 'fit'
				});

		licenseWin.show();
	}
}

function getLicenseAgreement_failure(module, operation, responseObject) {
	alert('KABOOM');
}

function storeLicenseAgreement(module, operation, agree) {
	Ext.Ajax.request({
				'url' : 'wiff.php',
				'params' : {
					'storeLicenseAgreement' : true,
					'context' : currentContext,
					'module' : module.name,
					'license' : module.license,
					'agree' : agree
				},
				'success' : function(responseObject) {
					storeLicenseAgreement_success(module, operation,
							responseObject);
				},
				'failure' : function(responseObject) {
					storeLicenseAgreement_failure(module, operation,
							responseObject);
				}
			});
}

function storeLicenseAgreement_success(module, operation, responseObject) {
	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}

	if (Ext.getCmp('license-window')) {
		Ext.getCmp('license-window').close();
	}

	askParameter(module, operation);
}

function storeLicenseAgreement_failure(module, operation, responseObject) {
	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}
}

/**
 * get phase list
 */
function getPhaseList(module, operation) {

	currentModule = module;

	localOperation = operation;
	if (currentModule.needphase == 'upgrade') {
		// This module replaces other modules
		// so, we force the operation to 'upgrade'
		localOperation = 'upgrade';
	}
	if (currentModule.needphase == 'replaced') {
		// This module is replaced by another module
		// so, we mark it for replacement
		localOperation = 'replaced';
	}

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					module : module.name,
					operation : localOperation,
					getPhaseList : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {
					getPhaseList_success(module, operation, responseObject);
				},
				failure : function(responseObject) {
					getPhaseList_failure(module, operation, responseObject);
				}

			});

}

function getPhaseList_success(module, operation, responseObject) {

	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
		Ext.Msg.alert('Server Error', response.error);
	}

	var data = response.data;

	currentPhaseList = data;
	currentPhaseIndex = 0;

	executePhaseList(operation);
}

function getPhaseList_failure(module, operation, responseObject) {
	Ext.Msg.alert('Error', 'Could not retrieve phase list');
}

/**
 * execute phase list
 */
function executePhaseList(operation) {

	var module = currentModule;

	var phase = currentPhaseList[currentPhaseIndex];

	if (!phase) {
		// Remove first module to install
		toInstall.remove(toInstall[0]);

		setModuleStatusInstalled(module, operation);

		return;
	}

	switch (phase) {

		case 'unpack' :

			Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					module : module.name,
					unpack : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {

					var response = eval('(' + responseObject.responseText + ')');
					if (response.error) {
						Ext.Msg.alert('Server Error', response.error);
					}

					var data = response.data;

					// Ext.Msg.alert('Module Unpack', 'Module <b>' + module.name
					// + '</b> unpacked successfully in context directory',
					// function(btn){
					currentPhaseIndex++;
					executePhaseList(operation);
					// });

				}
			});

			break;

		// HERE HERE HERE
		case 'clean-unpack' :
			Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					module : module.name,
					cleanUnpack : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {
					var response = eval('(' + responseObject.responseText + ')');
					if (response.error) {
						Ext.Msg.alert('Server Error', response.error);
					}
					var data = response.data;
					currentPhaseIndex++;
					executePhaseList(operation);
				}
			});
			break;

		case 'unregister-module' :

			Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					module : module.name,
					unregisterModule : true
				},
				success : function(responseObject) {
					var response = eval('(' + responseObject.responseText + ')');
					if (response.error) {
						Ext.Msg.alert('Server Error', response.error);
					}
					var data = response.data;
					currentPhaseIndex++;
					executePhaseList(operation);
				}
			});
			break;

		case 'purge-unreferenced-parameters-value' :
			Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					purgeUnreferencedParametersValue : true
				},
				success : function(responseObject) {
					var response = eval('(' + responseObject.responseText + ')');
					if (response.error) {
						Ext.Msg.alert('Server Error', response.error);
					}
					var data = response.data;
					currentPhaseIndex++;
					executePhaseList(operation);
				}
			});
			break;

		default :

			Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					context : currentContext,
					module : module.name,
					operation : operation,
					phase : phase,
					getProcessList : true,
					authInfo : Ext.encode(authInfo)
				},
				success : function(responseObject) {

					var response = eval('(' + responseObject.responseText + ')');
					if (response.error) {
						Ext.Msg.alert('Server Error', response.error);
					}

					var data = response.data;

					// processpanel = null;

					currentProcessList = data;
					executeProcessList(currentModule, phase, operation);

				}
			});

			break;
	}

}

function executeProcessList(module, phase, operation) {

	processList = currentProcessList;

	currentPhase = phase;

	if (processList.length != 0) {

		if (!processpanel) {
			processpanel = {};
		}

		if (!processpanel[module.name]) {

			var toolbar = new Ext.Toolbar({});

			processpanel[module.name] = new Ext.Panel({
						height : 400,
						columnWidth : 0.75,
						bbar : toolbar,
						bodyStyle : 'overflow:auto;'
					});

			processpanel[module.name].processbutton = new Ext.Button({
						text : 'Continue',
						disabled : true,
						handler : function(button, event) {
							processpanel[module.name].statustext.show();
							processpanel[module.name].processbutton.disable();
							processpanel[module.name].retrybutton.disable();
							processpanel[module.name].parambutton.disable();
							modulepanel.setModuleIcon(module.name,
									'x-icon-loading');
							processList[process].executed = true;
							executeProcessList(module, currentPhase, operation);
						}
					});

			processpanel[module.name].statustext = new Ext.Toolbar.TextItem({
				text : 'Processing...',
				style : "background-image:url(javascript/lib/ext/resources/images/default/grid/loading.gif);background-repeat:no-repeat;line-height:14px;padding-left:18px;"
			});

			processpanel[module.name].retrybutton = new Ext.Button({
						text : 'Retry',
						disabled : true,
						handler : function(button, event) {
							processpanel[module.name].statustext.show();
							processpanel[module.name].processbutton.disable();
							processpanel[module.name].retrybutton.disable();
							processpanel[module.name].parambutton.disable();
							modulepanel.setModuleIcon(module.name,
									'x-icon-loading');
							executeProcessList(module, currentPhase, operation);
						}
					});

			processpanel[module.name].ignorebutton = new Ext.Button({
				text : 'Ignore',
				hidden : true,
				disabled : true,
				handler : function(button, event) {
					Ext.Msg.show({

						title : 'Freedom Web Installer',
						msg : 'Incorrect process execution will cause problems in your freedom context',

						buttons : {
							ok : 'Continue',
							cancel : 'Cancel'
						},

						icon : Ext.MessageBox.WARNING,

						fn : function(buttonId) {
							switch (buttonId) {
								case 'ok' :
									modulepanel.setModuleIcon(module.name,
											'x-icon-loading');
									processList[process].executed = true;
									executeProcessList(module, currentPhase,
											operation);
									break;
								case 'cancel' :
									break;
							}
						}

					});

				}
			});

			processpanel[module.name].parambutton = new Ext.Button({
						text : 'Parameters',
						disabled : true,
						handler : function(button, event) {
							askParameter(module, operation);
						}
					});

			toolbar.add(processpanel[module.name].retrybutton);
			toolbar.add(processpanel[module.name].parambutton);
			toolbar.add(new Ext.Toolbar.Fill());
			toolbar.add(processpanel[module.name].statustext);
			toolbar.add(processpanel[module.name].processbutton);
			toolbar.add(processpanel[module.name].ignorebutton);

			globalwin.add(processpanel[module.name]);

		}

		globalwin.doLayout();

		var module = module;

		if (!processpanel[module.name].titlepanel) {
			processpanel[module.name].titlepanel = [];
		}

		if (!processpanel[module.name].titlepanel[phase])
			processpanel[module.name].titlepanel[phase] = processpanel[module.name]
					.add(new Ext.Panel({
								title : '<i>Executing ' + phase + ' for '
										+ module.name + '</i>',
								border : false
							}));

		for (var i = 0; i < processList.length; i++) {

			if (i == (processList.length - 1) && processList[i].executed) {
				// if there is no process to execute in this phase go on to next
				// phase.
				currentPhaseIndex++;
				executePhaseList(operation);
				return;
			}

			process = i;

			if (!processList[i].executed) {
				break;
			}

		}

		Ext.Ajax.request({
			url : 'wiff.php',
			params : {
				context : currentContext,
				module : module.name,
				operation : operation,
				phase : phase,
				process : process + '',
				execute : true,
				authInfo : Ext.encode(authInfo)
			},
			callback : function(options, serverSuccess, responseObject) {

				if (serverSuccess) {

					var response = eval('(' + responseObject.responseText + ')');

					var data = response.data;

					var success = response.success;

					var help = (!response.success)
							? processList[process].help
							: '';

					var html = response.error ? '<pre class="console">'
							+ response.error + '</pre>' : '';
					html += help ? '<p class="help">' + help + '</p>' : '';

				} else {

					var success = false;

					var help = 'Request failed : ' + responseObject.status
							+ ' - ' + responseObject.statusText;

					var html = help ? '<p class="help">' + help + '</p>' : '';

				}

				var optional = processList[process].attributes.optional == 'yes'
						? true
						: false;

				var getLabel = function(process, rank) {

					var label = '';

					if (process.label) {
						label = process.label;
					} else if (process.name && process.name == 'check') {

						label = 'Check';

						if (process.attributes.type) {
							if (process.attributes.type == 'syscommand') {
								label += ' system command';
							} else if (process.attributes.type == 'phpfunction') {
								label += ' php function';
							} else if (process.attributes.type == 'pearmodule') {
								label += ' pear module';
							} else if (process.attributes.type == 'apachemodule') {
								label += ' apache module';
							}

							else {
								label += ' ' + process.attributes.type;
							}
						}

						if (process.attributes['function']) {
							label += ' ' + process.attributes['function'];
						}

						if (process.attributes.command) {
							label += ' ' + process.attributes.command;
						}

						if (process.attributes['class']) {
							label += ' ' + process.attributes['class'];
						}

						if (process.attributes.module) {
							label += ' ' + process.attributes.module;
						}

					} else if (process.attributes.command) {
						label = 'Command ' + process.attributes.command;
					} else {
						label = 'Process ' + rank;
					}

					return label;
				}

				var label = getLabel(processList[process], process);

				iconCls = success ? 'x-icon-ok' : optional
						? 'x-icon-warning'
						: 'x-icon-ko';

				var panel = new Ext.Panel({
							collapsible : help || response.error,
							collapsed : success,
							title : label,
							iconCls : iconCls,
							html : html,
							border : false,
							style : 'padding:0px;'
						});

				processpanel[module.name].add(panel);

				if (process == processList.length - 1
						&& currentPhaseList.length - 1 == currentPhaseIndex) {
					modulepanel.setModuleIcon(module.name, 'x-icon-ok');
				}

				if (!success && !optional) {
					modulepanel.setModuleIcon(module.name, 'x-icon-ko');
				}

				processpanel[module.name].doLayout();

				// Autoscroll down.
				var div = processpanel[module.name].body.dom;
				div.scrollTop = div.scrollHeight;

				if (process == processList.length - 1 && success && !optional) {

					processpanel[module.name].ignorebutton.disable();
					processpanel[module.name].ignorebutton.hide();

					// Auto-continue
					processpanel[module.name].statustext.show();
					processpanel[module.name].processbutton.disable();
					processpanel[module.name].retrybutton.disable();
					processpanel[module.name].parambutton.disable();

					currentPhaseIndex++;
					executePhaseList(operation);
					return;
				}

				// if (success || optional) {
				if (success) {
					processList[process].executed = true;
					executeProcessList(module, phase, operation);
					return;
				}

				if (!success && !optional) {
					processpanel[module.name].processbutton.hide();
					processpanel[module.name].processbutton.disable();
					processpanel[module.name].retrybutton.show();
					processpanel[module.name].retrybutton.enable();
					processpanel[module.name].parambutton.show();
					if (module.hasParameter) {
						processpanel[module.name].parambutton.enable();
					}
					processpanel[module.name].statustext.hide();
					processpanel[module.name].ignorebutton.enable();
					processpanel[module.name].ignorebutton.show();

				}

				if (!success && optional) {
					processpanel[module.name].retrybutton.show();
					processpanel[module.name].retrybutton.enable();
					processpanel[module.name].parambutton.show();
					if (module.hasParameter) {
						processpanel[module.name].parambutton.enable();
					}
					processpanel[module.name].processbutton.show();
					processpanel[module.name].processbutton.enable();
					processpanel[module.name].ignorebutton.disable();
					processpanel[module.name].ignorebutton.hide();
					processpanel[module.name].statustext.hide();

				}

			}

		});

	} else {
		// if there is no process to execute in this phase go on to next phase.
		currentPhaseIndex++;
		executePhaseList(operation);
	}

}

function setModuleStatusInstalled(module, operation) {

	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					setStatus : true,
					context : currentContext,
					module : module.name,
					status : 'installed',
					errorstatus : '',
					operation : operation,
					authInfo : Ext.encode(authInfo)
				},
				callback : function(option, success, responseObject) {

					// Phase execution is over
					// Proceed to next module to install
					// installedStore[currentContext].load();
					// availableStore[currentContext].load();

					// Hide process panel in global window if applicable
					if (processpanel[module.name]) {
						processpanel[module.name].hide();
					}

					// Set proper icon
					modulepanel.setModuleIcon(module.name, 'x-icon-ok');

					wstart(module, operation);
				}
			});
}

function setRepoValidityIconLabel(repoName, repoIconId, repoLabelId) {
	Ext.Ajax.request({
		'url' : 'wiff.php',
		'params' : {
			'checkRepoValidity' : true,
			'name' : repoName
		},
		'cb_data' : {
			'name' : repoName,
			'icon' : repoIconId,
			'label' : repoLabelId
		},
		'success' : function(responseObject, requestObject) {
			var response = eval('(' + responseObject.responseText + ')');

			var repoName = requestObject.cb_data['name'];
			var repoIconId = requestObject.cb_data['icon'];
			var repoLabelId = requestObject.cb_data['label'];

			var icon = Ext.get(repoIconId);
			var label = Ext.get(repoLabelId);

			if (icon) {
				if (response.success) {
					if (response.data['valid']) {
						icon.dom.setAttribute('src', 'images/icons/accept.png');
						if (label) {
							label.dom.innerHTML = Ext.util.Format
									.htmlEncode(response.data['label']);
						}
					} else {
						icon.dom.setAttribute('src', 'images/icons/error.png');
						icon.dom.setAttribute('title', 'Repository ' + repoName
										+ ' is not accessible or invalid.');
					}
				} else {
					icon.dom
							.setAttribute('src', 'images/icons/exclamation.png');
					icon.dom.setAttribute('title',
							'Server could not check validity of repository '
									+ repoName);
				}
			}
		},
		'failure' : function(responseObject) {
			var icon = Ext.get(repoIconId);
			if (icon) {
				icon.setAttribute('src', 'images/icons/exclamation.png');
				icon.setAttribute('title', 'Request failed!');
			}
		}
	});
}

Ext.onReady(function() {

	Ext.BLANK_IMAGE_URL = 'javascript/lib/ext/resources/images/default/s.gif';
	Ext.QuickTips.init();

	Ext.Ajax.timeout = 3600000;

	checkPasswordFile();

	// Update Available Test
	needUpdate = false;
	Ext.Ajax.request({
				url : 'wiff.php',
				params : {
					needUpdate : true
				},
				success : function(responseObject) {
					var response = eval('(' + responseObject.responseText + ')');
					if (response.error) {
						Ext.Msg.alert('Server Error', response.error);
					} else {
						if (response.data) {
							needUpdate = true;
							Ext.Msg
									.confirm(
											'Freedom Web Installer',
											'Update available for Installer. Update now ?',
											function(btn) {
												if (btn == 'yes') {
													updateWIFF();
												}
											});
						}
					}

				},
				failure : function(responseObject) {

				}

			});
	// EO Update Available Test //

	view = new Ext.Viewport({
		layout : 'fit',
		items : [{
			xtype : 'grouptabpanel',
			id : 'group-tab-panel',
			tabWidth : 160,
			activeGroup : 0,
			items : [{
				mainItem : 0,
				items : [{
					title : 'Freedom <br/> Web Installer',
					html : "<div style='padding:30px;'><h1 style='margin-bottom:30px;font-size:large;'>Welcome to Web Installer for Freedom (WIFF)</h1>"
							+ "<p style='margin-bottom:30px;'>If you need help, follow this link to documentation wiki. Subscriptions and contributions are much appreciated.</p>"
							+ "<ul style='margin-left:30px;list-style-type: square;' >"
							+ "<li><a href='http://www.freedom-ecm.org/wiff' target='_blank'><h2>Documentation</h2></a></li>"
							+ "</ul></div>"

				}, {
					title : 'Setup',
					iconCls : 'x-icon-setup',
					tabTip : 'Setup WIFF',
					layout : 'fit',
					style : 'padding:10px;',
					items : [{
						title : 'Setup',
						iconCls : 'x-icon-setup',
						bodyStyle : 'overflow:auto;',
						items : [{
							title : 'WIFF Information',
							style : 'padding:10px;font-size:small;',
							bodyStyle : 'padding:5px;',
							listeners : {
								render : function(panel) {

									var currentVersion = null;
									var availableVersion = null;

									var displayInfo = function() {
										if (currentVersion && availableVersion) {
											var html = '<ul><li class="x-form-item"><b>Current Version :</b> '
													+ currentVersion
													+ '</li><li class="x-form-item"><b>Available Version :</b> '
													+ availableVersion
													+ '</li></ul>'
											panel.body.update(html);
										}
									};

									Ext.Ajax.request({
										url : 'wiff.php',
										params : {
											version : true
										},
										success : function(responseObject) {
											var response = eval('('
													+ responseObject.responseText
													+ ')');
											if (response.error) {
												Ext.Msg.alert('Server Error',
														response.error);
											}
											currentVersion = response.data;
											displayInfo();
										},
										failure : function(responseObject) {

										}

									});

									Ext.Ajax.request({
										url : 'wiff.php',
										params : {
											availVersion : true
										},
										success : function(responseObject) {
											var response = eval('('
													+ responseObject.responseText
													+ ')');
											if (response.error) {
												Ext.Msg.alert('Server Error',
														response.error);
											}
											availableVersion = response.data;
											displayInfo();
										},
										failure : function(responseObject) {

										}
									});

								}
							},
							tbar : [{
										xtype : 'button',
										text : 'Update',
										iconCls : 'x-icon-wiff-update',
										handler : function(b, e) {
											updateWIFF();
										},
										disabled : true,
										listeners : {
											render : function(button) {
												if (needUpdate) {
													this.enable();
												}
											}
										}
									}, {
										xtype : 'button',
										text : 'Password',
										iconCls : 'x-icon-wiff-password',
										handler : function(b, e) {
											displayPasswordWindow(true);
										},
										listeners : {
											render : function(button) {
												if (needUpdate) {
													this.enable();
												}
											}
										}
									}]
						}, {
							title : 'Debug',
							style : 'padding:10px;padding-top:0px;font-size:small;',
							listeners : {
								render : function(panel) {

								}
							},
							tbar : [{
								text : 'Debug Mode OFF',
								enableToggle : true,
								iconCls : 'x-icon-debug',
								disabled : true,
								listeners : {
									render : function(button) {

										Ext.Ajax.request({
											url : 'wiff.php',
											params : {
												getParam : true,
												paramName : 'debug'
											},
											success : function(responseObject) {

												var response = eval('('
														+ responseObject.responseText
														+ ')');
												if (response.error) {
													Ext.Msg.alert(
															'Server Error',
															response.error);
												} else {
													if (response.data == 'yes') {
														button
																.setText('Debug Mode ON');
														button.toggle();
													} else {
														button
																.setText('Debug Mode OFF');
													}
													button.enable();
												}

											},
											failure : function(responseObject) {

											}

										});

									}
								},
								toggleHandler : function(button, state) {
									if (state) {

										button.setText('Debug Mode ON');

										Ext.Ajax.request({
											url : 'wiff.php',
											params : {
												setParam : true,
												paramName : 'debug',
												paramValue : 'yes'
											},
											success : function(responseObject) {

												var response = eval('('
														+ responseObject.responseText
														+ ')');
												if (response.error) {
													Ext.Msg.alert(
															'Server Error',
															response.error);
												} else {
												}

											},
											failure : function(responseObject) {

											}

										});
									} else {

										button.setText('Debug Mode OFF');

										Ext.Ajax.request({
											url : 'wiff.php',
											params : {
												setParam : true,
												paramName : 'debug',
												paramValue : 'no'
											},
											success : function(responseObject) {

												var response = eval('('
														+ responseObject.responseText
														+ ')');
												if (response.error) {
													Ext.Msg.alert(
															'Server Error',
															response.error);
												} else {
												}

											},
											failure : function(responseObject) {

											}

										});
									}
								}
							}]
						}, {
							title : 'Repositories',
							style : 'padding:10px;padding-top:0px;font-size:small;',
							listeners : {
								render : function(panel) {

									repoStore = new Ext.data.JsonStore({
												url : 'wiff.php',
												baseParams : {
													getRepoList : true
												},
												root : 'data',
												fields : ['name', 'baseurl',
														'description',
														'protocol', 'host',
														'path', 'default',
														'url', 'authentified',
														'login', 'password',
														'displayUrl', 'label'],
												autoLoad : true
											});

									var actions = new Ext.ux.grid.RowActions({
												header : '',
												autoWidth : false,
												width : 44,
												actions : [{
															iconCls : 'x-icon-setup',
															tooltip : 'Modify'
														}, {
															iconCls : 'x-icon-cross',
															tooltip : 'Remove'
														}]
											});

									actions.on({
										action : function(grid, record, action,
												row, col) {

											var repositoryName = record
													.get('name');

											switch (action) {
												case 'x-icon-cross' :

													Ext.Msg
															.confirm(
																	'Freedom Web Installer',
																	'Delete repository <b>'
																			+ repositoryName
																			+ '</b> ?',
																	function(
																			btn) {
																		if (btn == 'yes') {

																			mask = new Ext.LoadMask(
																					Ext
																							.getBody(),
																					{
																						msg : 'Deleting...'
																					});
																			mask
																					.show();

																			Ext.Ajax
																					.request(
																							{
																								url : 'wiff.php',
																								params : {
																									deleteRepo : true,
																									name : repositoryName
																								},
																								success : function(
																										responseObject) {

																									mask
																											.hide();

																									var response = eval('('
																											+ responseObject.responseText
																											+ ')');
																									if (response.error) {
																										Ext.Msg
																												.alert(
																														'Server Error',
																														response.error);
																									} else {
																										// Ext.Msg.alert('Freedom
																										// Web
																										// Installer',
																										// 'Delete
																										// successful.',
																										// function(btn){
																										grid
																												.getStore()
																												.reload();
																										Ext
																												.getCmp('create-context-form')
																												.fireEvent(
																														'render',
																														Ext
																																.getCmp('create-context-form'));
																										// });

																									}

																								},
																								failure : function(
																										responseObject) {

																								}

																							});
																		}
																	});

													break;

												case 'x-icon-setup' :

													var win = displayRepositoryWindow(
															grid, record);

													win.show();

													// Ext.Msg.alert('Freedom
													// Web Installer', 'Modify
													// Repository ' +
													// repositoryName);

													break;
											}

										}
									});

									var grid = new Ext.grid.GridPanel({
										border : false,
										store : repoStore,
										stripeRows : true,
										loadMask : true,
										tbar : [{
											text : 'Add Repository',
											tooltip : 'Add a new available repository for context(s)',
											iconCls : 'x-icon-install',
											handler : function(button,
													eventObject) {

												var win = displayRepositoryWindow(grid);

												win.show();

											}
										}],
										columns : [actions, {
													id : 'name',
													header : 'Repository',
													dataIndex : 'name',
													width : 140
												}, {
													id : 'label',
													header : 'Label',
													dataIndex : 'label',
													width : 140
												}, {
													id : 'description',
													header : 'Description',
													dataIndex : 'description'
												}, {
													id : 'url',
													header : 'Url',
													dataIndex : 'displayUrl',
													width : 400
												}],
										autoExpandColumn : 'description',
										autoHeight : true,
										plugins : [actions]
									});

									grid.getView().emptyText = 'No defined repositories';

									panel.add(grid);

								}
							}
						}, new Ext.ux.MediaPanel({
							title : 'PHP Info',
							style : 'padding:10px;padding-top:0px;font-size:small;overflow:auto;',
							height : 400,
							collapsible : true,
							collapsed : true,
							iconCls : 'x-icon-php',
							mediaCfg : {
								mediaType : 'HTM',
								url : 'wiff.php?phpInfo=true',
								style : {
									display : 'inline',
									width : '100px',
									height : '80px'
								},
								params : {
									wmode : 'opaque',
									scale : 'exactfit',
									salign : 't'
								}
							}
						})]

					}]

				}, {
					title : 'Create Context',
					iconCls : 'x-icon-create',
					tabTip : 'Create new context',
					style : 'padding:10px',
					layout : 'column',
					items : [{
						xtype : 'form',
						id : 'create-context-form',
						columnWidth : 1,
						bodyStyle : 'padding:10px',
						frame : true,
						title : 'Create New Context',
						items : [{
									xtype : 'textfield',
									fieldLabel : 'Name',
									name : 'name',
									anchor : '-15'
								}, {
									xtype : 'textfield',
									fieldLabel : 'Root',
									name : 'root',
									anchor : '-15'
								}, {
									xtype : 'textarea',
									fieldLabel : 'Description',
									name : 'desc',
									anchor : '-15'
								}, {
									xtype : 'textfield',
									fieldLabel : 'Url',
									name : 'url',
									anchor : '-15'
								}],

						buttons : [{
							text : 'Create',
							handler : function() {
								Ext.getCmp('create-context-form').getForm()
										.submit({
											url : 'wiff.php',
											success : function(form, action) {
												updateContextList('select-last');
												form.reset();
												var panel = Ext
														.getCmp('create-context-form');
												panel
														.fireEvent('render',
																panel);
											},
											failure : function(form, action) {
												if (action && action.result) {
													Ext.Msg
															.alert(
																	'Failure',
																	action.result.error);
												} else {
													Ext.Msg
															.alert('Failure',
																	'Select at least one repository.');
												}
											},
											params : {
												createContext : true
											},
											waitMsg : 'Creating Context...'
										})
							}
						}],
						listeners : {
							render : function(panel) {

								repoStore = new Ext.data.JsonStore({
											url : 'wiff.php',
											baseParams : {
												getRepoList : true
											},
											root : 'data',
											fields : ['name', 'baseurl',
													'description', 'protocol',
													'host', 'path', 'url',
													'authentified', 'login',
													'password', 'displayUrl',
													'label', 'default'],
											autoLoad : true
										});

								repoBoxList = new Array();

								repoStore.on('load', function() {

									repoStore.each(function(record) {

										repoBoxList.push({
											boxLabel : '<b>'
													+ record.get('label')
													+ '</b>'
													+ (record
															.get('description')
															? ' <i>('
																	+ record
																			.get('description')
																	+ ')</i>'
															: ''),
											name : 'repo-' + record.get('name'),
											checked : (record.get('default') == 'yes')
													? true
													: false
										});

									});

									panel.remove(panel.checkBoxGroup);

									panel.checkBoxGroup = new Ext.form.CheckboxGroup(
											{
												fieldLabel : 'Repositories',
												allowBlank : false,
												blankText : "You must select at least one repository.",
												columns : 1,
												items : repoBoxList
											});

									panel.checkBoxGroup = panel
											.add(panel.checkBoxGroup);
									panel.doLayout();

								});

							}
						}
					}]
				}]
			}, {
				mainItem : 0,
				id : 'context-list',
				items : [{
							id : 'context-list-title',
							title : 'Context List',
							iconCls : 'x-icon-list'
						}]
			}, {
				mainItem : 0,
				id : 'archive-list',
				items : [{
							id : 'archive-list-title',
							title : 'Archive List',
							iconCls : 'x-icon-list'
						}]
			}]
		}]

	});

	updateContextList();

	updateArchiveList();

});