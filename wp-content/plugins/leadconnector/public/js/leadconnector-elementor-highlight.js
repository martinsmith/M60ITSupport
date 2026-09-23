(function () {
  "use strict";

  var highlightEnabled = true;
  var elementsWithListeners = new WeakSet();

  var TEXT_WIDGET_SELECTOR = [
    ".elementor-widget-heading",
    ".elementor-widget-text-editor",
    ".elementor-widget-button",
    ".elementor-widget-icon-box",
  ].join(", ");

  var IMAGE_WIDGET_SELECTOR = ".elementor-widget-image";

  var LEGACY_TEXT_SELECTORS = [
    { selector: ".hero-heading-class", elementType: "heading" },
    { selector: ".hero-description-class", elementType: "description" },
    {
      selector: ".about-us-story-header-class",
      elementType: "about-us-story-header",
    },
    {
      selector: ".about-us-story-content-class",
      elementType: "about-us-story-content",
    },
  ];

  var LEGACY_IMAGE_SELECTORS = [
    { selector: ".hero-image-class", elementType: "hero-image-class" },
    { selector: ".about-us-image-class", elementType: "about-us-image-class" },
  ];

  var pageMode = null;
  /** Remembers last iframe click so old FE preview (semantic elementType only) can still update the right widget. */
  var lastClickedTarget = null;

  /**
   * Edit contract comes from WP post meta `_leadconnector_ai_page_version`,
   * injected by PHP as LeadConnectorAIPage.version (see class-leadconnector-public.php).
   * - missing / < 2 → legacy (4 text + 2 image CSS targets)
   * - >= 2 → universal Elementor widget editing via data-id
   * Do not infer mode from CSS classes (container _css_classes are unreliable).
   */
  function getAiPageVersion() {
    var cfg = window.LeadConnectorAIPage;
    if (!cfg || cfg.version === undefined || cfg.version === null) {
      return 1;
    }
    var parsed = parseInt(cfg.version, 10);
    return isNaN(parsed) ? 1 : parsed;
  }

  function applyPageMode() {
    var legacy = getAiPageVersion() < 2;
    pageMode = legacy ? "legacy" : "modern";
    document.body.classList.remove("lc-ai-page-legacy", "lc-ai-page-modern");
    document.body.classList.add(
      legacy ? "lc-ai-page-legacy" : "lc-ai-page-modern"
    );
    return legacy;
  }

  function isLegacyPageMode() {
    if (pageMode === null) {
      return applyPageMode();
    }
    return pageMode === "legacy";
  }

  var WIDGET_TYPE_LABELS = {
    heading: "Heading",
    "text-editor": "Text Block",
    button: "Button",
    "icon-box": "Icon Box",
    image: "Image",
  };

  function getFullText(element) {
    return (element.textContent || element.innerText || "").trim();
  }

  function getWidgetWrapper(target) {
    return target.closest(".elementor-widget[data-id]");
  }

  function getElementId(widgetEl) {
    return widgetEl ? widgetEl.getAttribute("data-id") || "" : "";
  }

  function getWidgetType(widgetEl) {
    if (!widgetEl) return "";
    return widgetEl.getAttribute("data-widget_type") || "";
  }

  function parseWidgetType(rawType) {
    if (!rawType) return "";
    return rawType.split(".")[0];
  }

  function getSectionRoot(widgetEl) {
    if (!widgetEl) return null;
    var current = widgetEl.parentElement;
    var lastContainer = null;
    while (current) {
      var elType = current.getAttribute("data-element_type");
      if (elType === "container" || elType === "section") {
        lastContainer = current;
      }
      current = current.parentElement;
    }
    return lastContainer;
  }

  function getSectionLabel(sectionEl) {
    if (!sectionEl) return "";
    var classes = sectionEl.className || "";
    var match = classes.match(/ai-section-(\w[\w-]*)/);
    if (match) {
      var raw = match[1];
      return raw
        .split("-")
        .map(function (w) {
          return w.charAt(0).toUpperCase() + w.slice(1);
        })
        .join(" ");
    }
    var pageContent =
      document.querySelector(".elementor-location-single") ||
      document.querySelector("[data-elementor-type='wp-page']") ||
      document.querySelector(".elementor") ||
      document.body;
    var allRoots = pageContent.querySelectorAll(
      ":scope > .elementor-element[data-element_type='container'], :scope > .elementor-section, :scope > .elementor-element[data-element_type='section']"
    );
    if (allRoots.length === 0) {
      allRoots = pageContent.querySelectorAll(
        ".elementor-element[data-element_type='container']:not([data-element_type='container'] [data-element_type='container'])"
      );
    }
    for (var i = 0; i < allRoots.length; i++) {
      if (allRoots[i] === sectionEl || allRoots[i].contains(sectionEl)) {
        return "Section " + (i + 1);
      }
    }
    return "Section";
  }

  function getElementLabel(widgetEl, widgetType, sectionEl) {
    var label = WIDGET_TYPE_LABELS[widgetType] || widgetType || "Element";
    if (!sectionEl) return label;
    var siblings = sectionEl.querySelectorAll(
      '.elementor-widget-' + widgetType.replace(/\s+/g, '-')
    );
    if (siblings.length > 1) {
      for (var i = 0; i < siblings.length; i++) {
        if (siblings[i] === widgetEl) {
          label += " " + (i + 1);
          break;
        }
      }
    }
    return label;
  }

  function getIconBoxSubField(clickTarget, widgetEl) {
    if (!widgetEl || !clickTarget) return null;
    var titleEl = widgetEl.querySelector(".elementor-icon-box-title");
    var descEl = widgetEl.querySelector(".elementor-icon-box-description");

    if (titleEl && (titleEl === clickTarget || titleEl.contains(clickTarget) || clickTarget.closest(".elementor-icon-box-title"))) {
      return "title";
    }
    if (descEl && (descEl === clickTarget || descEl.contains(clickTarget) || clickTarget.closest(".elementor-icon-box-description"))) {
      return "description";
    }

    var contentWrapper = widgetEl.querySelector(".elementor-icon-box-content");
    if (contentWrapper && contentWrapper.contains(clickTarget)) {
      if (titleEl && titleEl.contains(clickTarget)) return "title";
      return "description";
    }
    return null;
  }

  function getTextFromWidget(widgetEl, widgetType, subField) {
    if (!widgetEl) return "";
    var container = widgetEl.querySelector(".elementor-widget-container");
    if (!container) container = widgetEl;

    if (widgetType === "heading") {
      var heading = container.querySelector(
        "h1, h2, h3, h4, h5, h6, .elementor-heading-title"
      );
      return heading ? getFullText(heading) : getFullText(container);
    }
    if (widgetType === "text-editor") {
      var editor = container.querySelector(".elementor-text-editor");
      return editor ? getFullText(editor) : getFullText(container);
    }
    if (widgetType === "button") {
      var btn = container.querySelector(
        ".elementor-button-text, .elementor-button"
      );
      return btn ? getFullText(btn) : getFullText(container);
    }
    if (widgetType === "icon-box") {
      if (subField === "title") {
        var t = container.querySelector(".elementor-icon-box-title");
        return t ? getFullText(t) : "";
      }
      if (subField === "description") {
        var d = container.querySelector(".elementor-icon-box-description");
        return d ? getFullText(d) : "";
      }
      var title = container.querySelector(".elementor-icon-box-title");
      return title ? getFullText(title) : getFullText(container);
    }
    return getFullText(container);
  }

  function sendToParent(payload) {
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage(
          Object.assign({ source: "leadconnector-elementor-iframe" }, payload),
          "*"
        );
      }
    } catch (e) {
      console.warn("LeadConnector: Could not send message to parent", e);
    }
  }

  /**
   * Legacy FE (pre elementId contract) reads `selector` for API saves and
   * `elementType` for live-preview updates. Keep emitting both until FE/BE ship.
   */
  function withLegacyClickFields(payload, legacySelector, legacyElementType) {
    return Object.assign({}, payload, {
      selector: legacySelector,
      elementType: legacyElementType,
    });
  }

  function getLegacyTargetClass(elementType) {
    var classMap = {
      heading: "hero-heading-class",
      description: "hero-description-class",
      "about-us-story-header": "about-us-story-header-class",
      "about-us-story-content": "about-us-story-content-class",
    };
    return classMap[elementType] || null;
  }

  function updateLegacyHeroContent(data) {
    if (!data || !data.elementType || !data.content) {
      return false;
    }

    var targetClass = getLegacyTargetClass(data.elementType);
    if (!targetClass) {
      return false;
    }

    var elements = document.querySelectorAll("." + targetClass);
    if (elements.length === 0) {
      return false;
    }

    elements.forEach(function (element) {
      var contentElement =
        element.querySelector(
          "h1, h2, h3, h4, h5, h6, .elementor-heading-title"
        ) ||
        element.querySelector(
          ".elementor-widget-container, .elementor-text-editor"
        ) ||
        element;
      if (data.content) contentElement.textContent = data.content;
    });

    sendToParent({
      type: "content-updated",
      elementType: data.elementType,
      success: true,
    });
    return true;
  }

  function handleTextClick(e) {
    if (!highlightEnabled) return;
    var widgetEl = getWidgetWrapper(e.target);
    if (!widgetEl) return;

    var elementId = getElementId(widgetEl);
    var rawType = getWidgetType(widgetEl);
    var widgetType = parseWidgetType(rawType);
    var sectionEl = getSectionRoot(widgetEl);
    var sectionLabel = getSectionLabel(sectionEl);
    var subField = widgetType === "icon-box" ? getIconBoxSubField(e.target, widgetEl) : null;
    var elementLabel = getElementLabel(widgetEl, widgetType, sectionEl);
    if (subField) {
      elementLabel += " > " + (subField === "title" ? "Title" : "Description");
    }
    var text = getTextFromWidget(widgetEl, widgetType, subField);

    lastClickedTarget = {
      elementId: elementId,
      widgetType: widgetType,
      subField: subField,
    };

    sendToParent(
      withLegacyClickFields(
        {
          type: "elementor-click",
          elementId: elementId,
          widgetType: widgetType,
          sectionLabel: sectionLabel,
          elementLabel: elementLabel,
          subField: subField,
          text: text,
        },
        elementId,
        widgetType || "heading"
      )
    );
  }

  function handleImageOverlayClick(e, widgetEl) {
    e.preventDefault();
    e.stopPropagation();
    if (!highlightEnabled) return;

    var elementId = getElementId(widgetEl);
    var img = widgetEl.querySelector("img");
    var imgSrc = img ? img.getAttribute("src") || "" : "";
    var naturalW = img ? img.naturalWidth : 0;
    var naturalH = img ? img.naturalHeight : 0;
    var sectionEl = getSectionRoot(widgetEl);
    var sectionLabel = getSectionLabel(sectionEl);

    lastClickedTarget = {
      elementId: elementId,
      widgetType: "image",
      subField: null,
    };

    sendToParent(
      withLegacyClickFields(
        {
          type: "image-click",
          elementId: elementId,
          widgetType: "image",
          sectionLabel: sectionLabel,
          elementLabel: "Image",
          imageUrl: imgSrc,
          dimensions: { width: naturalW, height: naturalH },
        },
        elementId,
        elementId
      )
    );
  }

  function initEventListeners() {
    if (applyPageMode()) {
      initLegacyEventListeners();
      return;
    }
    initModernEventListeners();
  }

  function initModernEventListeners() {
    var textWidgets = document.querySelectorAll(TEXT_WIDGET_SELECTOR);
    textWidgets.forEach(function (element) {
      if (elementsWithListeners.has(element)) return;

      element.addEventListener(
        "click",
        function (e) {
          handleTextClick(e);
        },
        { passive: true, capture: true }
      );
      elementsWithListeners.add(element);
    });

    var imageWidgets = document.querySelectorAll(IMAGE_WIDGET_SELECTOR);
    imageWidgets.forEach(function (element) {
      if (elementsWithListeners.has(element)) return;

      var img = element.querySelector("img");
      if (!img) {
        elementsWithListeners.add(element);
        return;
      }

      if (window.getComputedStyle(element).position === "static") {
        element.style.position = "relative";
      }

      var overlay = document.createElement("button");
      overlay.className = "leadconnector-regenerate-image-overlay";
      if (!highlightEnabled) overlay.style.display = "none";
      overlay.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-right:6px;vertical-align:middle;display:inline-block"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/></svg>Regenerate Image';
      element.appendChild(overlay);

      function positionOverlay() {
        var imgRect = img.getBoundingClientRect();
        var parentRect = element.getBoundingClientRect();
        overlay.style.left =
          imgRect.left - parentRect.left + imgRect.width / 2 + "px";
        overlay.style.top =
          imgRect.top - parentRect.top + imgRect.height / 2 + "px";
        overlay.style.transform = "translate(-50%, -50%)";
      }

      element.addEventListener("mouseenter", positionOverlay);
      if (!img.complete) img.addEventListener("load", positionOverlay);
      positionOverlay();

      overlay.addEventListener(
        "click",
        function (e) {
          handleImageOverlayClick(e, element);
        },
        { capture: true }
      );

      elementsWithListeners.add(element);
    });
  }

  function initLegacyEventListeners() {
    LEGACY_TEXT_SELECTORS.forEach(function (legacy) {
      var elements = document.querySelectorAll(legacy.selector);
      elements.forEach(function (element) {
        if (elementsWithListeners.has(element)) return;

        element.addEventListener(
          "click",
          function (e) {
            if (!highlightEnabled) return;
            var clickedElement = e.target;
            if (
              !element.contains(clickedElement) &&
              clickedElement !== element
            ) {
              return;
            }

            var legacySelector = legacy.selector.replace(/^\./, "");
            var elementId = getElementId(element);

            lastClickedTarget = {
              elementId: elementId || null,
              widgetType: legacy.elementType,
              subField: null,
              legacySelector: legacySelector,
            };

            sendToParent(
              withLegacyClickFields(
                {
                  type: "elementor-click",
                  elementId: elementId || undefined,
                  widgetType: legacy.elementType,
                  sectionLabel: "",
                  elementLabel: legacy.elementType,
                  subField: null,
                  text: getFullText(element),
                },
                legacySelector,
                legacy.elementType
              )
            );
          },
          { passive: true, capture: true }
        );
        elementsWithListeners.add(element);
      });
    });

    LEGACY_IMAGE_SELECTORS.forEach(function (legacy) {
      var elements = document.querySelectorAll(legacy.selector);
      elements.forEach(function (element) {
        if (elementsWithListeners.has(element)) return;

        var img = element.querySelector("img");
        if (!img) {
          elementsWithListeners.add(element);
          return;
        }

        if (window.getComputedStyle(element).position === "static") {
          element.style.position = "relative";
        }

        var overlay = document.createElement("button");
        overlay.className = "leadconnector-regenerate-image-overlay";
        if (!highlightEnabled) overlay.style.display = "none";
        overlay.innerHTML =
          '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-right:6px;vertical-align:middle;display:inline-block"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/></svg>Regenerate Image';
        element.appendChild(overlay);

        function positionOverlay() {
          var imgRect = img.getBoundingClientRect();
          var parentRect = element.getBoundingClientRect();
          overlay.style.left =
            imgRect.left - parentRect.left + imgRect.width / 2 + "px";
          overlay.style.top =
            imgRect.top - parentRect.top + imgRect.height / 2 + "px";
          overlay.style.transform = "translate(-50%, -50%)";
        }

        element.addEventListener("mouseenter", positionOverlay);
        if (!img.complete) img.addEventListener("load", positionOverlay);
        positionOverlay();

        overlay.addEventListener(
          "click",
          function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!highlightEnabled) return;

            var imgSrc = img.getAttribute("src") || "";
            var naturalW = img.naturalWidth;
            var naturalH = img.naturalHeight;
            var elementId = getElementId(element);
            var legacySelector = legacy.selector.replace(/^\./, "");

            lastClickedTarget = {
              elementId: elementId || null,
              widgetType: "image",
              subField: null,
              legacySelector: legacySelector,
            };

            sendToParent(
              withLegacyClickFields(
                {
                  type: "image-click",
                  elementId: elementId || undefined,
                  widgetType: "image",
                  sectionLabel: "",
                  elementLabel: "Image",
                  imageUrl: imgSrc,
                  dimensions: { width: naturalW, height: naturalH },
                },
                legacySelector,
                legacy.elementType
              )
            );
          },
          { capture: true }
        );

        elementsWithListeners.add(element);
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initEventListeners);
  } else {
    setTimeout(initEventListeners, 500);
  }
  setTimeout(initEventListeners, 1500);

  /* -----------------------------------------------------------------------
   * Color preview utilities
   * ----------------------------------------------------------------------- */
  function normalizeHex(value) {
    if (!value || typeof value !== "string") return "";
    value = value.trim();
    var m = value.match(/^#([0-9A-Fa-f]{6})$/);
    if (m) return "#" + m[1].toLowerCase();
    var m8 = value.match(/^#([0-9A-Fa-f]{6})([0-9A-Fa-f]{2})$/);
    if (m8) return "#" + m8[1].toLowerCase();
    var rgb = value.match(/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/);
    if (rgb) {
      var r = parseInt(rgb[1], 10);
      var g = parseInt(rgb[2], 10);
      var b = parseInt(rgb[3], 10);
      return (
        "#" +
        [r, g, b]
          .map(function (n) {
            var h = n.toString(16);
            return h.length === 1 ? "0" + h : h;
          })
          .join("")
      );
    }
    var rgba = value.match(
      /^rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*[\d.]+\s*\)$/
    );
    if (rgba) {
      var r2 = parseInt(rgba[1], 10);
      var g2 = parseInt(rgba[2], 10);
      var b2 = parseInt(rgba[3], 10);
      return (
        "#" +
        [r2, g2, b2]
          .map(function (n) {
            var h = n.toString(16);
            return h.length === 1 ? "0" + h : h;
          })
          .join("")
      );
    }
    // SECURITY: fall through to "" rather than returning the raw input. This
    // function is used as a validator by applyPreviewColors(), whose result is
    // written into <style>.textContent. Returning the unrecognised input
    // unchanged made it an identity function for any non-color string, letting
    // arbitrary CSS be injected into the page's stylesheets.
    return "";
  }

  var COLOR_KEYS = [
    "primary",
    "secondary",
    "accent",
    "light_neutral",
    "light_neutral_text",
  ];

  function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function applyPreviewColors(data) {
    var currentColors = data && data.current;
    var newColors = data && data.new;
    if (!currentColors || !newColors) return;

    var replacements = [];
    for (var k = 0; k < COLOR_KEYS.length; k++) {
      var key = COLOR_KEYS[k];
      var orig = normalizeHex(currentColors[key]);
      var target = normalizeHex(newColors[key]);
      // Defence in depth behind normalizeHex(): `target` is written into
      // <style>.textContent, so accept only a literal hex colour. Anything
      // else is dropped rather than injected into the stylesheet.
      if (!/^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test(target || "")) continue;
      if (orig && target && orig !== target) {
        replacements.push({ orig: orig, target: target, key: key });
      }
    }
    if (replacements.length === 0) return;

    var styleTags = document.querySelectorAll("style");
    for (var s = 0; s < styleTags.length; s++) {
      var text = styleTags[s].textContent;
      var mod = false;
      for (var r = 0; r < replacements.length; r++) {
        var re = new RegExp(escapeRegex(replacements[r].orig), "gi");
        var mm = text.match(re);
        if (mm) {
          text = text.replace(re, replacements[r].target);
          mod = true;
        }
      }
      if (mod) styleTags[s].textContent = text;
    }

    var nodes = document.body.querySelectorAll("*");
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      var cs = window.getComputedStyle(el);
      var bgHex = normalizeHex(cs.backgroundColor);
      var fgHex = normalizeHex(cs.color);
      for (var ri = 0; ri < replacements.length; ri++) {
        if (bgHex === replacements[ri].orig) {
          el.style.backgroundColor = replacements[ri].target;
          break;
        }
      }
      for (var rf = 0; rf < replacements.length; rf++) {
        if (fgHex === replacements[rf].orig) {
          el.style.color = replacements[rf].target;
          break;
        }
      }
    }
  }

  /* -----------------------------------------------------------------------
   * Content preview — update DOM by data-id
   * ----------------------------------------------------------------------- */
  function updateContent(data) {
    if (!data || !data.content) {
      console.warn("LeadConnector: Invalid updateContent data", data);
      return;
    }

    if (isLegacyPageMode()) {
      if (!updateLegacyHeroContent(data)) {
        console.warn("LeadConnector: Legacy content update failed", data);
      }
      return;
    }

    var targetEl = null;
    var elementId = data.elementId;
    var widgetType = data.widgetType;
    var subField = data.subField;

    // Old FE only sends semantic elementType on preview — use last click target.
    if (!elementId && lastClickedTarget && lastClickedTarget.elementId) {
      elementId = lastClickedTarget.elementId;
      widgetType = widgetType || lastClickedTarget.widgetType;
      subField = subField != null ? subField : lastClickedTarget.subField;
    }

    if (elementId) {
      targetEl = document.querySelector('[data-id="' + elementId + '"]');
    }

    // Old FE / shim may pass Elementor id via elementType when selector was the id.
    if (!targetEl && data.elementType) {
      targetEl = document.querySelector('[data-id="' + data.elementType + '"]');
      if (targetEl && !elementId) {
        elementId = data.elementType;
      }
    }

    if (!targetEl) {
      // Last resort: legacy CSS class map (semantic elementType from old FE).
      if (updateLegacyHeroContent(data)) {
        return;
      }
      console.warn(
        "LeadConnector: Element not found for id",
        elementId || data.elementType
      );
      return;
    }

    widgetType = widgetType || parseWidgetType(getWidgetType(targetEl));
    var container = targetEl.querySelector(".elementor-widget-container") || targetEl;

    if (widgetType === "heading") {
      var heading = container.querySelector(
        "h1, h2, h3, h4, h5, h6, .elementor-heading-title"
      );
      if (heading) heading.textContent = data.content;
      else container.textContent = data.content;
    } else if (widgetType === "text-editor") {
      var editor = container.querySelector(".elementor-text-editor");
      if (editor) {
        editor.innerHTML = "";
        var p = document.createElement("p");
        p.textContent = data.content;
        editor.appendChild(p);
      } else {
        container.textContent = data.content;
      }
    } else if (widgetType === "button") {
      var btn = container.querySelector(".elementor-button-text");
      if (btn) btn.textContent = data.content;
      else container.textContent = data.content;
    } else if (widgetType === "icon-box") {
      if (subField === "title" || data.subField === "title") {
        var t = container.querySelector(".elementor-icon-box-title");
        if (t) t.textContent = data.content;
      } else if (subField === "description" || data.subField === "description") {
        var d = container.querySelector(".elementor-icon-box-description");
        if (d) d.textContent = data.content;
      } else {
        var title = container.querySelector(".elementor-icon-box-title");
        if (title) title.textContent = data.content;
        else container.textContent = data.content;
      }
    } else {
      var fallback =
        container.querySelector(
          "h1, h2, h3, h4, h5, h6, .elementor-heading-title"
        ) ||
        container.querySelector(".elementor-text-editor") ||
        container;
      fallback.textContent = data.content;
    }

    sendToParent({
      type: "content-updated",
      elementId: elementId,
      elementType: data.elementType || widgetType,
      success: true,
    });
  }

  /* -----------------------------------------------------------------------
   * Message handler — receives commands from the parent LeadConnector app
   * ----------------------------------------------------------------------- */
  function handleParentMessage(event) {
    if (!event.data || event.data.source !== "leadconnector-parent-app") return;
    var messageType = event.data.type;
    var messageData = event.data.data;

    if (messageType === "toggleHighlight") {
      highlightEnabled = !!(messageData && messageData.enabled);
      var styleTag = document.getElementById(
        "leadconnector-elementor-highlight-css"
      );
      if (styleTag) styleTag.disabled = !highlightEnabled;
      var overlays = document.querySelectorAll(
        ".leadconnector-regenerate-image-overlay"
      );
      for (var oi = 0; oi < overlays.length; oi++) {
        overlays[oi].style.display = highlightEnabled ? "" : "none";
      }
    }
    if (messageType === "updateContent") updateContent(messageData);
    if (messageType === "updateColors") applyPreviewColors(messageData);
  }

  window.addEventListener("message", handleParentMessage);
})();
