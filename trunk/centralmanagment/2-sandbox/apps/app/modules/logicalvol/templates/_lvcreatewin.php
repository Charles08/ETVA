<?php
include_partial('asynchronousJob/functions');
?>
<script>

/*
 * validation type
 * validates lvsize form field
 * Cannot exceed volume group size
 */
Ext.apply(Ext.form.VTypes, {

    lvsize : function(val, field) {
        if (field.totallvsize) {            
            var tsize = Ext.getCmp(field.totallvsize);
            return (val > 0 && val <= parseFloat(tsize.getValue()));
        }

        return true;
    },
    lvsizeText : <?php echo json_encode(__('Cannot exceed total volume group size')) ?>
});

Ext.ns("lvwin.createForm");

lvwin.createForm.Main = function(node_id, level) {

    Ext.QuickTips.init();   	

    this.level = level;
    this.node_id = node_id;    

    this.totalvgsize = new Ext.form.Hidden({
   //     id: 'total-vg-size',
        name:'total-vg-size'
    });

    this.lvname = new Ext.form.TextField({        
        fieldLabel: <?php echo json_encode(__('Logical volume name')) ?>,
        allowBlank: false,
        name:'lvname',        
        maxLength: 50,
        selectOnFocus:true,
	    anchor: '90%'
    });


    this.lvsize = new Ext.form.NumberField({
       // id: 'form-lvsize',
        fieldLabel: <?php echo json_encode(__('Logical volume size (MB)')) ?>,
        name: 'size',
        maxLength: 50,
        vtype: 'lvsize',
        allowBlank: false,
        totallvsize: this.totalvgsize.id,
        //'total-vg-size',
	    anchor: '90%'
    });

    var baseParams;
    if(level == 'cluster'){
        baseParams = {'cid':node_id,'level':level};
    }else if(level == 'node'){
        baseParams = {'nid':node_id,'level':level};
    }else{
        baseParams = {'nid':node_id};
    }

    this.lvsnapshotusage = new Ext.form.SliderField({
                                ref: 'lvsnapshotusage',
                                id: 'lvsnapshotusage',
                                name: 'snapshotusage',
                                anchor: '95%',
                                tipText: function(thumb){
                                            return String(thumb.value) + '%';
                                },
                                value: 20,
                                fieldLabel: <?php echo json_encode(__('Snapshot usage (%)')) ?>,
                            });

    this.lvformat = new Ext.form.ComboBox({
                    ref:'lvformat',
                    name:'format',
                    id:'lvformat'
                    ,editable: false
                    ,typeAhead: false
                    ,fieldLabel: <?php echo json_encode(__('Format')) ?>,
                    width:150,hiddenName:'format'
                    ,valueField: 'format',displayField: 'format',forceSelection: true,emptyText: <?php echo json_encode(__('Select format...')) ?>
                    ,store: new Ext.data.ArrayStore({
                            fields: ['format'],
                            data : <?php
                                        /*
                                         * build interfaces model dynamic
                                         */
                                        $disk_formats = sfConfig::get('app_disk_formats');
                                        $disk_format_elem = array();

                                        foreach($disk_formats as $eformat)
                                            $disk_format_elem[] = '['.json_encode($eformat).']';
                                        echo '['.implode(',',$disk_format_elem).']'."\n";
                                    ?>
                            })
                    ,mode: 'local'
                    ,lastQuery:''
                    //,allowBlank:false
                    ,triggerAction: 'all'
    });

    this.vg = new Ext.form.ComboBox({
                
        valueField:'name'

        // could be undefined as we use custom template
        ,displayField:'name'

        // query all records on trigger click
        ,triggerAction:'all'

        // minimum characters to start the search
        ,minChars:2

        // do not allow arbitrary values
        ,forceSelection:true

        // otherwise we will not receive key events
        ,enableKeyEvents:true      

        // make the drop down list resizable
        ,resizable:true

        // we need wider list for paging toolbar
        ,minListWidth:250

        // force user to fill something
        ,allowBlank:false

        // store getting items from server
        ,store:new Ext.data.JsonStore({
            root:'data'
            ,totalProperty:'total'
            ,fields:[
                ,{name:'id'}
                ,{name:'name', type:'string'}
                ,{name:'size', mapping:'value', type:'string'}
            ]
            ,url:<?php echo json_encode(url_for('volgroup/jsonListFree'))?>
            ,baseParams:baseParams
            ,listeners:{
                'loadexception':function(store,options,resp,error){
                    
                    console.log(resp);

                    if(resp.status==401) return;

                    var msg = <?php echo json_encode(__(EtvaVolumegroupPeer::_NOTAVAILABLE_)) ?>;

                    if( resp.responseText ){
                        var response = Ext.util.JSON.decode(resp.responseText);
                        msg = response['error'];
                    }

                    Ext.Msg.show({title: 'Error',
                        buttons: Ext.MessageBox.OK,
                        msg: msg,
                        icon: Ext.MessageBox.ERROR});
                }

            }
        })
        // concatenate vgname and size (MB)
        ,tpl:'<tpl for="."><div class="x-combo-list-item">{name} ({[byte_to_MBconvert(values.size,2,"floor")]} MB)</div></tpl>'

        // listeners
        ,listeners:{
            // sets raw value to concatenated last and first names
             select:{scope:this,fn:function(combo, record, index) {
                var size = byte_to_MBconvert(record.get('size'),2,'floor');
                combo.setRawValue(record.get('name') + ' (' + size+' MB)');
                //Ext.getCmp('total-vg-size').setValue(size);
                this.totalvgsize.setValue(size);
                //Ext.getCmp('form-lvsize').setValue(size);
                this.lvsize.setValue(size);
                
            }}
            // repair raw value after blur
            ,blur:function() {                
                var val = this.getRawValue();
                this.setRawValue.defer(1, this, [val]);
            }

            // set tooltip and validate
            ,render:function() {
                this.el.set(
                    //{qtip:'Type at least ' + this.minChars + ' characters to search in volume group'}
                    {qtip: <?php echo json_encode(__('Choose volume group')) ?>}
                );
                //this.validate();
            }
            // requery if field is cleared by typing
            ,keypress:{buffer:100, fn:function() {
                if(!this.getRawValue()) {
                    this.doQuery('', true);
                }
            }}
        
        }

        // label
        ,fieldLabel: <?php echo json_encode(__('Volume Group')) ?>
        ,anchor:'90%'
    });// end this.vg
    

    // field set
    var allFields = new Ext.form.FieldSet({
        autoHeight:true,
        border:false,
        labelWidth:160,defaults:{msgTarget: 'side'},
        items: [this.lvname, this.vg, this.lvsize, this.lvformat, this.lvsnapshotusage]
    });

    // define window and pop-up - render formPanel
    lvwin.createForm.Main.superclass.constructor.call(this, {        
        bodyStyle: 'padding-top:10px;',monitorValid:true,
        items: [allFields],
        buttons: [{
            text: __('Save'),
            formBind:true,
            handler: this.sendRequest,
            scope: this
            },
            {
            text: __('Cancel'),
            scope:this,
            handler:function(){this.ownerCt.close();}
            }]// end buttons
        ,listeners:{
                render:{delay:100,fn:function(){
                        this.lvname.focus.defer(500, this.lvname);                                                                  
                }}
            }

    });// end superclass constructor    

};// end lvwin.createForm.Main function

