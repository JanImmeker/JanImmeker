jQuery(function($){
  function gatherAdv(){
    var adv = {};
    $('input[name^="botsauto_adv_style"], select[name^="botsauto_adv_style"]').each(function(){
      var m = this.name.match(/botsauto_adv_style\[([^\]]+)\]\[([^\]]+)\]/);
      if(!m) return;
      if(!adv[m[1]]) adv[m[1]] = {};
      var val;
      if(this.type==='checkbox') val=this.checked?'1':'0';
      else val=$(this).val();
      adv[m[1]][m[2]] = val;
    });
    return adv;
  }
  function gatherBase(){
    return {
      primary: $('input[name="botsauto_style[primary]"]').val(),
      text: $('input[name="botsauto_style[text]"]').val(),
      background: $('input[name="botsauto_style[background]"]').val(),
      font: $('select[name="botsauto_style[font]"]').val(),
      checklist_title: $('input[name="botsauto_style[checklist_title]"]').val(),
      title_position: $('select[name="botsauto_style[title_position]"]').val(),
      image: $('#botsauto-image').val(),
      image_align: $('select[name="botsauto_style[image_align]"]').val(),
      image_width: $('input[name="botsauto_style[image_width]"]').val(),
      note_icon: $('input[name="botsauto_style[note_icon]"]').val(),
      note_icon_color: $('input[name="botsauto_style[note_icon_color]"]').val(),
      done_icon: $('input[name="botsauto_style[done_icon]"]').val(),
      done_icon_color: $('input[name="botsauto_style[done_icon_color]"]').val(),
      rotate_notice: $('textarea[name="botsauto_style[rotate_notice]"]').val()
    };
  }
  function updatePreview(){
    var style = gatherBase();
    var adv = gatherAdv();
    $('.botsauto-logo-title').attr('class','botsauto-logo-title '+style.title_position);
    $('.botsauto-title').text(style.checklist_title);
    var w = '#botsauto-preview';
    var css = '';
    css += w+' *,'+w+' *::before,'+w+' *::after{box-sizing:border-box;margin:0;padding:0;}';
    if(adv.container){
      css += w+'{color:'+style.text+';background:'+style.background+';font-size:'+adv.container['font-size']+';padding:'+adv.container.padding+';font-family:'+style.font+';}';
    }
    if(adv.phase){
      css += w+' .botsauto-phase>details>.phase-toggle{color:'+adv.phase['text-color']+';background:'+adv.phase['background-color']+';font-size:'+adv.phase['font-size']+';font-weight:'+adv.phase['font-weight']+';list-style:none;display:flex;align-items:center;cursor:pointer;width:100%;box-sizing:border-box;padding:'+adv.phase['padding']+' !important;}';
      css += w+' .botsauto-phase>details>.phase-toggle::-webkit-details-marker{display:none;}';
      css += w+" .botsauto-phase>details>.phase-toggle::marker{content:"";font-size:0;}";
      if(adv.phase_icon && adv.phase_icon.position==='right'){ css += w+' .botsauto-phase>details>.phase-toggle{flex-direction:row-reverse;}'; }
      css += w+' .botsauto-phase-icon{color:'+adv.phase_icon.color+';font-size:'+adv.phase_icon.size+';padding:'+adv.phase_icon.padding+';display:inline-flex;align-items:center;}';
      css += w+' .botsauto-phase-icon .expanded{display:none;}';
      css += w+' .botsauto-phase[open] .botsauto-phase-icon .collapsed{display:none;}';
      css += w+' .botsauto-phase[open] .botsauto-phase-icon .expanded{display:inline;}';
      if(adv.phase_icon && adv.phase_icon.animation==='1'){ css += w+' .botsauto-phase-icon{transition:transform .2s;}'+w+' .botsauto-phase[open] .botsauto-phase-icon{transform:rotate(90deg);}'; }
    }
    if(adv.question){
      css += w+' .botsauto-question-row{color:'+adv.question['text-color']+';font-size:'+adv.question['font-size']+';font-style:'+adv.question['font-style']+';margin:0 0 .25em;flex:1 1 100%;display:flex!important;align-items:center;justify-content:space-between;flex-wrap:nowrap;}';
      css += w+' .botsauto-question-row .botsauto-question-label{flex:1 1 auto;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}';
      css += w+' .botsauto-question-row details.botsauto-info{flex-shrink:0;margin-left:auto;display:inline-block;}';
      css += w+' .botsauto-question-row details.botsauto-info>summary{display:inline-block;cursor:pointer;margin-left:.5em;white-space:nowrap;}';
      css += w+' .botsauto-question-row details.botsauto-info>summary::-webkit-details-marker{display:none;}';
      css += w+" .botsauto-question-row details.botsauto-info>summary::marker{content:'';font-size:0;}";
      css += w+' .botsauto-answer-row{display:flex;align-items:center;width:100%;gap:.5em;}';
    }
    css += w+' .botsauto-header{margin-bottom:1em;font-family:'+style.font+';}';
    css += w+' .botsauto-logo-title{display:flex;justify-content:center;align-items:center;margin-bottom:1em;}';
    css += w+' .botsauto-logo-title.above,.botsauto-logo-title.below{flex-direction:column;}';
    css += w+' .botsauto-logo-title.left,.botsauto-logo-title.right{flex-direction:row;}';
    css += w+' .botsauto-logo-title.left .botsauto-title{margin-right:1em;}';
    css += w+' .botsauto-logo-title.right .botsauto-logo{margin-right:1em;}';
    css += w+' .botsauto-logo-title.below .botsauto-title{order:2;}';
    css += w+' .botsauto-logo-title.below .botsauto-logo{order:1;}';
    css += w+' .botsauto-logo-title.right .botsauto-title{order:2;}';
    css += w+' .botsauto-logo-title.right .botsauto-logo{order:1;}';
    css += w+' .botsauto-header .botsauto-fields{max-width:500px;margin:0 auto;}';
    css += w+' .botsauto-header .botsauto-fields p{margin:0;}';
    css += w+' .botsauto-header label{color:'+style.primary+';display:block;margin-bottom:.5em;}';
    if(style.image){
      css += w+' .botsauto-logo{text-align:'+style.image_align+';margin-bottom:0;}';
      css += w+' .botsauto-logo img{max-width:'+style.image_width+'px;height:auto;}';
    } else {
      css += w+' .botsauto-logo{display:none;}';
    }
    if(adv.title){
      css += w+' .botsauto-title{color:'+adv.title['text-color']+';background:'+adv.title['background-color']+';font-size:'+adv.title['font-size']+';font-weight:'+adv.title['font-weight']+';font-style:'+adv.title['font-style']+';padding:'+adv.title['padding']+';text-align:center;}';
    }
    css += w+" .botsauto-checklist li{display:flex;flex-wrap:wrap;align-items:flex-start;margin-bottom:.5em;padding-left:1.2em;}";
    if(adv.item){
      css += w+' .botsauto-checklist label{color:'+adv.item['text-color']+';font-size:'+adv.item['font-size']+';display:inline-block;vertical-align:middle;flex:1 1 auto;min-width:0;}';
    }
    if(adv.checked){
      css += w+' input:checked+label{color:'+adv.checked['text-color']+';text-decoration:'+adv.checked['text-decoration']+';}';
    }
    if(adv.checkbox){
      css += w+' .botsauto-checkbox{accent-color:'+adv.checkbox.color+';background:'+adv.checkbox['background-color']+';border-color:'+adv.checkbox['border-color']+';border-style:solid;border-width:1px;width:'+adv.checkbox.size+';height:'+adv.checkbox.size+';}';
    }
    if(adv.button){
      css += w+' .button-primary{background:'+adv.button['background-color']+';color:'+adv.button['text-color']+';padding:'+adv.button.padding+';border-radius:'+adv.button['border-radius']+';border-color:'+adv.button['border-color']+';}';
    }
    if(adv.info_button){
      css += w+' .botsauto-info-btn{background:'+adv.info_button['background-color']+';color:'+adv.info_button['text-color']+';padding:'+adv.info_button.padding+';border-radius:'+adv.info_button['border-radius']+';border-color:'+adv.info_button['border-color']+';border-style:solid;line-height:1;vertical-align:middle;display:inline-flex;align-items:center;justify-content:center;font-size:'+adv.info_button['font-size']+';text-align:'+adv.info_button['text-align']+';width:'+adv.info_button.width+';height:'+adv.info_button.height+';}';
    }
    if(adv.info_popup){
      css += w+' .botsauto-info-content{display:none;background:'+adv.info_popup['background-color']+';color:'+adv.info_popup['text-color']+';padding:'+adv.info_popup.padding+';border-radius:'+adv.info_popup['border-radius']+';margin:0 0 .25em;}';
    }
   if(adv.field){
      css += w+' input[type=text],'+w+' input[type=email]{background:'+adv.field['background-color']+';color:'+adv.field['text-color']+';border-color:'+adv.field['border-color']+';border-radius:'+adv.field['border-radius']+';border-style:'+adv.field['border-style']+';border-width:'+adv.field['border-width']+';width:'+adv.field.width+';box-sizing:border-box;}';
   }
    if(adv.note){
       css += w+' .botsauto-note textarea{color:'+adv.note['text-color']+';background:'+adv.note['background-color']+';font-size:'+adv.note['font-size']+';}';
    }
    css += w+' .botsauto-note-btn{background:none;border:none;color:'+style.note_icon_color+';cursor:pointer;margin-left:auto;flex-shrink:0;}';
    css += w+' .botsauto-note-btn.botsauto-done{color:'+style.done_icon_color+';}';
    css += w+' .botsauto-rotate-notice{display:none;position:fixed;bottom:0;left:0;right:0;background:'+style.primary+';color:#fff;padding:10px;text-align:center;z-index:999;}';
    if(adv.completed){
      css += w+' .botsauto-completed label{color:'+adv.completed['text-color']+';font-size:'+adv.completed['font-size']+';font-family:'+adv.completed['font-family']+';}';
    }
   $('#botsauto-preview-style').text(css);
}
  $('.color-field, input[name^="botsauto_style"], select[name^="botsauto_style"], textarea[name^="botsauto_style"], input[name^="botsauto_adv_style"], #botsauto-image, input[name="botsauto_style[image_width]"], select[name="botsauto_style[image_align]"], input[name="botsauto_style[checklist_title]"], select[name="botsauto_style[title_position]"]').on('input change', updatePreview);
  $('#botsauto-toggle-mobile').on('click',function(){
    $('#botsauto-preview-container').toggleClass('mobile');
  });
  $('#botsauto-image-btn').on('click',function(e){
    e.preventDefault();
    var frame=wp.media({title:'Selecteer afbeelding',button:{text:'Gebruik afbeelding'},multiple:false});
    frame.on('select',function(){
      var url=frame.state().get('selection').first().get('url');
      $('#botsauto-image').val(url).trigger('change');
    });
    frame.open();
  });
  $('#botsauto-reset-form').on('submit',function(e){
    if(!confirm('Weet je zeker dat je de standaardinstellingen wilt herstellen?')){
      e.preventDefault();
    }
  });
  updatePreview();
  $('#botsauto-preview').find('details.botsauto-info').each(function(){
    var d=$(this);
    var content=d.parent().next('.botsauto-info-content');
    if(content.length){
      content.css('display', d.prop('open') ? 'block' : 'none');
      d.on('toggle',function(){content.css('display', this.open ? 'block':'none');});
    }
  });
  $('#botsauto-preview').on('click','.botsauto-note-btn',function(e){
    e.preventDefault();
    var n=$(this).next('.botsauto-note');
    n.toggle();
  });
  var rotate=$('#botsauto-preview .botsauto-rotate-notice');
  function checkRotate(){
    if($('#botsauto-preview-container').hasClass('mobile')) rotate.show();
    else rotate.hide();
  }
  $('#botsauto-toggle-mobile').on('click',checkRotate);
  checkRotate();
});
