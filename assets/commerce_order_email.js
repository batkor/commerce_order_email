(function ($) {
  Drupal.behaviors.commerceOrderEmail = {
    attach: function (context, settings) {
      $('#edit-preview-submit').mousedown(function () {
        let $editors = $('.CodeMirror');
        $.each($editors, function (key, editor) {
          editor.CodeMirror.save();
        });
      })
    }
  }
})(jQuery);