/**
 * @author Clément Laballe
 */
Ext.onReady(function(){

    Ext.BLANK_IMAGE_URL = 'javascript/lib/ext/resources/images/default/s.gif';
    Ext.QuickTips.init();
	
	installedStore = {};
	availableStore = {};
	
	function reloadModuleStore()
	{
		if(installedStore[currentContext])
		{
		installedStore[currentContext].load();
		}
		if(availableStore[currentContext])
		{
		availableStore[currentContext].load();
		}
	}
    
    var view = new Ext.Viewport({
        layout: 'fit',
        items: [{
            xtype: 'grouptabpanel',
            id: 'group-tab-panel',
            tabWidth: 160,
            activeGroup: 0,
            items: [{
                mainItem: 0,
                items: [{
                    title: 'Freedom <br/> Web Installer'
                }, 
//				{
//                    title: 'Parameters',
//                    iconCls: 'x-icon-setup',
//                    tabTip: 'Set WIFF parameters',
//                    layout: 'fit',
//                    style: 'padding:10px;',
//                    items: []
//                },
				 {
                    title: 'Create Context',
                    iconCls: 'x-icon-create',
                    tabTip: 'Create new context',
                    style: 'padding:10px',
                    items: [{
                        xtype: 'form',
                        id: 'create-context-form',
                        width: '600px',
                        bodyStyle: 'padding:10px',
                        frame: true,
                        title: 'Create New Context',
                        items: [{
                            xtype: 'textfield',
                            fieldLabel: 'Name',
                            name: 'name',
                            anchor: '-15',
                        }, {
                            xtype: 'textfield',
                            fieldLabel: 'Root',
                            name: 'root',
                            anchor: '-15'
                        }, {
                            xtype: 'textarea',
                            fieldLabel: 'Description',
                            name: 'desc',
                            anchor: '-15'
                        }],
                        
                        buttons: [{
                            text: 'Create',
                            handler: function(){
                                Ext.getCmp('create-context-form').getForm().submit({
                                    url: 'wiff.php',
                                    success: function(form, action){
                                        updateContextList();
                                        form.reset();
                                    },
                                    failure: function(form, action){
                                        Ext.Msg.alert('Failure', action.result.error);
                                    },
                                    params: {
                                        createContext: true
                                    },
                                    waitMsg: 'Creating Context...'
                                })
                            }
                        }],
                        listeners: {
                            render: function(panel){
                            
                                repoStore = new Ext.data.JsonStore({
                                    url: 'wiff.php',
                                    baseParams: {
                                        getRepoList: true
                                    },
                                    root: 'data',
                                    fields: ['name', 'baseurl', 'description'],
                                    autoLoad: true
                                });
                                
                                repoBoxList = new Array();
                                
                                repoStore.on('load', function(){
                                
                                    repoStore.each(function(record){
                                    
                                        repoBoxList.push({
                                            boxLabel: record.get('description') + ' <i>(' + record.get('baseurl') + ')</i>',
                                            name: 'repo-' + record.get('name')
                                        });
                                        
                                    });
                                    
                                    var checkBoxGroup = new Ext.form.CheckboxGroup({
                                        fieldLabel: 'Repositories',
                                        columns: 1,
                                        items: repoBoxList
                                    });
                                    
                                    panel.add(checkBoxGroup);
                                    panel.doLayout();
                                    
                                });
                                
                                
                            }
                        }
                    }]
                }]
            }, {
                mainItem: 0,
                id: 'context-list',
                items: [{
                    id: 'context-list-title',
                    title: 'Context List',
                    iconCls: 'x-icon-list'
                }]
            }]
        }]
    
    });
    
    updateContextList();
    
    /**
     * Update context list
     */
    function updateContextList(){
    
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                getContextList: true
            },
            success: function(responseObject){
            
                var response = eval('(' + responseObject.responseText + ')');
                if (response.error) {
                    Ext.Msg.alert('Server Error', response.error);
                }
                var data = response.data;
                
                var panel = Ext.getCmp('context-list');
                
                panel.items.each(function(item, index, len){
                    if (item.id != 'context-list-title') {
                        this.remove(item, true);
                    }
                }, panel);
                
                for (var i = 0; i < data.length; i++) {
                
                    panel.add({
                        title: data[i].name,
                        iconCls: 'x-icon-context',
                        tabTip: data[i].description,
                        style: 'padding:10px;',
                        layout: 'fit',
                        listeners: {
                            activate: function(panel){
                                currentContext = panel.title;
								reloadModuleStore();
                            }
                        },
                        items: [{
                            xtype: 'panel',
                            title: data[i].name,
                            iconCls: 'x-icon-context',
                            id: data[i].name,
                            bodyStyle: 'overflow-y:auto;',
                            items: [{
                                id: data[i].name + '-installed',
                                title: 'Installed',
                                columnWidth: .45,
                                layout: 'fit',
                                style: 'padding:10px;',
                                listeners: {
                                    afterrender: function(panel){
										
										currentContext = panel.ownerCt.title;
										                                    
                                        var status = new Ext.ux.grid.RowActions({
                                            header: 'Status',
                                            autoWidth: true,
                                            actions: [{
                                                iconCls: 'x-icon-ok',
                                                tooltip: "No Error",
												hideIndex: "(errorstatus!='')"
                                            }, {
                                                iconCls: 'x-icon-ko',
                                                tooltip: "See Error",
												hideIndex: "(errorstatus=='')"
                                            }
											]
                                        });
										
										status.on({
                                            action: function(grid, record, action, row, col){
                                                                                         
                                                switch (action) {
                                                    case 'x-icon-ko':
                                                        Ext.Msg.alert('Freedom Web Installer','Error happened during <b>' + record.get('errorstatus') + '</b>');
                                                        break;
                                                }
                                              
                                                
                                            }
                                        });
                                        
                                        var actions = new Ext.ux.grid.RowActions({
                                            header: 'Actions',
                                            autoWidth: false,
                                            actions: [{
                                                iconCls: 'x-icon-update',
                                                tooltip: 'Update',
                                                hideIndex: '!canUpdate'
                                            }, {
                                                iconCls: 'x-icon-param',
                                                tooltip: 'Parameters'
                                            }, {
                                                iconCls: 'x-icon-help',
                                                tooltip: 'Help'
                                            }, {
                                                iconCls: 'x-icon-remove',
                                                tooltip: 'Remove',
                                                hideIndex: "(name=='freedom-core')"
                                            }]
                                        });
                                        
                                        actions.on({
                                            action: function(grid, record, action, row, col){
                                                                                            
                                                currentModule = {
                                                    name: record.get('name')
                                                };
                                                
                                                switch (action) {
                                                    case 'x-icon-update':
                                                        var operation = 'upgrade';
                                                        break;
                                                    case 'x-icon-param':
                                                        var operation = 'parameter';
                                                        break;
                                                    case 'x-icon-help':
                                                        var operation = 'help';
                                                        break;
                                                    case 'x-icon-remove':
                                                        var operation = 'uninstall';
                                                        break;
                                                        
                                                }
                                                
                                                if (operation == 'parameter') {
                                                    askParameter(currentModule,operation);
                                                }
                                                if (operation == 'upgrade') {
                                                    upgrade(currentModule);
                                                }
                                                if (operation == 'remove') {
                                                    remove(currentModule);
                                                }
                                                
                                            }
                                        });
                                        
                                        installedStore[currentContext] = new Ext.data.JsonStore({
                                            url: 'wiff.php',
                                            baseParams: {
                                                context: this.ownerCt.id,
                                                getInstalledModuleList: true
                                            },
                                            root: 'data',
                                            fields: ['name', 'version', 'availableversion', 'description', 'errorstatus', {
                                                name: 'canUpdate',
                                                type: 'boolean'
                                            }],
                                            autoLoad: true
                                        });
                                        
                                        var grid = new Ext.grid.GridPanel({
                                            border: false,
                                            store: installedStore[currentContext],
                                            stripeRows: true,
                                            columns: [actions, {
                                                id: 'name',
                                                header: 'Module',
                                                dataIndex: 'name',
                                                width: 140
                                            }, {
                                                id: 'installed-version',
                                                header: 'Installed Version',
                                                dataIndex: 'version'
                                            }, {
                                                id: 'available-version',
                                                header: 'Available Version',
                                                dataIndex: 'availableversion'
                                            }, status, {
                                                id: 'description',
                                                header: 'Description',
                                                dataIndex: 'description'
                                            }],
                                            autoExpandColumn: 'description',
                                            autoHeight: true,
                                            plugins: [actions, status]
                                        });
                                        
                                        grid.getView().getRowClass = function(record, index){
                                            return (record.data.errorstatus ? 'red-row' : '') ;
                                        };
                                        
                                        grid.getView().emptyText = 'No installed modules';
                                        
                                        panel.add(grid);
                                        
                                    }
                                }
                            }, {
                                id: data[i].name + '-available',
                                title: 'Available',
                                columnWidth: .45,
                                layout: 'fit',
                                style: 'padding:10px;padding-top:0px;',
                                listeners: {
                                    render: function(panel){
                                    
                                        var actions = new Ext.ux.grid.RowActions({
                                            header: 'Actions',
                                            autoWidth: false,
                                            actions: [{
                                                iconCls: 'x-icon-install',
                                                tooltip: 'Install'
                                            }, {
                                                hideIndex: 'true'
                                            }, {
                                                iconCls: 'x-icon-help',
                                                tooltip: 'Help'
                                            }]
                                        });
                                        
                                        actions.on({
                                            action: function(grid, record, action, row, col){
                                            
                                                var module = record.get('name');
                                                
                                                switch (action) {
                                                    case 'x-icon-install':
                                                        var operation = 'install';
                                                        break;
                                                    case 'x-icon-help':
                                                        var operation = 'help';
                                                        break;
                                                }
                                                
                                                if (operation == 'install') {
                                                
                                                    install(module);
                                                    
                                                }
                                                
                                            }
                                        });
                                        
                                        availableStore[currentContext] = new Ext.data.JsonStore({
                                            url: 'wiff.php',
                                            baseParams: {
                                                context: this.ownerCt.id,
                                                getAvailableModuleList: true
                                            },
                                            root: 'data',
                                            fields: ['name', 'version', 'description'],
                                            autoLoad: true
                                        });
                                        
                                        var grid = new Ext.grid.GridPanel({
                                            border: false,
                                            store: availableStore[currentContext],
                                            stripeRows: true,
                                            columns: [actions, {
                                                id: 'name',
                                                header: 'Module',
                                                dataIndex: 'name',
                                                width: 140
                                            }, {
                                                id: 'available-version',
                                                header: 'Available Version',
                                                dataIndex: 'version'
                                            }, {
                                                id: 'description',
                                                header: 'Description',
                                                dataIndex: 'description'
                                            }],
                                            autoExpandColumn: 'description',
                                            autoHeight: true,
                                            plugins: [actions]
                                        });
                                        
                                        grid.getView().emptyText = 'No available modules';
                                        
                                        panel.add(grid);
                                        
                                    }
                                }
                            }],
                        
                        }]
                    })
                }
                
            },
            failure: function(){
                Ext.Msg.alert('Error', 'Could not retrieve context list');
            }
            
        });
        
    }
    
    function upgrade(module){
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module,
                getModuleDependencies: true
            },
            success: function(responseObject){
            
                var response = eval('(' + responseObject.responseText + ')');
                if (response.error) {
                    Ext.Msg.alert('Server Error', response.error);
                }
                
                var data = response.data;
                
                toDownload = data;
                toInstall = data.slice();
                
                htmlModuleList = '<ul>';
                for (var i = 0; i < toDownload.length; i++) {
                    htmlModuleList = htmlModuleList + '<li><b>' + toDownload[i].name + '</b></li>';
                }
                htmlModuleList = htmlModuleList + '</ul>';
                
                Ext.Msg.show({
                    title: 'Freedom Web Installer',
                    msg: 'Installer will download and install following module(s) : <br/>' + htmlModuleList,
                    buttons: {
                        ok: true,
                        cancel: true
                    },
                    fn: function(btn){
                        switch (btn) {
                            case 'ok':
                                for (var i = 0; i < toDownload.length; i++) {
                                    download(toDownload[i]);
                                }
                                break;
                            case 'cancel':
                                // Do nothing. Will simply close message window.
                                break;
                        }
                    }
                });
                
            }
        });
    };
	
	function remove(module){
        
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module.name,
                operation: operation,
                getPhaseList: true
            },
            success: function(responseObject){
            
                var response = eval('(' + responseObject.responseText + ')');
                if (response.error) {
                    Ext.Msg.alert('Server Error', response.error);
                }
                
                var data = response.data;
                
                currentPhaseList = data;
                currentPhaseIndex = 0;
                
                executePhaseList();
                
            },
            failure: function(){
                Ext.Msg.alert('Error', 'Could not retrieve phase list');
            }
            
        });

    };
    
    function install(module){
    
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module,
                getModuleDependencies: true
            },
            success: function(responseObject){
            
                var response = eval('(' + responseObject.responseText + ')');
                if (response.error) {
                    Ext.Msg.alert('Server Error', response.error);
                }
                
                var data = response.data;
				                
                toDownload = data;
                toInstall = data.slice();
                
                htmlModuleList = '<ul>';
                for (var i = 0; i < toDownload.length; i++) {
                    htmlModuleList = htmlModuleList + '<li><b>' + toDownload[i].name + '</b> <i>(Version ' + toDownload[i].version + ' )</i></li>';
                }
                htmlModuleList = htmlModuleList + '</ul>';
                
                Ext.Msg.show({
                    title: 'Freedom Web Installer',
                    msg: 'Installer will download and install following module(s) : <br/><br/>' + htmlModuleList,
                    buttons: {
                        ok: true,
                        cancel: true
                    },
                    fn: function(btn){
                        switch (btn) {
                            case 'ok':
                                for (var i = 0; i < toDownload.length; i++) {
                                    download(toDownload[i]);
                                }
                                break;
                            case 'cancel':
                                // Do nothing. Will simply close message window.
                                break;
                        }
                    }
                });
                
            }
        });
        
    }
    
    function download(module){
    
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module.name,
                download: true
            },
            success: function(responseObject){
                toDownload.remove(module);
                if (toDownload.length == 0) {
                    askParameter(toInstall[toInstall.length - 1],'install');
                }
            }
            
        });
        
    }
    
    function askParameter(module,operation){
        
		Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module.name,
                getParameterList: true
            },
            success: function(responseObject){
                var response = eval('(' + responseObject.responseText + ')');
                if (response.error) {
                    Ext.Msg.alert('Server Error', response.error);
                }
                
                var data = response.data;
                
                if (data.length > 0) {
                
                    var form = new Ext.form.FormPanel({
                        id: 'parameter-panel',
                        labelWidth: 200,
                        border: false,
                        frame: true,
                        bodyStyle: 'padding:15px;',
                        buttons: [{
                            text: 'Save Parameters',
                            handler: function(){
                            
                                form = Ext.getCmp('parameter-panel').getForm();
                                
                                form.submit({
                                    url: 'wiff.php',
                                    success: function(form, action){
                                        Ext.getCmp('parameter-window').close();
                                        getPhaseList(module, operation);
                                    },
				    failure: function(form, action){
                                        Ext.Msg.alert('Failure', action.result.error);
                                    },
                                    params: {
                                        context: currentContext,
                                        module: module.name,
                                        storeParameter: true
                                    },
                                    waitMsg: 'Saving Parameters...'
                                });
                            
                            }
                        },{
							text: 'Cancel',
							handler: function(){
								
								Ext.getCmp('parameter-window').close();
								
							}
						}]
                    
                    });
                    
                    for (var i = 0; i < data.length; i++) {
                    
                        form.add({
                            xtype: 'textfield',
                            name: data[i].name,
                            fieldLabel: data[i].label,
                            value: data[i].value ? data[i].value : data[i].default
                        });
                        
                    }
                    
                    var parameterWindow = new Ext.Window({
                        title: 'Parameters for ' + module.name,
                        id: 'parameter-window'
                    });
                    
                    parameterWindow.add(form);
                    
                    parameterWindow.show();
                    
                }
                else {
					Ext.Msg.alert('Freedom Web Installer', '<b>' + module.name + '</b> does not have parameters to define.', function(btn){
						if(operation == 'install')
						{
							getPhaseList(module,operation);
						}
					}) ;
					
                }
                
            }
        })
        
    }
    
    function getPhaseList(module, operation){
        
        currentModule = module;
        
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module.name,
                operation: operation,
                getPhaseList: true
            },
            success: function(responseObject){
            
                var response = eval('(' + responseObject.responseText + ')');
                if (response.error) {
                    Ext.Msg.alert('Server Error', response.error);
                }
                
                var data = response.data;
                
                currentPhaseList = data;
                currentPhaseIndex = 0;
                
                executePhaseList();
                
            },
            failure: function(){
                Ext.Msg.alert('Error', 'Could not retrieve phase list');
            }
            
        });
        
    }
    
    function executePhaseList(){
    
        var module = currentModule;
        
        var phase = currentPhaseList[currentPhaseIndex];
		
        if (!phase) {
			// Phase execution is over
			// Proceed to next module to install
            installedStore[currentContext].load();
            availableStore[currentContext].load();
			
			// Remove last module to install
			toInstall.remove(toInstall[toInstall.length-1]);
			
			// Start installing next module in list
			if (toInstall[toInstall.length - 1]) {
				askParameter(toInstall[toInstall.length - 1], 'install');
			}

				return;
        }
		
		
		
			switch (phase) {
			
				case 'unpack':
					
					Ext.Ajax.request({
						url: 'wiff.php',
						params: {
							context: currentContext,
							module: module.name,
							unpack: true
						},
						success: function(responseObject){
						
							var response = eval('(' + responseObject.responseText + ')');
							if (response.error) {
								Ext.Msg.alert('Server Error', response.error);
							}
							
							var data = response.data;
							
							Ext.Msg.alert('Module Unpack', 'Module <b>' + module.name + '</b> unpacked successfully in context directory', function(btn){
								currentPhaseIndex++;
								executePhaseList();
							});
							
						}
					});
					
					break;
					
				default:
					
					Ext.Ajax.request({
						url: 'wiff.php',
						params: {
							context: currentContext,
							module: module.name,
							phase: phase,
							getProcessList: true
						},
						success: function(responseObject){
						
							var response = eval('(' + responseObject.responseText + ')');
							if (response.error) {
								Ext.Msg.alert('Server Error', response.error);
							}
							
							var data = response.data;
							
							processwin = null;
							currentProcessList = data;
							executeProcessList(currentModule, phase);
							
						}
					});
					
					break;
			}        
        
    }
    
    function executeProcessList(module, phase){
	
        processList = currentProcessList;
		
		if (processList.length != 0) {
		
			if (!processwin) {
				processwin = new Ext.Window({
					title: 'Executing ' + phase,
					id: 'process-window',
					resizable: true
				});
				
				processpanel = new Ext.Panel({
					border: false,
					height: 300,
					width: 300,
					bodyStyle: 'overflow:auto;'
				});
				
				processwin.add(processpanel);
				
				var processbutton = new Ext.Button({
					text: 'Continue',
					handler: function(button, event){
						processwin.destroy();
						processwin = null;
						currentPhaseIndex++;
						executePhaseList();
					}
				});
				
				var retrybutton = new Ext.Button({
					text: 'Retry',
					scope: this,
					handler: function(button, event){
						processwin.destroy();
						processwin = null;
						for (var i = 0; i < processList.length; i++) {
							processList[i].executed = false;
						}
						executeProcessList(module, phase);
					}
				});
				
				var toolbar = new Ext.Toolbar({});
				
				toolbar.add(processbutton);
				toolbar.add(retrybutton);
				
				processwin.add(toolbar);
				
			}
			
			processwin.show();
			
			var module = module;
			
			for (var i = 0; i < processList.length; i++) {
			
				if (i == (processList.length - 1) && processList[i].executed) {
					return;
				}
				
				process = i;
				
				if (!processList[i].executed) {
					break;
				}
				
			}
			
			Ext.Ajax.request({
				url: 'wiff.php',
				params: {
					context: currentContext,
					module: module.name,
					phase: phase,
					process: process + '',
					execute: true
				},
				success: function(responseObject){
				
					var response = eval('(' + responseObject.responseText + ')');
					
					var data = response.data;
					
					var success = response.success;
					
					var optional = processList[process].attributes.optional == 'yes' ? true : false;
					
					var label = processList[process].label ? processList[process].label : 'Process ' + process;
				        var help = ( !response.success ) ? processList[process].help : '';

					iconCls = success ? 'x-icon-ok' : optional ? 'x-icon-warning' : 'x-icon-ko';
					
					var panel = new Ext.Panel({
   					        collapsible: help || response.error,
					        collapsed: true,
						title: label,
						iconCls: iconCls,
						html: '<p class="help">' + help + '</p><pre class="console">' + response.error + '</pre>',
						border: false,
						style: 'padding-left:5px;padding-right:5px;padding-top:5px;padding-bottom:0px;'
					});
					
					processpanel.add(panel);
					
					processwin.doLayout();
					
					if (success || optional) {
						processList[i].executed = true;
						executeProcessList(module, phase);
					}
					else {
					//Ext.Msg.alert('Error',response.error);
					}
					
				},
				failure: function(){
					Ext.Msg.alert('Error', 'Error when executing a process.');
				}
				
				
			});
			
		} else {
			// if there is no process to execute in this phase go on to next phase.
			currentPhaseIndex++;
            executePhaseList();
		}
		
    }
    
});