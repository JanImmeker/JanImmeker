jQuery(function($){
  function gatherAdv(){
    var adv = {};
    $('input[name^="botsauto_adv_style"]').each(function(){
      var m = this.name.match(/botsauto_adv_style\[([^\]]+)\]\[([^\]]+)\]/);
      if(!m) return;
      if(!adv[m[1]]) adv[m[1]] = {};
      adv[m[1]][m[2]] = $(this).val();
    });
    return adv;
  }
  function gatherBase(){
    return {
      primary: $('input[name="botsauto_style[primary]"]').val(),
      text: $('input[name="botsauto_style[text]"]').val(),
      background: $('input[name="botsauto_style[background]"]').val(),
      font: $('select[name="botsauto_style[font]"]').val(),
      image: $('#botsauto-image').val(),
      image_align: $('select[name="botsauto_style[image_align]"]').val(),
      image_width: $('input[name="botsauto_style[image_width]"]').val()
    };
  }
  function updatePreview(){
    var style = gatherBase();
    var adv = gatherAdv();
    var w = '#botsauto-preview';
    var css = '';
    css += w+' *,'+w+' *::before,'+w+' *::after{box-sizing:border-box;margin:0;padding:0;}';
    if(adv.container){
      css += w+'{color:'+style.text+';background:'+style.background+';font-size:'+adv.container['font-size']+';padding:'+adv.container.padding+';font-family:'+style.font+';}';
    }
    if(adv.phase){
      css += w+' .botsauto-phase>summary{color:'+adv.phase['text-color']+';background:'+adv.phase['background-color']+';font-size:'+adv.phase['font-size']+';font-weight:'+adv.phase['font-weight']+';list-style:none;position:relative;padding-left:1.2em;padding-top:10px;padding-bottom:10px;}';
      css += w+' .botsauto-phase>summary::-webkit-details-marker{display:none;}';
      css += w+' .botsauto-phase>summary::before{content:"\25B6";position:absolute;left:0;}';
      css += w+' .botsauto-phase[open]>summary::before{content:"\25BC";}';
    }
    if(adv.question){
      css += w+' .botsauto-question-text{color:'+adv.question['text-color']+';font-size:'+adv.question['font-size']+';font-style:'+adv.question['font-style']+';margin:0 0 .2em;flex-basis:100%;}';
    }
    css += w+' .botsauto-header label{color:'+style.primary+';}';
    if(style.image){
      css += w+' .botsauto-logo{text-align:'+style.image_align+';margin-bottom:1em;}';
      css += w+' .botsauto-logo img{max-width:'+style.image_width+'px;height:auto;}';
    } else {
      css += w+' .botsauto-logo{display:none;}';
    }
    if(adv.item){
      css += w+' .botsauto-checklist label{color:'+adv.item['text-color']+';font-size:'+adv.item['font-size']+';display:inline-block;vertical-align:middle;}';
    }
    if(adv.checked){
      css += w+' input:checked+label{color:'+adv.checked['text-color']+';text-decoration:'+adv.checked['text-decoration']+';}';
    }
    if(adv.checkbox){
      css += w+' .botsauto-checkbox{accent-color:'+adv.checkbox.color+';width:'+adv.checkbox.size+';height:'+adv.checkbox.size+';}';
    }
    if(adv.button){
      css += w+' .button-primary{background:'+adv.button['background-color']+';color:'+adv.button['text-color']+';padding:'+adv.button.padding+';border-radius:'+adv.button['border-radius']+';}';
    }
   if(adv.field){
      css += w+' input[type=text],'+w+' input[type=email]{background:'+adv.field['background-color']+';color:'+adv.field['text-color']+';border-color:'+adv.field['border-color']+';border-radius:'+adv.field['border-radius']+';border-style:'+adv.field['border-style']+';border-width:'+adv.field['border-width']+';width:'+adv.field.width+';box-sizing:border-box;}';
   }
    if(adv.note){
       css += w+' .botsauto-note textarea{color:'+adv.note['text-color']+';background:'+adv.note['background-color']+';font-size:'+adv.note['font-size']+';}';
    }
   $('#botsauto-preview-style').text(css);
 }
  $('.color-field, select[name^="botsauto_style"], input[name^="botsauto_adv_style"], #botsauto-image, input[name="botsauto_style[image_width]"], select[name="botsauto_style[image_align]"]').on('input change', updatePreview);
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
  updatePreview();
});
