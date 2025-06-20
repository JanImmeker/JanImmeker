jQuery(function($){
  function updateSummaries(){
    $('#botsauto-editor .botsauto-phase').each(function(){
      var t = $(this).find('.phase-field').first().val() || 'Fase';
      $(this).find('summary').text(t);
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
      if(parts[2]!=='' || !questions.children().length){
        questions.append($('#botsauto-question-template').html());
        q = questions.children().last();
        q.find('.question-field').val(parts[2]);
      } else {
        q = questions.children().last();
      }
      var items = q.find('.botsauto-items');
      items.append($('#botsauto-item-template').html());
      items.children().last().find('.item-field').val(parts[3]);
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
  });

  $(document).on('click','.botsauto-add-item',function(e){
    e.preventDefault();
    $(this).closest('.botsauto-question').find('.botsauto-items').append($('#botsauto-item-template').html());
  });

  $(document).on('click','.botsauto-remove-item',function(e){
    e.preventDefault();
    var q = $(this).closest('.botsauto-question');
    $(this).closest('.botsauto-item').remove();
    if(!q.find('.botsauto-item').length){
      q.remove();
    }
    if(!$('#botsauto-editor .botsauto-phase').has(q).length){
      // handled below
    }
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
        $(this).find('.botsauto-item').each(function(i){
          var item = $(this).find('.item-field').val();
          var q = i===0 ? question : '';
          lines.push((phase||'')+'|'+(desc||'')+'|'+(q||'')+'|'+(item||''));
        });
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

  buildEditor();
});
