(function(){function r(e,n,t){function o(i,f){if(!n[i]){if(!e[i]){var c="function"==typeof require&&require;if(!f&&c)return c(i,!0);if(u)return u(i,!0);var a=new Error("Cannot find module '"+i+"'");throw a.code="MODULE_NOT_FOUND",a}var p=n[i]={exports:{}};e[i][0].call(p.exports,function(r){var n=e[i][1][r];return o(n||r)},p,p.exports,r,e,n,t)}return n[i].exports}for(var u="function"==typeof require&&require,i=0;i<t.length;i++)o(t[i]);return o}return r})()({1:[function(require,module,exports){
"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.DrupalImageEditor = void 0;

/*globals Drupal:false */

/*globals JSONEditor:false */

/*globals jQuery:false */

/**
 * @file DrupalImageEditor class.
 *
 * @external Drupal
 *
 * @external JSONEditor
 *
 * @external jQuery
 */
var DrupalImageEditor = JSONEditor.AbstractEditor.extend({
  getNumColumns: function getNumColumns() {
    return 4;
  },
  build: function build() {
    var _this = this;

    this.title = this.header = this.label = this.theme.getFormInputLabel(this.getTitle(), this.isRequired()); // Editor options.
    // @todo Replace JSONEditor.defaults with this.defaults.

    this.options = jQuery.extend({}, {
      'title': 'Browse',
      'icon': '',
      'image_url': '/'
    }, JSONEditor.defaults.options.drupal_image || {}, this.options.drupal_image || {}); // Don't show uploader if this is readonly

    if (!this.schema.readOnly && !this.schema.readonly) {
      this.input = this.theme.getFormInputField('text');
      this.button = this.getButton(this.path + '-media', 'upload', Drupal.t('Select/Upload Media')); // @todo: Add support for multiple file/image URL editors.

      var media_library_settings = 'media_library_opener_id=patternkit.opener.jsonlibrary' + '&' + encodeURIComponent('media_library_allowed_types[0]') + '=image' + '&media_library_selected_type=image' + '&media_library_remaining=1' + '&' + encodeURIComponent('media_library_opener_parameters[field_widget_id]') + '=' + this.path;
      this.input.addEventListener('change', function (e) {
        e.preventDefault();
        e.stopPropagation();

        _this.setValue(e.target.value);
      });
      this.button.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation(); // @see /core/misc/dialog/dialog.ajax.es6.js

        var $dialog = jQuery('#drupal-modal');

        if (!$dialog.length) {
          // Create the element if needed.
          $dialog = jQuery("<div id=\"drupal-modal\" class=\"ui-front\"/>").appendTo('body');
        }

        _this.dialog = Drupal.dialog($dialog.append(jQuery('<span>', {
          id: 'patternkit_image_dialog_loading'
        })), {
          title: Drupal.t('Choose Image'),
          width: 900,
          height: 900
        }).showModal();
        Drupal.ajax({
          url: _this.options.image_url + '?' + media_library_settings,
          base: 'drupal-modal',
          wrapper: 'patternkit_image_dialog_loading'
        }).execute();
      });
    }

    var description = this.schema.description || '';
    this.preview = this.theme.getFormInputDescription(description);
    this.container.appendChild(this.preview);
    this.control = this.theme.getFormControl(this.label, this.input, this.preview);
    this.container.appendChild(this.control);

    if (this.button) {
      this.container.appendChild(this.button);
    }

    window.requestAnimationFrame(function () {
      _this.refreshPreview();
    });
  },
  afterInputReady: function afterInputReady() {
    var _this2 = this;

    if (this.value) {
      var img = document.createElement('img');
      img.style.maxWidth = '100%';
      img.style.maxHeight = '100px';

      img.onload = function (event) {
        _this2.preview.appendChild(img);
      };

      img.onerror = function (error) {
        console.error('upload error', error, _this2);
      };

      img.src = this.container.querySelector('input').value;
    }

    this.theme.afterInputReady(this.input);
  },
  refreshPreview: function refreshPreview() {
    if (this.last_preview === this.value) {
      return;
    }

    this.last_preview = this.value;
    this.preview.innerHTML = '';

    if (!this.value) {
      return;
    }

    this.afterInputReady();
  },
  enable: function enable() {
    if (!this.always_disabled) {
      if (this.input) {
        this.input.disabled = false;
      }

      this._super();
    }
  },
  disable: function disable(always_disabled) {
    if (always_disabled) {
      this.always_disabled = true;
    }

    if (this.input) {
      this.input.disabled = true;
    }

    if (this.button) {
      this.button.disabled = true;
    }

    this._super();
  },
  setValue: function setValue(val) {
    if (this.value !== val) {
      this.value = val;
      this.input.value = this.value;
      this.refreshPreview();
      this.refreshWatchedFieldValues();
      this.onChange(true);
    }
  },
  destroy: function destroy() {
    if (this.preview && this.preview.parentNode) {
      this.preview.parentNode.removeChild(this.preview);
    }

    if (this.title && this.title.parentNode) {
      this.title.parentNode.removeChild(this.title);
    }

    if (this.input && this.input.parentNode) {
      this.input.parentNode.removeChild(this.input);
    }

    if (this.input && this.input.parentNode) {
      this.input.parentNode.removeChild(this.input);
    }

    this._super();
  }
});
exports.DrupalImageEditor = DrupalImageEditor;

},{}],2:[function(require,module,exports){
"use strict";

var _DrupalImageEditor = require("./DrupalImageEditor.es6");

function _typeof(obj) { "@babel/helpers - typeof"; if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") { _typeof = function _typeof(obj) { return typeof obj; }; } else { _typeof = function _typeof(obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }; } return _typeof(obj); }

(function ($, Drupal, JSONEditor) {
  'use strict';

  Drupal.behaviors.patternkitEditor = {
    attach: function attach(context, settings) {
      if (!window.JSONEditor) {
        return;
      } // Ajax command response to allow updating Editor field values.


      Drupal.AjaxCommands.prototype.patternkitEditorUpdate = function (ajax, response, status) {
        window.patternkitEditor.getEditor(response.selector).setValue(response.value);
      };

      var saveSchema = function saveSchema() {
        $('#schema_instance_config').val(JSON.stringify(window.patternkitEditor.getValue()));

        if (window.M) {
          window.M.updateTextFields();
        }
      };

      var $target = $('#editor-shadow-injection-target', context);
      $target.once('patternkit-editor').each(function () {
        var shadow = this.attachShadow({
          mode: 'open'
        });
        var theme_js = settings.patternkitEditor.themeJS;

        if (typeof theme_js === 'string') {
          theme_js = [theme_js];
        }

        for (var i = 0; theme_js && i < theme_js.length; i++) {
          var script_element = document.createElement('script');
          script_element.type = "text/javascript";
          script_element.src = theme_js[i];
          document.getElementsByTagName('head')[0].appendChild(script_element);
        }

        var editor_dom = '';

        if (settings.patternkitEditor.themeStylesheet) {
          editor_dom = '<link rel="stylesheet" id="theme_stylesheet" href="' + settings.patternkitEditor.themeStylesheet + '">';
        } // @todo Re-eval with this shadow dom webfont bug:
        // https://bugs.chromium.org/p/chromium/issues/detail?id=336876


        if (settings.patternkitEditor.iconStylesheet) {
          var icons_element = document.createElement('link');
          icons_element.rel = "stylesheet";
          icons_element.id = "icon_stylesheet";
          icons_element.href = settings.patternkitEditor.iconStylesheet;
          document.getElementsByTagName('head')[0].appendChild(icons_element);
          editor_dom += '<link rel="stylesheet" id="icon_stylesheet" href="' + settings.patternkitEditor.iconStylesheet + '">';
        }

        editor_dom += '<div id="editor_holder"></div>';
        shadow.innerHTML += editor_dom;
        var data = {
          schema: JSON.parse(settings.patternkitEditor.schemaJson),
          starting: JSON.parse(settings.patternkitEditor.startingJson)
        };
        JSONEditor.defaults.options.theme = settings.patternkitEditor.theme;
        JSONEditor.defaults.options.iconlib = settings.patternkitEditor.icons;
        JSONEditor.defaults.options.keep_oneof_values = false;
        JSONEditor.defaults.options.disable_edit_json = true;
        JSONEditor.defaults.options.disable_collapse = false;
        JSONEditor.defaults.options.collapse = false;
        JSONEditor.defaults.options.ajax = true;
        JSONEditor.defaults.options.drupal_image = {
          image_url: settings.patternkitEditor.imageUrl
        };
        JSONEditor.defaults.editors.drupal_image = _DrupalImageEditor.DrupalImageEditor;
        JSONEditor.defaults.resolvers.unshift(function (schema) {
          if (schema.type === 'string' && schema.format === 'image') {
            return 'drupal_image';
          }
        }); // Override how references are resolved.

        JSONEditor.prototype._loadExternalRefs = function (schema, callback) {
          var _this = this;

          var refs = this._getExternalRefs(schema);

          var done = 0,
              waiting = 0,
              callback_fired = false;
          $.each(refs, function (url) {
            if (_this.refs[url]) {
              return;
            }

            if (!_this.options.ajax) {
              throw "Must set ajax option to true to load external ref " + url;
            }

            _this.refs[url] = 'loading';
            waiting++;
            var r = new XMLHttpRequest();
            var uri = settings.path.baseUrl + url + '/schema';
            r.open("GET", uri, true);

            r.onreadystatechange = function () {
              if (r.readyState !== 4) {
                return;
              } // Request succeeded.


              if (r.status === 200) {
                var response;

                try {
                  response = JSON.parse(r.responseText);
                } catch (e) {
                  window.console.log(e);
                  throw "Failed to parse external ref " + url;
                }

                if (!response || _typeof(response) !== "object") {
                  throw "External ref does not contain a valid schema - " + url;
                }

                _this.refs[url] = response;

                _this._loadExternalRefs(response, function () {
                  done++;

                  if (done >= waiting && !callback_fired) {
                    callback_fired = true;
                    callback();
                  }
                });
              } // Request failed.
              else {
                  window.console.log(r);
                  throw "Failed to fetch ref via ajax- " + url;
                }
            };

            r.send();
          });

          if (!waiting) {
            callback();
          }
        }; // Initialize the editor with a JSON schema.


        var config = {
          schema: data.schema,
          refs: {}
        };

        if (_typeof(data.starting) === 'object' && !$.isEmptyObject(data.starting)) {
          config.startval = data.starting;
        }

        window.patternkitEditor = new JSONEditor($target[0].shadowRoot.getElementById('editor_holder'), config);
        JSONEditor.plugins.sceditor.emoticonsEnabled = false;
        window.patternkitEditor.on('ready', function () {
          if (window.M) {
            window.M.updateTextFields();
          }
        });
        window.patternkitEditor.on('change', saveSchema); // Drupal triggers Ajax submit via input events.
        // This is before allowing other events, so we need to add a pre-hook
        // to trigger the editor update with latest field values.
        // @TODO Add handling for AJAX errors and re-attach.

        var parent_call = Drupal.Ajax.prototype.beforeSubmit;

        Drupal.Ajax.prototype.beforeSubmit = function (formValues, elementSettings, options) {
          if (window.patternkitEditor) {
            var index = formValues.findIndex(function (o) {
              return o.name === "settings[instance_config]";
            });

            if (index !== -1) {
              window.patternkitEditor.disable();
              saveSchema();
              formValues[index] = {
                name: "settings[instance_config]",
                value: JSON.stringify(window.patternkitEditor.getValue()),
                type: "hidden",
                required: false
              };
              window.patternkitEditor.destroy();
              delete window.patternkitEditor;
            }
          }

          parent_call.call(this, formValues, elementSettings, options);
        };

        $('[data-drupal-selector="edit-actions-submit"]').parent('form').once().each(function () {
          $(this).on('submit', function (e) {
            e.preventDefault();
            window.patternkitEditor.disable();
            saveSchema();
            window.patternkitEditor.destroy();
            delete window.patternkitEditor;
            $(this).off('submit');
          });
        });
      });
    }
  };
})(jQuery, Drupal, JSONEditor);

},{"./DrupalImageEditor.es6":1}]},{},[2]);
