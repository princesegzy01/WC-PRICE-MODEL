jQuery(document).ready(function(event) {
    var table = jQuery('#allDistributorsProductTable').dataTable({
        "aoColumnDefs": [
            { "bSortable": false, "aTargets": [0] }
        ]
    });

    var table2 = jQuery('#allOtherProductsTable').dataTable({
        "aoColumnDefs": [
            { "bSortable": false, "aTargets": [0] }
        ]
    });

    jQuery.fn.serializeObject = function() {var o = {}; var a = this.serializeArray(); jQuery.each(a, function() {if (o[this.name] !== undefined) {if (!o[this.name].push) {o[this.name] = [o[this.name]]; } o[this.name].push(this.value || ''); } else {o[this.name] = this.value || ''; } }); return o; };
   
    // Bulk Select Products - My List
    jQuery("#bulk_select, #bulk_select2").click(function(e){ 

        table.$('tr')
            .each(function(i,row){
                var checked = jQuery('input[type="checkbox"]:checked',row).length;
                if (checked == 1){
                    jQuery('input[type="checkbox"]',row).prop("checked", false); 
                }else{
                    jQuery('input[type="checkbox"]',row).prop("checked", true); 
                }
            }); 
    });

    // Bulk Select Products - Drugstoc List
    jQuery("#bulk_select_, #bulk_select_2").click(function(e){ 

        table2.$('tr')
            .each(function(i,row){
                var checked = jQuery('input[type="checkbox"]:checked',row).length;
                if (checked == 1){
                    jQuery('input[type="checkbox"]',row).prop("checked", false); 
                }else{
                    jQuery('input[type="checkbox"]',row).prop("checked", true); 
                }
            }); 
    });   
  
    // Update Selected Products - My List and Drugstoc List Tables
    jQuery("#bulkupdate, #bulkupdate2, #bulkattach, #bulkattach2").click(function(e){
    // jQuery("#bulkupdate, #bulkupdate2").click(function(e){
        e.preventDefault(); 

        var thisid = jQuery(this).prop('id'),
            data = [];
        
        if(thisid == 'bulkupdate' || thisid == 'bulkupdate2'){
            table.$('tr')
            .each(function(i,row){
                var checked = jQuery('input[type="checkbox"]:checked',row).length;
                if (checked == 1){
                    data.push({
                        id: jQuery('input[type="checkbox"]:checked',row).val(),
                        price: jQuery('input[type="text"]',row).val()
                    }) 
                }
            }); 
        }else if(thisid == 'bulkattach' || thisid == 'bulkattach2'){
            table2.$('tr')
            .each(function(i,row){
                var checked = jQuery('input[type="checkbox"]:checked',row).length;
                if (checked == 1){
                    data.push({
                        id: jQuery('input[type="checkbox"]:checked',row).val(),
                        price: jQuery('input[type="text"]',row).val()
                    }) 
                }
            }); 
        }     

        MyAjax.products = data; // Assign data to global var
        MyAjax.distributor = jQuery("input[name='distributor']").val();

        if(data.length < 1 ) {
            alert("Please select products you wish to update"); 
            return false; 
        } 

        jQuery.ajax({
            url: MyAjax.ajaxurl,
            type: "POST",
            data: {
                action : 'bulk-update',  
                products: MyAjax.products,
                distributor: MyAjax.distributor, 
                ds_price_nouce : MyAjax.ds_price_nouce // send the nonce along with the request
            }, 
            beforeSend: function(){
                // jQuery(""+thisid).after("<img src='"+MyAjax.pluginurl+"/js/loading.gif' border='0'>");
                jQuery(".msg").after("<img src='"+MyAjax.pluginurl+"/js/loading.gif' border='0'>");
            },
            success: function(r){
                if(r.success == true){
                    console.log(r);
                    jQuery("input[type='checkbox']").prop('checked', false);
                    alert("Update Complete!");
                }else{
                    console.log(r);
                    alert("Price Update Failed!");
                }
            },
            complete: function(){
                jQuery(".msg + img").remove();
                // jQuery(""+thisid+"+ img").remove();
            }
        });
    });

    // Update Single Product 
    jQuery(document).on('click', '.btn_update_single_product_price, .btn_attach_single_product_price', function(e) { 

        e.preventDefault(); 

        var id = jQuery(this).data("id"),
            data = jQuery("#update-price-"+id).serializeObject();

        MyAjax.product_id = data.product_id;
        MyAjax.product_price = data.product_price;
        MyAjax.distributor = data.distributor;
 
        jQuery.ajax({
            url: MyAjax.ajaxurl,
            type: "POST",
            data: {
                action : 'myajax-submit',  
                p_ID: MyAjax.product_id,
                p_price: MyAjax.product_price,
                distributor: MyAjax.distributor, 
                ds_price_nouce : MyAjax.ds_price_nouce // send the nonce along with the request
            }, 
            beforeSend: function(){
                jQuery("input#id" + MyAjax.product_id).after("<img src='"+MyAjax.pluginurl+"/js/loading.gif' border='0'>");
            },
            success: function(r){
                if(r.success == true){
                    console.log(r);
                    jQuery(this).children("input[name=product_price]").val(r.price);
                }else{
                    console.log(r);
                }
            },
            complete: function(){
                jQuery("input#id" + MyAjax.product_id+"+ img").remove();
                alert("Update Complete");
            }
        });

        return false;
    });   


    // Validate all Numeric fields
    jQuery(document).on('keydown','.digit', function(e){
        var keys = [8, 9, /*16, 17, 18,*/ 19, 20, 27, 33, 34, 35, 36, 37, 38, 39, 40, 45, 46, 48, 144, 145];
        if (jQuery.inArray(e.which, keys) >= 0) {
            return true;
        }else if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });

}); 