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
   			getArchivedContextList: true,
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
	            title: data[i].name + ' (' + data[i].datetime.substr(0,10) + ')',
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
	                archive: data[i],
	                iconCls: 'x-icon-archive',
	                id: 'archive-'+data[i].name,
	                bodyStyle: 'overflow-y:auto;',
	                items: [{
	                    layout: 'anchor',
	                    title: 'Archive Information',
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
	                                title: 'Create Context from Archive',
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
	                                        xtype: 'textfield',
	                                        fieldLabel: 'Root',
	                                        name: 'root',
	                                        anchor: '-15'
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
	                                        anchor: '-15'
	                                    }, {
	                                        xtype: 'textfield',
	                                        fieldLabel: 'Vault Root',
	                                        name: 'vault_root',
	                                        anchor: '-15'
	                                    }],
	                                    
	                                    buttons: [{
	                                        text: 'Save',
	                                        handler: function(){
	                                            Ext.getCmp('create-archive-form').getForm().submit({
	                                                url: 'wiff.php',
	                                                success: function(form, action){
	                                                    updateContextList('select-last');
	                                                    form.reset();
	                                                    var panel = Ext.getCmp('create-archive-form');
	                                                    panel.fireEvent('render', panel);
	                                                    win.close();
	                                                    win.destroy();
	                                                },
	                                                failure: function(form, action){
	                                                    updateContextList('select-last');
	                                                    if (action && action.result) {
	                                                        Ext.Msg.alert('Failure', action.result.error);
	                                                    }
	                                                },
	                                                params: {
	                                                    createContextFromArchive: true,
	                                                    archiveId: button.archive.id
	                                                },
	                                                waitMsg: 'Creating Context from Archive...'
	                                            })
	                                        }
	                                    }]
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
							            archiveId: button.archive.id
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
							            archiveId: button.archive.id
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
	                        
	                        var contextInfoHtml = '<ul><li class="x-form-item"><b>Archive Datetime :</b> ' + this.archive.datetime + '</li><li class="x-form-item"><b>Description :</b> ' + this.archive.description + '</li></ul><p>';
	                        
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
	                            
	                            currentArchive = panel.archive.id;
	                            	                            
	                            archiveStore[currentArchive] = new Ext.data.JsonStore({
	                            	data: panel.archive.moduleList,	                                
	                                fields: ['name', 'versionrelease', 'availableversionrelease', 'description', 'infopath', 'errorstatus'],
	                                sortInfo: {
	                                    field: 'name',
	                                    direction: "ASC"
	                                }
	                            });
	                            
	                            var selModel = new Ext.grid.RowSelectionModel();
	                            
	                            var grid = new Ext.grid.GridPanel({
	                                selModel: selModel,
	                                loadMask: true,
	                                border: false,
	                                store: archiveStore[currentArchive],
	                                stripeRows: true,
	                                columns: [ {
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
	} else {
		Ext.Msg.alert('WIFF', 'Archive created.',function(){
			updateArchiveList();
		});
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
	
	console.log('Download Archive', response);

}

function downloadArchive_failure(responseObject){
	console.log('Download Archive Failure');
}

function deleteArchive_success(responseObject){

	var response = eval('(' + responseObject.responseText + ')');
	if (response.error) {
	    Ext.Msg.alert('Server Error', response.error);
	} else {
		Ext.Msg.alert('WIFF', 'Archive deleted.',function(){
			updateArchiveList();
		});
	}

}

function deleteArchive_failure(responseObject){
	console.log('Archive Failure');
}

function updateArchiveList_failure(responseObject){
    Ext.Msg.alert('Error', 'Could not retrieve archive list');
}