jQuery(function($){
  function b64enc(str){return btoa(unescape(encodeURIComponent(str)));}
  function b64dec(str){return decodeURIComponent(escape(atob(str)));}
  function updateSummaries(){
    $('#botsauto-editor .botsauto-phase').each(function(){
      var t = $(this).find('.phase-field').first().val() || 'Fase';
      $(this).find('summary.phase-toggle .botsauto-phase-title').text(t);
    });
  }

  function buildEditor(){
    var content = $('#botsauto_content').val() || '';
    var lines = content.split(/\n/);
    $('#botsauto-editor').empty();
    var phase = null, questions = null;
    lines.forEach(function(line){
      if(!line) return;
      var parts = line.split('|');
      if(parts[0]!==phase){
        $('#botsauto-editor').append($('#botsauto-phase-template').html());
        var ph = $('#botsauto-editor .botsauto-phase').last();
        ph.find('.phase-field').val(parts[0]);
        ph.find('.desc-field').val(parts[1]);
        questions = ph.find('.botsauto-questions');
        phase = parts[0];
      }
      var q;
      var info = {text:'',url:''};
      if(parts.length>4 && parts[4]){
        try{info=JSON.parse(b64dec(parts[4]));}catch(e){}
      }
      if(parts[2]!=='' || !questions.children().length){
        questions.append($('#botsauto-question-template').html());
        q = questions.children().last();
        q.find('.question-field').val(parts[2]);
        q.find('.info-text').val(info.text);
        q.find('.info-url').val(info.url);
      } else {
        q = questions.children().last();
      }
      if(parts[3]){
        var items = q.find('.botsauto-items');
        items.append($('#botsauto-item-template').html());
        items.children().last().find('.item-field').val(parts[3]);
      }
    });
    
    updateSummaries();
  }

  $('#botsauto-add-phase').on('click',function(e){
    e.preventDefault();
    $('#botsauto-editor').append($('#botsauto-phase-template').html());
    updateSummaries();
  });

  $(document).on('click','.botsauto-add-question',function(e){
    e.preventDefault();
    $(this).closest('.botsauto-phase').find('.botsauto-questions').append($('#botsauto-question-template').html());
    updateSummaries();
  });



  $(document).on('click','.botsauto-add-item',function(e){
    e.preventDefault();
    $(this).closest('.botsauto-question').find('.botsauto-items').append($('#botsauto-item-template').html());
  });


  $(document).on('click','.botsauto-remove-item',function(e){
    e.preventDefault();
    var q = $(this).closest('.botsauto-question');
    $(this).closest('.botsauto-item').remove();
  });

  $(document).on('click','.botsauto-remove-question',function(e){
    e.preventDefault();
    var phase = $(this).closest('.botsauto-phase');
    $(this).closest('.botsauto-question').remove();
    if(!phase.find('.botsauto-question').length){
      phase.remove();
    }
    updateSummaries();
  });

  $(document).on('click','.botsauto-remove-phase',function(e){
    e.preventDefault();
    $(this).closest('.botsauto-phase').remove();
    updateSummaries();
  });

  $(document).on('input','.phase-field',updateSummaries);

  $('form#post').on('submit',function(){
    var lines = [];
    $('#botsauto-editor .botsauto-phase').each(function(){
      var phase = $(this).find('.phase-field').first().val();
      var desc  = $(this).find('.desc-field').first().val();
        $(this).find('.botsauto-question').each(function(){
          var question = $(this).find('.question-field').first().val();
          var infoText = $(this).find('.info-text').val();
          var infoUrl  = $(this).find('.info-url').val();
          var info = '';
          if(infoText || infoUrl){
            info = b64enc(JSON.stringify({text:infoText,url:infoUrl}));
          }
          var items = $(this).find('.botsauto-item');
          if(!items.length){
            lines.push((phase||'')+'|'+(desc||'')+'|'+(question||'')+'||'+info);
          }else{
            items.each(function(i){
              var item = $(this).find('.item-field').val();
              var q = i===0 ? question : '';
              var inf = i===0 ? info : '';
              lines.push((phase||'')+'|'+(desc||'')+'|'+(q||'')+'|'+(item||'')+'|'+inf);
            });
          }
        });
    });
    $('#botsauto_content').val(lines.join('\n'));
  });
  $('#botsauto-import-btn').on('click',function(e){
    e.preventDefault();
    var id = $('#botsauto-import-select').val();
    if(!id) return;
    $.get(botsautoAjax.ajaxurl,{action:'botsauto_import',id:id},function(resp){
      if(resp.success){
        $('#botsauto_content').val(resp.data);
        buildEditor();
      }
    });
  });

  $(document).on('click','.botsauto-shortcode',function(e){
    e.preventDefault();
    var val = $(this).data('shortcode');
    var tmp = $('<input>');
    $('body').append(tmp);
    tmp.val(val).select();
    document.execCommand('copy');
    tmp.remove();
  });

  function tryInit(){
    if($('#botsauto-editor').length){
      buildEditor();
    }else{
      setTimeout(tryInit,300);
    }
  }
  tryInit();
});
