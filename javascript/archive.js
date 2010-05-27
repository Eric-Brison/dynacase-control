/**
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */


archiveStore = {};

/**
 * Update archive list
 */
function updateArchiveList(select){
    
  	Ext.Ajax.request({
   		url: 'wiff.php',
   		params: {
   			getContextList: true,
   			authInfo: Ext.encode(authInfo)
   		},
   		success: function(responseObject){
   			updateArchiveList_success(responseObject, select);
   		},
   		failure: function(responseObject){
   			updateArchiveList_failure(responseObject);
   		}
   	});
   	
}

function reloadArchiveStore(){
    if (archiveStore[currentArchive]) {
        archiveStore[currentArchive].load();
    }
}

function updateArchiveList_success(responseObject, select){
    var response = eval('(' + responseObject.responseText + ')');
    if (response.error) {
        Ext.Msg.alert('Server Error', response.error);
    } else {
	    var data = response.data;
	    
	    archiveList = data;
	    
	    var panel = Ext.getCmp('archive-list');
	    
	    panel.items.each(function(item, index, len){
	        if (item.id != 'archive-list-title') {
	            this.remove(item, true);
	        }
	    }, panel);
	
	    for (var i = 0; i < data.length; i++) {
	    
	        panel.add({
	            title: data[i].name,
	            iconCls: 'x-icon-archive',
	            tabTip: data[i].description,
	            style: 'padding:10px;',
	            layout: 'fit',
	            listeners: {
	                activate: function(panel){
	                    currentArchive = panel.title;
	                    reloadArchiveStore();
	                }
	            },
	            items: [{
	                xtype: 'panel',
	                title: data[i].name,
	                iconCls: 'x-icon-archive',
	                id: 'archive-'+data[i].name,
	                bodyStyle: 'overflow-y:auto;',
	                items: [{
	                    layout: 'anchor',
	                    title: 'Context Information',
	                    style: 'padding:10px;font-size:small;',
	                    bodyStyle: 'padding:5px;',
	                    xtype: 'panel',
	                    archive: data[i],
	                    //html: contextInfoHtml,
	                    tbar: [{
	                        text: 'Create Context',
	                        tooltip: 'Create Context from Archive',
	                        iconCls: 'x-icon-create',
	                        archive: data[i],
	                        handler: function(button){
	                            
	                        	var win = new Ext.Window({
	                                title: 'Create Context',
	                                iconCls: 'x-icon-create',
	                                layout: 'fit',
	                                border: false,
	                                modal: true,
	                                width: 600,
	                                items: [{
	                                    xtype: 'form',
	                                    id: 'create-archive-form',
	                                    columnWidth: 1,
	                                    bodyStyle: 'padding:10px',
	                                    frame: true,
	                                    autoHeight: true,
	                                    items: [{
	                                        xtype: 'textfield',
	                                        fieldLabel: 'Name',
	                                        name: 'name',
	                                        anchor: '-15',
	                                        value: button.archive.name
	                                    }, {
	                                        xtype: 'displayfield',
	                                        fieldLabel: 'Root',
	                                        name: 'root',
	                                        anchor: '-15',
	                                        value: button.archive.root
	                                    }, {
	                                        xtype: 'textarea',
	                                        fieldLabel: 'Description',
	                                        name: 'desc',
	                                        anchor: '-15',
	                                        value: button.archive.description
	                                    }, {
	                                        xtype: 'textfield',
	                                        fieldLabel: 'Url',
	                                        name: 'url',
	                                        anchor: '-15',
	                                        value: button.archive.url
	                                    }],
	                                    
	                                    buttons: [{
	                                        text: 'Save',
	                                        handler: function(){
	                                            Ext.getCmp('create-archive-form').getForm().submit({
	                                                url: 'wiff.php',
	                                                success: function(form, action){
	                                                    updateContextList('select-last');
	                                                    form.reset();
	                                                    var panel = Ext.getCmp('create-context-form');
	                                                    panel.fireEvent('render', panel);
	                                                    win.close();
	                                                    win.destroy();
	                                                },
	                                                failure: function(form, action){
	                                                    updateContextList('select-last');
	                                                    if (action && action.result) {
	                                                        Ext.Msg.alert('Failure', action.result.error);
	                                                    }
	                                                    else {
	                                                        Ext.Msg.alert('Failure', 'Select at least one repository.');
	                                                    }
	                                                },
	                                                params: {
	                                                    saveContext: true,
	                                                    root: button.archive.root
	                                                },
	                                                waitMsg: 'Saving Context...'
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
	                                                fields: ['name', 'baseurl', 'description', 'protocol', 'host', 'path', 'url', 'authentified', 'login', 'password', 'displayUrl'],
	                                                autoLoad: true
	                                            });
	                                            
	                                            repoBoxList = new Array();
	                                            
	                                            repoStore.on('load', function(){
	                                            
	                                                repoStore.each(function(record){
	                                                
	                                                    var checked = false;
	                                                    
	                                                    for (var j = 0; j < button.archive.repo.length; j++) {
	                                                        if (button.archive.repo[j].name == record.get('name')) {
	                                                            checked = true;
	                                                        }
	                                                    }
	                                                    
	                                                    repoBoxList.push({
	                                                        boxLabel: record.get('description') + ' <i>(' + record.get('displayUrl') + ')</i>' + ')</i>',
	                                                        name: 'repo-' + record.get('name'),
	                                                        checked: checked
	                                                    });
	                                                    
	                                                });
	                                                
	                                                panel.remove(panel.checkBoxGroup);
	                                                
	                                                panel.checkBoxGroup = new Ext.form.CheckboxGroup({
	                                                    fieldLabel: 'Repositories',
	                                                    allowBlank: false,
	                                                    blankText: "You must select at least one repository.",
	                                                    columns: 1,
	                                                    items: repoBoxList
	                                                });
	                                                
	                                                panel.checkBoxGroup = panel.add(panel.checkBoxGroup);
	                                                panel.doLayout();
	                                                
	                                            });
	                                            
	                                            
	                                        }
	                                    }
	                                }]
	                            });
	                            
	                            win.show();
	                        	
	                        }
						},{
	                        text: 'Download',
	                        tooltip: 'Download',
	                        iconCls: 'x-icon-archive',
	                        archive: data[i],
	                        handler: function(button){
	                            
	                        	Ext.Ajax.request({
							        url: 'wiff.php',
							        params: {
							        	downloadArchive: true,
							            archiveId: button.archive.name
							        },
							        success: function(responseObject){						        	
							            downloadArchive_success(responseObject);
							        },
							        failure: function(responseObject){
							            downloadArchive_failure(responseObject);
							        }
							    });
	                        	
	                        }
						},{
	                        text: 'Delete',
	                        tooltip: 'Delete',
	                        iconCls: 'x-icon-delete-archive',
	                        archive: data[i],
	                        handler: function(button){
	                            
	                        	Ext.Ajax.request({
							        url: 'wiff.php',
							        params: {
							        	deleteArchive: true,
							            archiveId: button.archive.name
							        },
							        success: function(responseObject){						        	
							            deleteArchive_success(responseObject);
							        },
							        failure: function(responseObject){
							            deleteArchive_failure(responseObject);
							        }
							    });
	                        	
	                        }
						}],
	                    refresh: function(){
	                        
	                        var contextInfoHtml = '<ul><li class="x-form-item"><b>Archive Date :</b> ' + '</li><li class="x-form-item"><b>Root :</b> ' + this.archive.root + '</li><li class="x-form-item"><b>Description :</b> ' + this.archive.description + '</li><li class="x-form-item"><b>Url :</b>' + (this.archive.url ? '<a href=' + this.archive.url + ' target="_blank" > ' + this.archive.url + '</a>' : '<i> no url</i>') + '</ul><p>';
	                        
	                        this.body.update(contextInfoHtml);
	                        
	                    },
	                    listeners: {
	                        render: function(panel){
	                            panel.refresh();
	                        }
	                    }
	                
	                }, {
	                    id: 'archive-'+data[i].name + '-installed',
	                    title: 'Installed',
	                    columnWidth: .45,
	                    layout: 'fit',
	                    style: 'padding:10px;padding-top:0px;',
	                    archive: data[i],
	                    listeners: {
	                        afterrender: function(panel){
	                            
	                            currentArchive = panel.ownerCt.title;
	                            
	                            archiveStore[currentArchive] = new Ext.data.JsonStore({
	                                url: 'wiff.php',
	                                baseParams: {
	                                    context: this.ownerCt.id,
	                                    getInstalledModuleList: true,
	                                    authInfo: Ext.encode(authInfo)
	                                },
	                                root: 'data',
	                                fields: ['name', 'versionrelease', 'availableversionrelease', 'description', 'infopath', 'errorstatus'],
	                                //autoLoad: true,
	                                sortInfo: {
	                                    field: 'name',
	                                    direction: "ASC"
	                                },
	                                listeners: { //                                        beforeload: function(store, options){
	                                    //                                            //return false;
	                                    //                                            Ext.Msg.alert('Freedom Web Installer', 'Here I could ask for repository login/password', function(){
	                                    //                                                return false;
	                                    //                                            });
	                                    //                                        },
	                                    //                                        load: function(){
	                                    //                                            console.log('LOAD');
	                                    //                                        },
	                                    //                                        exception: function(){
	                                    //                                            console.log('Exception on load');
	                                    //                                        }
	                                }
	                            });
	                            
	                            var selModel = new Ext.grid.CheckboxSelectionModel({
	                                //header: '',
	                                listeners: {
	                                    // prevent selection of records
	                                    beforerowselect: function(selModel, rowIndex, keepExisting, record){
	                                        if ((record.get('canUpdate') != true)) {
	                                            return false;
	                                        }
	                                    }
	                                }
	                            });
	                            
	                            var grid = new Ext.grid.GridPanel({
	                                selModel: selModel,
	                                loadMask: true,
	                                border: false,
	                                store: archiveStore[currentArchive],
	                                stripeRows: true,
	                                columns: [selModel, {
	                                    id: 'name',
	                                    header: 'Module',
	                                    dataIndex: 'name',
	                                    width: 140
	                                }, {
	                                    id: 'installed-version',
	                                    header: 'Installed Version',
	                                    dataIndex: 'versionrelease'
	                                }, {
	                                    id: 'description',
	                                    header: 'Description',
	                                    dataIndex: 'description'
	                                }],
	                                autoExpandColumn: 'description',
	                                autoHeight: true
	                            });
	                            
	                            grid.getView().getRowClass = function(record, index){
	                                return (record.data.errorstatus ? 'red-row' : '');
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
	            Ext.getCmp('archive-list').setActiveTab(Ext.getCmp('archive-list').items.last());
	        }
	        else {
	            if (window.currentContext) {
	            
	                var contextArray = Ext.getCmp('archive-list').items.items;
	                
	                for (var i = 0; i < contextArray.length; i++) {
	                    if (contextArray[i].title == currentContext) {
	                        Ext.getCmp('archive-list').setActiveTab(contextArray[i]);
	                    }
	                }
	                
	            }
	            
	        }
	    }
    
    }
    
}

function archive_success(responseObject){
	
	 var response = eval('(' + responseObject.responseText + ')');
	 if (response.error) {
	     Ext.Msg.alert('Server Error', response.error);
	 }

}

function archive_failure(responseObject){
	console.log('Archive Failure');
}

function downloadArchive_success(responseObject){
	
	 var response = eval('(' + responseObject.responseText + ')');
	 if (response.error) {
	     Ext.Msg.alert('Server Error', response.error);
	 }

}

function downloadArchive_failure(responseObject){
	console.log('Download Archive Failure');
}

function deleteArchive_success(responseObject){
	
	 var response = eval('(' + responseObject.responseText + ')');
	 if (response.error) {
	     Ext.Msg.alert('Server Error', response.error);
	 }

}

function deleterchive_failure(responseObject){
	console.log('Archive Failure');
}

function updateArchiveList_failure(responseObject){
    Ext.Msg.alert('Error', 'Could not retrieve archive list');
}