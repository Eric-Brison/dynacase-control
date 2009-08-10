/**
 * @author Clément Laballe
 */
Ext.onReady(function(){

    Ext.BLANK_IMAGE_URL = 'javascript/lib/ext/resources/images/default/s.gif';
    Ext.QuickTips.init();
    
    Ext.Ajax.timeout = 3600000;
    
    installedStore = {};
    availableStore = {};
    
    function reloadModuleStore(){
        if (installedStore[currentContext]) {
            installedStore[currentContext].load();
        }
        if (availableStore[currentContext]) {
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
                }, //				{
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
					layout: 'column',
                    items: [{
                        xtype: 'form',
                        id: 'create-context-form',
                        columnWidth: 1,
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
                                        updateContextList('select-last');
                                        form.reset();
                                        var panel = Ext.getCmp('create-context-form');
                                        panel.fireEvent('render', panel);
                                    },
                                    failure: function(form, action){
                                        if (action && action.result) {
                                            Ext.Msg.alert('Failure', action.result.error);
                                        }
                                        else {
                                            Ext.Msg.alert('Failure', 'Select at least one repository.');
                                        }
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
                                
                                    // First repository is selected by default.
                                    var first = true;
                                    
                                    repoStore.each(function(record){
                                    
                                        repoBoxList.push({
                                            boxLabel: record.get('description') + ' <i>(' + record.get('baseurl') + ')</i>',
                                            name: 'repo-' + record.get('name'),
                                            checked: first
                                        });
                                        
                                        if (first) {
                                            first = false;
                                        }
                                        
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
    function updateContextList(select){
    
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                getContextList: true
            },
            success: function(responseObject){
                updateContextList_success(responseObject, select);
            },
            failure: function(responseObject){
                updateContextList_failure(responseObject);
            }
            
        });
        
    }
    
    function updateContextList_success(responseObject, select){
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
        
            var repositoryHtml = '<ul>';
            for (var j = 0; j < data[i].repo.length; j++) {
                repositoryHtml += '<li class="x-form-item" style="margin-left:30px;">' + data[i].repo[j].description + ' <i>(' + data[i].repo[j].baseurl + ')</i></li>'
            }
            repositoryHtml += '</ul>'
            var contextInfoHtml = '<ul><li class="x-form-item"><b>Root :</b> ' + data[i].root + '</li><li class="x-form-item"><b>Description :</b> ' + data[i].description + '</li><li class="x-form-item"><b>Repositories :</b> ' + repositoryHtml + '</li></ul><p>';
            
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
                        xtype: 'form',
                        title: 'Context Information',
                        style: 'padding:10px;font-size:small;',
                        bodyStyle: 'padding:5px;',
                        html: contextInfoHtml
                    }, {
                        id: data[i].name + '-installed',
                        title: 'Installed',
                        columnWidth: .45,
                        layout: 'fit',
                        style: 'padding:10px;padding-top:0px;',
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
                                    }]
                                });
                                
                                status.on({
                                    action: function(grid, record, action, row, col){
                                    
                                        switch (action) {
                                            case 'x-icon-ko':
                                                Ext.Msg.alert('Freedom Web Installer', 'Error happened during <b>' + record.get('errorstatus') + '</b>');
                                                break;
                                        }
                                        
                                    }
                                });
                                
                                var actions = new Ext.ux.grid.RowActions({
                                    header: '',
                                    autoWidth: false,
                                    width: 70,
                                    actions: [{
                                        iconCls: 'x-icon-update',
                                        tooltip: 'Update',
                                        hideIndex: '!canUpdate'
                                    }, {
                                        iconCls: 'x-icon-param',
                                        tooltip: 'Parameters',
                                        hideIndex: '!hasParameter'
                                    }, {
                                        iconCls: 'x-icon-help',
                                        tooltip: 'Help',
                                        hideIndex: '!infopath'
                                        //                                    }, {
                                        //                                        iconCls: 'x-icon-remove',
                                        //                                        tooltip: 'Remove',
                                        //                                        hideIndex: "(name=='freedom-core')"
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
                                            //                                            case 'x-icon-remove':
                                            //                                                var operation = 'uninstall';
                                            //                                                break;
                                        
                                        }
                                        
                                        if (operation == 'parameter') {
                                            askParameter(currentModule, operation);
                                        }
                                        if (operation == 'upgrade') {
                                            upgrade([currentModule.name]);
                                        }
                                        if (operation == 'help') {
                                            window.open(record.get('infopath'), '_newtab');
                                        }
                                        //                                        if (operation == 'remove') {
                                        //                                            remove(currentModule);
                                        //                                        }
                                    
                                    }
                                });
                                
                                
                                
                                installedStore[currentContext] = new Ext.data.JsonStore({
                                    url: 'wiff.php',
                                    baseParams: {
                                        context: this.ownerCt.id,
                                        getInstalledModuleList: true
                                    },
                                    root: 'data',
                                    fields: ['name', 'version', 'availableversion', 'description', 'infopath', 'errorstatus', {
                                        name: 'canUpdate',
                                        type: 'boolean'
                                    }, {
                                        name: 'hasParameter',
                                        type: 'boolean'
                                    }],
                                    autoLoad: true,
                                    sortInfo: {
                                        field: 'name',
                                        direction: "ASC"
                                    }
                                });
                                
                                var selModel = new Ext.grid.CheckboxSelectionModel({
                                    header: '',
                                    listeners: {
                                        // prevent selection of records
                                        beforerowselect: function(selModel, rowIndex, keepExisting, record){
                                            if ((record.get('canUpdate') != true)) {
                                                return false;
                                            }
                                        },
                                    }
                                });
                                
                                var grid = new Ext.grid.GridPanel({
                                    selModel: selModel,
                                    tbar: [{
                                        text: 'Upgrade Selection',
                                        tooltip: 'Upgrade selected module(s)',
                                        iconCls: 'x-icon-install',
                                        handler: function(button, eventObject){
                                            var selections = grid.getSelectionModel().getSelections();
                                            var modules = [];
                                            for (var i = 0; i < selections.length; i++) {
                                                modules.push(selections[i].get('name'));
                                            }
                                            upgrade(modules);
                                        }
                                    }],
                                    border: false,
                                    store: installedStore[currentContext],
                                    stripeRows: true,
                                    columns: [selModel, actions, {
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
                                    return (record.data.errorstatus ? 'red-row' : '');
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
                                    header: '',
                                    autoWidth: false,
                                    width: 20,
                                    actions: [{
                                        //                                        iconCls: 'x-icon-install',
                                        //                                        tooltip: 'Install'
                                        //                                    }, {
                                        iconCls: 'x-icon-help',
                                        tooltip: 'Help',
                                        hideIndex: '!infopath'
                                    }]
                                });
                                
                                actions.on({
                                    action: function(grid, record, action, row, col){
                                    
                                        var module = record.get('name');
                                        
                                        switch (action) {
                                            //                                            case 'x-icon-install':
                                            //                                                var operation = 'install';
                                            //                                                break;
                                            case 'x-icon-help':
                                                var operation = 'help';
                                                break;
                                        }
                                        
                                        //                                        if (operation == 'install') {
                                        //                                            install([module]);
                                        //                                        }
                                        
                                        if (operation == 'help') {
                                            window.open(record.get('infopath'), '_newtab');
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
                                    fields: ['name', 'version', 'description', 'infopath', 'basecomponent'],
                                    autoLoad: true,
                                    sortInfo: {
                                        field: 'name',
                                        direction: "ASC"
                                    }
                                });
                                
                                var selModel = new Ext.grid.CheckboxSelectionModel({
                                    header: ''
                                });
                                
                                var grid = new Ext.grid.GridPanel({
                                    border: false,
                                    store: availableStore[currentContext],
                                    stripeRows: true,
                                    selModel: selModel,
                                    tbar: [{
                                        text: 'Install Selection',
                                        tooltip: 'Install selected module(s)',
                                        iconCls: 'x-icon-install',
                                        handler: function(button, eventObject){
                                            var selections = grid.getSelectionModel().getSelections();
                                            var modules = [];
                                            for (var i = 0; i < selections.length; i++) {
                                                modules.push(selections[i].get('name'));
                                            }
                                            install(modules);
                                        }
                                    }],
                                    columns: [selModel, actions, {
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
                                
                                grid.getStore().on('load', function(store, records, options){
                                
                                    var recs = [];
                                    grid.getStore().each(function(rec){
                                        if (rec.get('basecomponent')) {
                                            recs.push(rec);
                                        }
                                    });
                                    grid.getSelectionModel().selectRecords(recs, true);
                                    
                                    grid.getSelectionModel().on('rowdeselect', function(selModel, rowIndex, record){
                                        if ((record.get('basecomponent') == 'yes')) {
                                            grid.getSelectionModel().selectRecords([record], true);
                                        }
                                    });
                                    
                                });
                                
                                grid.getView().emptyText = 'No available modules';
                                
                                panel.add(grid);
                                
                            }
                        }
                    }],
                
                }]
            })
        }
        
        // Selection of repository to display
        if (data.length != 0) {
            if (select == 'select-last') {
                Ext.getCmp('context-list').setActiveTab(Ext.getCmp('context-list').items.last());
            }
            else {
                Ext.getCmp('context-list').setActiveTab(Ext.getCmp('context-list').items.itemAt(1));
            }
        }
        
        
    }
    
    function updateContextList_failure(responseObject){
        Ext.Msg.alert('Error', 'Could not retrieve context list');
    }
    
    /**
     * upgrade a module
     */
    function upgrade(modulelist){
        mask = new Ext.LoadMask(Ext.getBody(), {
            msg: 'Resolving dependencies...'
        });
        mask.show();
        
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                'modulelist[]': modulelist,
                getModuleDependencies: true
            },
            success: function(responseObject){
                upgrade_success(responseObject);
            },
            failure: function(responseObject){
                upgrade_failure(responseObject);
            }
        });
    };
    
    function upgrade_success(responseObject){
    
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
                            download(toDownload[i], 'upgrade');
                        }
                        break;
                    case 'cancel':
                        // Do nothing. Will simply close message window.
                        break;
                }
            }
        });
    }
    
    function upgrade_failure(module, reponseObject){
        mask.hide();
    }
    
    /**
     * remove a module
     */
    function remove(module, operation){
    
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module.name,
                operation: operation,
                getPhaseList: true
            },
            success: function(responseObject){
                remove_success(module, operation, responseObject);
            },
            failure: function(responseObject){
                remove_failure(module, operation, responseObject);
            }
            
        });
        
    };
    
    function remove_success(module, operation, responseObject){
        var response = eval('(' + responseObject.responseText + ')');
        if (response.error) {
            Ext.Msg.alert('Server Error', response.error);
        }
        
        var data = response.data;
        
        currentPhaseList = data;
        currentPhaseIndex = 0;
        
        executePhaseList(operation);
    }
    
    function remove_failure(module, operation, responseObject){
        Ext.Msg.alert('Error', 'Could not retrieve phase list');
    }
    
    /**
     * install a module
     */
    function install(modulelist){
        mask = new Ext.LoadMask(Ext.getBody(), {
            msg: 'Resolving dependencies...'
        });
        mask.show();
        
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                'modulelist[]': modulelist,
                getModuleDependencies: true
            },
            success: function(responseObject){
                install_success(responseObject);
            },
            failure: function(responseObject){
                install_failure(responseObject);
            }
        });
        
    }
    
    function install_success(responseObject){
    
        mask.hide();
        
        var response = eval('(' + responseObject.responseText + ')');
        if (response.error) {
            Ext.Msg.alert('Server Error', response.error);
            return;
        }
        
        var data = response.data;
        
        toDownload = data;
        toInstall = data.slice();
        
        htmlModuleList = '<ul>';
        for (var i = 0; i < toDownload.length; i++) {
            htmlModuleList = htmlModuleList + '<li><b>' + toDownload[i].name + '</b> <i>(' + toDownload[i].version + ')</i></li>';
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
                        if (toDownload.length > 0) {
                            //for (var i = 0; i < toDownload.length; i++) {
                            download(toDownload[0], 'install');
                        //}
                        }
                        break;
                    case 'cancel':
                        // Do nothing. Will simply close message window.
                        break;
                }
            }
        });
    }
    
    function install_failure(responseObject){
        mask.hide();
    }
    
    /**
     * wstop
     */
    function wstop(operation){
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                wstop: 'yes'
            },
            callback: function(option, success, responseObject){
            
                globalwin = new Ext.Window({
                    title: 'Freedom Web Installer',
                    id: 'module-window',
                    layout: 'column',
                    resizable: true,
                    //height: 400,
                    width: 700,
                    modal: true,
                    //	bodyStyle: 'overflow:auto;'
                });
                
                modulepanel = new Ext.Panel({
                    title: 'Module List',
                    columnWidth: 0.25,
                    height: 422,
                    setModuleIcon: function(name, icon){
                        var panel = this.getComponent('module-' + name);
                        panel.setIconClass(icon);
                    }
                    
                });
                
                for (var i = 0; i < toInstall.length; i++) {
                    var panel = new Ext.Panel({
                        //collapsible: true,
                        //collapsed: true,
                        title: toInstall[i].name,
                        iconCls: 'x-icon-none',
                        //                        html: html,
                        id: 'module-' + toInstall[i].name,
                        border: false,
                        style: 'padding:0px;'
                    });
                    
                    modulepanel.insert(0, panel);
                }
                
                globalwin.add(modulepanel);
                
                processpanel = [];
                
                globalwin.show();
                
                askParameter(toInstall[toInstall.length - 1], operation);
                
            }
        });
    }
    
    /**
     * wstart
     */
    function wstart(module, operation){
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                wstart: 'yes'
            },
            callback: function(option, success, responseObject){
				
                //Ext.Msg.alert('Freedom Web Installer','Module <b>' + module.name + '</b> installed successfully', function(){
                // If applicable, start installing next module in list
                if (toInstall[toInstall.length - 1]) {
                    askParameter(toInstall[toInstall.length - 1], operation);
                }
                else {
                    Ext.Msg.alert('Freedom Web Installer', 'Install successful', function(){
                        globalwin.close();
                    });
                }
                //})
            
                // The end
            }
        });
    }
    
    /**
     * download a module
     */
    function download(module, operation){
    
        mask = new Ext.LoadMask(Ext.getBody(), {
            msg: 'Downloading...'
        });
        
        mask.show();
        
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module.name,
                download: true
            },
            success: function(responseObject){
                download_success(module, operation, responseObject);
            },
            failure: function(responseObject){
                download_failure(module, operation, responseObject);
            }
        });
        
    }
    
    function download_success(module, operation, responseObject){
        toDownload.remove(module);
        if (toDownload.length > 0) {
            download(toDownload[0], operation);
        }
        else {
            mask.hide();
            wstop(operation);
        }
    }
    
    function download_failure(module, operation, responseObject){
    }
    
    /**
     * ask parameter
     */
    function askParameter(module, operation){
    
        modulepanel.setModuleIcon(module.name, 'x-icon-loading');
        
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                context: currentContext,
                module: module.name,
                getParameterList: true
            },
            success: function(responseObject){
                askParameter_success(module, operation, responseObject);
            },
            failure: function(responseObject){
                askParameter_failure(module, operation, responseObject);
            }
        })
        
    }
    
    function askParameter_success(module, operation, responseObject){
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
                }, {
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
                id: 'parameter-window',
                modal: true
            });
            
            parameterWindow.add(form);
            
            parameterWindow.show();
            
        }
        else {
            if (operation == 'install' || operation == 'upgrade') {
                getPhaseList(module, operation);
            }
            
        }
    }
    
    function askParameter_failure(module, operation, responseObject){
    }
    
    /**
     * get phase list
     */
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
                getPhaseList_success(module, operation, responseObject);
            },
            failure: function(responseObject){
                getPhaseList_failure(module, operation, responseObject);
            }
            
        });
        
    }
    
    function getPhaseList_success(module, operation, responseObject){
    
        var response = eval('(' + responseObject.responseText + ')');
        if (response.error) {
            Ext.Msg.alert('Server Error', response.error);
        }
        
        var data = response.data;
        
        currentPhaseList = data;
        currentPhaseIndex = 0;
        
        executePhaseList(operation);
    }
    
    function getPhaseList_failure(module, operation, responseObject){
        Ext.Msg.alert('Error', 'Could not retrieve phase list');
    }
    
    /**
     * execute phase list
     */
    function executePhaseList(operation){
    
        var module = currentModule;
        
        var phase = currentPhaseList[currentPhaseIndex];
        
        if (!phase) {
            // Remove last module to install
            toInstall.remove(toInstall[toInstall.length - 1]);
            
            setModuleStatusInstalled(module, operation);
            
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
                        
                        //Ext.Msg.alert('Module Unpack', 'Module <b>' + module.name + '</b> unpacked successfully in context directory', function(btn){
                        currentPhaseIndex++;
                        executePhaseList(operation);
                    //});
                    
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
                        
                        //processpanel = null;
                        
                        currentProcessList = data;
                        executeProcessList(currentModule, phase, operation);
                        
                    }
                });
                
                break;
        }
        
    }
    
    function executeProcessList(module, phase, operation){
    
        processList = currentProcessList;
        
        if (processList.length != 0) {
        
            if (!processpanel) {
                processpanel = {};
            }
            
            if (!processpanel[module.name]) {
            
                var toolbar = new Ext.Toolbar({});
                
                processpanel[module.name] = new Ext.Panel({
                    height: 400,
                    columnWidth: 0.75,
                    bbar: toolbar,
                    bodyStyle: 'overflow:auto;'
                });
                
                processpanel[module.name].processbutton = new Ext.Button({
                    text: 'Continue',
                    disabled: true,
                    handler: function(button, event){
                        //processpanel[module.name].destroy();
                        //processpanel = null;
                        processpanel[module.name].statustext.show();
                        processpanel[module.name].processbutton.disable();
                        processpanel[module.name].retrybutton.disable();
						modulepanel.setModuleIcon(module.name, 'x-icon-loading');
                        processList[process].executed = true;
                        executeProcessList(module, phase, operation);                     
                    }
                });
                
                processpanel[module.name].statustext = new Ext.Toolbar.TextItem({
                    text: 'Processing...',
                    style: "background-image:url(javascript/lib/ext/resources/images/default/grid/loading.gif);background-repeat:no-repeat;line-height:14px;padding-left:18px;"
                });
                
                processpanel[module.name].retrybutton = new Ext.Button({
                    text: 'Retry',
                    disabled: true,
                    handler: function(button, event){
                        //processpanel[module.name].destroy();
                        //processpanel[module.name] = null;
                        processpanel[module.name].statustext.show();
                        processpanel[module.name].processbutton.disable();
                        processpanel[module.name].retrybutton.disable();
                        modulepanel.setModuleIcon(module.name, 'x-icon-loading');
                        for (var i = 0; i < processList.length; i++) {
                            processList[i].executed = false;
                        }
                        executeProcessList(module, phase, operation);
                    }
                });
                
                processpanel[module.name].ignorebutton = new Ext.Button({
                    text: 'Ignore',
                    hidden: true,
                    disabled: true,
                    handler: function(button, event){
                        Ext.Msg.show({
                        
                            title: 'Freedom Web Installer',
                            msg: 'Incorrect process execution will cause problems in your freedom context',
                            
                            buttons: {
                                ok: 'Continue',
                                cancel: 'Cancel'
                            },
                            
                            fn: function(buttonId){
                                switch (buttonId) {
                                    case 'ok':
                                        //processpanel.destroy();
                                        //processpanel = null;
										
										modulepanel.setModuleIcon(module.name, 'x-icon-loading');							
                                        processList[process].executed = true;
                        				executeProcessList(module, phase, operation);
                                        break;
                                    case 'cancel':
                                        break;
                                }
                            }
                            
                        });
                        
                    }
                });
                
                toolbar.add(processpanel[module.name].retrybutton);
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
                processpanel[module.name].titlepanel[phase] = processpanel[module.name].add(new Ext.Panel({
                    title: '<i>Executing ' + phase + ' for ' + module.name + '</i>',
                    border: false
                }));
            
            for (var i = 0; i < processList.length; i++) {
            
                if (i == (processList.length - 1) && processList[i].executed) {
                    // if there is no process to execute in this phase go on to next phase.
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
                url: 'wiff.php',
                params: {
                    context: currentContext,
                    module: module.name,
                    phase: phase,
                    process: process + '',
                    execute: true
                },
                callback: function(options, serverSuccess, responseObject){
                
                    if (serverSuccess) {
                    
                        var response = eval('(' + responseObject.responseText + ')');
                        
                        var data = response.data;
                        
                        var success = response.success;
                        
                        var help = (!response.success) ? processList[process].help : '';
                        
                        var html = response.error ? '<pre class="console">' + response.error + '</pre>' : '';
                        html += help ? '<p class="help">' + help + '</p>' : '';
                        
                    }
                    else {
                    
                        var success = false;
                        
                        var help = 'Request failed : ' + responseObject.status + ' - ' + responseObject.statusText;
                        
                        var html = help ? '<p class="help">' + help + '</p>' : '';
                        
                    }
                    
                    var optional = processList[process].attributes.optional == 'yes' ? true : false;
                    
                    var getLabel = function(process, rank){
                    
                        var label = '';
                        
                        if (process.label) {
                            label = process.label;
                        }
                        else 
                            if (process.name && process.name == 'check') {
                            
                                label = 'Check';
                                
                                if (process.attributes.type) {
                                    if (process.attributes.type == 'syscommand') {
                                        label += ' system command';
                                    }
                                    else 
                                        if (process.attributes.type == 'phpfunction') {
                                            label += ' php function';
                                        }
                                        else 
                                            if (process.attributes.type == 'pearmodule') {
                                                label += ' pear module';
                                            }
                                            else 
                                                if (process.attributes.type == 'apachemodule') {
                                                    label += ' apache module';
                                                }
                                                
                                                else {
                                                    label += ' ' + process.attributes.type;
                                                }
                                }
                                
                                if (process.attributes.function) {
                                    label += ' ' + process.attributes.function;
                                }
                                
                                if (process.attributes.command) {
                                    label += ' ' + process.attributes.command;
                                }
                                
                                if (process.attributes.class) {
                                    label += ' ' + process.attributes.class;
                                }
                                
                                if (process.attributes.module) {
                                    label += ' ' + process.attributes.module;
                                }
                                
                            }
                            else 
                                if (process.attributes.command) {
                                    label = 'Command ' + process.attributes.command;
                                }
                                else {
                                    label = 'Process ' + rank;
                                }
                        
                        return label;
                    }
                    
                    var label = getLabel(processList[process], process);
                    
                    iconCls = success ? 'x-icon-ok' : optional ? 'x-icon-warning' : 'x-icon-ko';
                    
                    var panel = new Ext.Panel({
                        collapsible: help || response.error,
                        collapsed: success,
                        title: label,
                        iconCls: iconCls,
                        html: html,
                        border: false,
                        style: 'padding:0px;'
                    });
                    
                    processpanel[module.name].add(panel);
                    
                    if (process == processList.length - 1 && currentPhaseList.length - 1 == currentPhaseIndex) {
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
						
						//Auto-continue
						processpanel[module.name].statustext.show();
                        processpanel[module.name].processbutton.disable();
                        processpanel[module.name].retrybutton.disable();
						
						currentPhaseIndex++;
                        executePhaseList(operation);
						return; 
                    }
					
                    //if (success || optional) {
					if(success && !optional){
                        processList[process].executed = true;
                        executeProcessList(module, phase, operation);
						return;
                    }
                    					
					if (!success && !optional) {
                        processpanel[module.name].processbutton.hide();
                        processpanel[module.name].retrybutton.enable();
                        processpanel[module.name].statustext.hide();
                        processpanel[module.name].ignorebutton.enable();
                        processpanel[module.name].ignorebutton.show();
                        
                    }
					
					if (!success && optional) {
                        processpanel[module.name].processbutton.show();
                        processpanel[module.name].processbutton.enable();
                        processpanel[module.name].ignorebutton.disable();
                        processpanel[module.name].ignorebutton.hide();
                        processpanel[module.name].statustext.hide();
                        
                    }
                    
                    
			        
                }
            
            });
            
        }
        else {
            // if there is no process to execute in this phase go on to next phase.
            currentPhaseIndex++;
            executePhaseList(operation);
        }
        
    }
    
    function setModuleStatusInstalled(module, operation){
		
        Ext.Ajax.request({
            url: 'wiff.php',
            params: {
                setStatus: true,
                context: currentContext,
                module: module.name,
                status: 'installed',
                errorstatus: ''
            },
            callback: function(option, success, responseObject){
            
                // Phase execution is over
                // Proceed to next module to install
                installedStore[currentContext].load();
                availableStore[currentContext].load();
				
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
    
});