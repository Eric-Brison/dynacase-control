/**
 * @author Clément Laballe
 */
Ext.onReady(function(){

    Ext.BLANK_IMAGE_URL = 'lib/ext/resources/images/default/s.gif';
    Ext.QuickTips.init();
    
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
                }, {
                    title: 'Parameters',
                    iconCls: 'x-icon-setup',
                    tabTip: 'Set WIFF parameters',
                    layout: 'fit',
                    style: 'padding:10px;',
                    items: [{
                        xtype: 'panel',
                        title: 'Test',
                        items: [{
                            label: 'name'
                        }]
                    }]
                }, {
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
                                    failure: function(){
                                        Ext.Msg.alert('Failure', 'Failure');
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
                                            boxLabel: record.get('name') + ' (' + record.get('baseurl') + ')',
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
     * @param {Object} data JSON context list
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
                                
                                //								if(installedStoreList[panel.title])
                                //								{
                                //									installedStoreList[panel.title].load();
                                //								}
                            }
                        },
                        items: [{
                            xtype: 'panel',
                            title: data[i].name,
                            iconCls: 'x-icon-context',
                            id: data[i].name,
							bodyStyle: 'overflow-y:auto;',
                            //layout: 'column',
                            items: [{
                                id: data[i].name + '-installed',
                                title: 'Installed',
                                columnWidth: .45,
                                layout: 'fit',
                                style: 'padding:10px;',
                                listeners: {
                                    render: function(panel){
                                    
                                        var actions = new Ext.ux.grid.RowActions({
                                            header: '',
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
                                                hideIndex: "(name=='core')"
                                            }]
                                        });
                                        
                                        actions.on({
                                            action: function(grid, record, action, row, col){
                                            
                                                var context = currentContext;
                                                
                                                var module = record.get('name');
                                                
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
                                                
                                                Ext.Ajax.request({
                                                    url: 'wiff.php',
                                                    params: {
                                                        context: context,
                                                        module: module,
                                                        operation: operation,
                                                        getPhaseList: true
                                                    },
                                                    success: function(responseObject){
                                                    
                                                        var response = eval('(' + responseObject.responseText + ')');
                                                        if (response.error) {
                                                            Ext.Msg.alert('Server Error', response.error);
                                                        }
                                                        
                                                        var data = response.data;
                                                        
                                                        for (var i = 0; i < data.length; i++) {
                                                            console.log(data[i]);
                                                            
                                                        }
                                                        
                                                    },
                                                    failure: function(){
                                                        Ext.Msg.alert('Error', 'Could not retrieve phase list');
                                                    }
                                                    
                                                });
                                                
                                            }
                                        });
                                        
                                        installedStore = new Ext.data.JsonStore({
                                            url: 'wiff.php',
                                            baseParams: {
                                                context: this.ownerCt.id,
                                                getInstalledModuleList: true
                                            },
                                            root: 'data',
                                            fields: ['name', 'version', 'description', {
                                                name: 'canUpdate',
                                                type: 'boolean'
                                            }],
                                            autoLoad: true
                                        });
                                        
                                        var grid = new Ext.grid.GridPanel({
                                            //hideHeaders: true,
                                            border: false,
                                            store: installedStore,
                                            stripeRows: true,
                                            columns: [actions, {
                                                id: 'name',
                                                header: 'Module',
                                                dataIndex: 'name',
                                                width: '140px'
                                            }, {
                                                id: 'installed-version',
                                                header: 'Installed Version',
                                                dataIndex: 'version'
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
                                            header: '',
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
                                            
                                                var context = currentContext;
                                                
                                                var module = record.get('name');
                                                
                                                switch (action) {
                                                    case 'x-icon-install':
                                                        var operation = 'install';
                                                        break;
                                                    case 'x-icon-help':
                                                        var operation = 'help';
                                                        break;
                                                }
                                                
                                                Ext.Ajax.request({
                                                    url: 'wiff.php',
                                                    params: {
                                                        context: context,
                                                        module: module,
                                                        operation: operation,
                                                        getPhaseList: true
                                                    },
                                                    success: function(responseObject){
                                                    
                                                        var response = eval('(' + responseObject.responseText + ')');
                                                        if (response.error) {
                                                            Ext.Msg.alert('Server Error', response.error);
                                                        }
                                                        
                                                        var data = response.data;
                                                        
                                                        for (var i = 0; i < data.length; i++) {
                                                            console.log(data[i]);
                                                            
                                                        }
                                                        
                                                    },
                                                    failure: function(){
                                                        Ext.Msg.alert('Error', 'Could not retrieve phase list');
                                                    }
                                                    
                                                });
                                                
                                            }
                                        });
                                        
                                        availableStore = new Ext.data.JsonStore({
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
                                            store: availableStore,
                                            stripeRows: true,
                                            columns: [actions, {
                                                id: 'name',
                                                header: 'Module',
                                                dataIndex: 'name',
                                                width: '140px'
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
    
    //	Ext.Ajax.request({
    //		url:'wiff.php',
    //		params: {
    //			context: 'Production',
    //			getModuleList: true
    //		},
    //		success: function(responseObject){
    //			
    //			var response = eval('(' + responseObject.responseText + ')');
    //			if (response.error) {
    //				Ext.Msg.alert('Server Error',response.error);
    //			}
    //			
    //		},
    //		failure: function(){
    //            Ext.Msg.alert('Error', 'Could not retrieve module list');
    //        }
    //	
    //	});

});
