jQuery(function($){
  function updateSummaries(){
    $('#botsauto-editor .botsauto-phase').each(function(){
      var t = $(this).find('.phase-field').first().val() || 'Fase';
      $(this).find('summary').text(t);
    });
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

  $('#botsauto-form').on('submit',function(){
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

  updateSummaries();
});
