/******/ (function (modules) {
  // webpackBootstrap
  /******/ // install a JSONP callback for chunk loading
  /******/ function webpackJsonpCallback(data) {
    /******/ var chunkIds = data[0];
    /******/ var moreModules = data[1];
    /******/ var executeModules = data[2];
    /******/
    /******/ // add "moreModules" to the modules object,
    /******/ // then flag all "chunkIds" as loaded and fire callback
    /******/ var moduleId,
      chunkId,
      i = 0,
      resolves = [];
    /******/ for (; i < chunkIds.length; i++) {
      /******/ chunkId = chunkIds[i];
      /******/ if (
        Object.prototype.hasOwnProperty.call(installedChunks, chunkId) &&
        installedChunks[chunkId]
      ) {
        /******/ resolves.push(installedChunks[chunkId][0]);
        /******/
      }
      /******/ installedChunks[chunkId] = 0;
      /******/
    }
    /******/ for (moduleId in moreModules) {
      /******/ if (
        Object.prototype.hasOwnProperty.call(moreModules, moduleId)
      ) {
        /******/ modules[moduleId] = moreModules[moduleId];
        /******/
      }
      /******/
    }
    /******/ if (parentJsonpFunction) parentJsonpFunction(data);
    /******/
    /******/ while (resolves.length) {
      /******/ resolves.shift()();
      /******/
    }
    /******/
    /******/ // add entry modules from loaded chunk to deferred list
    /******/ deferredModules.push.apply(deferredModules, executeModules || []);
    /******/
    /******/ // run deferred modules when all chunks ready
    /******/ return checkDeferredModules();
    /******/
  }
  /******/ function checkDeferredModules() {
    /******/ var result;
    /******/ for (var i = 0; i < deferredModules.length; i++) {
      /******/ var deferredModule = deferredModules[i];
      /******/ var fulfilled = true;
      /******/ for (var j = 1; j < deferredModule.length; j++) {
        /******/ var depId = deferredModule[j];
        /******/ if (installedChunks[depId] !== 0) fulfilled = false;
        /******/
      }
      /******/ if (fulfilled) {
        /******/ deferredModules.splice(i--, 1);
        /******/ result = __webpack_require__(
          (__webpack_require__.s = deferredModule[0]),
        );
        /******/
      }
      /******/
    }
    /******/
    /******/ return result;
    /******/
  }
  /******/
  /******/ // The module cache
  /******/ var installedModules = {};
  /******/
  /******/ // object to store loaded and loading chunks
  /******/ // undefined = chunk not loaded, null = chunk preloaded/prefetched
  /******/ // Promise = chunk loading, 0 = chunk loaded
  /******/ var installedChunks = {
    /******/ app: 0,
    /******/
  };
  /******/
  /******/ var deferredModules = [];
  /******/
  /******/ // The require function
  /******/ function __webpack_require__(moduleId) {
    /******/
    /******/ // Check if module is in cache
    /******/ if (installedModules[moduleId]) {
      /******/ return installedModules[moduleId].exports;
      /******/
    }
    /******/ // Create a new module (and put it into the cache)
    /******/ var module = (installedModules[moduleId] = {
      /******/ i: moduleId,
      /******/ l: false,
      /******/ exports: {},
      /******/
    });
    /******/
    /******/ // Execute the module function
    /******/ modules[moduleId].call(
      module.exports,
      module,
      module.exports,
      __webpack_require__,
    );
    /******/
    /******/ // Flag the module as loaded
    /******/ module.l = true;
    /******/
    /******/ // Return the exports of the module
    /******/ return module.exports;
    /******/
  }
  /******/
  /******/
  /******/ // expose the modules object (__webpack_modules__)
  /******/ __webpack_require__.m = modules;
  /******/
  /******/ // expose the module cache
  /******/ __webpack_require__.c = installedModules;
  /******/
  /******/ // define getter function for harmony exports
  /******/ __webpack_require__.d = function (exports, name, getter) {
    /******/ if (!__webpack_require__.o(exports, name)) {
      /******/ Object.defineProperty(exports, name, {
        enumerable: true,
        get: getter,
      });
      /******/
    }
    /******/
  };
  /******/
  /******/ // define __esModule on exports
  /******/ __webpack_require__.r = function (exports) {
    /******/ if (typeof Symbol !== "undefined" && Symbol.toStringTag) {
      /******/ Object.defineProperty(exports, Symbol.toStringTag, {
        value: "Module",
      });
      /******/
    }
    /******/ Object.defineProperty(exports, "__esModule", { value: true });
    /******/
  };
  /******/
  /******/ // create a fake namespace object
  /******/ // mode & 1: value is a module id, require it
  /******/ // mode & 2: merge all properties of value into the ns
  /******/ // mode & 4: return value when already ns object
  /******/ // mode & 8|1: behave like require
  /******/ __webpack_require__.t = function (value, mode) {
    /******/ if (mode & 1) value = __webpack_require__(value);
    /******/ if (mode & 8) return value;
    /******/ if (
      mode & 4 &&
      typeof value === "object" &&
      value &&
      value.__esModule
    )
      return value;
    /******/ var ns = Object.create(null);
    /******/ __webpack_require__.r(ns);
    /******/ Object.defineProperty(ns, "default", {
      enumerable: true,
      value: value,
    });
    /******/ if (mode & 2 && typeof value != "string")
      for (var key in value)
        __webpack_require__.d(
          ns,
          key,
          function (key) {
            return value[key];
          }.bind(null, key),
        );
    /******/ return ns;
    /******/
  };
  /******/
  /******/ // getDefaultExport function for compatibility with non-harmony modules
  /******/ __webpack_require__.n = function (module) {
    /******/ var getter =
      module && module.__esModule
        ? /******/ function getDefault() {
            return module["default"];
          }
        : /******/ function getModuleExports() {
            return module;
          };
    /******/ __webpack_require__.d(getter, "a", getter);
    /******/ return getter;
    /******/
  };
  /******/
  /******/ // Object.prototype.hasOwnProperty.call
  /******/ __webpack_require__.o = function (object, property) {
    return Object.prototype.hasOwnProperty.call(object, property);
  };
  /******/
  /******/ // __webpack_public_path__
  /******/ __webpack_require__.p = "/";
  /******/
  /******/ var jsonpArray = (window["webpackJsonp"] =
    window["webpackJsonp"] || []);
  /******/ var oldJsonpFunction = jsonpArray.push.bind(jsonpArray);
  /******/ jsonpArray.push = webpackJsonpCallback;
  /******/ jsonpArray = jsonpArray.slice();
  /******/ for (var i = 0; i < jsonpArray.length; i++)
    webpackJsonpCallback(jsonpArray[i]);
  /******/ var parentJsonpFunction = oldJsonpFunction;
  /******/
  /******/
  /******/ // add entry module to deferred list
  /******/ deferredModules.push([0, "chunk-vendors"]);
  /******/ // run deferred modules when ready
  /******/ return checkDeferredModules();
  /******/
})(
  /************************************************************************/
  /******/ {
    /***/ 0: /***/ function (module, exports, __webpack_require__) {
      module.exports = __webpack_require__("cd49");

      /***/
    },

    /***/ "0a9e": /***/ function (module, exports, __webpack_require__) {
      // extracted by mini-css-extract-plugin
      /***/
    },

    /***/ 5640: /***/ function (
      module,
      __webpack_exports__,
      __webpack_require__,
    ) {
      "use strict";
      /* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_PublishFunnel_vue_vue_type_style_index_0_id_3954d822_prod_scoped_true_lang_css__WEBPACK_IMPORTED_MODULE_0__ =
        __webpack_require__("83d3");
      /* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_PublishFunnel_vue_vue_type_style_index_0_id_3954d822_prod_scoped_true_lang_css__WEBPACK_IMPORTED_MODULE_0___default =
        /*#__PURE__*/ __webpack_require__.n(
          _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_PublishFunnel_vue_vue_type_style_index_0_id_3954d822_prod_scoped_true_lang_css__WEBPACK_IMPORTED_MODULE_0__,
        );
      /* unused harmony reexport * */

      /***/
    },

    /***/ 6538: /***/ function (
      module,
      __webpack_exports__,
      __webpack_require__,
    ) {
      "use strict";
      /* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_App_vue_vue_type_style_index_0_id_60fa85a4_prod_lang_css__WEBPACK_IMPORTED_MODULE_0__ =
        __webpack_require__("0a9e");
      /* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_App_vue_vue_type_style_index_0_id_60fa85a4_prod_lang_css__WEBPACK_IMPORTED_MODULE_0___default =
        /*#__PURE__*/ __webpack_require__.n(
          _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_App_vue_vue_type_style_index_0_id_60fa85a4_prod_lang_css__WEBPACK_IMPORTED_MODULE_0__,
        );
      /* unused harmony reexport * */

      /***/
    },

    /***/ "83d3": /***/ function (module, exports, __webpack_require__) {
      // extracted by mini-css-extract-plugin
      /***/
    },

    /***/ "95b1": /***/ function (
      module,
      __webpack_exports__,
      __webpack_require__,
    ) {
      "use strict";
      /* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_Settings_vue_vue_type_style_index_0_id_7a6bd024_prod_scoped_true_lang_css__WEBPACK_IMPORTED_MODULE_0__ =
        __webpack_require__("f6c0");
      /* harmony import */ var _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_Settings_vue_vue_type_style_index_0_id_7a6bd024_prod_scoped_true_lang_css__WEBPACK_IMPORTED_MODULE_0___default =
        /*#__PURE__*/ __webpack_require__.n(
          _node_modules_mini_css_extract_plugin_dist_loader_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_2_node_modules_cache_loader_dist_cjs_js_ref_1_0_node_modules_vue_loader_lib_index_js_vue_loader_options_Settings_vue_vue_type_style_index_0_id_7a6bd024_prod_scoped_true_lang_css__WEBPACK_IMPORTED_MODULE_0__,
        );
      /* unused harmony reexport * */

      /***/
    },

    /***/ cd49: /***/ function (
      module,
      __webpack_exports__,
      __webpack_require__,
    ) {
      "use strict";
      // ESM COMPAT FLAG
      __webpack_require__.r(__webpack_exports__);

      // EXTERNAL MODULE: ./node_modules/vue/dist/vue.runtime.esm.js
      var vue_runtime_esm = __webpack_require__("2b0e");

      // CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"53d01920-vue-loader-template"}!./node_modules/cache-loader/dist/cjs.js??ref--13-0!./node_modules/babel-loader/lib!./node_modules/vue-loader/lib/loaders/templateLoader.js??ref--6!./node_modules/cache-loader/dist/cjs.js??ref--1-0!./node_modules/vue-loader/lib??vue-loader-options!./src/App.vue?vue&type=template&id=60fa85a4
      var render = function render() {
        var _vm = this,
          _c = _vm._self._c,
          _setup = _vm._self._setupProxy;
        return _c(
          "div",
          {
            attrs: {
              id: "app",
            },
          },
          [
            _c("h1", [_vm._v("LeadConnector Settings")]),
            _c("Settings", {
              attrs: {
                enableTextWidget: _vm.settings.enable_text_widget,
                apiKey: _vm.settings.api_key,
                baseURL: _vm.settings.base_URL,
              },
            }),
          ],
          1,
        );
      };
      var staticRenderFns = [];

      // CONCATENATED MODULE: ./src/App.vue?vue&type=template&id=60fa85a4

      // EXTERNAL MODULE: ./node_modules/core-js/modules/web.dom-exception.stack.js
      var web_dom_exception_stack = __webpack_require__("b7ef");

      // EXTERNAL MODULE: ./node_modules/tslib/tslib.es6.js
      var tslib_es6 = __webpack_require__("9ab4");

      // EXTERNAL MODULE: ./node_modules/vue-property-decorator/lib/index.js + 15 modules
      var lib = __webpack_require__("1b40");

      // CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"53d01920-vue-loader-template"}!./node_modules/cache-loader/dist/cjs.js??ref--13-0!./node_modules/babel-loader/lib!./node_modules/vue-loader/lib/loaders/templateLoader.js??ref--6!./node_modules/cache-loader/dist/cjs.js??ref--1-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/Settings.vue?vue&type=template&id=7a6bd024&scoped=true
      var Settingsvue_type_template_id_7a6bd024_scoped_true_render =
        function render() {
          var _vm = this,
            _c = _vm._self._c,
            _setup = _vm._self._setupProxy;
          return _c(
            "div",
            {
              staticClass: "lead-connector-settings",
              attrs: {
                id: "lead-connector-settings",
              },
            },
            [
              _c(
                "div",
                {
                  staticClass: "oauth-authorization-row",
                },
                [
                  _c(
                    "a",
                    {
                      attrs: {
                        href: this.oAuthUrl,
                        target: "_blank",
                      },
                    },
                    [
                      _c(
                        "b-button",
                        [
                          _vm._v(
                            " " +
                              _vm._s(
                                this.isAuthorizing
                                  ? "Authorizing"
                                  : "Authorize",
                              ) +
                              " ",
                          ),
                          _c("b-icon-box-arrow-up-right"),
                        ],
                        1,
                      ),
                      _c(
                        "b-modal",
                        {
                          attrs: {
                            id: "oauth-modal",
                          },
                        },
                        [_c("h2", [_vm._v("Authorizing ......")])],
                      ),
                    ],
                    1,
                  ),
                ],
              ),
              _c(
                "div",
                {
                  staticClass: "api-key-input-contaier",
                },
                [
                  _c(
                    "b-form-group",
                    {
                      attrs: {
                        id: "fieldset-api-input",
                        description:
                          "You'll find the API key under location level -> settings -> Company page",
                        label: "API key",
                        "label-for": "api-key-input",
                        "label-align": "left",
                        "invalid-feedback": _vm.apiErrorMessage,
                      },
                    },
                    [
                      _c(
                        "b-col",
                        {
                          staticStyle: {
                            padding: "0",
                          },
                          attrs: {
                            sm: "12",
                            lg: "6",
                          },
                        },
                        [
                          _c("b-form-input", {
                            attrs: {
                              id: "api-key-input",
                              placeholder: "enter API key",
                              state: _vm.isvalidApi,
                            },
                            model: {
                              value: _vm.api_key,
                              callback: function ($$v) {
                                _vm.api_key = $$v;
                              },
                              expression: "api_key",
                            },
                          }),
                          _c(
                            "b-form-invalid-feedback",
                            {
                              attrs: {
                                id: "api-key-input-feedback",
                              },
                            },
                            [
                              _c("span", {
                                domProps: {
                                  innerHTML: _vm._s(_vm.apiErrorMessage),
                                },
                              }),
                            ],
                          ),
                        ],
                        1,
                      ),
                    ],
                    1,
                  ),
                  !this.isAPIsaving
                    ? _c(
                        "b-button",
                        {
                          attrs: {
                            variant: "success",
                          },
                          on: {
                            click: function ($event) {
                              return _vm.saveAPI();
                            },
                          },
                        },
                        [_vm._v(" Save")],
                      )
                    : _c("b-spinner", {
                        staticClass: "align-middle text-success my-2",
                      }),
                ],
                1,
              ),
              _c(
                "div",
                {
                  staticClass: "accordion",
                  attrs: {
                    role: "tablist",
                  },
                },
                [
                  _c(
                    "b-card",
                    {
                      staticClass: "mb-1",
                      attrs: {
                        "no-body": "",
                      },
                    },
                    [
                      _c(
                        "b-card-header",
                        {
                          staticClass: "p-1",
                          attrs: {
                            "header-tag": "header",
                            role: "tab",
                          },
                        },
                        [
                          _c(
                            "div",
                            {
                              directives: [
                                {
                                  name: "b-toggle",
                                  rawName: "v-b-toggle.accordion-1",
                                  modifiers: {
                                    "accordion-1": true,
                                  },
                                },
                              ],
                              staticClass: "hl_wrapper-text-widget--toggle",
                              attrs: {
                                block: "",
                                variant: "info",
                              },
                            },
                            [
                              _c(
                                "div",
                                [
                                  _c("b-icon-chat-left"),
                                  _c(
                                    "span",
                                    {
                                      staticClass: "header-text",
                                    },
                                    [_vm._v("Chat Widget")],
                                  ),
                                ],
                                1,
                              ),
                              _vm.visible1
                                ? _c("b-icon", {
                                    attrs: {
                                      icon: "chevron-down",
                                    },
                                  })
                                : _vm._e(),
                              !_vm.visible1
                                ? _c("b-icon", {
                                    attrs: {
                                      icon: "chevron-right",
                                    },
                                  })
                                : _vm._e(),
                            ],
                            1,
                          ),
                        ],
                      ),
                      _c(
                        "b-collapse",
                        {
                          attrs: {
                            id: "accordion-1",
                            accordion: "my-accordion",
                            role: "tabpanel",
                          },
                          model: {
                            value: _vm.visible1,
                            callback: function ($$v) {
                              _vm.visible1 = $$v;
                            },
                            expression: "visible1",
                          },
                        },
                        [
                          _c("b-card-body", [
                            _c(
                              "div",
                              {
                                staticClass: "chat-widget-setting-root",
                              },
                              [
                                _c("input", {
                                  attrs: {
                                    type: "hidden",
                                    name: "enable_text_widget",
                                    value: "0",
                                  },
                                }),
                                _c(
                                  "b-form-checkbox",
                                  {
                                    attrs: {
                                      id: "lead_connector_setting_enable_text_widget",
                                      name: "enable_text_widget",
                                      value: "1",
                                      "unchecked-value": "0",
                                    },
                                    model: {
                                      value: _vm.chatWidgetEnable,
                                      callback: function ($$v) {
                                        _vm.chatWidgetEnable = $$v;
                                      },
                                      expression: "chatWidgetEnable",
                                    },
                                  },
                                  [_vm._v(" Enable Chat-widget ")],
                                ),
                                !this.isAPIsaving
                                  ? _c(
                                      "b-button",
                                      {
                                        on: {
                                          click: function ($event) {
                                            return _vm.saveAPI($event);
                                          },
                                        },
                                      },
                                      [
                                        _vm._v(
                                          " " +
                                            _vm._s(
                                              _vm.chatWidgetEnable === "1"
                                                ? "Pull and Save"
                                                : "Save",
                                            ),
                                        ),
                                      ],
                                    )
                                  : _c("b-spinner", {
                                      staticClass:
                                        "align-middle text-primary my-2",
                                    }),
                                _c(
                                  "label",
                                  {
                                    staticStyle: {
                                      "font-size": "10px",
                                    },
                                  },
                                  [
                                    _c(
                                      "p",
                                      {
                                        staticStyle: {
                                          "margin-top": "5px",
                                        },
                                      },
                                      [
                                        _vm._v(
                                          " We will fetch the latest settings from your account ",
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                                _c(
                                  "p",
                                  {
                                    staticClass: "text-warning mb-0",
                                  },
                                  [_vm._v(_vm._s(this.chatWidgetWarning))],
                                ),
                              ],
                              1,
                            ),
                          ]),
                        ],
                        1,
                      ),
                    ],
                    1,
                  ),
                  _c(
                    "b-card",
                    {
                      staticClass: "mb-1",
                      attrs: {
                        "no-body": "",
                      },
                    },
                    [
                      _c(
                        "b-card-header",
                        {
                          staticClass: "p-1",
                          attrs: {
                            "header-tag": "header",
                            role: "tab",
                          },
                        },
                        [
                          _c(
                            "div",
                            {
                              directives: [
                                {
                                  name: "b-toggle",
                                  rawName: "v-b-toggle.accordion-2",
                                  modifiers: {
                                    "accordion-2": true,
                                  },
                                },
                              ],
                              staticClass: "hl_wrapper-text-widget--toggle",
                              attrs: {
                                block: "",
                                variant: "info",
                              },
                            },
                            [
                              _c(
                                "div",
                                [
                                  _c("b-icon-funnel"),
                                  _c(
                                    "span",
                                    {
                                      staticClass: "header-text",
                                    },
                                    [_vm._v("Funnels")],
                                  ),
                                ],
                                1,
                              ),
                              _vm.visible2
                                ? _c("b-icon", {
                                    attrs: {
                                      icon: "chevron-down",
                                    },
                                  })
                                : _vm._e(),
                              !_vm.visible2
                                ? _c("b-icon", {
                                    attrs: {
                                      icon: "chevron-right",
                                    },
                                  })
                                : _vm._e(),
                            ],
                            1,
                          ),
                        ],
                      ),
                      _c(
                        "b-collapse",
                        {
                          attrs: {
                            id: "accordion-2",
                            accordion: "my-accordion",
                            role: "tabpanel",
                          },
                          model: {
                            value: _vm.visible2,
                            callback: function ($$v) {
                              _vm.visible2 = $$v;
                            },
                            expression: "visible2",
                          },
                        },
                        [
                          _c("b-card-body", [
                            _c(
                              "div",
                              [
                                _c("b-table", {
                                  ref: "selectableTable",
                                  attrs: {
                                    striped: "",
                                    hover: "",
                                    busy: _vm.isBusy,
                                    items: this.publishedPages,
                                    "sticky-header": "",
                                    fields: _vm.publishedPageTablefields,
                                    "select-mode": "single",
                                    "selected-variant": "",
                                  },
                                  scopedSlots: _vm._u([
                                    {
                                      key: "table-busy",
                                      fn: function () {
                                        return [
                                          _c(
                                            "div",
                                            {
                                              staticClass:
                                                "text-center text-success my-2",
                                            },
                                            [
                                              _c("b-spinner", {
                                                staticClass: "align-middle",
                                              }),
                                              _c("strong", [
                                                _vm._v("Loading..."),
                                              ]),
                                            ],
                                            1,
                                          ),
                                        ];
                                      },
                                      proxy: true,
                                    },
                                    {
                                      key: "cell(slug)",
                                      fn: function (data) {
                                        return [
                                          _vm._v(
                                            " " +
                                              _vm._s("/" + data.item.slug) +
                                              " ",
                                          ),
                                        ];
                                      },
                                    },
                                    {
                                      key: "cell(url)",
                                      fn: function (data) {
                                        return [
                                          _c(
                                            "div",
                                            [
                                              _c(
                                                "a",
                                                {
                                                  attrs: {
                                                    active: "false",
                                                    href: `${data.item.url}`,
                                                    target: "_blank",
                                                  },
                                                },
                                                [_vm._v("View ")],
                                              ),
                                              _c("b-icon-box-arrow-up-right"),
                                            ],
                                            1,
                                          ),
                                        ];
                                      },
                                    },
                                    {
                                      key: "cell(context)",
                                      fn: function (data) {
                                        return [
                                          _c(
                                            "b-button",
                                            {
                                              staticClass: "no-border",
                                              attrs: {
                                                variant: "outline-danger",
                                              },
                                              on: {
                                                click: function ($event) {
                                                  return _vm.deletePost(
                                                    $event,
                                                    data.item,
                                                  );
                                                },
                                              },
                                            },
                                            [
                                              _c("b-icon-trash", {
                                                directives: [
                                                  {
                                                    name: "b-modal",
                                                    rawName:
                                                      "v-b-modal.confirm-post-delete",
                                                    modifiers: {
                                                      "confirm-post-delete": true,
                                                    },
                                                  },
                                                ],
                                              }),
                                            ],
                                            1,
                                          ),
                                          _c(
                                            "b-button",
                                            {
                                              staticClass: "no-border",
                                              attrs: {
                                                variant: "outline-secondary",
                                              },
                                              on: {
                                                click: function ($event) {
                                                  return _vm.editFunnel(
                                                    $event,
                                                    data.item,
                                                  );
                                                },
                                              },
                                            },
                                            [_c("b-icon-pencil-square")],
                                            1,
                                          ),
                                        ];
                                      },
                                    },
                                    {
                                      key: "cell(edit_url)",
                                      fn: function (data) {
                                        return [
                                          _c(
                                            "div",
                                            [
                                              _c(
                                                "a",
                                                {
                                                  attrs: {
                                                    href: `${_vm.hostURL}/location/${_vm.location_id}/funnels-websites/funnels/${data.item.lc_funnel_id}/steps/${data.item.lc_step_id}`,
                                                    target: "_blank",
                                                  },
                                                },
                                                [_vm._v("Edit")],
                                              ),
                                              _c("b-icon-box-arrow-up-right"),
                                            ],
                                            1,
                                          ),
                                        ];
                                      },
                                    },
                                    {
                                      key: "cell(selected)",
                                      fn: function ({ rowSelected, index }) {
                                        return [
                                          rowSelected
                                            ? [
                                                _c("input", {
                                                  key: index + "selected",
                                                  attrs: {
                                                    type: "checkbox",
                                                    checked: "",
                                                  },
                                                  on: {
                                                    change: function ($event) {
                                                      return _vm.onTableCheckBox(
                                                        $event,
                                                        index,
                                                      );
                                                    },
                                                  },
                                                }),
                                                _c(
                                                  "span",
                                                  {
                                                    staticClass: "sr-only",
                                                  },
                                                  [_vm._v("Selected")],
                                                ),
                                              ]
                                            : [
                                                _c("input", {
                                                  key: index + "un - selected",
                                                  attrs: {
                                                    type: "checkbox",
                                                  },
                                                  on: {
                                                    change: function ($event) {
                                                      return _vm.onTableCheckBox(
                                                        $event,
                                                        index,
                                                      );
                                                    },
                                                  },
                                                }),
                                                _c(
                                                  "span",
                                                  {
                                                    staticClass: "sr-only",
                                                  },
                                                  [_vm._v("Not selected")],
                                                ),
                                              ],
                                        ];
                                      },
                                    },
                                  ]),
                                }),
                                _c(
                                  "b-button",
                                  {
                                    attrs: {
                                      variant: "outline-primary",
                                    },
                                    on: {
                                      click: function ($event) {
                                        return _vm.handleAddNewFunnel(
                                          $event,
                                          1,
                                        );
                                      },
                                    },
                                  },
                                  [_vm._v("Add New")],
                                ),
                              ],
                              1,
                            ),
                          ]),
                        ],
                        1,
                      ),
                    ],
                    1,
                  ),
                ],
                1,
              ),
              this.showAddNewFunnelModal
                ? _c("PublishFunnel", {
                    attrs: {
                      showModal: this.showAddNewFunnelModal,
                      onClose: this.onModalClose,
                      funnelOptions: this.funnels,
                      editPost: this.editPost,
                      home_url: this.home_url,
                      host_url: this.hostURL,
                    },
                  })
                : _vm._e(),
              _c(
                "b-modal",
                {
                  attrs: {
                    id: "confirm-post-delete",
                    title: "Delete Page ?",
                    centered: "",
                  },
                  on: {
                    ok: this.onPostDelete,
                  },
                },
                [
                  _c(
                    "p",
                    {
                      staticClass: "my-4",
                    },
                    [
                      _vm._v(
                        " Are you sure you want to delete this page from wordpress? ",
                      ),
                    ],
                  ),
                ],
              ),
              _c(
                "b-alert",
                {
                  staticClass: "position-fixed fixed-bottom m-0 rounded-0",
                  staticStyle: {
                    "z-index": "2000",
                  },
                  attrs: {
                    dismissible: "",
                    variant: _vm.alertVariant,
                  },
                  model: {
                    value: _vm.showAlertTimer,
                    callback: function ($$v) {
                      _vm.showAlertTimer = $$v;
                    },
                    expression: "showAlertTimer",
                  },
                },
                [_vm._v(" " + _vm._s(this.alertTitle) + " ")],
              ),
            ],
            1,
          );
        };
      var Settingsvue_type_template_id_7a6bd024_scoped_true_staticRenderFns =
        [];

      // CONCATENATED MODULE: ./src/components/Settings.vue?vue&type=template&id=7a6bd024&scoped=true

      // EXTERNAL MODULE: ./node_modules/core-js/modules/web.url-search-params.delete.js
      var web_url_search_params_delete = __webpack_require__("88a7");

      // EXTERNAL MODULE: ./node_modules/core-js/modules/web.url-search-params.has.js
      var web_url_search_params_has = __webpack_require__("271a");

      // EXTERNAL MODULE: ./node_modules/core-js/modules/web.url-search-params.size.js
      var web_url_search_params_size = __webpack_require__("5494");

      // CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js?{"cacheDirectory":"node_modules/.cache/vue-loader","cacheIdentifier":"53d01920-vue-loader-template"}!./node_modules/cache-loader/dist/cjs.js??ref--13-0!./node_modules/babel-loader/lib!./node_modules/vue-loader/lib/loaders/templateLoader.js??ref--6!./node_modules/cache-loader/dist/cjs.js??ref--1-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/PublishFunnel.vue?vue&type=template&id=3954d822&scoped=true
      var PublishFunnelvue_type_template_id_3954d822_scoped_true_render =
        function render() {
          var _vm = this,
            _c = _vm._self._c,
            _setup = _vm._self._setupProxy;
          return _c(
            "b-modal",
            {
              attrs: {
                title: "Add New Page",
                size: "lg",
                visible: this.showModal,
                "ok-only": "",
                "ok-title": "Save Page",
                "ok-variant": "success",
                busy: !(
                  !!_vm.selectedFunnel &&
                  !!_vm.selectedStep &&
                  !!_vm.selectedMethod &&
                  !!_vm.pageSlug
                ),
                scrollable: "",
                centered: "",
              },
              on: {
                close: this.onCloseModal,
                change: this.onModalChange,
                ok: this.onOk,
              },
              scopedSlots: _vm._u(
                [
                  this.isSubmitting
                    ? {
                        key: "modal-footer",
                        fn: function () {
                          return [
                            _c(
                              "div",
                              {
                                staticClass: "text-center text-success my-2",
                              },
                              [
                                _c("b-spinner", {
                                  staticClass: "loadgin-spinner",
                                }),
                                _c("strong", [_vm._v("Publishing Funnel...")]),
                              ],
                              1,
                            ),
                          ];
                        },
                        proxy: true,
                      }
                    : null,
                ],
                null,
                true,
              ),
            },
            [
              _c(
                "p",
                {
                  staticClass: "my-4",
                },
                [
                  _c(
                    "b-form-group",
                    {
                      attrs: {
                        id: "fieldset-funnel-input",
                        description:
                          "Choose the funnel you want to publish as wordpress page",
                        label: "Choose funnel",
                        "label-for": "funnel-input",
                      },
                    },
                    [
                      _c("b-form-select", {
                        attrs: {
                          id: "funnel-input",
                          options: this.funnels,
                          "value-field": "_id",
                          "text-field": "name",
                        },
                        on: {
                          change: this.onFunnelChange,
                        },
                        scopedSlots: _vm._u([
                          {
                            key: "first",
                            fn: function () {
                              return [
                                _c(
                                  "b-form-select-option",
                                  {
                                    attrs: {
                                      value: null,
                                      disabled: "",
                                    },
                                  },
                                  [_vm._v("-- Please select a Funnel --")],
                                ),
                              ];
                            },
                            proxy: true,
                          },
                        ]),
                        model: {
                          value: _vm.selectedFunnel,
                          callback: function ($$v) {
                            _vm.selectedFunnel = $$v;
                          },
                          expression: "selectedFunnel",
                        },
                      }),
                    ],
                    1,
                  ),
                  _c("span", [
                    _vm._v(" Funnel : " + _vm._s(_vm.selectedFunnel)),
                  ]),
                  _c(
                    "b-form-group",
                    {
                      attrs: {
                        id: "fieldset-funnel-step-input",
                        description: "Choose the funnel step",
                        label: "Choose Step",
                        "label-for": "funnel-step-input",
                      },
                    },
                    [
                      _c("b-form-select", {
                        attrs: {
                          id: "funnel-step-input",
                          disabled: !this.selectedFunnel,
                          options: this.steps,
                          "value-field": "id",
                          "text-field": "name",
                        },
                        on: {
                          change: this.onStepChange,
                        },
                        scopedSlots: _vm._u([
                          {
                            key: "first",
                            fn: function () {
                              return [
                                _c(
                                  "b-form-select-option",
                                  {
                                    attrs: {
                                      value: null,
                                      disabled: "",
                                    },
                                  },
                                  [
                                    _vm.loadingStep
                                      ? _c("span", [
                                          _vm._v("loading funnel steps..."),
                                        ])
                                      : _c("span", [
                                          _vm._v(
                                            "-- Please select a Funnel step--",
                                          ),
                                        ]),
                                  ],
                                ),
                              ];
                            },
                            proxy: true,
                          },
                        ]),
                        model: {
                          value: _vm.selectedStep,
                          callback: function ($$v) {
                            _vm.selectedStep = $$v;
                          },
                          expression: "selectedStep",
                        },
                      }),
                      _vm.loadingStep
                        ? _c(
                            "span",
                            {
                              staticClass: "loading-steps-spinner",
                            },
                            [
                              _c("b-spinner", {
                                staticClass: "loading-steps-spinner",
                                attrs: {
                                  small: "",
                                  label: "Loading...",
                                },
                              }),
                            ],
                            1,
                          )
                        : _vm._e(),
                    ],
                    1,
                  ),
                  _c(
                    "b-form-group",
                    {
                      attrs: {
                        id: "fieldset-funnel-display-method-input",
                        description: "Choose the display method",
                        label: "Page Display Method",
                        "label-for": "funnel-display-method-input",
                      },
                    },
                    [
                      _c("b-form-select", {
                        attrs: {
                          id: "funnel-display-method-input",
                          disabled: !this.selectedFunnel,
                          options: this.displayMethod,
                        },
                        scopedSlots: _vm._u([
                          {
                            key: "first",
                            fn: function () {
                              return [
                                _c(
                                  "b-form-select-option",
                                  {
                                    attrs: {
                                      value: null,
                                      disabled: "",
                                    },
                                  },
                                  [
                                    _vm._v(
                                      "-- Please select a Page Display Method--",
                                    ),
                                  ],
                                ),
                              ];
                            },
                            proxy: true,
                          },
                        ]),
                        model: {
                          value: _vm.selectedMethod,
                          callback: function ($$v) {
                            _vm.selectedMethod = $$v;
                          },
                          expression: "selectedMethod",
                        },
                      }),
                    ],
                    1,
                  ),
                  this.selectedMethod === "iframe"
                    ? _c(
                        "b-form-group",
                        {
                          attrs: {
                            id: "fieldset-funnel-include-tracking-code",
                            description:
                              "If enabled, the tracking code in funnel will track wordpress as well",
                            label: "Tracking code",
                            "label-for": "include-tracking-code-input",
                          },
                        },
                        [
                          _c(
                            "b-form-checkbox",
                            {
                              attrs: {
                                id: "include-tracking-code-input",
                                disabled: !_vm.selectedFunnel,
                                name: "tracking-code-input",
                                value: "1",
                                "unchecked-value": "0",
                              },
                              model: {
                                value: _vm.includeTrackingCode,
                                callback: function ($$v) {
                                  _vm.includeTrackingCode = $$v;
                                },
                                expression: "includeTrackingCode",
                              },
                            },
                            [_vm._v(" Include Tracking Code ")],
                          ),
                        ],
                        1,
                      )
                    : _vm._e(),
                  _c(
                    "b-form-group",
                    {
                      attrs: {
                        id: "fieldset-funnel-slug_input",
                        description:
                          this.home_url +
                          "/" +
                          (!!this.pageSlug ? this.pageSlug : ""),
                        label: "Custom Slug",
                        "label-for": "funnel-slug-input",
                        "invalid-feedback": _vm.inValidSlugMessage,
                      },
                    },
                    [
                      _c("b-form-input", {
                        attrs: {
                          id: "funnel-slug-input",
                          placeholder: "enter slug",
                          disabled: !this.selectedFunnel,
                          formatter: this.slugFormatter,
                          state: _vm.isvalidSlug,
                        },
                        model: {
                          value: _vm.pageSlug,
                          callback: function ($$v) {
                            _vm.pageSlug = $$v;
                          },
                          expression: "pageSlug",
                        },
                      }),
                    ],
                    1,
                  ),
                  _c(
                    "b-form-group",
                    {
                      attrs: {
                        id: "fieldset-funnel-preview-url",
                        description: "For referene only *",
                        label: "Preview URL",
                        "label-for": "funnel-preview-input",
                      },
                    },
                    [
                      _c("b-form-input", {
                        attrs: {
                          id: "funnel-preview-input",
                          placeholder: "Preview URL",
                          disabled: "",
                        },
                        model: {
                          value: _vm.pagePreviewURL,
                          callback: function ($$v) {
                            _vm.pagePreviewURL = $$v;
                          },
                          expression: "pagePreviewURL",
                        },
                      }),
                    ],
                    1,
                  ),
                ],
                1,
              ),
            ],
          );
        };
      var PublishFunnelvue_type_template_id_3954d822_scoped_true_staticRenderFns =
        [];

      // CONCATENATED MODULE: ./src/components/PublishFunnel.vue?vue&type=template&id=3954d822&scoped=true

      // CONCATENATED MODULE: ./src/constants/index.ts
      const PERMAS_LINKS_ERROR_STR = `It seems like your account's Permalink Settings set to 'plain', please change it in order to use this plugin, more info <a href='https://wordpress.org/support/article/settings-permalinks-screen/' target='_blank'>here.</a>`;
      const getApiURL = function (endpoint, data, directEndpoint = false) {
        // eslint-disable-next-line
        const leadconnector_admin_settings =
          window.leadconnector_admin_settings;
        let apiURL = `${leadconnector_admin_settings.proxy_url}?endpoint=${encodeURIComponent(endpoint)}&_wpnonce=${leadconnector_admin_settings.nonce}&direct_endpoint=${String(directEndpoint)}`;
        if (data) {
          apiURL = apiURL + `&data=${JSON.stringify(data)}`;
        }
        console.log({
          apiURL,
        });
        return apiURL;
      };
      const COLUMNS_KEYS = {
        STEP_NAME: "leadconnector_step_name",
        FUNNEL_NAME: "leadconnector_funnel_name",
        PAGE_URL: "url",
        EDIT_URL: "edit_url",
        MODIFIED_DATE: "human_modified_date",
        CONTEXT: "context",
        SLUG: "slug",
      };
      const POSTS_TABLE_COLUMNS = [
        {
          key: COLUMNS_KEYS.STEP_NAME,
          label: "Page",
          sortable: true,
        },
        {
          key: COLUMNS_KEYS.FUNNEL_NAME,
          label: "Funnel Name",
          sortable: true,
        },
        {
          key: COLUMNS_KEYS.SLUG,
          label: "Slug",
          sortable: true,
        },
        {
          key: COLUMNS_KEYS.PAGE_URL,
          label: "View",
          sortable: true,
        },
        {
          key: COLUMNS_KEYS.EDIT_URL,
          label: "Edit",
          sortable: false,
        },
        {
          key: COLUMNS_KEYS.MODIFIED_DATE,
          label: "Last Modified",
          sortable: true,
        },
        {
          key: COLUMNS_KEYS.CONTEXT,
          label: "",
          sortable: false,
        },
      ];
      const DISPLAY_METHOD = ["iframe", "redirect"];
      const DISPLAY_METHOD_OPTIONS = [
        {
          value: DISPLAY_METHOD[0],
          text: "Embed Full Page iFrame",
        },
        {
          value: DISPLAY_METHOD[1],
          text: "Redirect to Funnel URL",
        },
      ];
      const MESSAGES = {
        INVALID_API_KEY: "API key is invalid",
        FUNNELS_API_FAIL: "Failed to fetch the funnels from you account",
        NO_FUNNELS: "You don't have any funnels in your account",
        POSTS_API_FAIL: "Failed to fetch the Pages",
        DELETE_POST_API_FAIL: "Failed to delete the post",
        POST_DELETED_SUCCESS: "Post deleted successfully",
        POST_CREATED_SUCCESS: "Post created successfully",
        POST_UPDATED_SUCCESS: "Post updated successfully",
      };
      // const LEAD_CONNECTOR_OAUTH_CLIENT_ID = "66de8e254d78673a12df3ae9-m1j8cox4";
      const LEAD_CONNECTOR_OAUTH_CLIENT_ID =
        "6705407d183014f80462d9f1-m20kdypv";
      // 6705407d183014f80462d9f1-m20kdypv
      const LEAD_CONNECTOR_OAUTH_CALLBACK_URL =
        "http://localhost:9610/wordpress/lc-plugin/callback";
      const currentURL = window.location.origin;
      // const ACTUAL_CALLBACK_URL = host + '/wp-admin/admin.php?page=lead-connector';
      const LEAD_CONNECTOR_ROOT_DOMAIN =
        "https://staging-hl-marketplace--wordpress-f1fwx1ha.web.app";
      // const LEAD_CONNECTOR_ROOT_DOMAIN = "https://marketplace.leadconnectorhq.com"
      const LEAD_CONNECTOR_OAUTH_URL =
        LEAD_CONNECTOR_ROOT_DOMAIN +
        "/oauth/chooselocation?response_type=code&redirect_uri=" +
        LEAD_CONNECTOR_OAUTH_CALLBACK_URL +
        "&client_id=" +
        LEAD_CONNECTOR_OAUTH_CLIENT_ID +
        "&scope=funnels/funnel.readonly%20wordpress.site.readonly&state=" +
        currentURL;
      // CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--15-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/ts-loader??ref--15-3!./node_modules/cache-loader/dist/cjs.js??ref--1-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/PublishFunnel.vue?vue&type=script&lang=ts

      let PublishFunnelvue_type_script_lang_ts_AddFunnelModal = class AddFunnelModal
        extends lib["c" /* Vue */]
      {
        constructor() {
          super(...arguments);
          this.selectedFunnel = null;
          this.selectedStep = null;
          this.loadingStep = false;
          this.selectedMethod = DISPLAY_METHOD[0];
          this.pageSlug = null;
          this.pagePreviewURL = null;
          this.selecetdFunnelDetails = null;
          this.selecetdStepDetails = null;
          this.editablePost = false;
          this.isBusy = true;
          this.funnels = [];
          this.steps = [];
          this.displayMethod = DISPLAY_METHOD_OPTIONS;
          this.isvalidSlug = null;
          this.inValidSlugMessage = "";
          this.isSubmitting = false;
          this.includeTrackingCode = "1";
        }
        mounted() {
          this.funnels = this.funnelOptions;
          if (this.editPost) {
            this.editablePost = true;
            if (this.editPost.slug) {
              this.pageSlug = this.editPost.slug;
            }
            if (this.editPost.lc_funnel_id) {
              this.selectedFunnel = this.editPost.lc_funnel_id;
              this.onFunnelChange(this.selectedFunnel);
            }
            if (this.editPost.lc_step_id) {
              this.selectedStep = this.editPost.lc_step_id;
              this.pagePreviewURL = `${this.host_url}/v2/preview/${this.editPost.lc_step_id}`;
            }
            if (this.editPost.lc_display_method) {
              this.selectedMethod = this.editPost.lc_display_method;
            }
            if (
              !this.editPost.lc_include_tracking_code ||
              this.editPost.lc_include_tracking_code === "0"
            ) {
              this.includeTrackingCode = "0";
            }
          }
        }
        onFunnelChange(change) {
          debugger;
          this.selectedFunnel = change;
          //reset value on every funnel change
          this.selectedStep = null;
          this.pagePreviewURL = "";
          const selectedFunnelDetail = this.funnels.find(
            (funnel) => funnel._id === change,
          );
          if (selectedFunnelDetail) {
            this.selecetdFunnelDetails = selectedFunnelDetail;
            this.steps = selectedFunnelDetail.steps;
            this.loadingStep = false;
            // fetch(
            //   getApiURL(
            //     `v1/funnels/${this.selecetdFunnelDetails.id}/pages/?includeMeta=true&includePageDataDownloadURL=true`
            //   )
            // ).then(response => {
            //   if (response.ok) {
            //     response.json().then(text => {
            //       this.steps = text.funnelPages;
            //       if (this.selectedStep) {
            //         this.onStepChange(this.selectedStep);
            //       }
            //     });
            //   }
            //   this.loadingStep = false;
            // });
          }
        }
        onStepChange(change) {
          this.selectedStep = change;
          const selectedPageDetails = this.steps.find((step) => {
            return step.id === this.selectedStep;
          });
          this.selecetdStepDetails = selectedPageDetails;
          if (this.selecetdStepDetails) {
            if (
              this.selecetdFunnelDetails &&
              this.selecetdFunnelDetails.domainURL
            ) {
              this.pagePreviewURL = `https://${this.selecetdFunnelDetails.domainURL}${this.selecetdStepDetails.url}`;
            } else {
              this.pagePreviewURL = `${this.host_url}/v2/preview/${this.selecetdStepDetails.id}`;
            }
          }
        }
        slugFormatter(value) {
          value = value.toLowerCase().replace(/\s/g, "-");
          let str = value.replace(/^\s+|\s+$/g, ""); // trim
          str = str.toLowerCase();
          // remove accents, swap ñ for n, etc
          const from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
          const to = "aaaaeeeeiiiioooouuuunc------";
          for (let i = 0, l = from.length; i < l; i++) {
            str = str.replace(new RegExp(from.charAt(i), "g"), to.charAt(i));
          }
          str = str
            .replace(/[^a-z0-9 -]/g, "") // remove invalid chars
            .replace(/\s+/g, "-") // collapse whitespace and replace by -
            .replace(/-+/g, "-"); // collapse dashes
          return str;
        }
        onCloseModal() {
          this.onClose && this.onClose(false);
        }
        onModalChange(isVisible) {
          if (!isVisible) {
            this.onClose && this.onClose(false);
          }
        }
        async onOk(bvModalEvent) {
          var _this$selecetdStepDet,
            _this$selecetdFunnelD,
            _this$selecetdStepDet2,
            _this$selecetdFunnelD2,
            _this$selecetdStepDet3,
            _this$selecetdStepDet4,
            _this$selecetdFunnelD3,
            _this$selecetdFunnelD4;
          bvModalEvent.preventDefault();
          this.isvalidSlug = null;
          this.inValidSlugMessage = "";
          this.isSubmitting = true;
          // let trackingCode;
          // if (
          //   this.selecetdStepDetails &&
          //   this.selecetdStepDetails.pageDataDownloadURL
          // ) {
          //   const response = await fetch(
          //     getApiURL(this.selecetdStepDetails.pageDataDownloadURL, undefined, true)
          //   );
          //   try {
          //     if (response.ok) {
          //       const res = await response.json();
          //       trackingCode = res.trackingCode;
          //     }
          //   } catch (err) {
          //     console.log(err);
          //   }
          // }
          const response = await fetch(getApiURL("wp_insert_post"), {
            method: "POST",
            body: JSON.stringify({
              lc_step_url: this.pagePreviewURL,
              lc_slug: this.pageSlug,
              lc_step_id:
                (_this$selecetdStepDet = this.selecetdStepDetails) === null ||
                _this$selecetdStepDet === void 0
                  ? void 0
                  : _this$selecetdStepDet.id,
              lc_funnel_id:
                (_this$selecetdFunnelD = this.selecetdFunnelDetails) === null ||
                _this$selecetdFunnelD === void 0
                  ? void 0
                  : _this$selecetdFunnelD._id,
              lc_step_name:
                (_this$selecetdStepDet2 = this.selecetdStepDetails) === null ||
                _this$selecetdStepDet2 === void 0
                  ? void 0
                  : _this$selecetdStepDet2.name,
              lc_funnel_name:
                (_this$selecetdFunnelD2 = this.selecetdFunnelDetails) ===
                  null || _this$selecetdFunnelD2 === void 0
                  ? void 0
                  : _this$selecetdFunnelD2.name,
              template_id:
                this.editablePost && this.editPost
                  ? this.editPost.template_id
                  : -1,
              lc_display_method: this.selectedMethod,
              lc_step_meta:
                (_this$selecetdStepDet3 = this.selecetdStepDetails) === null ||
                _this$selecetdStepDet3 === void 0
                  ? void 0
                  : _this$selecetdStepDet3.meta,
              lc_step_page_download_url:
                (_this$selecetdStepDet4 = this.selecetdStepDetails) === null ||
                _this$selecetdStepDet4 === void 0
                  ? void 0
                  : _this$selecetdStepDet4.pageDataDownloadURL,
              lc_include_tracking_code: this.includeTrackingCode,
              lc_funnel_tracking_code: {
                headerCode: btoa(
                  ((_this$selecetdFunnelD3 = this.selecetdFunnelDetails) ===
                    null || _this$selecetdFunnelD3 === void 0
                    ? void 0
                    : _this$selecetdFunnelD3.tracking_code_head) || "",
                ),
                footerCode: btoa(
                  ((_this$selecetdFunnelD4 = this.selecetdFunnelDetails) ===
                    null || _this$selecetdFunnelD4 === void 0
                    ? void 0
                    : _this$selecetdFunnelD4.tracking_code_body) || "",
                ),
              },
            }),
          });
          this.isSubmitting = false;
          if (response.ok) {
            response.text().then((res) => {
              let response = {};
              try {
                response = JSON.parse(res);
              } catch (error) {
                console.log("fail to parse response", res, error);
                return;
              }
              if (response.error) {
                if (response.code === 1009) {
                  this.isvalidSlug = false;
                  const newSlug = this.pageSlug + "-" + ~~(Math.random() * 10);
                  this.inValidSlugMessage =
                    response.message + ", try using " + newSlug;
                  this.pageSlug = newSlug;
                }
                return;
              }
              this.onClose && this.onClose(true);
            });
          }
        }
      };
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        PublishFunnelvue_type_script_lang_ts_AddFunnelModal.prototype,
        "showModal",
        void 0,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        PublishFunnelvue_type_script_lang_ts_AddFunnelModal.prototype,
        "onClose",
        void 0,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        PublishFunnelvue_type_script_lang_ts_AddFunnelModal.prototype,
        "funnelOptions",
        void 0,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        PublishFunnelvue_type_script_lang_ts_AddFunnelModal.prototype,
        "editPost",
        void 0,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        PublishFunnelvue_type_script_lang_ts_AddFunnelModal.prototype,
        "home_url",
        void 0,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        PublishFunnelvue_type_script_lang_ts_AddFunnelModal.prototype,
        "host_url",
        void 0,
      );
      PublishFunnelvue_type_script_lang_ts_AddFunnelModal = Object(
        tslib_es6["a" /* __decorate */],
      )(
        [lib["a" /* Component */]],
        PublishFunnelvue_type_script_lang_ts_AddFunnelModal,
      );
      /* harmony default export */ var PublishFunnelvue_type_script_lang_ts =
        PublishFunnelvue_type_script_lang_ts_AddFunnelModal;
      // CONCATENATED MODULE: ./src/components/PublishFunnel.vue?vue&type=script&lang=ts
      /* harmony default export */ var components_PublishFunnelvue_type_script_lang_ts =
        PublishFunnelvue_type_script_lang_ts;
      // EXTERNAL MODULE: ./src/components/PublishFunnel.vue?vue&type=style&index=0&id=3954d822&prod&scoped=true&lang=css
      var PublishFunnelvue_type_style_index_0_id_3954d822_prod_scoped_true_lang_css =
        __webpack_require__("5640");

      // EXTERNAL MODULE: ./node_modules/vue-loader/lib/runtime/componentNormalizer.js
      var componentNormalizer = __webpack_require__("2877");

      // CONCATENATED MODULE: ./src/components/PublishFunnel.vue

      /* normalize component */

      var component = Object(componentNormalizer["a" /* default */])(
        components_PublishFunnelvue_type_script_lang_ts,
        PublishFunnelvue_type_template_id_3954d822_scoped_true_render,
        PublishFunnelvue_type_template_id_3954d822_scoped_true_staticRenderFns,
        false,
        null,
        "3954d822",
        null,
      );

      /* harmony default export */ var PublishFunnel = component.exports;
      // CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--15-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/ts-loader??ref--15-3!./node_modules/cache-loader/dist/cjs.js??ref--1-0!./node_modules/vue-loader/lib??vue-loader-options!./src/components/Settings.vue?vue&type=script&lang=ts

      let Settingsvue_type_script_lang_ts_LeadConnectorSettings = class LeadConnectorSettings
        extends lib["c" /* Vue */]
      {
        constructor() {
          super(...arguments);
          this.showAddNewFunnelModal = false;
          this.isBusy = false;
          this.visible1 = true;
          this.visible2 = false;
          this.showConfirmPostDelete = false;
          this.isAPIsaving = false;
          this.isvalidApi = null;
          this.editPost = null;
          this.chatWidgetEnable = String(this.enableTextWidget);
          this.api_key = "";
          this.location_id = "";
          this.home_url = "";
          this.apiErrorMessage = "";
          this.chatWidgetWarning = "";
          this.alertTitle = "";
          this.showAlertTimer = 0;
          this.alertVariant = "warning";
          this.hostURL = "";
          this.selectedTableRows = [];
          this.funnels = [];
          this.publishedPages = [];
          this.oAuthUrl = LEAD_CONNECTOR_OAUTH_URL;
          this.isAuthorizing = false;
          this.publishedPageTablefields = POSTS_TABLE_COLUMNS;
        }
        onEnableTextWidget(value) {
          this.chatWidgetEnable = String(value);
        }
        onApiKey(value) {
          this.api_key = String(value);
        }
        async saveAPI(enableTextWidget) {
          var _leadConnectorSeeting;
          this.api_key = this.api_key && this.api_key.trim();
          const body = {
            api_key: this.api_key,
          };
          if (enableTextWidget !== undefined && enableTextWidget !== null) {
            body.enable_text_widget = this.chatWidgetEnable;
          }
          this.isvalidApi = null;
          this.isAPIsaving = true;
          let leadConnectorSeetings = {};
          leadConnectorSeetings = await fetch(getApiURL("wp_save_options"), {
            method: "POST",
            body: JSON.stringify(body),
          });
          console.log(leadConnectorSeetings);
          if (
            (_leadConnectorSeeting = leadConnectorSeetings) !== null &&
            _leadConnectorSeeting !== void 0 &&
            _leadConnectorSeeting.ok
          ) {
            const response = await leadConnectorSeetings.json();
            if (response.error) {
              this.isvalidApi = false;
              this.apiErrorMessage = response.message
                ? MESSAGES.INVALID_API_KEY
                : "";
            }
            if (response.success) {
              this.init();
              this.isvalidApi = true;
              if (response.warning_msg) {
                if (
                  enableTextWidget !== undefined &&
                  enableTextWidget !== null &&
                  this.chatWidgetEnable === "1"
                ) {
                  this.chatWidgetWarning = response.warning_msg;
                } else {
                  this.apiErrorMessage = response.warning_msg;
                  this.chatWidgetWarning = "";
                }
              } else {
                this.chatWidgetWarning = "";
              }
              if (response.location_id) {
                this.location_id = response.location_id;
              }
              if (response.home_url) {
                this.home_url = response.home_url;
              }
              if (response.white_label_url) {
                this.hostURL = response.white_label_url;
              } else {
                this.hostURL = String(this.baseURL);
              }
            }
          } else {
            if (leadConnectorSeetings.status === 404) {
              this.isvalidApi = false;
              this.apiErrorMessage = PERMAS_LINKS_ERROR_STR;
            }
            console.error(await leadConnectorSeetings.text());
          }
          this.isAPIsaving = false;
        }
        async init() {
          const funnelsReponse = await fetch(getApiURL("funnels_get_list"));
          if (funnelsReponse.ok) {
            const response = await funnelsReponse.json();
            if (response.error) {
              this.showToast(MESSAGES.FUNNELS_API_FAIL, false);
              return;
            }
            this.funnels = response.funnels;
            if (this.funnels.length === 0) {
              this.showToast(MESSAGES.NO_FUNNELS, false, "warning");
            }
          } else {
            console.error(await funnelsReponse.text());
            this.showToast(MESSAGES.FUNNELS_API_FAIL, false);
          }
        }
        async fetchUserSettings(onSuccess) {
          const leadConnectorSeetings = await fetch(
            getApiURL("wp_get_lc_options"),
          );
          if (leadConnectorSeetings.ok) {
            const response = await leadConnectorSeetings.json();
            onSuccess && onSuccess(response);
            if (response.api_key) {
              this.api_key = response.api_key;
              this.isvalidApi =
                !response.text_widget_error && response.api_key.length > 0
                  ? true
                  : null;
            }
            if (response.enable_text_widget) {
              this.chatWidgetEnable = response.enable_text_widget;
            }
            if (response.location_id) {
              this.location_id = response.location_id;
            }
            if (response.text_widget_error) {
              this.isvalidApi = false;
              this.apiErrorMessage = !response.warning_msg
                ? MESSAGES.INVALID_API_KEY
                : response.warning_msg;
            }
            if (
              response.enable_text_widget === "1" &&
              response.warning_msg &&
              response.warning_msg.includes("chat")
            ) {
              this.chatWidgetWarning = response.warning_msg;
            }
            if (response.home_url) {
              this.home_url = response.home_url;
            }
            if (response.white_label_url) {
              this.hostURL = response.white_label_url;
            } else {
              this.hostURL = String(this.baseURL);
            }
          } else {
            if (leadConnectorSeetings.status === 404) {
              this.isvalidApi = false;
              this.apiErrorMessage = PERMAS_LINKS_ERROR_STR;
            }
            console.error(await leadConnectorSeetings.text());
          }
        }
        async fetchPublishedPages() {
          this.isBusy = true;
          const funnelsPost = await fetch(getApiURL("wp_get_all_posts"));
          if (funnelsPost.ok) {
            const response = await funnelsPost.json();
            this.publishedPages = response;
          } else {
            console.error(await funnelsPost.text());
            this.showToast(MESSAGES.POSTS_API_FAIL, false);
          }
          this.isBusy = false;
        }
        getOAuthAuthorizationCode(urlParams) {
          const oauthCode =
            urlParams.get("leadconnector_token") ||
            urlParams.get("lc_code") ||
            urlParams.get("code");
          return oauthCode && oauthCode.length === 40 ? oauthCode : null;
        }
        async checkForOAuthAuthorization() {
          const urlParams = new URLSearchParams(window.location.search);
          const oauthCode = this.getOAuthAuthorizationCode(urlParams);
          if (oauthCode) {
            // Cross check this with @Gaurav Kanted if it will always be 40 chars
            this.isAuthorizing = true;
            this.$bvModal.show("oauth-modal");
          }
          const AuthorizationResponse = await fetch(
            getApiURL("wp_validate_oauth", {
              code: oauthCode,
            }),
          );
        }
        async mounted() {
          const urlParams = new URLSearchParams(window.location.search);
          if (this.getOAuthAuthorizationCode(urlParams)) {
            // Cross check this with @Gaurav Kanted if it will always be 40 chars
            this.checkForOAuthAuthorization();
          }
          this.chatWidgetEnable = String(this.enableTextWidget);
          this.api_key = this.apiKey;
          this.hostURL = String(this.baseURL);
          this.fetchUserSettings((response) => {
            if (
              (response.api_key || response.oauth_access_token) &&
              !response.text_widget_error
            ) {
              this.init();
              this.fetchPublishedPages();
            }
          });
        }
        handleAddNewFunnel() {
          this.showAddNewFunnelModal = true;
        }
        editFunnel(e, postInfo) {
          this.editPost = postInfo;
          this.showAddNewFunnelModal = true;
        }
        async onPostDelete() {
          if (this.editPost) {
            var _this$editPost;
            const funnelsPost = await fetch(
              getApiURL("wp_delete_post", {
                post_id:
                  (_this$editPost = this.editPost) === null ||
                  _this$editPost === void 0
                    ? void 0
                    : _this$editPost.template_id,
                force_delete: true,
              }),
            );
            if (funnelsPost.ok) {
              const response = await funnelsPost.json();
              if (response && response.error) {
                this.showToast(MESSAGES.DELETE_POST_API_FAIL, false);
              } else {
                this.showToast(MESSAGES.POST_DELETED_SUCCESS, true);
                this.fetchPublishedPages();
              }
            }
            this.editPost = null;
          }
        }
        deletePost(e, postInfo) {
          this.editPost = postInfo;
        }
        onRowSelected(items) {
          this.selectedTableRows = items;
        }
        showToast(toastbody, isSuccess = true, variant) {
          this.alertTitle = toastbody;
          if (!variant) {
            variant = isSuccess ? "success" : "danger";
          }
          this.alertVariant = variant;
          this.showAlertTimer = 5;
        }
        onModalClose(reload) {
          this.showAddNewFunnelModal = false;
          if (reload) {
            this.showToast(
              !this.editPost
                ? MESSAGES.POST_CREATED_SUCCESS
                : MESSAGES.POST_UPDATED_SUCCESS,
            );
            this.fetchPublishedPages();
          }
          this.editPost = null;
        }
      };
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        Settingsvue_type_script_lang_ts_LeadConnectorSettings.prototype,
        "enableTextWidget",
        void 0,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        Settingsvue_type_script_lang_ts_LeadConnectorSettings.prototype,
        "apiKey",
        void 0,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["b" /* Prop */])()],
        Settingsvue_type_script_lang_ts_LeadConnectorSettings.prototype,
        "baseURL",
        void 0,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["d" /* Watch */])("enableTextWidget")],
        Settingsvue_type_script_lang_ts_LeadConnectorSettings.prototype,
        "onEnableTextWidget",
        null,
      );
      Object(tslib_es6["a" /* __decorate */])(
        [Object(lib["d" /* Watch */])("apiKey")],
        Settingsvue_type_script_lang_ts_LeadConnectorSettings.prototype,
        "onApiKey",
        null,
      );
      Settingsvue_type_script_lang_ts_LeadConnectorSettings = Object(
        tslib_es6["a" /* __decorate */],
      )(
        [
          Object(lib["a" /* Component */])({
            components: {
              PublishFunnel: PublishFunnel,
            },
          }),
        ],
        Settingsvue_type_script_lang_ts_LeadConnectorSettings,
      );
      /* harmony default export */ var Settingsvue_type_script_lang_ts =
        Settingsvue_type_script_lang_ts_LeadConnectorSettings;
      // CONCATENATED MODULE: ./src/components/Settings.vue?vue&type=script&lang=ts
      /* harmony default export */ var components_Settingsvue_type_script_lang_ts =
        Settingsvue_type_script_lang_ts;
      // EXTERNAL MODULE: ./src/components/Settings.vue?vue&type=style&index=0&id=7a6bd024&prod&scoped=true&lang=css
      var Settingsvue_type_style_index_0_id_7a6bd024_prod_scoped_true_lang_css =
        __webpack_require__("95b1");

      // CONCATENATED MODULE: ./src/components/Settings.vue

      /* normalize component */

      var Settings_component = Object(componentNormalizer["a" /* default */])(
        components_Settingsvue_type_script_lang_ts,
        Settingsvue_type_template_id_7a6bd024_scoped_true_render,
        Settingsvue_type_template_id_7a6bd024_scoped_true_staticRenderFns,
        false,
        null,
        "7a6bd024",
        null,
      );

      /* harmony default export */ var Settings = Settings_component.exports;
      // CONCATENATED MODULE: ./node_modules/cache-loader/dist/cjs.js??ref--15-0!./node_modules/thread-loader/dist/cjs.js!./node_modules/babel-loader/lib!./node_modules/ts-loader??ref--15-3!./node_modules/cache-loader/dist/cjs.js??ref--1-0!./node_modules/vue-loader/lib??vue-loader-options!./src/App.vue?vue&type=script&lang=ts

      const BASE_URL = "https://app.leadconnectorhq.com";
      let Appvue_type_script_lang_ts_App = class App
        extends lib["c" /* Vue */]
      {
        constructor() {
          super(...arguments);
          this.settings = {
            base_URL: BASE_URL,
          };
        }
        mounted() {
          const settingsHolderElement = document.getElementById(
            "lead-connecter-settings-holder",
          );
          const settings = settingsHolderElement
            ? settingsHolderElement.getAttribute("data-settings")
            : "";
          if (settings !== null) {
            try {
              this.settings = {
                ...this.settings,
                ...JSON.parse(settings),
              };
              this.settings.api_key = atob(this.settings.api_key || "");
              if (
                settingsHolderElement !== null &&
                !!settingsHolderElement.parentNode
              ) {
                settingsHolderElement.parentNode.removeChild(
                  settingsHolderElement,
                );
              }
            } catch (err) {
              console.error(err);
            }
          }
        }
      };
      Appvue_type_script_lang_ts_App = Object(tslib_es6["a" /* __decorate */])(
        [
          Object(lib["a" /* Component */])({
            components: {
              Settings: Settings,
            },
          }),
        ],
        Appvue_type_script_lang_ts_App,
      );
      /* harmony default export */ var Appvue_type_script_lang_ts =
        Appvue_type_script_lang_ts_App;
      // CONCATENATED MODULE: ./src/App.vue?vue&type=script&lang=ts
      /* harmony default export */ var src_Appvue_type_script_lang_ts =
        Appvue_type_script_lang_ts;
      // EXTERNAL MODULE: ./src/App.vue?vue&type=style&index=0&id=60fa85a4&prod&lang=css
      var Appvue_type_style_index_0_id_60fa85a4_prod_lang_css =
        __webpack_require__("6538");

      // CONCATENATED MODULE: ./src/App.vue

      /* normalize component */

      var App_component = Object(componentNormalizer["a" /* default */])(
        src_Appvue_type_script_lang_ts,
        render,
        staticRenderFns,
        false,
        null,
        null,
        null,
      );

      /* harmony default export */ var src_App = App_component.exports;
      // EXTERNAL MODULE: ./node_modules/bootstrap-vue/esm/index.js + 275 modules
      var esm = __webpack_require__("5f5b");

      // EXTERNAL MODULE: ./node_modules/bootstrap-vue/esm/icons/plugin.js
      var icons_plugin = __webpack_require__("b1e0");

      // EXTERNAL MODULE: ./node_modules/bootstrap/dist/css/bootstrap.css
      var bootstrap = __webpack_require__("f9e3");

      // EXTERNAL MODULE: ./node_modules/bootstrap-vue/dist/bootstrap-vue.css
      var bootstrap_vue = __webpack_require__("2dd8");

      // CONCATENATED MODULE: ./src/main.ts

      vue_runtime_esm["default"].config.productionTip = false;
      // Vue.component("BCard", BCard);
      // Vue.component("BCardText", BCardText);
      // Vue.component("BCardBody", BCardBody);
      // Vue.component("BButton", BButton);
      // Vue.component("BCardHeader", BCardHeader);
      // Vue.component("BCollapse", BCollapse);
      // Note that Vue automatically prefixes directive names with `v-`
      // Vue.directive("b-card", VBCard);
      vue_runtime_esm["default"].use(esm["a" /* BootstrapVue */]);
      vue_runtime_esm["default"].use(icons_plugin["a" /* IconsPlugin */]);
      new vue_runtime_esm["default"]({
        render: (h) => h(src_App),
      }).$mount("#app");

      /***/
    },

    /***/ f6c0: /***/ function (module, exports, __webpack_require__) {
      // extracted by mini-css-extract-plugin
      /***/
    },

    /******/
  },
);