// define public methods
Ext.extend(lvwin.createForm.Main, Ext.form.FormPanel, {
    
    /*
    * send soap request
    * on success store returned object in DB (lvStoreDB)
    */       
    sendRequest:function(){
        // if necessary fields valid...
        if(this.getForm().isValid()){            
            var lvname = this.lvname.getValue();                        
            var size = this.lvsize.getValue();
            var vg = this.vg.getValue();

            // create parameters array to pass to soap request....
            var params;
            var options;
    
            if(this.level == 'cluster'){
                options = { 'cluster':this.node_id, 'level':this.level };
            }else if(this.level == 'node'){
                options = { 'node':this.node_id, 'level':this.level };
            }else{
                options = { 'node':this.node_id };
            }

            options['persnapshotusage'] = this.lvsnapshotusage.getValue();
            options['format'] = this.lvformat.getValue();

            var scope_form = this;
            AsynchronousJob.Functions.Create( 'logicalvol', 'create',
                                                {'name':lvname, 'volumegroup': vg, 'size':size+'M'},
                                                options,
                                                function(resp,opt) { // success fh
                                                    /*var response = Ext.util.JSON.decode(resp.responseText);
                                                    Ext.ux.Logger.info(response['agent'],response['response']);*/
                                                    scope_form.fireEvent('updated');
                                                });
            /*
            var conn = new Ext.data.Connection({
                            listeners:{
                                // wait message.....
                                beforerequest:function(){

                                    Ext.MessageBox.show({
                                        title: <?php echo json_encode(__('Please wait...')) ?>,
                                        msg: <?php echo json_encode(__('Creating logical volume...')) ?>,
                                        width:300,
                                        wait:true
                                    });

                                },// on request complete hide message
                                requestcomplete:function(){Ext.MessageBox.hide();}
                                ,requestexception:function(c,r,o){
                                        Ext.MessageBox.hide();
                                        Ext.Ajax.fireEvent('requestexception',c,r,o);}
                            }
            });// end conn


            conn.request({
                url: <?php echo json_encode(url_for('logicalvol/jsonCreate'))?>,
                params: params,
                scope:this,
                success: function(resp,opt){
                    var response = Ext.util.JSON.decode(resp.responseText);
                    Ext.ux.Logger.info(response['agent'],response['response']);                    
                    this.fireEvent('updated');                                                
                    
                },
                failure: function(resp,opt) {
                    var response = Ext.util.JSON.decode(resp.responseText);
                    
                    if(response)
                    {
                        if(response['action']=='reload'){
                            
                            Ext.Msg.show({
                            title: String.format(<?php echo json_encode(__('Error {0}')) ?>,response['agent']),
                            buttons: Ext.MessageBox.OK,
                            msg: String.format(<?php echo json_encode(__('Error reloading logical volume {0}!')) ?>,lvname)+'<br>'+response['info'],
                            icon: Ext.MessageBox.ERROR});
                        }
                        else
                        {
                            Ext.Msg.show({
                                title: String.format(<?php echo json_encode(__('Error {0}')) ?>,response['agent']),
                                buttons: Ext.MessageBox.OK,
                                msg: String.format(<?php echo json_encode(__('Unable to create logical volume {0}!')) ?>,lvname)+'<br>'+response['info'],
                                icon: Ext.MessageBox.ERROR});
                        }
                    }
                                        
                }
            });// END Ajax request
            */


        }//end isValid

    }

});


</script>
