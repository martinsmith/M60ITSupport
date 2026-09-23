// leadconnector-admin.js — LeadConnector admin: text-widget submit label + CDN "purge all domains" toolbar (leadconnector_cdn_config from enqueue_purge_everything_on_all_domains_script()).
(function () {
  "use strict";

  var purgeAuthorizedOverride;

  function toggleInputs(enabled) {
    const element = document.getElementById(
      "leadconnector_text-widget--submit",
    );
    if (!!element) {
      if (enabled) {
        element.value = "Pull and Save";
      } else {
        element.value = "Save";
      }
    }
  }

  /**
   * Whether the CDN purge submenu may be shown: last authorization-status response if fetched, else localized leadconnector_cdn_config.
   * @returns {boolean}
   */
  function isPurgeToolbarAuthorized() {
    if (purgeAuthorizedOverride !== undefined) {
      return purgeAuthorizedOverride === true;
    }
    return !!(
      typeof leadconnector_cdn_config !== "undefined" &&
      leadconnector_cdn_config.wpPurgeAllDomainsCacheAuthorized
    );
  }

  var refreshPurgeToolbarAuthPromise = null;

  /**
   * GET leadconnector_cdn_config.wpPurgeAllDomainsCacheStatusUrl with X-WP-Nonce; sets purgeAuthorizedOverride and toggles the purge toolbar row.
   * Concurrent calls share one in-flight request.
   * @returns {Promise<void>}
   */
  function refreshPurgeToolbarAuthorization() {
    if (
      typeof leadconnector_cdn_config === "undefined" ||
      !leadconnector_cdn_config.siteId ||
      !leadconnector_cdn_config.wpPurgeAllDomainsCacheStatusUrl ||
      !leadconnector_cdn_config.restNonce
    ) {
      return Promise.resolve();
    }
    if (refreshPurgeToolbarAuthPromise) {
      return refreshPurgeToolbarAuthPromise;
    }
    refreshPurgeToolbarAuthPromise = fetch(
      leadconnector_cdn_config.wpPurgeAllDomainsCacheStatusUrl,
      {
        method: "GET",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-WP-Nonce": leadconnector_cdn_config.restNonce,
        },
      },
    )
      .then(function (httpResponse) {
        if (!httpResponse.ok) {
          throw new Error("HTTP " + httpResponse.status);
        }
        return httpResponse.json();
      })
      .then(function (body) {
        var flag = !!(body && body.wpPurgeAllDomainsCacheAuthorized);
        purgeAuthorizedOverride = flag;
        if (flag) {
          addPurgeEverythingOnAllDomainsMenuItem();
        } else {
          removePurgeEverythingOnAllDomainsMenuItem();
        }
      })
      .catch(function () {
        // Non-2xx or network: leave localized value and any previous override unchanged.
      })
      .finally(function () {
        refreshPurgeToolbarAuthPromise = null;
      });
    return refreshPurgeToolbarAuthPromise;
  }

  /**
   * Removes the injected purge menu item when authorization-status is false (e.g. disconnect).
   */
  function removePurgeEverythingOnAllDomainsMenuItem() {
    var existing = document.getElementById(
      "wp-admin-bar-cdn_menu_purge_all_domains",
    );
    if (existing && existing.parentNode) {
      existing.parentNode.removeChild(existing);
    }
  }

  function purgeEverythingOnAllDomains() {
    var purgeMenuAnchor = document.querySelector(
      "#wp-admin-bar-cdn_menu_purge_all_domains a",
    );
    if (!purgeMenuAnchor || typeof leadconnector_cdn_config === "undefined") {
      return;
    }
    if (
      !leadconnector_cdn_config.proxyUrl ||
      !leadconnector_cdn_config.restNonce
    ) {
      alert("Purge is not configured.");
      return;
    }

    var originalLabel = purgeMenuAnchor.textContent;
    purgeMenuAnchor.textContent = "Purging…";
    purgeMenuAnchor.style.opacity = "0.6";
    purgeMenuAnchor.style.pointerEvents = "none";

    var purgeRequestUrl =
      leadconnector_cdn_config.proxyUrl +
      "?endpoint=" +
      encodeURIComponent("wp_purge_all_domains_cache") +
      "&_wpnonce=" +
      encodeURIComponent(leadconnector_cdn_config.restNonce) +
      "&direct_endpoint=false";

    fetch(purgeRequestUrl, {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then(function (httpResponse) {
        return httpResponse.json().then(function (responseJson) {
          return { httpResponse: httpResponse, responseJson: responseJson };
        });
      })
      .then(function (proxyPayload) {
        if (!proxyPayload.httpResponse.ok) {
          var errorBody = proxyPayload.responseJson;
          var errorMessage =
            (errorBody &&
              (errorBody.message ||
                (errorBody.data && errorBody.data.message))) ||
            "HTTP " + proxyPayload.httpResponse.status;
          throw new Error(errorMessage);
        }
        var successBody = proxyPayload.responseJson;
        if (successBody && successBody.error === true) {
          throw new Error(successBody.message || "Purge failed.");
        }
      })
      .catch(function (purgeError) {
        alert(purgeError.message || "Purge failed.");
      })
      .finally(function () {
        purgeMenuAnchor.textContent = originalLabel;
        purgeMenuAnchor.style.opacity = "1";
        purgeMenuAnchor.style.pointerEvents = "auto";
      });
  }

  /**
   * Host CDN admin-bar submenu (underscore vs hyphen slug varies by environment).
   * @returns {HTMLElement|null}
   */
  function getCdnToolbarMenuList() {
    return (
      document.getElementById("wp-admin-bar-cdn_menu-default") ||
      document.getElementById("wp-admin-bar-cdn-menu-default")
    );
  }

  /**
   * Adds the "Purge everything on all domains" item to the host CDN admin bar menu when configured.
   */
  function addPurgeEverythingOnAllDomainsMenuItem() {
    var toolbarMenuList = getCdnToolbarMenuList();
    if (
      !toolbarMenuList ||
      toolbarMenuList.querySelector("#wp-admin-bar-cdn_menu_purge_all_domains")
    ) {
      return;
    }
    if (
      typeof leadconnector_cdn_config === "undefined" ||
      !leadconnector_cdn_config.siteId ||
      !isPurgeToolbarAuthorized()
    ) {
      return;
    }

    var toolbarMenuItem = document.createElement("li");
    toolbarMenuItem.id = "wp-admin-bar-cdn_menu_purge_all_domains";
    toolbarMenuItem.setAttribute("role", "group");

    var toolbarMenuLink = document.createElement("a");
    toolbarMenuLink.className = "ab-item";
    toolbarMenuLink.setAttribute("role", "menuitem");
    toolbarMenuLink.href = "#";
    toolbarMenuLink.textContent = "Purge everything on all domains";
    toolbarMenuLink.addEventListener("click", function (clickEvent) {
      clickEvent.preventDefault();
      purgeEverythingOnAllDomains();
    });

    toolbarMenuItem.appendChild(toolbarMenuLink);
    toolbarMenuList.appendChild(toolbarMenuItem);
  }

  var mutationDebounceTimer = null;

  /**
   * Debounces MutationObserver notifications before retrying addPurgeEverythingOnAllDomainsMenuItem (CDN menu may mount late).
   */
  function scheduleAddPurgeMenuFromObserver() {
    if (mutationDebounceTimer) {
      clearTimeout(mutationDebounceTimer);
    }
    mutationDebounceTimer = setTimeout(function () {
      mutationDebounceTimer = null;
      addPurgeEverythingOnAllDomainsMenuItem();
    }, 50);
  }

  function initPurgeEverythingOnAllDomainsToolbar() {
    addPurgeEverythingOnAllDomainsMenuItem();

    // The bundled LeadConnector admin Vue app dispatches the
    // 'leadconnector:purge-toolbar-refresh' CustomEvent after a successful
    // wp_validate_oauth / wp_disconnect call, which triggers a re-fetch of
    // the authorization-status endpoint without reloading the page. This
    // replaces an earlier implementation that monkey-patched window.fetch
    // globally to introspect every admin XHR; the global wrapper conflicted
    // with other plugins (WP.org Plugin Guideline #11 — plugins should not
    // hijack the admin dashboard or interfere with each other).
    window.addEventListener("leadconnector:purge-toolbar-refresh", function () {
      refreshPurgeToolbarAuthorization();
    });

    // wp.hooks (the WordPress 5.0+ JS action/filter system) is also accepted
    // as a refresh signal so external integrations can trigger a toolbar
    // refresh without coupling to the CustomEvent name.
    if (
      typeof window.wp !== "undefined" &&
      window.wp &&
      window.wp.hooks &&
      typeof window.wp.hooks.addAction === "function"
    ) {
      window.wp.hooks.addAction(
        "leadconnector.purge-toolbar.refresh",
        "leadconnector/admin-bar",
        function () {
          refreshPurgeToolbarAuthorization();
        }
      );
    }

    if (
      typeof leadconnector_cdn_config !== "undefined" &&
      leadconnector_cdn_config.siteId
    ) {
      if (!leadconnector_cdn_config.wpPurgeAllDomainsCacheAuthorized) {
        refreshPurgeToolbarAuthorization();
      }
    }

    var observerRoot = document.getElementById("wpadminbar") || document.body;
    new MutationObserver(function (mutationRecords) {
      for (var index = 0; index < mutationRecords.length; index++) {
        var mutationRecord = mutationRecords[index];
        if (
          mutationRecord.type === "childList" &&
          mutationRecord.addedNodes.length
        ) {
          scheduleAddPurgeMenuFromObserver();
          break;
        }
      }
    }).observe(observerRoot, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") {
    document.addEventListener(
      "DOMContentLoaded",
      initPurgeEverythingOnAllDomainsToolbar,
    );
  } else {
    initPurgeEverythingOnAllDomainsToolbar();
  }

  window.addEventListener("load", function () {
    var enabledTextWidgetCheckBox = document.querySelector(
      "#lead_connector_setting_enable_text_widget",
    );
    if (!!enabledTextWidgetCheckBox) {
      toggleInputs(enabledTextWidgetCheckBox.checked ? true : false);

      enabledTextWidgetCheckBox.addEventListener(
        "change",
        function () {
          toggleInputs(enabledTextWidgetCheckBox.checked ? true : false);
        },
        false,
      );
    }

    document
      .querySelectorAll(
        "#toplevel_page_lc-plugin .wp-submenu .external_link[data-href]",
      )
      .forEach(function (span) {
        var parentLink = span.closest("a");
        if (parentLink) {
          parentLink.href = span.getAttribute("data-href");
          parentLink.target = "_blank";
          parentLink.rel = "noopener noreferrer";
        }
      });
  });
})();
