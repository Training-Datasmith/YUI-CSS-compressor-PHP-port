$(function(){

    var input_css = $('#input-css')
      , output_css = $('#output-css')
      , output_container = $('#output-container')
      , original_size = $('#original-size')
      , compressed_size = $('#compressed-size')
      , bytes_saved = $('#bytes-saved')
      , compression_ratio = $('#compression-ratio')
      , compress_btn = $('#compress-btn')
      , less_console = $('#less-error-message')

        /**
         * Prints LESS compilation errors
         */
      , less_error = function(e) {
            var content, errorline
              , template = '<li><label>{line}</label><pre class="{class}">{content}</pre></li>'
              , error = [];

            content = '<h3>'  + (e.type || "Syntax") + "Error: " + (e.message || 'There is an error in your .less file') +
                      '</h3>' + '<p>';

            errorline = function (e, i, classname) {
                if (e.extract[i] != undefined) {
                    error.push(template.replace(/\{line\}/, (window.parseInt(e.line, 10) || 0) + (i - 1))
                                       .replace(/\{class\}/, classname)
                                       .replace(/\{content\}/, e.extract[i]));
                }
            };

            if (e.stack) {
                content += '<br/>' + e.stack.split('\n').join('<br/>');
            } else if (e.extract) {
                errorline(e, 0, '');
                errorline(e, 1, 'line');
                errorline(e, 2, '');
                content += 'on line ' + e.line + ', column ' + (e.column + 1) + ':</p>' +
                            '<ul>' + error.join('') + '</ul>';
            }

            less_console.html(content).slideDown('fast');
      }

        /**
         * Compresses user's CSS with the PHP port of the YUI compressor
         */
      , compress = function(form_data) {
            $.post(window.location.href, form_data, function(data, text_status, jq_xhr){
                less_console.slideUp('fast');

                output_css.val(data.css);
                original_size.html(data.original_size);
                compressed_size.html(data.compressed_size);
                bytes_saved.html(data.bytes_saved);
                compression_ratio.html(data.compression_ratio);

                output_container.slideDown('fast');

                compress_btn.button('reset');
            }, 'json');
      };

    $('#options-form').on('submit', function(e){
        e && e.preventDefault();

        var data = {
            css: input_css.val(),
            options: $(this).serialize()
        };

        compress_btn.button('loading');

        if (!!$('#enable-less:checked').val()) {
            try {
              new(less.Parser)().parse(data.css, function (err, tree) {
                  if (err) {
                      less_error(err);
                      compress_btn.button('reset');
                  } else {
                      data.css = tree.toCSS();
                      compress(data);
                  }
              });
            } catch (err) {
              less_error(err);
              compress_btn.button('reset');
            }
        } else {
            compress(data);
        }
    });

});
