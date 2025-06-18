jQuery(function($){
  function updateSummaries() {
    $('#botsauto-editor .botsauto-phase').each(function(){
      var title = $(this).find('.phase-field').first().val() || 'Fase';
      $(this).find('summary').text(title);
    });
  }

  $('#botsauto-add-phase').on('click', function(e){
    e.preventDefault();
    var tpl = $('#botsauto-phase-template').html();
    $('#botsauto-editor').append(tpl);
    updateSummaries();
  });

  $(document).on('click','.botsauto-add-item', function(e){
    e.preventDefault();
    var tpl = $('#botsauto-item-template').html();
    $(this).closest('.botsauto-phase').find('.botsauto-items').append(tpl);
  });

  $(document).on('click','.botsauto-remove-item', function(e){
    e.preventDefault();
    var phase = $(this).closest('.botsauto-phase');
    $(this).closest('.botsauto-item').remove();
    if(!phase.find('.botsauto-item').length){
      phase.remove();
    }
    updateSummaries();
  });

  $(document).on('click','.botsauto-remove-phase', function(e){
    e.preventDefault();
    $(this).closest('.botsauto-phase').remove();
    updateSummaries();
  });

  $(document).on('input','.phase-field', updateSummaries);

  $('#botsauto-form').on('submit', function(){
    var lines = [];
    $('#botsauto-editor .botsauto-phase').each(function(){
      var phase = $(this).find('.phase-field').first().val();
      var desc  = $(this).find('.desc-field').first().val();
      $(this).find('.botsauto-item').each(function(){
        var question = $(this).find('.question-field').val();
        var item = $(this).find('.item-field').val();
        lines.push((phase||'')+'|'+(desc||'')+'|'+(question||'')+'|'+(item||''));
      });
    });
    $('#botsauto_content').val(lines.join("\n"));
  });

  updateSummaries();
});
